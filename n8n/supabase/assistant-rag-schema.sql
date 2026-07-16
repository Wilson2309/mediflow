-- MediFlow Assistant RAG schema for Supabase / pgvector.
-- Run once in a private Supabase project. Never expose the service-role key to browsers.
--
-- Dimensions are explicit and tied to the documented defaults:
--   OpenAI text-embedding-3-small: 1536
--   Gemini gemini-embedding-001 default: 3072
-- If a different model or output dimension is selected, create a matching table/function
-- and re-embed every document. Never mix embedding spaces in one table.

create extension if not exists vector with schema extensions;

create table if not exists public.assistant_documents_openai_1536 (
    id bigserial primary key,
    content text not null check (char_length(content) between 1 and 12000),
    metadata jsonb not null default '{}'::jsonb,
    document_id text generated always as (metadata->>'document_id') stored,
    knowledge_checksum text generated always as (metadata->>'checksum') stored,
    embedding extensions.vector(1536) not null,
    created_at timestamptz not null default now()
);

create table if not exists public.assistant_documents_gemini_3072 (
    id bigserial primary key,
    content text not null check (char_length(content) between 1 and 12000),
    metadata jsonb not null default '{}'::jsonb,
    document_id text generated always as (metadata->>'document_id') stored,
    knowledge_checksum text generated always as (metadata->>'checksum') stored,
    embedding extensions.vector(3072) not null,
    created_at timestamptz not null default now()
);

-- IF NOT EXISTS preserves an existing table definition. Fail closed instead of
-- silently accepting a table created by an earlier incompatible experiment.
do $$
declare
    target record;
    content_type text;
    metadata_type text;
    embedding_type text;
begin
    for target in
        select * from (values
            ('assistant_documents_openai_1536', 1536),
            ('assistant_documents_gemini_3072', 3072)
        ) as expected(table_name, dimensions)
    loop
        select c.data_type into content_type
        from information_schema.columns c
        where c.table_schema = 'public' and c.table_name = target.table_name and c.column_name = 'content';

        select c.data_type into metadata_type
        from information_schema.columns c
        where c.table_schema = 'public' and c.table_name = target.table_name and c.column_name = 'metadata';

        select format_type(a.atttypid, a.atttypmod) into embedding_type
        from pg_attribute a
        where a.attrelid = to_regclass('public.' || target.table_name)
          and a.attname = 'embedding'
          and a.attnum > 0
          and not a.attisdropped;

        if content_type is distinct from 'text' then
            raise exception 'MEDIFLOW_RAG_SCHEMA_INCOMPATIBLE: %.content must be text', target.table_name;
        end if;
        if metadata_type is distinct from 'jsonb' then
            raise exception 'MEDIFLOW_RAG_SCHEMA_INCOMPATIBLE: %.metadata must be jsonb', target.table_name;
        end if;
        if embedding_type is distinct from format('vector(%s)', target.dimensions) then
            raise exception 'MEDIFLOW_RAG_SCHEMA_INCOMPATIBLE: %.embedding must be vector(%)', target.table_name, target.dimensions;
        end if;
    end loop;
end;
$$;

create index if not exists assistant_documents_openai_metadata_idx
    on public.assistant_documents_openai_1536 using gin (metadata jsonb_path_ops);
create index if not exists assistant_documents_gemini_metadata_idx
    on public.assistant_documents_gemini_3072 using gin (metadata jsonb_path_ops);

create unique index if not exists assistant_documents_openai_identity_idx
    on public.assistant_documents_openai_1536 (document_id, knowledge_checksum)
    where document_id is not null and knowledge_checksum is not null;
create unique index if not exists assistant_documents_gemini_identity_idx
    on public.assistant_documents_gemini_3072 (document_id, knowledge_checksum)
    where document_id is not null and knowledge_checksum is not null;

-- Serialize identical document/checksum inserts and turn safe retries into no-ops.
create or replace function public.deduplicate_assistant_document_insert()
returns trigger
language plpgsql
security invoker
set search_path = public
as $$
declare
    metadata_json jsonb;
    document_id_value text;
    checksum_value text;
    identity_key text;
