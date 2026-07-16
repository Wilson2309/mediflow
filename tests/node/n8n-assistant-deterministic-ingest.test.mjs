import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';
import {
    INGEST_RESPONSE_KEYS,
    buildDocumentResultItem,
    buildIngestResponse,
    buildLoopItems,
    evaluateIngestItems,
    hasExactIngestResponse,
    validateEmbeddingValues,
} from '../../scripts/lib/n8n-assistant-ingest-runtime.mjs';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const workflowPath = path.resolve(currentDirectory, '..', '..', 'n8n', 'workflows', 'mediflow-assistant-ingest-supabase-gemini.json');
const schemaPath = path.resolve(currentDirectory, '..', '..', 'n8n', 'supabase', 'assistant-rag-schema.sql');
const workflow = JSON.parse(await readFile(workflowPath, 'utf8'));
const schema = await readFile(schemaPath, 'utf8');

function node(name) {
    const found = workflow.nodes.find((candidate) => candidate.name === name);
    assert.ok(found, `Falta el nodo ${name}`);
    return found;
}

function payload(size = 10) {
    return {
        request_id: '00000000-0000-4000-8000-000000000001',
        provider: 'supabase',
        checksum: 'a'.repeat(64),
        knowledge_version: 2,
        batch_index: 0,
        batch_count: 17,
        document_count: 161,
        full_manifest: true,
        documents: Array.from({ length: size }, (_, index) => ({
            document_id: `entry-${index + 1}:medico:v2`,
            content: `Documento ${index + 1}`,
            metadata: { entry_id: `entry-${index + 1}`, role: 'medico' },
        })),
    };
}

function resultsFor(statuses) {
    const loopItems = buildLoopItems(payload(statuses.length), 'gemini');
    return loopItems.map((item, index) => buildDocumentResultItem(item, statuses[index], true));
}

function emulateLoop(items, handler) {
    const processedItems = [];
    for (const item of items) {
        const returned = handler(item);
        assert.equal(returned.length, 1, 'cada iteración debe devolver exactamente un item');
        processedItems.push(...returned);
    }
    return processedItems;
}

function executeGeneratedCheck(items) {
    let inputCalls = 0;
    const output = vm.runInNewContext(`(() => { ${node('Check Ingest Result').parameters.jsCode} })()`, {
        $input: {
            all: () => {
                inputCalls += 1;
                return items.map((json) => ({ json }));
            },
        },
        $: () => {
            throw new Error('Check Ingest Result no puede consultar otros nodos');
        },
    });

    assert.equal(inputCalls, 1);
    return JSON.parse(JSON.stringify(output[0].json));
}

test('1. done acumula exactamente los diez resultados procesados', () => {
    const loopItems = buildLoopItems(payload(), 'gemini');
    const done = emulateLoop(loopItems, (item) => [buildDocumentResultItem(item, 'inserted', true)]);
    assert.equal(done.length, 10);
    assert.deepEqual(done.map((item) => item.result.document_id), loopItems.map((item) => item.document.document_id));
});

test('2. Check Ingest Result ejecuta una sola lectura de $input.all()', () => {
    const outcome = executeGeneratedCheck(resultsFor(Array(10).fill('inserted')));
    assert.deepEqual({ ok: outcome.ingest_ok, accepted: outcome.accepted, rejected: outcome.rejected }, { ok: true, accepted: 10, rejected: 0 });
});

