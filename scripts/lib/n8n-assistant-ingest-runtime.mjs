export const INGEST_RESPONSE_KEYS = Object.freeze([
    'ok',
    'request_id',
    'accepted',
    'rejected',
    'checksum',
    'knowledge_version',
    'activated',
]);

export const DOCUMENT_RESULT_KEYS = Object.freeze([
    'document_id',
    'checksum',
    'storage_status',
    'confirmed',
]);

export const INGEST_RECEIPT_KEYS = Object.freeze([
    'provider',
    'checksum',
    'knowledge_version',
    'batch_index',
    'batch_count',
    'document_count',
    'accepted_count',
    'full_manifest',
    'receipt_status',
    'manifest_activated',
]);

export const BASE_REQUEST_CONTEXT_KEYS = Object.freeze([
    'request_id',
    'provider',
    'checksum',
    'knowledge_version',
    'batch_index',
    'batch_count',
    'document_count',
    'batch_document_count',
    'full_manifest',
]);

export const REQUEST_CONTEXT_KEYS = Object.freeze([
    ...BASE_REQUEST_CONTEXT_KEYS,
    'expected_document_id',
]);

const CONFIRMED_STORAGE_STATUSES = new Set(['inserted', 'already_present']);

export function createRequestContext(payload, provider) {
    return {
        request_id: payload?.request_id ?? null,
        provider,
        checksum: payload?.checksum ?? null,
        knowledge_version: payload?.knowledge_version ?? null,
        batch_index: payload?.batch_index ?? null,
        batch_count: payload?.batch_count ?? null,
        document_count: payload?.document_count ?? null,
        batch_document_count: Array.isArray(payload?.documents) ? payload.documents.length : 0,
        full_manifest: payload?.full_manifest === true,
    };
}

export function buildLoopItems(payload, provider) {
    const request = createRequestContext(payload, provider);

    return (Array.isArray(payload?.documents) ? payload.documents : []).map((document) => ({
        // This per-item value is the minimum integrity evidence that lets the
        // done output reject a foreign result without copying the whole batch.
        request: { ...request, expected_document_id: document.document_id },
        document: {
            document_id: document.document_id,
            content: document.content,
            metadata: { ...document.metadata },
        },
    }));
}

export function buildDocumentResultItem(context, storageStatus, confirmed) {
    return {
        request: { ...context.request },
        result: {
            document_id: context.document.document_id,
            checksum: context.request.checksum,
            storage_status: storageStatus,
            confirmed,
        },
    };
}

export function validateEmbeddingValues(values, expectedDimensions) {
    if (!Array.isArray(values)) {
        return { valid: false, code: 'EMBEDDING_NOT_ARRAY' };
    }
    if (values.length !== expectedDimensions) {
        return { valid: false, code: 'EMBEDDING_DIMENSIONS_INVALID' };
    }
    if (!values.every((value) => typeof value === 'number' && Number.isFinite(value))) {
        return { valid: false, code: 'EMBEDDING_VALUES_INVALID' };
    }

    return { valid: true, code: null };
}

function sameRequest(left, right) {
    if (!left || !right) return false;

    return BASE_REQUEST_CONTEXT_KEYS.every((key) => left[key] === right[key]);
}

export function evaluateIngestItems(items) {
    const values = Array.isArray(items) ? items : [];
    const request = values[0]?.request ?? null;
    const batchDocumentCount = Number.isInteger(request?.batch_document_count)
        ? request.batch_document_count
        : 0;
    const expectedIds = values.map((item) => item?.request?.expected_document_id);
    const expectedSet = new Set(expectedIds);
    const seen = new Set();
    let inserted = 0;
    let alreadyPresent = 0;
    let invalid = false;
    const exactRequest = request
        && !Array.isArray(request)
        && Object.keys(request).length === REQUEST_CONTEXT_KEYS.length
        && REQUEST_CONTEXT_KEYS.every((key) => Object.hasOwn(request, key));

    if (!exactRequest
        || batchDocumentCount < 1
        || expectedIds.length !== batchDocumentCount
        || expectedSet.size !== expectedIds.length
        || values.length !== batchDocumentCount) {
        invalid = true;
    }

    for (const item of values) {
        const exactItem = item
            && !Array.isArray(item)
            && Object.keys(item).length === 2
            && Object.hasOwn(item, 'request')
            && Object.hasOwn(item, 'result');
        const itemRequest = item?.request;
        const exactItemRequest = itemRequest && Object.keys(itemRequest).length === REQUEST_CONTEXT_KEYS.length && REQUEST_CONTEXT_KEYS.every((key) => Object.hasOwn(itemRequest, key));
        const result = item?.result;
        const exactResult = result
            && !Array.isArray(result)
            && Object.keys(result).length === DOCUMENT_RESULT_KEYS.length
            && DOCUMENT_RESULT_KEYS.every((key) => Object.hasOwn(result, key));
        const documentId = result?.document_id;
        const storageStatus = result?.storage_status;

        if (!exactItem
            || !exactItemRequest
            || !sameRequest(request, itemRequest)
            || !exactResult
            || typeof itemRequest.expected_document_id !== 'string'
            || itemRequest.expected_document_id.length === 0
            || typeof documentId !== 'string'
            || documentId.length === 0
            || documentId !== itemRequest.expected_document_id
            || seen.has(documentId)
            || result.checksum !== request?.checksum
            || result.confirmed !== true
            || !CONFIRMED_STORAGE_STATUSES.has(storageStatus)) {
            invalid = true;
            continue;
        }

        seen.add(documentId);
        if (storageStatus === 'inserted') inserted += 1;
        if (storageStatus === 'already_present') alreadyPresent += 1;
    }

    if (seen.size !== expectedSet.size || expectedIds.some((documentId) => !seen.has(documentId))) {
        invalid = true;
    }

    const ingestOk = !invalid;

    return {
        request,
        ingest_ok: ingestOk,
        inserted: ingestOk ? inserted : 0,
        already_present: ingestOk ? alreadyPresent : 0,
        failed: ingestOk ? 0 : batchDocumentCount,
        accepted: ingestOk ? batchDocumentCount : 0,
        rejected: ingestOk ? 0 : batchDocumentCount,
        is_final_batch: Boolean(request && request.batch_index === request.batch_count - 1),
    };
}