begin
    metadata_json := new.metadata::jsonb;
    document_id_value := metadata_json->>'document_id';
    checksum_value := metadata_json->>'checksum';

    if coalesce(document_id_value, '') = ''
       or coalesce(checksum_value, '') !~ '^[a-f0-9]{64}$' then
        raise exception 'MEDIFLOW_INVALID_DOCUMENT_IDENTITY' using errcode = 'P0001';
    end if;

    identity_key := document_id_value || ':' || checksum_value;
    perform pg_advisory_xact_lock(hashtextextended(identity_key, 0));

    if tg_table_name = 'assistant_documents_openai_1536' and exists (
        select 1 from public.assistant_documents_openai_1536 d
        where d.document_id = document_id_value
          and d.knowledge_checksum = checksum_value
    ) then
        return null;
    end if;

    if tg_table_name = 'assistant_documents_gemini_3072' and exists (
        select 1 from public.assistant_documents_gemini_3072 d
        where d.document_id = document_id_value
          and d.knowledge_checksum = checksum_value
    ) then
        return null;
    end if;

    return new;
end;
$$;

drop trigger if exists assistant_documents_openai_deduplicate on public.assistant_documents_openai_1536;
create trigger assistant_documents_openai_deduplicate
before insert on public.assistant_documents_openai_1536
for each row execute function public.deduplicate_assistant_document_insert();
drop trigger if exists assistant_documents_gemini_deduplicate on public.assistant_documents_gemini_3072;
create trigger assistant_documents_gemini_deduplicate
before insert on public.assistant_documents_gemini_3072
for each row execute function public.deduplicate_assistant_document_insert();

