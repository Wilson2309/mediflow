import assert from 'node:assert/strict';
import test from 'node:test';
import {
    buildDocumentResultItem,
    buildLoopItems,
    evaluateIngestItems,
    validateIngestReceipt,
} from '../../scripts/lib/n8n-assistant-ingest-runtime.mjs';

const CHECKSUM = '3426dea3a19861815b95bd744a8a1ca195290ad654b50b883924374ab4df929e';
const PROVIDER = 'gemini';
const DOCUMENTS = Array.from({ length: 161 }, (_, index) => ({
    document_id: `entry-${index + 1}:medico:v2`,
    content: `Documento ${index + 1}`,
    metadata: { entry_id: `entry-${index + 1}`, role: 'medico' },
}));

function payloadFor(batchIndex, documents = DOCUMENTS.slice(batchIndex * 10, (batchIndex + 1) * 10)) {
    return {
        request_id: `00000000-0000-4000-8000-${String(batchIndex + 1).padStart(12, '0')}`,
        provider: 'supabase',
        checksum: CHECKSUM,
        knowledge_version: 2,
        batch_index: batchIndex,
        batch_count: 17,
        document_count: 161,
        full_manifest: true,
        documents,
    };
}

function createState() {
    return {
        documents: new Map(DOCUMENTS.slice(0, 10).map((document) => [document.document_id, CHECKSUM])),
        receipts: new Map(),
        manifest: { active: false },
        calls: { embeddings: 0, rpc: 0, inserts: 0 },
    };
}

function receiptFor(outcome, receiptStatus = 'inserted') {
    const request = outcome.request;
    return {
        provider: PROVIDER,
        checksum: request.checksum,
        knowledge_version: request.knowledge_version,
        batch_index: request.batch_index,
        batch_count: request.batch_count,
        document_count: request.document_count,
        accepted_count: outcome.accepted,
        full_manifest: request.full_manifest,
        receipt_status: receiptStatus,
        manifest_activated: false,
    };
}

function receiptMatches(receipt, outcome) {
    const request = outcome.request;
    return receipt.provider === PROVIDER && receipt.checksum === request.checksum
        && receipt.knowledge_version === request.knowledge_version && receipt.batch_index === request.batch_index
        && receipt.batch_count === request.batch_count && receipt.document_count === request.document_count
        && receipt.accepted_count === outcome.accepted && receipt.full_manifest === request.full_manifest;
}

function manifestCanActivate(state) {
    if (state.receipts.size !== 17 || state.documents.size !== 161) return false;
    for (let index = 0; index < 17; index += 1) {
        const receipt = state.receipts.get(index);
        if (!receipt || receipt.batch_index !== index || receipt.checksum !== CHECKSUM
            || receipt.knowledge_version !== 2 || receipt.batch_count !== 17
            || receipt.document_count !== 161 || receipt.provider !== PROVIDER) return false;
    }
    return [...state.documents.values()].every((checksum) => checksum === CHECKSUM);
}

function processBatch(state, batchIndex, { forceFailure = false, documents } = {}) {
    const loopItems = buildLoopItems(payloadFor(batchIndex, documents), PROVIDER);
    const done = loopItems.map((item) => {
        const storedChecksum = state.documents.get(item.document.document_id);
        if (forceFailure || (storedChecksum && storedChecksum !== item.request.checksum)) return buildDocumentResultItem(item, 'failed', false);
        if (storedChecksum === item.request.checksum) return buildDocumentResultItem(item, 'already_present', true);
        state.calls.embeddings += 1;
        state.calls.rpc += 1;
        state.calls.inserts += 1;
        state.documents.set(item.document.document_id, item.request.checksum);
        return buildDocumentResultItem(item, 'inserted', true);
    });
    const outcome = evaluateIngestItems(done);
    if (!outcome.ingest_ok) return { outcome, activated: false, receipt: null };

    const currentReceipt = state.receipts.get(batchIndex);
    if (currentReceipt && !receiptMatches(currentReceipt, outcome)) return { outcome, activated: false, receipt: null, code: 'RECEIPT_CONFLICT' };
    const receipt = currentReceipt ?? receiptFor(outcome);
    if (!currentReceipt) state.receipts.set(batchIndex, receipt);
    assert.equal(validateIngestReceipt(receipt, outcome, PROVIDER), true);

    const activated = batchIndex === 16 && manifestCanActivate(state);
    if (activated) state.manifest = { active: true, checksum: CHECKSUM, document_count: 161, knowledge_version: 2, provider: PROVIDER };
    return { outcome, activated, receipt: { ...receipt, receipt_status: currentReceipt ? 'already_present' : 'inserted' } };
}