export function buildIngestResponse({ ok, request, accepted, rejected, activated = false }) {
    return {
        ok: ok === true,
        request_id: request?.request_id ?? null,
        accepted: Number.isInteger(accepted) ? accepted : 0,
        rejected: Number.isInteger(rejected) ? rejected : 0,
        checksum: request?.checksum ?? null,
        knowledge_version: request?.knowledge_version ?? null,
        activated: activated === true,
    };
}

export function hasExactIngestResponse(response) {
    return Boolean(response)
        && !Array.isArray(response)
        && Object.keys(response).length === INGEST_RESPONSE_KEYS.length
        && INGEST_RESPONSE_KEYS.every((key) => Object.hasOwn(response, key));
}

export function validateIngestReceipt(response, outcome, provider) {
    const request = outcome?.request;

    return Boolean(
        response
        && !Array.isArray(response)
        && Object.keys(response).length === INGEST_RECEIPT_KEYS.length
        && INGEST_RECEIPT_KEYS.every((key) => Object.hasOwn(response, key))
        && response.provider === provider
        && response.checksum === request?.checksum
        && String(response.knowledge_version) === String(request?.knowledge_version)
        && Number.isInteger(response.batch_index)
        && response.batch_index === request?.batch_index
        && Number.isInteger(response.batch_count)
        && response.batch_count === request?.batch_count
        && Number.isInteger(response.document_count)
        && response.document_count === request?.document_count
        && Number.isInteger(response.accepted_count)
        && response.accepted_count === outcome?.accepted
        && response.full_manifest === request?.full_manifest
        && ['inserted', 'already_present'].includes(response.receipt_status)
        && typeof response.manifest_activated === 'boolean'
        && (!response.manifest_activated || request?.batch_index === request?.batch_count - 1)
    );
}

function inline(functionReference) {
    return functionReference.toString();
}

export function validateIngestReceiptCode(provider) {
    return [
        `const validateIngestReceipt = ${inline(validateIngestReceipt)};`,
        `const outcome = $('Check Ingest Result').first().json;`,
        `const rawResponses = $input.all().map((item) => item.json ?? {});`,
        `const responses = rawResponses.flatMap((value) => Array.isArray(value) ? value : (Array.isArray(value?.data) ? value.data : [value]));`,
        `const response = responses.length === 1 ? responses[0] : null;`,
        `const receiptValid = validateIngestReceipt(response, outcome, ${JSON.stringify(provider)});`,
        `return [{ json: { ...outcome, receipt_valid: receiptValid, manifest_activated: receiptValid && response.manifest_activated === true } }];`,
    ].join('\\n');
}

export function convertDocumentsCode(provider) {
    return `const createRequestContext = ${inline(createRequestContext)};
const buildLoopItems = ${inline(buildLoopItems)};
const initial = $('Constant Time Signature Check').first().json;
return buildLoopItems(initial.payload, ${JSON.stringify(provider)}).map((json, index) => ({ json, pairedItem: { item: index } }));`;
}

export function checkIngestResultCode() {
    return `const DOCUMENT_RESULT_KEYS = ${JSON.stringify(DOCUMENT_RESULT_KEYS)};
const BASE_REQUEST_CONTEXT_KEYS = ${JSON.stringify(BASE_REQUEST_CONTEXT_KEYS)};
const REQUEST_CONTEXT_KEYS = ${JSON.stringify(REQUEST_CONTEXT_KEYS)};
const CONFIRMED_STORAGE_STATUSES = new Set(['inserted', 'already_present']);
const sameRequest = ${inline(sameRequest)};
const evaluateIngestItems = ${inline(evaluateIngestItems)};
const items = $input.all();
return [{ json: evaluateIngestItems(items.map((item) => item.json)) }];`;
}