-- Deterministic write RPC used by the ingest workflows. The public wrappers keep
-- the PostgREST body contract exactly {content, metadata, embedding}; the internal
-- implementation serializes retries by document_id + checksum and confirms storage.
create or replace function public.upsert_assistant_document_internal(
    target_provider text,
    expected_dimensions integer,
    input_content text,
    input_metadata jsonb,
    input_embedding jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = public, extensions
as $$
declare
    target_document_id text;
    target_checksum text;
    inserted_id bigint;
    stored boolean := false;
    stored_content text;
    stored_metadata jsonb;
begin
    if target_provider not in ('openai', 'gemini')
       or expected_dimensions not in (1536, 3072)
       or (target_provider = 'openai' and expected_dimensions <> 1536)
       or (target_provider = 'gemini' and expected_dimensions <> 3072) then
        raise exception 'MEDIFLOW_INVALID_EMBEDDING_PROVIDER' using errcode = 'P0001';
    end if;

    if input_content is null or char_length(input_content) not between 1 and 12000
       or jsonb_typeof(input_metadata) is distinct from 'object'
       or jsonb_typeof(input_embedding) is distinct from 'array'
       or jsonb_array_length(input_embedding) <> expected_dimensions
       or exists (
           select 1
           from jsonb_array_elements(input_embedding) as element(value)
           where jsonb_typeof(element.value) is distinct from 'number'
       ) then
        raise exception 'MEDIFLOW_INVALID_DOCUMENT_PAYLOAD' using errcode = 'P0001';
    end if;

    target_document_id := input_metadata->>'document_id';
    target_checksum := input_metadata->>'checksum';
    if coalesce(target_document_id, '') = ''
       or char_length(target_document_id) > 180
       or coalesce(target_checksum, '') !~ '^[a-f0-9]{64}$' then
        raise exception 'MEDIFLOW_INVALID_DOCUMENT_IDENTITY' using errcode = 'P0001';
    end if;

    perform pg_advisory_xact_lock(hashtextextended(target_provider || ':' || target_document_id || ':' || target_checksum, 0));

    if target_provider = 'openai' then
        select d.content, d.metadata
        into stored_content, stored_metadata
        from public.assistant_documents_openai_1536 d
        where d.document_id = target_document_id
          and d.knowledge_checksum = target_checksum;
        if found then
            if stored_content is distinct from input_content
               or stored_metadata is distinct from input_metadata then
                raise exception 'MEDIFLOW_DOCUMENT_IDENTITY_CONFLICT' using errcode = 'P0001';
            end if;
            return jsonb_build_object(
                'document_id', target_document_id,
                'checksum', target_checksum,
                'storage_status', 'already_present',
                'confirmed', true
            );
        end if;

        insert into public.assistant_documents_openai_1536 (content, metadata, embedding)
        values (input_content, input_metadata, (input_embedding::text)::extensions.vector(1536))
        returning id into inserted_id;

        select d.content, d.metadata
        into stored_content, stored_metadata
        from public.assistant_documents_openai_1536 d
        where d.document_id = target_document_id
          and d.knowledge_checksum = target_checksum
          and (inserted_id is null or d.id = inserted_id);
        stored := found;
    else
        select d.content, d.metadata
        into stored_content, stored_metadata
        from public.assistant_documents_gemini_3072 d
        where d.document_id = target_document_id
          and d.knowledge_checksum = target_checksum;
        if found then
            if stored_content is distinct from input_content
               or stored_metadata is distinct from input_metadata then
                raise exception 'MEDIFLOW_DOCUMENT_IDENTITY_CONFLICT' using errcode = 'P0001';
            end if;
            return jsonb_build_object(
                'document_id', target_document_id,
                'checksum', target_checksum,
                'storage_status', 'already_present',
                'confirmed', true
            );
        end if;

        insert into public.assistant_documents_gemini_3072 (content, metadata, embedding)
        values (input_content, input_metadata, (input_embedding::text)::extensions.vector(3072))
        returning id into inserted_id;

        select d.content, d.metadata
        into stored_content, stored_metadata
        from public.assistant_documents_gemini_3072 d
        where d.document_id = target_document_id
          and d.knowledge_checksum = target_checksum
          and (inserted_id is null or d.id = inserted_id);
        stored := found;
    end if;

    if not stored then
        raise exception 'MEDIFLOW_DOCUMENT_CONFIRMATION_FAILED' using errcode = 'P0001';
    end if;

    if stored_content is distinct from input_content
       or stored_metadata is distinct from input_metadata then
        raise exception 'MEDIFLOW_DOCUMENT_IDENTITY_CONFLICT' using errcode = 'P0001';
    end if;

    return jsonb_build_object(
        'document_id', target_document_id,
        'checksum', target_checksum,
        'storage_status', case when inserted_id is null then 'already_present' else 'inserted' end,
        'confirmed', true
    );
end;
$$;

create or replace function public.upsert_assistant_document_openai(
    content text,
    metadata jsonb,
    embedding jsonb
)
returns jsonb
language sql
security definer
set search_path = public, extensions
as $$
    select public.upsert_assistant_document_internal('openai', 1536, $1, $2, $3);
$$;

create or replace function public.upsert_assistant_document_gemini(
    content text,
    metadata jsonb,
    embedding jsonb
)
returns jsonb
language sql
security definer
set search_path = public, extensions
as $$
    select public.upsert_assistant_document_internal('gemini', 3072, $1, $2, $3);
$$;

-- pgvector HNSW over vector supports at most 2,000 dimensions. OpenAI 1,536 can
-- use HNSW; Gemini 3,072 deliberately uses exact search for this small corpus.
create index if not exists assistant_documents_openai_embedding_idx
    on public.assistant_documents_openai_1536
    using hnsw (embedding extensions.vector_cosine_ops);
drop index if exists public.assistant_documents_gemini_embedding_idx;

create or replace function public.match_assistant_documents_openai(
    query_embedding extensions.vector(1536),
    match_count integer default 5,
    filter jsonb default '{}'::jsonb
)
returns table (id bigint, content text, metadata jsonb, similarity double precision)
language sql
stable
security invoker
set search_path = public, extensions
as $$
    select d.id, d.content, d.metadata, 1 - (d.embedding <=> query_embedding) as similarity
    from public.assistant_documents_openai_1536 d
    where d.metadata @> filter
    order by d.embedding <=> query_embedding
    limit greatest(1, least(match_count, 50));
$$;

create or replace function public.match_assistant_documents_gemini(
    query_embedding extensions.vector(3072),
    match_count integer default 5,
    filter jsonb default '{}'::jsonb
)
returns table (id bigint, content text, metadata jsonb, similarity double precision)
language sql
stable
security invoker
set search_path = public, extensions
as $$
    select d.id, d.content, d.metadata, 1 - (d.embedding <=> query_embedding) as similarity
    from public.assistant_documents_gemini_3072 d
    where d.metadata @> filter
    order by d.embedding <=> query_embedding
    limit greatest(1, least(match_count, 50));
$$;

create table if not exists public.assistant_knowledge_manifests (
    provider text primary key check (provider in ('openai', 'gemini')),
    active_checksum text not null check (active_checksum ~ '^[a-f0-9]{64}$'),
    knowledge_version text not null,
    document_count integer not null check (document_count >= 0),
    activated_at timestamptz not null default now()
);

-- Sentinel rows make the final-batch activation an UPDATE instead of an unsafe
-- create-then-update race. A zero document count never represents active knowledge.
insert into public.assistant_knowledge_manifests
    (provider, active_checksum, knowledge_version, document_count)
values
    ('openai', repeat('0', 64), '0', 0),
    ('gemini', repeat('0', 64), '0', 0)
on conflict (provider) do nothing;

create table if not exists public.assistant_request_nonces (
    request_id uuid primary key,
    received_at timestamptz not null default now(),
    expires_at timestamptz not null,
    workflow_type text not null check (workflow_type in ('query', 'ingest')),
    role text not null check (role in ('administrador', 'recepcionista', 'caja_finanzas', 'medico', 'super_admin', 'system')),
    status text not null check (status in ('accepted', 'completed', 'fallback', 'failed')),
    check (expires_at > received_at)
);

create index if not exists assistant_request_nonces_expiry_idx
    on public.assistant_request_nonces (expires_at);
create index if not exists assistant_request_nonces_rate_idx
    on public.assistant_request_nonces (workflow_type, role, received_at desc);

create table if not exists public.assistant_ingest_batches (
    request_id uuid primary key,
    provider text not null check (provider in ('openai', 'gemini')),
    checksum text not null check (checksum ~ '^[a-f0-9]{64}$'),
    knowledge_version text not null check (char_length(knowledge_version) between 1 and 32),
    batch_index integer not null check (batch_index >= 0),
    batch_count integer not null check (batch_count > 0 and batch_index < batch_count),
    document_count integer not null check (document_count > 0),
    accepted_count integer not null check (accepted_count > 0 and accepted_count <= document_count),
    full_manifest boolean not null,
    received_at timestamptz not null default now(),
    unique (provider, checksum, batch_index)
);

do $$
begin
    if not exists (
        select 1 from pg_constraint
        where conname = 'assistant_ingest_batches_knowledge_version_length_check'
          and conrelid = 'public.assistant_ingest_batches'::regclass
    ) then
        alter table public.assistant_ingest_batches
            add constraint assistant_ingest_batches_knowledge_version_length_check
            check (char_length(knowledge_version) between 1 and 32);
    end if;
end;
$$;

create index if not exists assistant_ingest_batches_received_idx
    on public.assistant_ingest_batches (received_at);

-- Only the final batch can attempt activation. The receipt RPC validates the
-- complete set rather than relying on a count(*) alone or an INSERT trigger.
drop trigger if exists assistant_activate_manifest_from_receipt on public.assistant_ingest_batches;
drop function if exists public.activate_assistant_manifest_from_receipt();

create or replace function public.try_activate_assistant_manifest(
    input_provider text,
    input_checksum text,
    input_knowledge_version text,
    input_batch_count integer,
    input_document_count integer
)
returns boolean
language plpgsql
security definer
set search_path = public
as $$
declare
    receipt_count integer;
    accepted_total integer;
    inconsistent_count integer;
    distinct_indexes integer;
    min_index integer;
    max_index integer;
    stored_document_count bigint := 0;
    stored_distinct_count bigint := 0;
    null_document_count bigint := 0;
    manifest_confirmed boolean := false;
begin
    if input_provider not in ('openai', 'gemini')
       or input_checksum !~ '^[a-f0-9]{64}$'
       or char_length(coalesce(input_knowledge_version, '')) not between 1 and 32
       or input_batch_count < 1
       or input_document_count < 1 then
        raise exception 'MEDIFLOW_INVALID_MANIFEST_RECEIPT' using errcode = 'P0001';
    end if;

    -- The current Gemini knowledge package is immutable for this phase:
    -- schema version 2, 161 documents, and batches indexed from 0 to 16.
    if input_provider = 'gemini'
       and (input_batch_count <> 17
            or input_document_count <> 161
            or input_knowledge_version <> '2') then
        return false;
    end if;

    perform pg_advisory_xact_lock(hashtextextended(input_provider || ':' || input_checksum, 0));

    select
        count(*),
        coalesce(sum(r.accepted_count), 0),
        count(*) filter (where not r.full_manifest
            or r.batch_count <> input_batch_count
            or r.document_count <> input_document_count
            or r.knowledge_version <> input_knowledge_version
            or r.batch_index < 0
            or r.batch_index >= input_batch_count),
        count(distinct r.batch_index),
        min(r.batch_index),
        max(r.batch_index)
    into receipt_count, accepted_total, inconsistent_count, distinct_indexes, min_index, max_index
    from public.assistant_ingest_batches r
    where r.provider = input_provider and r.checksum = input_checksum;

    if input_provider = 'openai' then
        select count(*), count(distinct d.document_id),
            count(*) filter (where d.document_id is null or btrim(d.document_id) = '')
        into stored_document_count, stored_distinct_count, null_document_count
        from public.assistant_documents_openai_1536 d
        where d.knowledge_checksum = input_checksum;
    else
        select count(*), count(distinct d.document_id),
            count(*) filter (where d.document_id is null or btrim(d.document_id) = '')
        into stored_document_count, stored_distinct_count, null_document_count
        from public.assistant_documents_gemini_3072 d
        where d.knowledge_checksum = input_checksum;
    end if;

    if receipt_count = input_batch_count
       and distinct_indexes = input_batch_count
       and min_index = 0
       and max_index = input_batch_count - 1
       and accepted_total = input_document_count
       and inconsistent_count = 0
       and stored_document_count = input_document_count
       and stored_distinct_count = input_document_count
       and null_document_count = 0 then
        update public.assistant_knowledge_manifests
        set active_checksum = input_checksum,
            knowledge_version = input_knowledge_version,
            document_count = input_document_count,
            activated_at = now()
        where provider = input_provider;

        select exists (
            select 1
            from public.assistant_knowledge_manifests m
            where m.provider = input_provider
              and m.active_checksum = input_checksum
              and m.document_count = input_document_count
              and m.knowledge_version = input_knowledge_version
        ) into manifest_confirmed;
    end if;

    return manifest_confirmed;
end;
$$;

create or replace function public.record_assistant_ingest_batch_receipt(
    input_request_id uuid,
    input_provider text,
    input_checksum text,
    input_knowledge_version text,
    input_batch_index integer,
    input_batch_count integer,
    input_document_count integer,
    input_accepted_count integer,
    input_full_manifest boolean
)
returns jsonb
language plpgsql
security definer
set search_path = public
as $$
declare
    existing_receipt public.assistant_ingest_batches%rowtype;
    request_receipt public.assistant_ingest_batches%rowtype;
    receipt_status text;
    manifest_activated boolean := false;
begin
    if input_provider not in ('openai', 'gemini')
       or input_checksum !~ '^[a-f0-9]{64}$'
       or char_length(coalesce(input_knowledge_version, '')) not between 1 and 32
       or input_batch_index < 0
       or input_batch_count < 1
       or input_batch_index >= input_batch_count
       or input_document_count < 1
       or input_accepted_count < 1
       or input_accepted_count > input_document_count
       or input_full_manifest is null then
        raise exception 'MEDIFLOW_INVALID_INGEST_RECEIPT' using errcode = 'P0001';
    end if;

    perform pg_advisory_xact_lock(hashtextextended(input_provider || ':' || input_checksum, 0));

    select *
    into request_receipt
    from public.assistant_ingest_batches r
    where r.request_id = input_request_id;
    if found
       and (request_receipt.provider <> input_provider
            or request_receipt.checksum <> input_checksum
            or request_receipt.batch_index <> input_batch_index) then
        raise exception 'MEDIFLOW_INGEST_REQUEST_ID_CONFLICT' using errcode = 'P0001';
    end if;

    select *
    into existing_receipt
    from public.assistant_ingest_batches r
    where r.provider = input_provider
      and r.checksum = input_checksum
      and r.batch_index = input_batch_index
    for update;

    if found then
        if existing_receipt.batch_count <> input_batch_count
           or existing_receipt.document_count <> input_document_count
           or existing_receipt.accepted_count <> input_accepted_count
           or existing_receipt.knowledge_version <> input_knowledge_version
           or existing_receipt.full_manifest is distinct from input_full_manifest then
            raise exception 'MEDIFLOW_INGEST_RECEIPT_CONFLICT' using errcode = 'P0001';
        end if;
        receipt_status := 'already_present';
    else
        insert into public.assistant_ingest_batches (
            request_id, provider, checksum, knowledge_version, batch_index,
            batch_count, document_count, accepted_count, full_manifest
        ) values (
            input_request_id, input_provider, input_checksum, input_knowledge_version, input_batch_index,
            input_batch_count, input_document_count, input_accepted_count, input_full_manifest
        );
        receipt_status := 'inserted';
    end if;

    if input_full_manifest and input_batch_index = input_batch_count - 1 then
        manifest_activated := public.try_activate_assistant_manifest(
            input_provider,
            input_checksum,
            input_knowledge_version,
            input_batch_count,
            input_document_count
        );
    end if;

    return jsonb_build_object(
        'provider', input_provider,
        'checksum', input_checksum,
        'knowledge_version', input_knowledge_version,
        'batch_index', input_batch_index,
        'batch_count', input_batch_count,
        'document_count', input_document_count,
        'accepted_count', input_accepted_count,
        'full_manifest', input_full_manifest,
        'receipt_status', receipt_status,
        'manifest_activated', manifest_activated
    );
end;
$$;

-- Secondary cost control: at most 60 accepted nonces per workflow/role/minute.
-- An advisory transaction lock makes the count-and-insert decision serial per scope.
create or replace function public.enforce_assistant_nonce_rate_limit()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
    recent_count integer;
begin
    perform pg_advisory_xact_lock(hashtext(new.workflow_type || ':' || new.role));
    select count(*) into recent_count
    from public.assistant_request_nonces
    where workflow_type = new.workflow_type
      and role = new.role
      and received_at >= now() - interval '1 minute';

    if recent_count >= 60 then
        raise exception 'MEDIFLOW_RATE_LIMITED' using errcode = 'P0001';
    end if;

    return new;
end;
$$;

drop trigger if exists assistant_nonce_rate_limit on public.assistant_request_nonces;
create trigger assistant_nonce_rate_limit
before insert on public.assistant_request_nonces
for each row execute function public.enforce_assistant_nonce_rate_limit();

create or replace function public.cleanup_expired_assistant_nonces()
returns integer
language plpgsql
security definer
set search_path = public
as $$
declare
    deleted_count integer;
begin
    delete from public.assistant_request_nonces where expires_at < now();
    get diagnostics deleted_count = row_count;
    return deleted_count;
end;
$$;

-- Delete one explicitly selected inactive checksum. Active or staging checksums are never
-- selected implicitly, which keeps concurrent ingests and rollback candidates intact.
drop function if exists public.cleanup_inactive_assistant_documents();
create or replace function public.cleanup_inactive_assistant_documents(
    target_provider text,
    checksum_to_delete text
)
returns bigint
language plpgsql
security definer
set search_path = public
as $$
declare
    deleted_count bigint := 0;
    active_checksum text;
begin
    if target_provider not in ('openai', 'gemini')
       or checksum_to_delete !~ '^[a-f0-9]{64}$' then
        raise exception 'MEDIFLOW_INVALID_CLEANUP_SCOPE' using errcode = 'P0001';
    end if;
    select m.active_checksum into active_checksum
    from public.assistant_knowledge_manifests m where m.provider = target_provider;
    if active_checksum = checksum_to_delete then
        raise exception 'MEDIFLOW_ACTIVE_CHECKSUM_PROTECTED' using errcode = 'P0001';
    end if;
    if target_provider = 'openai' then
        delete from public.assistant_documents_openai_1536 where knowledge_checksum = checksum_to_delete;
    else
        delete from public.assistant_documents_gemini_3072 where knowledge_checksum = checksum_to_delete;
    end if;
    get diagnostics deleted_count = row_count;
    return deleted_count;
end;
$$;

alter table public.assistant_documents_openai_1536 enable row level security;
alter table public.assistant_documents_gemini_3072 enable row level security;
alter table public.assistant_knowledge_manifests enable row level security;
alter table public.assistant_request_nonces enable row level security;
alter table public.assistant_ingest_batches enable row level security;

revoke all on function public.enforce_assistant_nonce_rate_limit() from public, anon, authenticated;
revoke all on function public.cleanup_expired_assistant_nonces() from public, anon, authenticated;
revoke all on function public.cleanup_inactive_assistant_documents(text, text) from public, anon, authenticated;
revoke all on function public.try_activate_assistant_manifest(text, text, text, integer, integer) from public, anon, authenticated;
revoke all on function public.record_assistant_ingest_batch_receipt(uuid, text, text, text, integer, integer, integer, integer, boolean) from public, anon, authenticated;
revoke all on function public.match_assistant_documents_openai(extensions.vector, integer, jsonb) from public, anon, authenticated;
revoke all on function public.match_assistant_documents_gemini(extensions.vector, integer, jsonb) from public, anon, authenticated;
revoke all on function public.deduplicate_assistant_document_insert() from public, anon, authenticated;
revoke all on function public.upsert_assistant_document_internal(text, integer, text, jsonb, jsonb) from public, anon, authenticated;
revoke all on function public.upsert_assistant_document_openai(text, jsonb, jsonb) from public, anon, authenticated;
revoke all on function public.upsert_assistant_document_gemini(text, jsonb, jsonb) from public, anon, authenticated;

-- SECURITY DEFINER functions must be owned by the private SQL deployment role.
-- Only service_role receives execute on the public ingest entry points below.
grant execute on function public.cleanup_expired_assistant_nonces() to service_role;
grant execute on function public.cleanup_inactive_assistant_documents(text, text) to service_role;
grant execute on function public.match_assistant_documents_openai(extensions.vector, integer, jsonb) to service_role;
grant execute on function public.match_assistant_documents_gemini(extensions.vector, integer, jsonb) to service_role;
grant execute on function public.upsert_assistant_document_openai(text, jsonb, jsonb) to service_role;
grant execute on function public.upsert_assistant_document_gemini(text, jsonb, jsonb) to service_role;
grant execute on function public.record_assistant_ingest_batch_receipt(uuid, text, text, text, integer, integer, integer, integer, boolean) to service_role;

revoke all on table public.assistant_documents_openai_1536, public.assistant_documents_gemini_3072,
    public.assistant_knowledge_manifests, public.assistant_request_nonces, public.assistant_ingest_batches
    from anon, authenticated;

-- No public/authenticated policies are created intentionally. n8n uses a private service-role
-- credential server-to-server. Review grants and RLS with the Supabase project owner.
