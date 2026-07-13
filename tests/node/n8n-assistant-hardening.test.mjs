import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { validateWorkflow } from '../../scripts/lib/n8n-assistant-workflow-validator.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

async function workflow(name) {
    return JSON.parse(await readFile(path.join(root, 'n8n', 'workflows', name), 'utf8'));
}

function errorText(result) {
    return result.errors.join('\n');
}

test('acepta la consulta Supabase con manifiesto fail-closed y checksum posfiltrado', async () => {
    const result = validateWorkflow(
        await workflow('mediflow-assistant-query-supabase-openai.json'),
        { source: 'mediflow-assistant-query-supabase-openai.json' },
    );

    assert.equal(result.valid, true, errorText(result));
    assert.deepEqual(result.errors, []);
});

test('rechaza una consulta Supabase que omite la validación del manifiesto activo', async () => {
    const candidate = await workflow('mediflow-assistant-query-supabase-openai.json');
    candidate.nodes = candidate.nodes.filter((node) => node.name !== 'Validate Active Knowledge Manifest');
    delete candidate.connections['Validate Active Knowledge Manifest'];

    const result = validateWorkflow(candidate, { source: 'missing-active-manifest-query.json' });

    assert.equal(result.valid, false);
    assert.match(errorText(result), /manifiesto activo/i);
});

test('rechaza postfiltro sin checksum o con módulos generales mixtos', async () => {
    const candidate = await workflow('mediflow-assistant-query-supabase-openai.json');
    const filter = candidate.nodes.find((node) => node.name === 'Filter Role Module Context');
    filter.parameters.jsCode = filter.parameters.jsCode
        .replace('metadata.checksum === activeChecksum', 'true')
        .replace('modules.every', 'modules.some');

    const result = validateWorkflow(candidate, { source: 'weak-role-module-filter-query.json' });

    assert.equal(result.valid, false);
    assert.match(errorText(result), /postfiltro.*checksum|puramente generales/i);
});

test('rechaza el guardrail amplio que confunde texto español con una ruta Windows', async () => {
    const candidate = await workflow('mediflow-assistant-query-supabase-openai.json');
    const validator = candidate.nodes.find((node) => node.name === 'Validate Final Response');
    assert.match(validator.parameters.jsCode, /\[a-z\]:\[\\\\\/\]/i);
    validator.parameters.jsCode = validator.parameters.jsCode.replace('(?:[a-z]:[\\\\/])', '(?:[a-z]:)');

    const result = validateWorkflow(candidate, { source: 'broad-colon-guardrail-query.json' });

    assert.equal(result.valid, false);
    assert.match(errorText(result), /guardrail de rutas/i);
});

test('clasifica error interno del nonce como fallback 200, no como replay 409', async () => {
    const candidate = await workflow('mediflow-assistant-query-supabase-openai.json');
    const classifier = candidate.nodes.find((node) => node.name === 'Classify Anti-Replay Result');
    const code = classifier.parameters.jsCode;

    assert.match(code, /internalFailure/);
    assert.match(code, /status_code:\s*rateLimited \? 429 : \(conflict \? 409 : 200\)/);
    assert.match(code, /NONCE_ERROR/);
    assert.match(code, /No encontré una respuesta exacta/);
});

test('el esquema Gemini evita HNSW incompatible sobre vector de 3072 dimensiones', async () => {
    const sql = await readFile(path.join(root, 'n8n', 'supabase', 'assistant-rag-schema.sql'), 'utf8');

    assert.doesNotMatch(
        sql,
        /create\s+index[^;]*assistant_documents_gemini[^;]*using\s+hnsw/is,
    );
    assert.match(sql, /drop index if exists public\.assistant_documents_gemini_embedding_idx/i);
    assert.match(sql, /vector\(3072\)/i);
    assert.match(sql, /stored_document_count\s*=\s*new\.document_count/i);
    assert.match(sql, /stored_distinct_count\s*=\s*new\.document_count/i);
    assert.match(sql, /count\(distinct d\.document_id\)/i);
});