export function embeddingValidationCode(provider, dimensions) {
    const expression = provider === 'gemini'
        ? '$json?.embedding?.values'
        : '$json?.data?.[0]?.embedding';

    return `const validateEmbeddingValues = ${inline(validateEmbeddingValues)};
const context = $('Classify Existing Document').item.json;
const values = ${expression};
const validation = validateEmbeddingValues(values, ${dimensions});
return [{ json: validation.valid
  ? { ...context, embedding: values, embedding_valid: true }
  : { ...context, embedding_valid: false, failure_code: validation.code }
}];`;
}

export function buildDocumentResultCode(status, confirmed) {
    return `const buildDocumentResultItem = ${inline(buildDocumentResultItem)};
return [{ json: buildDocumentResultItem($json, ${JSON.stringify(status)}, ${confirmed === true ? 'true' : 'false'}) }];`;
}

export function buildFailedResultCode() {
    return `const buildDocumentResultItem = ${inline(buildDocumentResultItem)};
let context = $json?.request && $json?.document ? $json : null;
if (!context) {
  try { context = $('Loop Over Documents').item.json; } catch { context = null; }
}
if (!context?.request || !context?.document) throw new Error('MEDIFLOW_INGEST_CONTEXT_MISSING');
return [{ json: buildDocumentResultItem(context, 'failed', false) }];`;
}

export function buildSummaryCode(persistent) {
    return `const buildIngestResponse = ${inline(buildIngestResponse)};
const outcome = $('Check Ingest Result').first().json;
const activated = outcome.is_final_batch ? ${persistent ? '$json.manifest_activated === true' : 'true'} : false;
return [{ json: { status_code: 200, response: buildIngestResponse({ ok: true, request: outcome.request, accepted: outcome.accepted, rejected: outcome.rejected, activated }) } }];`;
}

export function buildSafeErrorCode() {
    return `const buildIngestResponse = ${inline(buildIngestResponse)};
let request = $json?.request ?? null;
if (!request && $json?.payload) {
  request = { request_id: $json.payload.request_id, checksum: $json.payload.checksum, knowledge_version: $json.payload.knowledge_version, batch_document_count: Array.isArray($json.payload.documents) ? $json.payload.documents.length : 0 };
}
if (!request) {
  try { request = $('Loop Over Documents').item.json.request; } catch { request = null; }
}
if (!request) {
  try {
    const payload = $('Constant Time Signature Check').first().json.payload;
    request = { request_id: payload.request_id, checksum: payload.checksum, knowledge_version: payload.knowledge_version, batch_document_count: Array.isArray(payload.documents) ? payload.documents.length : 0 };
  } catch { request = null; }
}
const rejected = Number.isInteger(request?.batch_document_count) ? request.batch_document_count : 0;
return [{ json: { status_code: 200, response: buildIngestResponse({ ok: false, request, accepted: 0, rejected, activated: false }) } }];`;
}

export function linkedDocumentResultCode(sourceName, status, confirmed) {
    return `const buildDocumentResultItem = ${inline(buildDocumentResultItem)};
const context = $(${JSON.stringify(sourceName)}).item.json;
return [{ json: buildDocumentResultItem(context, ${JSON.stringify(status)}, ${confirmed === true ? 'true' : 'false'}) }];`;
}

export function validateRpcResultCode() {
    return `const context = $('Validate Document Embedding').item.json;
const responses = $input.all().map((item) => item.json);
const response = responses.length === 1 && Array.isArray(responses[0]) && responses[0].length === 1 ? responses[0][0] : responses[0];
const required = ['document_id', 'checksum', 'storage_status', 'confirmed'];
const exact = responses.length === 1 && response && !Array.isArray(response) && typeof response === 'object' && Object.keys(response).length === required.length && required.every((key) => Object.hasOwn(response, key));
const valid = exact
  && response.document_id === context.document.document_id
  && response.checksum === context.request.checksum
  && ['inserted', 'already_present'].includes(response.storage_status)
  && response.confirmed === true;
const { embedding, ...safeContext } = context;
return [{ json: valid
  ? { ...safeContext, rpc_valid: true, rpc_result: { document_id: response.document_id, checksum: response.checksum, storage_status: response.storage_status, confirmed: true } }
  : { ...safeContext, rpc_valid: false }
}];`;
}

export function buildInsertedResultCode() {
    return `const buildDocumentResultItem = ${inline(buildDocumentResultItem)};
const context = $('Validate RPC Result').item.json;
const rawRows = $input.all().map((item) => item.json ?? {});
const rows = rawRows.flatMap((value) => Array.isArray(value) ? value : (Array.isArray(value?.data) ? value.data : [value]));
const row = rows.length === 1 ? rows[0] : {};
const rpc = context.rpc_result ?? {};
const confirmed = rows.length === 1
  && row.document_id === context.document.document_id
  && row.knowledge_checksum === context.request.checksum
  && rpc.document_id === context.document.document_id
  && rpc.checksum === context.request.checksum
  && ['inserted', 'already_present'].includes(rpc.storage_status)
  && rpc.confirmed === true;
return [{ json: buildDocumentResultItem(context, confirmed ? rpc.storage_status : 'failed', confirmed) }];`;
}