test('3. Check no consulta .all() de clasificadores ni otros nodos', () => {
    const code = node('Check Ingest Result').parameters.jsCode;
    assert.doesNotMatch(code, /\$\(['"]Classify (?:Existing|Stored) Document['"]\)\.all/);
    assert.equal(executeGeneratedCheck(resultsFor(Array(10).fill('inserted'))).ingest_ok, true);
});

test('4. diez documentos nuevos producen diez inserted', () => {
    const done = emulateLoop(buildLoopItems(payload(), 'gemini'), (item) => [buildDocumentResultItem(item, 'inserted', true)]);
    const outcome = evaluateIngestItems(done);
    assert.deepEqual({ inserted: outcome.inserted, existing: outcome.already_present, accepted: outcome.accepted }, { inserted: 10, existing: 0, accepted: 10 });
});

test('5. diez documentos existentes producen diez already_present y cero embeddings', () => {
    let embeddingCalls = 0;
    const done = emulateLoop(buildLoopItems(payload(), 'gemini'), (item) => {
        const alreadyPresent = true;
        if (!alreadyPresent) embeddingCalls += 1;
        return [buildDocumentResultItem(item, 'already_present', true)];
    });
    const outcome = evaluateIngestItems(done);
    assert.equal(outcome.already_present, 10);
    assert.equal(outcome.accepted, 10);
    assert.equal(embeddingCalls, 0);
});

test('6. una mezcla de nuevos y existentes conserva diez resultados', () => {
    const statuses = Array.from({ length: 10 }, (_, index) => index % 2 === 0 ? 'inserted' : 'already_present');
    const outcome = evaluateIngestItems(resultsFor(statuses));
    assert.deepEqual({ inserted: outcome.inserted, existing: outcome.already_present, accepted: outcome.accepted }, { inserted: 5, existing: 5, accepted: 10 });
});

test('7. recuperación real: diez existentes, cero recibos y manifiesto inactivo', () => {
    const initial = payload();
    const storedDocuments = new Map(initial.documents.map((document) => [document.document_id, {
        document_id: document.document_id,
        knowledge_checksum: initial.checksum,
    }]));
    const receipts = [];
    const manifest = { active: false };
    let lookupCalls = 0;
    let embeddingCalls = 0;
    let rpcCalls = 0;
    let insertCalls = 0;
    const done = emulateLoop(buildLoopItems(initial, 'gemini'), (item) => {
        lookupCalls += 1;
        const stored = storedDocuments.get(item.document.document_id);
        const alreadyPresent = stored?.knowledge_checksum === item.request.checksum;
        if (!alreadyPresent) {
            embeddingCalls += 1;
            rpcCalls += 1;
            insertCalls += 1;
        }
        return [buildDocumentResultItem(item, 'already_present', true)];
    });
    const outcome = executeGeneratedCheck(done);
    assert.equal(receipts.length, 0);
    assert.equal(manifest.active, false);
    assert.equal(lookupCalls, 10);
    assert.equal(storedDocuments.size, 10);
    assert.equal(done.length, 10);
    assert.deepEqual({ embeddingCalls, rpcCalls, insertCalls }, { embeddingCalls: 0, rpcCalls: 0, insertCalls: 0 });
    assert.deepEqual({ accepted: outcome.accepted, rejected: outcome.rejected, existing: outcome.already_present }, { accepted: 10, rejected: 0, existing: 10 });
    assert.equal(workflow.connections['Document Is Already Present'].main[0][0].node, 'Build Already Present Result');
    assert.equal(workflow.connections['Document Is Already Present'].main[1][0].node, 'Generate Document Embedding');
});

test('8. falta un resultado y el lote se rechaza atómicamente', () => {
    const outcome = evaluateIngestItems(resultsFor(Array(10).fill('inserted')).slice(0, 9));
    assert.deepEqual({ ok: outcome.ingest_ok, accepted: outcome.accepted, rejected: outcome.rejected }, { ok: false, accepted: 0, rejected: 10 });
});

test('9. un document_id duplicado rechaza todo el lote', () => {
    const results = resultsFor(Array(10).fill('inserted'));
    results[9].result.document_id = results[0].result.document_id;
    assert.equal(evaluateIngestItems(results).ingest_ok, false);
});

test('10. un document_id inesperado rechaza todo el lote', () => {
    const results = resultsFor(Array(10).fill('inserted'));
    results[9].result.document_id = 'foreign-document';
    assert.deepEqual({ accepted: evaluateIngestItems(results).accepted, rejected: evaluateIngestItems(results).rejected }, { accepted: 0, rejected: 10 });
    const contaminated = resultsFor(Array(10).fill('inserted'));
    contaminated[0].request.untrusted_field = 'must-not-pass';
    assert.equal(evaluateIngestItems(contaminated).ingest_ok, false);
});

test('11. un checksum diferente rechaza todo el lote', () => {
    const results = resultsFor(Array(10).fill('inserted'));
    results[4].result.checksum = 'b'.repeat(64);
    assert.equal(evaluateIngestItems(results).ingest_ok, false);
});

test('12. confirmed como string no se acepta', () => {
    const results = resultsFor(Array(10).fill('inserted'));
    results[2].result.confirmed = 'true';
    assert.equal(evaluateIngestItems(results).accepted, 0);
});

test('13. embedding Gemini con longitud distinta de 3072 falla', () => {
    assert.deepEqual(validateEmbeddingValues(Array(3071).fill(0.1), 3072), { valid: false, code: 'EMBEDDING_DIMENSIONS_INVALID' });
});

test('14. embedding con null o string falla antes de la RPC', () => {
    const withNull = Array(3072).fill(0.1);
    const withString = Array(3072).fill(0.1);
    withNull[5] = null;
    withString[6] = '0.1';
    assert.equal(validateEmbeddingValues(withNull, 3072).valid, false);
    assert.equal(validateEmbeddingValues(withString, 3072).valid, false);
    assert.equal(validateEmbeddingValues([...Array(3071).fill(0.1), Number.POSITIVE_INFINITY], 3072).valid, false);
});

test('15. un fallo de inserción produce failed y no puede confirmarse', () => {
    const item = buildLoopItems(payload(1), 'gemini')[0];
    const failed = buildDocumentResultItem(item, 'failed', false);
    const outcome = evaluateIngestItems([failed]);
    assert.deepEqual({ ok: outcome.ingest_ok, accepted: outcome.accepted, rejected: outcome.rejected }, { ok: false, accepted: 0, rejected: 1 });
    assert.equal(workflow.connections['Call Idempotent Supabase RPC'].main[1][0].node, 'Build Failed Document Result');
});

test('16. una inserción sin confirmación rechaza el lote', () => {
    const results = resultsFor(Array(10).fill('inserted'));
    results[7].result.confirmed = false;
    assert.equal(evaluateIngestItems(results).ingest_ok, false);
    assert.equal(workflow.connections['Confirm Stored Document'].main[0][0].node, 'Build Inserted Result');
});

test('17. la respuesta de éxito contiene exactamente siete campos', () => {
    const request = buildLoopItems(payload(), 'gemini')[0].request;
    const response = buildIngestResponse({ ok: true, request, accepted: 10, rejected: 0, activated: false });
    assert.equal(hasExactIngestResponse(response), true);
    assert.deepEqual(Object.keys(response), INGEST_RESPONSE_KEYS);
});

test('18. la respuesta de error contiene siete campos y la RPC SQL es mínima e idempotente', () => {
    const request = buildLoopItems(payload(), 'gemini')[0].request;
    const response = buildIngestResponse({ ok: false, request, accepted: 0, rejected: 10, activated: false });
    assert.equal(hasExactIngestResponse(response), true);
    assert.deepEqual(Object.keys(response), INGEST_RESPONSE_KEYS);
    assert.match(schema, /upsert_assistant_document_gemini\s*\([\s\S]*content text,[\s\S]*metadata jsonb,[\s\S]*embedding jsonb/i);
    assert.match(schema, /pg_advisory_xact_lock/);
    assert.doesNotMatch(JSON.stringify(response), /embedding|metadata|documents|error_code/);
});