function completedState({ documentCount = 161, missingReceipt = null, mixedChecksum = false } = {}) {
    const state = createState();
    state.documents = new Map(DOCUMENTS.slice(0, documentCount).map((document, index) => [document.document_id, mixedChecksum && index === 0 ? 'b'.repeat(64) : CHECKSUM]));
    for (let index = 0; index < 17; index += 1) {
        if (index === missingReceipt) continue;
        const outcome = evaluateIngestItems(buildLoopItems(payloadFor(index), PROVIDER).map((item) => buildDocumentResultItem(item, 'already_present', true)));
        state.receipts.set(index, receiptFor(outcome));
    }
    return state;
}

test('Fase 4: simulacion completa de 17 lotes y reintento idempotente', () => {
    const state = createState();
    const initial = processBatch(state, 0);
    assert.deepEqual({ accepted: initial.outcome.accepted, rejected: initial.outcome.rejected, existing: initial.outcome.already_present, activated: initial.activated }, { accepted: 10, rejected: 0, existing: 10, activated: false });
    assert.deepEqual(state.calls, { embeddings: 0, rpc: 0, inserts: 0 });

    for (let index = 1; index < 17; index += 1) {
        const result = processBatch(state, index);
        assert.equal(result.outcome.ingest_ok, true);
        assert.equal(result.outcome.accepted, index === 16 ? 1 : 10);
        assert.equal(result.outcome.rejected, 0);
        assert.equal(result.activated, index === 16);
        assert.equal(result.receipt.receipt_status, 'inserted');
    }
    assert.deepEqual(state.calls, { embeddings: 151, rpc: 151, inserts: 151 });
    assert.equal(state.documents.size, 161);
    assert.equal(state.receipts.size, 17);
    assert.deepEqual(state.manifest, { active: true, checksum: CHECKSUM, document_count: 161, knowledge_version: 2, provider: PROVIDER });

    const beforeRetry = { ...state.calls };
    for (let index = 0; index < 17; index += 1) {
        const retry = processBatch(state, index);
        assert.equal(retry.outcome.ingest_ok, true);
        assert.equal(retry.outcome.already_present, index === 16 ? 1 : 10);
        assert.equal(retry.receipt.receipt_status, 'already_present');
        assert.equal(retry.activated, index === 16);
    }
    assert.deepEqual(state.calls, beforeRetry);
    assert.equal(state.manifest.active, true);
});

test('Fase 4: fallos de lote, recibos y manifiesto nunca activan parcialmente', () => {
    const stopped = createState();
    for (let index = 0; index < 5; index += 1) processBatch(stopped, index);
    const failed = processBatch(stopped, 5, { forceFailure: true });
    assert.equal(failed.outcome.ingest_ok, false);
    assert.equal(stopped.receipts.has(5), false);
    assert.equal(stopped.receipts.has(6), false);
    assert.equal(stopped.manifest.active, false);

    const conflicting = createState();
    const batchThree = evaluateIngestItems(buildLoopItems(payloadFor(3), PROVIDER).map((item) => buildDocumentResultItem(item, 'inserted', true)));
    conflicting.receipts.set(3, { ...receiptFor(batchThree), accepted_count: 9 });
    assert.equal(processBatch(conflicting, 3).code, 'RECEIPT_CONFLICT');

    assert.equal(manifestCanActivate(completedState({ missingReceipt: 8 })), false);
    assert.equal(manifestCanActivate(completedState({ documentCount: 160 })), false);
    assert.equal(manifestCanActivate(completedState({ mixedChecksum: true })), false);

    const duplicate = buildLoopItems(payloadFor(0), PROVIDER).map((item) => buildDocumentResultItem(item, 'already_present', true));
    duplicate[9].result.document_id = duplicate[0].result.document_id;
    assert.equal(evaluateIngestItems(duplicate).ingest_ok, false);

    const badConfirmed = buildLoopItems(payloadFor(0), PROVIDER).map((item) => buildDocumentResultItem(item, 'already_present', true));
    badConfirmed[0].result.confirmed = 'true';
    assert.equal(evaluateIngestItems(badConfirmed).ingest_ok, false);
    const unknownStatus = buildLoopItems(payloadFor(0), PROVIDER).map((item) => buildDocumentResultItem(item, 'already_present', true));
    unknownStatus[0].result.storage_status = 'unknown';
    assert.equal(evaluateIngestItems(unknownStatus).ingest_ok, false);
});
