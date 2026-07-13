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
    identity_key text;
begin
    if coalesce(new.metadata->>'document_id', '') = ''
       or coalesce(new.metadata->>'checksum', '') !~ '^[a-f0-9]{64}$' then
        raise exception 'MEDIFLOW_INVALID_DOCUMENT_IDENTITY' using errcode = 'P0001';
    end if;

    identity_key := new.metadata->>'document_id' || ':' || new.metadata->>'checksum';
    perform pg_advisory_xact_lock(hashtextextended(identity_key, 0));

    if tg_table_name = 'assistant_documents_openai_1536' and exists (
        select 1 from public.assistant_documents_openai_1536 d
        where d.document_id = new.metadata->>'document_id'
          and d.knowledge_checksum = new.metadata->>'checksum'
    ) then
        return null;
    end if;

    if tg_table_name = 'assistant_documents_gemini_3072' and exists (
        select 1 from public.assistant_documents_gemini_3072 d
        where d.document_id = new.metadata->>'document_id'
          and d.knowledge_checksum = new.metadata->>'checksum'
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
    knowledge_version text not null,
    batch_index integer not null check (batch_index >= 0),
    batch_count integer not null check (batch_count > 0 and batch_index < batch_count),
    document_count integer not null check (document_count > 0),
    accepted_count integer not null check (accepted_count > 0 and accepted_count <= document_count),
    full_manifest boolean not null,
    received_at timestamptz not null default now(),
    unique (provider, checksum, batch_index)
);

create index if not exists assistant_ingest_batches_received_idx
    on public.assistant_ingest_batches (received_at);

-- The manifest is activated atomically only when a complete, internally consistent
-- set of batch receipts exists. A final batch by itself can never activate knowledge.
create or replace function public.activate_assistant_manifest_from_receipt()
returns trigger
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
begin
    perform pg_advisory_xact_lock(hashtextextended(new.provider || ':' || new.checksum, 0));

    select
        count(*),
        coalesce(sum(r.accepted_count), 0),
        count(*) filter (where not r.full_manifest
            or r.batch_count <> new.batch_count
            or r.document_count <> new.document_count
            or r.knowledge_version <> new.knowledge_version),
        count(distinct r.batch_index),
        min(r.batch_index),
        max(r.batch_index)
    into receipt_count, accepted_total, inconsistent_count, distinct_indexes, min_index, max_index
    from public.assistant_ingest_batches r
    where r.provider = new.provider and r.checksum = new.checksum;

    if new.provider = 'openai' then
        select count(*), count(distinct d.document_id)
        into stored_document_count, stored_distinct_count
        from public.assistant_documents_openai_1536 d
        where d.knowledge_checksum = new.checksum;
    elsif new.provider = 'gemini' then
        select count(*), count(distinct d.document_id)
        into stored_document_count, stored_distinct_count
        from public.assistant_documents_gemini_3072 d
        where d.knowledge_checksum = new.checksum;
    end if;

    if new.full_manifest
       and receipt_count = new.batch_count
       and distinct_indexes = new.batch_count
       and min_index = 0
       and max_index = new.batch_count - 1
       and accepted_total = new.document_count
       and inconsistent_count = 0
       and stored_document_count = new.document_count
       and stored_distinct_count = new.document_count then
        update public.assistant_knowledge_manifests
        set active_checksum = new.checksum,
            knowledge_version = new.knowledge_version,
            document_count = new.document_count,
            activated_at = now()
        where provider = new.provider;
    end if;

    return new;
end;
$$;

drop trigger if exists assistant_activate_manifest_from_receipt on public.assistant_ingest_batches;
create trigger assistant_activate_manifest_from_receipt
after insert on public.assistant_ingest_batches
for each row execute function public.activate_assistant_manifest_from_receipt();

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
revoke all on function public.activate_assistant_manifest_from_receipt() from public, anon, authenticated;
revoke all on function public.match_assistant_documents_openai(extensions.vector, integer, jsonb) from public, anon, authenticated;
revoke all on function public.match_assistant_documents_gemini(extensions.vector, integer, jsonb) from public, anon, authenticated;
revoke all on function public.deduplicate_assistant_document_insert() from public, anon, authenticated;

grant execute on function public.cleanup_expired_assistant_nonces() to service_role;
grant execute on function public.cleanup_inactive_assistant_documents(text, text) to service_role;
grant execute on function public.match_assistant_documents_openai(extensions.vector, integer, jsonb) to service_role;
grant execute on function public.match_assistant_documents_gemini(extensions.vector, integer, jsonb) to service_role;

revoke all on table public.assistant_documents_openai_1536, public.assistant_documents_gemini_3072,
    public.assistant_knowledge_manifests, public.assistant_request_nonces, public.assistant_ingest_batches
    from anon, authenticated;

-- No public/authenticated policies are created intentionally. n8n uses a private service-role
-- credential server-to-server. Review grants and RLS with the Supabase project owner.
