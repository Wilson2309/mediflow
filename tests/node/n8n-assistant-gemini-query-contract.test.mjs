import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { validateWorkflow } from '../../scripts/lib/n8n-assistant-workflow-validator.mjs';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const workflowPath = path.resolve(currentDirectory, '..', '..', 'n8n', 'workflows', 'mediflow-assistant-query-supabase-gemini.json');
const workflow = JSON.parse(await readFile(workflowPath, 'utf8'));

function node(candidate, name) {
    const found = candidate.nodes.find((item) => item.name === name);
    assert.ok(found, `Falta el nodo ${name}`);

    return found;
}

function assertInvalid(candidate, pattern) {
    const result = validateWorkflow(candidate, { source: 'gemini-query-contract.json' });
    assert.equal(result.valid, false, 'el workflow alterado debe rechazarse');
    assert.match(result.errors.join('\n'), pattern);
}

test('la consulta Gemini usa embedding y RPC HTTP JSON con el contrato de 3072 dimensiones', () => {
    const embedding = node(workflow, 'Generate Query Embedding');
    const validation = node(workflow, 'Validate Query Embedding');
    const rpc = node(workflow, 'Call Gemini Vector RPC');

    assert.equal(embedding.type, 'n8n-nodes-base.httpRequest');
    assert.equal(embedding.parameters.contentType, 'json');
    assert.equal(embedding.parameters.specifyBody, 'json');
    assert.match(embedding.parameters.jsonBody, /RETRIEVAL_QUERY/);
    assert.match(embedding.parameters.jsonBody, /outputDimensionality:\s*3072/);
    assert.doesNotMatch(embedding.parameters.jsonBody, /JSON\.stringify/);
    assert.equal(embedding.parameters.options.response.response.responseFormat, 'json');
    assert.match(validation.parameters.jsCode, /Array\.isArray\(values\)/);
    assert.match(validation.parameters.jsCode, /values\.length === 3072/);
    assert.match(validation.parameters.jsCode, /Number\.isFinite/);
    assert.equal(rpc.parameters.contentType, 'json');
    assert.equal(rpc.parameters.specifyBody, 'json');
    assert.match(rpc.parameters.jsonBody, /query_embedding/);
    assert.match(rpc.parameters.jsonBody, /active_checksum/);
    assert.match(rpc.parameters.jsonBody, /role:/);
});

test('la consulta ambigua de recepcionista se resuelve antes del RAG con opciones permitidas', () => {
    const validator = node(workflow, 'Validate Raw Request');
    const guardrail = node(workflow, 'Build Role Guardrail Response');

    assert.match(validator.parameters.jsCode, /const ambiguous =/);
    assert.match(validator.parameters.jsCode, /\[\u00bf \]\*/);
    assert.match(validator.parameters.jsCode, /¿Cómo agendo una cita\?/);
    assert.match(validator.parameters.jsCode, /¿Cómo consulto un paciente\?/);
    assert.match(validator.parameters.jsCode, /blocked_suggestions/);
    assert.match(guardrail.parameters.jsCode, /blocked_suggestions/);
    assert.match(guardrail.parameters.jsCode, /suggestions, can_escalate: false/);
});

test('rechaza query embedding Raw, JSON serializado o con dimensión incorrecta', () => {
    for (const mutate of [
        (candidate) => {
            const embedding = node(candidate, 'Generate Query Embedding');
            embedding.parameters.contentType = 'raw';
            embedding.parameters.rawContentType = 'application/json';
            embedding.parameters.body = `={{ JSON.stringify({ taskType: 'RETRIEVAL_QUERY' }) }}`;
            delete embedding.parameters.jsonBody;
        },
        (candidate) => {
            const embedding = node(candidate, 'Generate Query Embedding');
            embedding.parameters.jsonBody = embedding.parameters.jsonBody.replace('3072', '1536');
        },
    ]) {
        const candidate = structuredClone(workflow);
        mutate(candidate);
        assertInvalid(candidate, /embedding y RPC HTTP JSON nativos/i);
    }
});

test('rechaza RPC sin checksum activo, filtro de rol o conexión controlada', () => {
    for (const mutate of [
        (candidate) => {
            const rpc = node(candidate, 'Call Gemini Vector RPC');
            rpc.parameters.jsonBody = rpc.parameters.jsonBody.replace('checksum: $json.active_checksum', 'checksum: null');
        },
        (candidate) => {
            const rpc = node(candidate, 'Call Gemini Vector RPC');
            rpc.parameters.jsonBody = rpc.parameters.jsonBody.replace('role: $json.payload.role, ', '');
        },
        (candidate) => {
            candidate.connections['Call Gemini Vector RPC'].main[1] = [];
        },
    ]) {
        const candidate = structuredClone(workflow);
        mutate(candidate);
        assertInvalid(candidate, /embedding y RPC HTTP JSON nativos/i);
    }
});
