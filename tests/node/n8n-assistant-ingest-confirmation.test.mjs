import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { validateWorkflow } from '../../scripts/lib/n8n-assistant-workflow-validator.mjs';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const fixturesDirectory = path.join(currentDirectory, 'fixtures', 'n8n');
const workflowsDirectory = path.resolve(currentDirectory, '..', '..', 'n8n', 'workflows');
const fixture = JSON.parse(await readFile(path.join(fixturesDirectory, 'ingest-confirmation-cases.json'), 'utf8'));

async function workflowFor(caseName) {
    const workflow = JSON.parse(await readFile(path.join(workflowsDirectory, fixture.workflow), 'utf8'));
    const vector = workflow.nodes.find((node) => node.name === 'Call Idempotent Supabase RPC');
    const existing = workflow.nodes.find((node) => node.name === 'Check Existing Document');
    const confirmed = workflow.nodes.find((node) => node.name === 'Confirm Stored Document');
    const check = workflow.nodes.find((node) => node.name === 'Check Ingest Result');

    switch (caseName) {
        case 'successful-insert':
        case 'empty-table-false-vector-confirmed':
        case 'already-present-retry':
        case 'both-valid-routes-complete-loop':
            return workflow;
        case 'duplicate-endpoint-config': {
            const endpoint = workflow.nodes.find((node) => node.name === 'Ingest Endpoint Configuration');
            workflow.nodes.push({ ...endpoint, id: 'duplicate-ingest-endpoint-config', name: 'Shadow Endpoint Configuration' });
            return workflow;
        }

        case 'vector-404-error':
            vector.onError = 'continueRegularOutput';
            return workflow;
        case 'metadata-incompatible-error':
            confirmed.onError = 'continueRegularOutput';
            return workflow;
        case 'embeddings-error':
            workflow.connections['Generate Document Embedding'].main[1] = [];
            return workflow;
        case 'unconfirmed-document':
            workflow.connections['Document Result Confirmed'].main[1] = [];
            return workflow;
        case 'partial-batch':
            check.parameters.jsCode = 'return [{ json: { ingest_ok: true, accepted: 10, rejected: 0 } }];';
            return workflow;
        case 'receipt-on-failure':
            workflow.connections['Ingest Batch Succeeded'].main[0][0].node = 'Final Batch';
            return workflow;
        case 'manifest-on-incomplete-batch':
            workflow.connections['Manifest Was Activated'].main[1] = [];
            return workflow;
        case 'real-confirmation-required':
            workflow.connections['RPC Result Is Valid'].main[0][0].node = 'Build Inserted Result';
            return workflow;
        case 'false-direct-loop':
            workflow.connections['Document Is Already Present'].main[1][0].node = 'Loop Over Documents';
            return workflow;
        case 'true-vector-store':
            workflow.connections['Document Is Already Present'].main[0][0].node = 'Generate Document Embedding';
            return workflow;
        case 'vector-unreachable-from-false':
            workflow.connections['Document Is Already Present'].main[1] = [];
            return workflow;
        case 'empty-existing-evidence':
            workflow.nodes.find((node) => node.name === 'Build Already Present Result').parameters.jsCode = "return [{ json: { result: { storage_status: 'already_present', confirmed: true } } }];";
            return workflow;
        default:
            throw new Error(`Caso de fixture desconocido: ${caseName}`);
    }
}

function assertRejected(result, pattern) {
    assert.equal(result.valid, false, 'se esperaba que el workflow fuera inválido');
    assert.match(result.errors.join('\n'), pattern);
}

test('acepta inserción exitosa con confirmación persistente real', async () => {
    const result = validateWorkflow(await workflowFor('successful-insert'), { source: fixture.workflow });
    assert.equal(result.valid, true);
});

test('acepta el reintento cuando el documento ya está presente con el mismo checksum', async () => {
    const result = validateWorkflow(await workflowFor('already-present-retry'), { source: fixture.workflow });
    assert.equal(result.valid, true);
});

test('empty table: FALSE reaches explicit embedding, RPC and confirmation', async () => {
    const workflow = await workflowFor('empty-table-false-vector-confirmed');
    assert.equal(workflow.connections['Document Is Already Present'].main[1][0].node, 'Generate Document Embedding');
    assert.equal(workflow.connections['Embedding Is Valid'].main[0][0].node, 'Call Idempotent Supabase RPC');
    assert.equal(workflow.connections['Call Idempotent Supabase RPC'].main[0][0].node, 'Validate RPC Result');
    assert.equal(workflow.connections['RPC Result Is Valid'].main[0][0].node, 'Confirm Stored Document');
    assert.equal(workflow.connections['Confirm Stored Document'].main[0][0].node, 'Build Inserted Result');
    assert.equal(workflow.connections['Build Inserted Result'].main[0][0].node, 'Document Result Confirmed');
    for (const name of ['Check Existing Document', 'Confirm Stored Document']) {
        const lookup = workflow.nodes.find((node) => node.name === name);
        const query = new Map(lookup.parameters.queryParameters.parameters.map((parameter) => [parameter.name, parameter.value]));
        assert.equal(lookup.type, 'n8n-nodes-base.httpRequest');
        assert.equal(lookup.parameters.nodeCredentialType, 'supabaseApi');
        assert.equal(query.get('select'), 'document_id,knowledge_checksum');
        assert.deepEqual([...query.keys()].sort(), ['document_id', 'knowledge_checksum', 'limit', 'select']);
    }
    assert.equal(validateWorkflow(workflow, { source: fixture.workflow }).valid, true);
});

test('existing document: TRUE builds evidence and avoids embedding/RPC', async () => {
    const workflow = await workflowFor('both-valid-routes-complete-loop');
    const existing = workflow.nodes.find((node) => node.name === 'Build Already Present Result');
    assert.match(existing.parameters.jsCode, /document_id.*checksum.*storage_status.*confirmed/s);
    assert.equal(workflow.connections['Document Is Already Present'].main[0][0].node, 'Build Already Present Result');
    assert.notEqual(workflow.connections['Document Is Already Present'].main[0][0].node, 'Generate Document Embedding');
    assert.equal(validateWorkflow(workflow, { source: fixture.workflow }).valid, true);
});

test('rechaza una segunda configuracion del host Supabase', async () => {
    const result = validateWorkflow(await workflowFor('duplicate-endpoint-config'), { source: fixture.workflow });
    assertRejected(result, /host Supabase/i);
});

test('rechaza los casos de error y evidencia incompleta de la fixture', async () => {
    const expectations = new Map([
        ['vector-404-error', /RPC Supabase/i],
        ['metadata-incompatible-error', /operaciones persistentes/i],
        ['embeddings-error', /flujo explícito/i],
        ['unconfirmed-document', /fallos deben terminar/i],
        ['partial-batch', /Check Ingest Result/i],
        ['receipt-on-failure', /recibo/i],
        ['manifest-on-incomplete-batch', /Manifest Was Activated|lote incompleto/i],
        ['real-confirmation-required', /flujo explícito/i],
        ['false-direct-loop', /flujo explícito/i],
        ['true-vector-store', /flujo explícito/i],
        ['vector-unreachable-from-false', /flujo explícito/i],
        ['empty-existing-evidence', /resultado explícito|contrato determinista/i],
    ]);

    for (const caseName of fixture.cases.filter((name) => expectations.has(name))) {
        assertRejected(validateWorkflow(await workflowFor(caseName), { source: `${fixture.workflow}:${caseName}` }), expectations.get(caseName));
    }
});
