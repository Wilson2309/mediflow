-- Repair only the empty Gemini RAG table from the failed first test ingest.
-- Run manually in the Supabase SQL Editor after reviewing every guard below.
-- This script never uses CASCADE and rolls back entirely if Gemini contains
-- knowledge or its manifest is active.

begin;

do $$
declare
    stored_documents bigint;
    manifest_count integer;
    manifest_checksum text;
    trigger_definition text;
begin
    if to_regclass('public.assistant_documents_gemini_3072') is null then
        raise exception 'MEDIFLOW_GEMINI_REPAIR_ABORTED: Gemini table is missing';
    end if;

    if to_regclass('public.assistant_ingest_batches') is null
       or to_regclass('public.assistant_knowledge_manifests') is null
       or to_regprocedure('public.deduplicate_assistant_document_insert()') is null then
        raise exception 'MEDIFLOW_GEMINI_REPAIR_ABORTED: base RAG schema is incomplete';
    end if;

    select pg_get_functiondef('public.deduplicate_assistant_document_insert()'::regprocedure)
    into trigger_definition;
    if trigger_definition not like '%metadata_json := new.metadata::jsonb;%'
       or trigger_definition like '%new.metadata->>%' then
        raise exception 'MEDIFLOW_GEMINI_REPAIR_ABORTED: deduplicate trigger is not the corrected jsonb version';
    end if;

    select count(*) into stored_documents
    from public.assistant_documents_gemini_3072;

    select document_count, active_checksum
    into manifest_count, manifest_checksum
    from public.assistant_knowledge_manifests
    where provider = 'gemini'
    for update;

    if stored_documents <> 0 then
        raise exception 'MEDIFLOW_GEMINI_REPAIR_ABORTED: Gemini table is not empty';
    end if;
    if manifest_count is distinct from 0
       or manifest_checksum is distinct from repeat('0', 64) then
        raise exception 'MEDIFLOW_GEMINI_REPAIR_ABORTED: Gemini manifest is active or non-empty';
    end if;
end;
$$;

-- With an empty table and inactive sentinel manifest, every Gemini receipt is
-- partial evidence from the failed test run and can be removed safely.
delete from public.assistant_ingest_batches
where provider = 'gemini';

drop trigger if exists assistant_documents_gemini_deduplicate
    on public.assistant_documents_gemini_3072;
drop function if exists public.match_assistant_documents_gemini(extensions.vector, integer, jsonb);
drop table public.assistant_documents_gemini_3072;

create table public.assistant_documents_gemini_3072 (
    id bigserial primary key,
    content text not null check (char_length(content) between 1 and 12000),
    metadata jsonb not null default '{}'::jsonb,
    document_id text generated always as (metadata->>'document_id') stored,
    knowledge_checksum text generated always as (metadata->>'checksum') stored,
    embedding extensions.vector(3072) not null,
    created_at timestamptz not null default now()
);

create index assistant_documents_gemini_metadata_idx
    on public.assistant_documents_gemini_3072 using gin (metadata jsonb_path_ops);
create unique index assistant_documents_gemini_identity_idx
    on public.assistant_documents_gemini_3072 (document_id, knowledge_checksum)
    where document_id is not null and knowledge_checksum is not null;

create trigger assistant_documents_gemini_deduplicate
before insert on public.assistant_documents_gemini_3072
for each row execute function public.deduplicate_assistant_document_insert();

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

alter table public.assistant_documents_gemini_3072 enable row level security;
revoke all on table public.assistant_documents_gemini_3072 from anon, authenticated;
revoke all on function public.match_assistant_documents_gemini(extensions.vector, integer, jsonb)
    from public, anon, authenticated;
grant execute on function public.match_assistant_documents_gemini(extensions.vector, integer, jsonb)
    to service_role;

do $$
declare
    content_type text;
    metadata_type text;
    embedding_type text;
    stored_documents bigint;
    receipt_count bigint;
    manifest_count integer;
    manifest_checksum text;
begin
    select data_type into content_type
    from information_schema.columns
    where table_schema = 'public' and table_name = 'assistant_documents_gemini_3072' and column_name = 'content';
    select data_type into metadata_type
    from information_schema.columns
    where table_schema = 'public' and table_name = 'assistant_documents_gemini_3072' and column_name = 'metadata';
    select format_type(a.atttypid, a.atttypmod) into embedding_type
    from pg_attribute a
    where a.attrelid = 'public.assistant_documents_gemini_3072'::regclass
      and a.attname = 'embedding' and a.attnum > 0 and not a.attisdropped;
    select count(*) into stored_documents from public.assistant_documents_gemini_3072;
    select count(*) into receipt_count from public.assistant_ingest_batches where provider = 'gemini';
    select document_count, active_checksum into manifest_count, manifest_checksum
    from public.assistant_knowledge_manifests where provider = 'gemini';

    if content_type is distinct from 'text'
       or metadata_type is distinct from 'jsonb'
       or embedding_type is distinct from 'vector(3072)'
       or stored_documents <> 0
       or receipt_count <> 0
       or manifest_count is distinct from 0
       or manifest_checksum is distinct from repeat('0', 64) then
        raise exception 'MEDIFLOW_GEMINI_REPAIR_ABORTED: post-repair verification failed';
    end if;
end;
$$;

commit;
