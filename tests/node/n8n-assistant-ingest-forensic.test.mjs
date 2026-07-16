import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const workflowPath = path.resolve(currentDirectory, '..', '..', 'n8n', 'workflows', 'mediflow-assistant-ingest-supabase-gemini.json');
const workflow = JSON.parse(await readFile(workflowPath, 'utf8'));

function node(name) {
    const found = workflow.nodes.find((candidate) => candidate.name === name);
    assert.ok(found, `Falta el nodo ${name}`);

    return found;
}

function requestForBatch(size = 10) {
    const checksum = 'a'.repeat(64);
    const documents = Array.from({ length: size }, (_, index) => ({
        document_id: `entry-${index + 1}:medico:v2`,
        content: `Documento ${index + 1}`,
        metadata: { role: 'medico' },
    }));

    return {
        payload: {
            request_id: '00000000-0000-4000-8000-000000000001',
            provider: 'supabase',
            batch_index: 0,
            batch_count: 17,
            full_manifest: true,
            checksum,
            knowledge_version: 2,
            document_count: 161,
            documents,
            timestamp: '2026-07-13T00:00:00.000Z',
        },
    };
}
function doneItems(storageStatus) {
    const payload = requestForBatch().payload;
    const request = {
        request_id: payload.request_id,
        provider: 'gemini',
        checksum: payload.checksum,
        knowledge_version: payload.knowledge_version,
        batch_index: payload.batch_index,
        batch_count: payload.batch_count,
        document_count: payload.document_count,
        batch_document_count: payload.documents.length,
        full_manifest: payload.full_manifest,
    };
    return payload.documents.map((document) => ({
        request: { ...request, expected_document_id: document.document_id },
        result: {
            document_id: document.document_id,
            checksum: payload.checksum,
            storage_status: storageStatus,
            confirmed: true,
        },
    }));
}


function executeCode(jsCode, { sources, input = [], currentJson = {} }) {
    const data = Object.fromEntries(Object.entries(sources).map(([name, items]) => [
        name,
        items.map((json) => ({ json })),
    ]));
    const selector = (name) => ({
        all: () => data[name] ?? [],
        first: () => (data[name] ?? [{ json: {} }])[0],
    });
    const output = vm.runInNewContext(`(() => { ${jsCode} })()`, {
        $: selector,
        $input: { all: () => input.map((json) => ({ json })) },
        $json: currentJson,
        Set,
        Math,
        Number,
        String,
        Object,
        Array,
        JSON,
    });

    return JSON.parse(JSON.stringify(output));
}

test('regresión forense: Check Ingest Result ya no consulta runs anteriores', () => {
    const input = doneItems('already_present');
    const check = executeCode(node('Check Ingest Result').parameters.jsCode, {
        sources: {},
        input,
    })[0].json;

    assert.deepEqual(
        { ingest_ok: check.ingest_ok, accepted: check.accepted, rejected: check.rejected },
        { ingest_ok: true, accepted: 10, rejected: 0 },
    );
    assert.match(node('Check Ingest Result').parameters.jsCode, /const items = \$input\.all\(\);/);
    assert.doesNotMatch(node('Check Ingest Result').parameters.jsCode, /\$\(['"]Classify (?:Existing|Stored) Document['"]\)\.all/);
});

test('the same generated code produces Laravel\'s seven-field success contract when all outcomes are present', () => {
    const request = requestForBatch();
    const stored = doneItems('inserted');
    const check = executeCode(node('Check Ingest Result').parameters.jsCode, {
        sources: {},
        input: stored,
    })[0].json;

    assert.deepEqual(
        { ingest_ok: check.ingest_ok, accepted: check.accepted, rejected: check.rejected },
        { ingest_ok: true, accepted: 10, rejected: 0 },
    );

    const response = executeCode(node('Build Ingest Summary').parameters.jsCode, {
        sources: { 'Check Ingest Result': [check] },
        currentJson: { ...check, manifest_activated: false },
    })[0].json.response;

    assert.deepEqual(response, {
        ok: true,
        request_id: request.payload.request_id,
        accepted: 10,
        rejected: 0,
        checksum: request.payload.checksum,
        knowledge_version: 2,
        activated: false,
    });
});

test('generated receipt validation declares its contract and emits batch_receipt_stored', () => {
    const outcome = {
        request: {
            checksum: 'a'.repeat(64),
            knowledge_version: 2,
            batch_index: 0,
            batch_count: 17,
            document_count: 161,
            full_manifest: true,
        },
        accepted: 10,
    };
    const receipt = {
        provider: 'gemini',
        checksum: outcome.request.checksum,
        knowledge_version: '2',
        batch_index: 0,
        batch_count: 17,
        document_count: 161,
        accepted_count: 10,
        full_manifest: true,
        receipt_status: 'inserted',
        manifest_activated: false,
    };
    const result = executeCode(node('Validate Batch Receipt').parameters.jsCode, {
        sources: { 'Check Ingest Result': [outcome] },
        input: [receipt],
    })[0].json;

    assert.equal(result.batch_receipt_stored, true);
    assert.equal(result.manifest_activated, false);
    assert.doesNotMatch(node('Validate Batch Receipt').parameters.jsCode, /receipt_valid/);
});

test('generated HTTP contracts send object JSON and use receipt input arguments', () => {
    const embedding = node('Generate Document Embedding').parameters;
    assert.equal(embedding.contentType, 'json');
    assert.equal(embedding.specifyBody, 'json');
    assert.match(embedding.jsonBody, /=\{\{ \{/);
    assert.equal(embedding.options.response.response.responseFormat, 'json');

    const receiptBody = node('Record Ingest Batch Receipt').parameters.jsonBody;
    for (const key of [
        'input_request_id', 'input_provider', 'input_checksum', 'input_knowledge_version',
        'input_batch_index', 'input_batch_count', 'input_document_count',
        'input_accepted_count', 'input_full_manifest',
    ]) {
        assert.match(receiptBody, new RegExp(`\\b${key}\\b`));
    }
});
