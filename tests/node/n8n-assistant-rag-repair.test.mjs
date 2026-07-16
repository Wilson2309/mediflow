import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const rootDirectory = path.resolve(currentDirectory, '..', '..');

async function readProjectFile(relativePath) {
    return readFile(path.join(rootDirectory, relativePath), 'utf8');
}

test('el esquema principal falla de forma explícita ante tipos RAG incompatibles', async () => {
    const sql = await readProjectFile('n8n/supabase/assistant-rag-schema.sql');

    assert.match(sql, /MEDIFLOW_RAG_SCHEMA_INCOMPATIBLE/i);
    assert.match(sql, /assistant_documents_openai_1536', 1536/i);
    assert.match(sql, /assistant_documents_gemini_3072', 3072/i);
    assert.match(sql, /metadata_type is distinct from 'jsonb'/i);
    assert.match(sql, /content_type is distinct from 'text'/i);
    assert.match(sql, /embedding_type is distinct from format\('vector\(%s\)'/i);
    assert.match(sql, /metadata_json\s*:=\s*new\.metadata::jsonb/i);
    assert.match(sql, /document_id_value\s*:=\s*metadata_json->>'document_id'/i);
    assert.match(sql, /checksum_value\s*:=\s*metadata_json->>'checksum'/i);
    assert.doesNotMatch(sql, /new\.metadata->>/i);
    assert.match(sql, /MEDIFLOW_DOCUMENT_IDENTITY_CONFLICT/i);
    assert.match(sql, /upsert_assistant_document_internal[\s\S]*?security definer[\s\S]*?set search_path = public, extensions/i);
    assert.match(sql, /revoke all on function public\.upsert_assistant_document_gemini[\s\S]*?from public, anon, authenticated/i);
    assert.match(sql, /revoke all on function public\.record_assistant_ingest_batch_receipt[\s\S]*?from public, anon, authenticated/i);
    assert.match(sql, /grant execute on function public\.record_assistant_ingest_batch_receipt[\s\S]*?to service_role/i);
    assert.match(sql, /create or replace function public\.try_activate_assistant_manifest/i);
    assert.match(sql, /input_batch_count <> 17/i);
    assert.match(sql, /input_document_count <> 161/i);
    assert.match(sql, /input_knowledge_version <> '2'/i);
    assert.match(sql, /input_full_manifest and input_batch_index = input_batch_count - 1/i);
});

test('el repair Gemini solo permite una tabla vacía y un manifiesto centinela', async () => {
    const sql = await readProjectFile('n8n/supabase/repair-empty-gemini-rag.sql');

    assert.match(sql, /^begin;/im);
    assert.match(sql, /Gemini table is not empty/i);
    assert.match(sql, /manifest is active or non-empty/i);
    assert.match(sql, /delete from public\.assistant_ingest_batches\s+where provider = 'gemini'/is);
    assert.doesNotMatch(sql, /delete from public\.assistant_request_nonces/i);
    assert.doesNotMatch(sql, /drop\s+(?:table|function|trigger)[^;]*\bcascade\b/is);
    assert.match(sql, /metadata jsonb not null/i);
    assert.match(sql, /embedding extensions\.vector\(3072\) not null/i);
    assert.match(sql, /document_id text generated always as \(metadata->>'document_id'\) stored/i);
    assert.match(sql, /commit;/i);
});
