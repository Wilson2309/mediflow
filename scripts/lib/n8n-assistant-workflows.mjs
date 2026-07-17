import { EXPECTED_RESPONSE_SCHEMA } from './n8n-assistant-workflow-validator.mjs';
import {
  buildDocumentResultCode,
  buildFailedResultCode,
  buildInsertedResultCode,
  buildSafeErrorCode,
  buildSummaryCode,
  checkIngestResultCode,
  convertDocumentsCode,
  embeddingValidationCode,
  linkedDocumentResultCode,
  validateIngestReceiptCode,
  validateRpcResultCode,
} from './n8n-assistant-ingest-runtime.mjs';

const FALLBACK = 'No encontré una respuesta exacta para eso. Puedes revisar la guía del módulo o contactar al administrador.';
const ROLE_DENIED = 'No puedo cambiar permisos ni acceder a funciones de otro rol.';
const SENSITIVE_DENIED = 'No puedo procesar esa pregunta porque parece contener información sensible. Reformúlala sin nombres de pacientes, identificaciones ni datos clínicos.';
const UNAUTHORIZED = 'Solicitud no autorizada.';

function workflowNode(id, name, type, typeVersion, position, parameters = {}, extra = {}) {
  return { id, name, type, typeVersion, position, parameters, ...extra };
}

function addConnection(connections, from, to, output = 0, type = 'main') {
  connections[from] ??= {};
  connections[from][type] ??= [];
  while (connections[from][type].length <= output) connections[from][type].push([]);
  connections[from][type][output].push({ node: to, type, index: 0 });
}

function ifParameters(expression) {
  return {
    conditions: {
      options: { caseSensitive: true, leftValue: '', typeValidation: 'strict', version: 2 },
      conditions: [{
        id: 'condition',
        leftValue: expression,
        rightValue: true,
        operator: { type: 'boolean', operation: 'true', singleValue: true },
      }],
      combinator: 'and',
    },
    options: {},
  };
}

function respondParameters(defaultStatus = 200) {
  return {
    respondWith: 'json',
    responseBody: '={{ JSON.stringify($json.response) }}',
    options: { responseCode: `={{ $json.status_code || ${defaultStatus} }}` },
  };
}

function queryRespondParameters(defaultStatus = 200) {
  return {
    respondWith: 'json',
    responseBody: '={{ $json.response }}',
    options: { responseCode: `={{ $json.status_code || ${defaultStatus} }}` },
  };
}

function cryptoParameters() {
  return {
    action: 'hmac',
    type: 'SHA256',
    value: '={{ $json.signature_input }}',
    dataPropertyName: 'expected_signature',
    encoding: 'hex',
  };
}

function responseObject(answer, confidence = 0, canEscalate = false) {
  return { answer, confidence, steps: [], suggestions: [], can_escalate: canEscalate };
}

function securityConfigurationCode(kind) {
  const securityConfig = kind === 'query'
    ? {
      max_request_age_seconds: 300,
      max_clock_skew_seconds: 30,
      max_question_length: 500,
      top_k: 5,
      similarity_threshold: 0.68,
      max_context_characters: 12000,
    }
    : {
      max_request_age_seconds: 300,
      max_clock_skew_seconds: 30,
      max_batch_documents: 100,
      max_raw_bytes: 524288,
    };
  return `const securityConfig = ${JSON.stringify(securityConfig)};
return $input.all().map((item, index) => ({ json: { ...item.json, security_config: securityConfig }, binary: item.binary, pairedItem: { item: index } }));`;
}

function queryValidationCode(roleModules) {
  return `const input = $input.first().json ?? {};
const securityConfig = input.security_config ?? {};
const MAX_REQUEST_AGE_SECONDS = Math.max(60, Math.min(600, Number(securityConfig.max_request_age_seconds ?? 300)));
const MAX_CLOCK_SKEW_SECONDS = Math.max(0, Math.min(60, Number(securityConfig.max_clock_skew_seconds ?? 30)));
const MAX_QUESTION_LENGTH = Math.max(100, Math.min(1000, Number(securityConfig.max_question_length ?? 500)));
const TOP_K = Math.max(3, Math.min(10, Number(securityConfig.top_k ?? 5)));
const SIMILARITY_THRESHOLD = Math.max(0, Math.min(1, Number(securityConfig.similarity_threshold ?? 0.68)));
const MAX_CONTEXT_CHARACTERS = Math.max(2000, Math.min(20000, Number(securityConfig.max_context_characters ?? 12000)));
const allowedKeys = ['request_id','question','role','module','route','connection_state','locale','knowledge_version','timestamp','allowed_modules'];
const prohibited = ['user_id','clinic_id','email','patient_id','doctor_id','payment_id','diagnosis','prescription','medical_record','password','token','cookies','historial'];
const roles = ${JSON.stringify(roleModules)};
const fail = (statusCode) => ({ valid: false, status_code: statusCode, response: ${JSON.stringify(responseObject(UNAUTHORIZED))} });
let rawBody;
try {
  rawBody = (await this.helpers.getBinaryDataBuffer(0, 'data')).toString('utf8');
} catch {
  return [{ json: fail(422) }];
}
if (Buffer.byteLength(rawBody, 'utf8') > 8192) return [{ json: fail(422) }];
const headers = Object.fromEntries(Object.entries(input.headers ?? {}).map(([key, value]) => [key.toLowerCase(), String(value)]));
const requestIdHeader = headers['x-mediflow-request-id'];
const timestampHeader = headers['x-mediflow-timestamp'];
const signature = (headers['x-mediflow-signature'] ?? '').toLowerCase();
const assistantVersion = headers['x-mediflow-assistant-version'];
if (!String(headers['content-type'] ?? '').toLowerCase().startsWith('application/json')) return [{ json: fail(422) }];
if (!requestIdHeader || !timestampHeader || !assistantVersion || !/^[a-f0-9]{64}$/.test(signature)) return [{ json: fail(401) }];
let body;
try { body = JSON.parse(rawBody); } catch { return [{ json: fail(422) }]; }
if (!body || Array.isArray(body) || typeof body !== 'object') return [{ json: fail(422) }];
const keys = Object.keys(body);
if (keys.some((key) => !allowedKeys.includes(key)) || prohibited.some((key) => Object.hasOwn(body, key))) return [{ json: fail(422) }];
if (keys.length !== allowedKeys.length || allowedKeys.some((key) => !Object.hasOwn(body, key))) return [{ json: fail(422) }];
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const timestampPattern = /^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:Z|[+-]\\d{2}:\\d{2})$/;
const parsedTimestamp = Date.parse(body.timestamp);
const ageSeconds = (Date.now() - parsedTimestamp) / 1000;
const roleModules = roles[body.role];
const validRoute = body.route === null || (typeof body.route === 'string' && body.route.length <= 255);
const validModule = body.module === null || (typeof body.module === 'string' && Boolean(roleModules?.includes(body.module)));
const validAllowedModules = Array.isArray(body.allowed_modules) && body.allowed_modules.length > 0 && body.allowed_modules.length <= 30 && new Set(body.allowed_modules).size === body.allowed_modules.length && body.allowed_modules.every((module) => typeof module === 'string' && roleModules?.includes(module));
const validVersion = body.knowledge_version === null || ((Number.isInteger(body.knowledge_version) || typeof body.knowledge_version === 'string') && String(body.knowledge_version).length <= 32);
if (body.timestamp !== timestampHeader || !timestampPattern.test(body.timestamp) || !Number.isFinite(parsedTimestamp) || ageSeconds > MAX_REQUEST_AGE_SECONDS || ageSeconds < -MAX_CLOCK_SKEW_SECONDS) return [{ json: fail(401) }];
if (!uuid.test(body.request_id) || body.request_id !== requestIdHeader || typeof body.question !== 'string' || body.question.trim().length < 2 || body.question.length > MAX_QUESTION_LENGTH || !roleModules || !validModule || !validAllowedModules || !validRoute || body.connection_state !== 'ONLINE' || body.locale !== 'es-EC' || !validVersion || String(body.knowledge_version ?? 'unknown') !== assistantVersion) return [{ json: fail(422) }];
const normalizedQuestion = body.question.normalize('NFKC').toLocaleLowerCase('es-EC');
const injectionPatterns = [
  /ignora(?:r)? (?:las |todas las )?(?:reglas|instrucciones)/,
  /(?:muestra|revela|dime).{0,30}(?:prompt|secreto|hmac|infraestructura)/,
  /act[uú]a como (?:administrador|super.?admin)/,
  /(?:cambia|eleva|modifica).{0,20}(?:mi )?rol/,
  /(?:informaci[oó]n|funciones).{0,20}(?:otro rol|super.?admin)/,
  /(?:ejecuta|llama).{0,20}(?:herramienta|comando|sql|terminal)/,
];
const sensitivePatterns = [
  /[a-z0-9.!#$%&'*+/?^_{|}~-]+@[a-z0-9-]+(?:[.][a-z0-9-]+)+/i,
  /(?:[+]?[0-9][ ().-]*){10,15}/,
  /(?:[0-9][ -]*?){13,19}/,
  /(?:c[eé]dula|identificaci[oó]n|paciente|historia cl[ií]nica de|diagn[oó]stico de|receta (?:para|de)).{0,80}[0-9]{6,}/i,
  /(?:password|contrase[nñ]a|token|api.?key|secret[o]?) *[:=] *[^ ]+/i,
];
const sensitive = sensitivePatterns.some((pattern) => pattern.test(body.question));
const guardText = normalizedQuestion.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
const restrictedByRole = {
  recepcionista: /(?:auditoria|financiero|finanzas|reporte financiero|cierre de caja|ingresos?|suscripcion|super.?admin)/i,
  caja_finanzas: /(?:historia clinica|historial clinico|diagnostico|tratamiento|receta medica|consulta clinica)/i,
  medico: /(?:auditoria financiera|cierre de caja|ingresos?|administrar usuarios?|suscripcion|super.?admin)/i,
  administrador: /(?:suscripciones globales|super.?admin|todas las clinicas)/i,
  super_admin: /(?:historia clinica|diagnostico|tratamiento|receta medica|consulta clinica|pago de clinica)/i,
};
const roleDenied = restrictedByRole[body.role]?.test(guardText) ?? false;
const injectionBlocked = injectionPatterns.some((pattern) => pattern.test(normalizedQuestion));
const ambiguous = /^[¿ ]*(?:como (?:hago|puedo hacer) (?:eso|esto)|como lo hago|que hago)[?!. ]*$/i.test(guardText.trim());
const clarificationSuggestions = {
  recepcionista: ['¿Cómo agendo una cita?', '¿Cómo consulto un paciente?', '¿Cómo reviso la agenda diaria?'],
};
return [{ json: {
  valid: true,
  signature_input: timestampHeader + '.' + rawBody,
  received_signature: signature,
  payload: { ...body, question: body.question.trim() },
  blocked_by_guardrail: sensitive || roleDenied || injectionBlocked || ambiguous,
  blocked_answer: sensitive ? ${JSON.stringify(SENSITIVE_DENIED)} : (ambiguous ? '¿Puedes aclarar si necesitas ayuda con una cita o con un paciente?' : ${JSON.stringify(ROLE_DENIED)}),
  blocked_suggestions: ambiguous && !sensitive && !roleDenied && !injectionBlocked ? (clarificationSuggestions[body.role] ?? []) : [],
  started_at_ms: Date.now(),
  config: { max_request_age_seconds: MAX_REQUEST_AGE_SECONDS, max_clock_skew_seconds: MAX_CLOCK_SKEW_SECONDS, top_k: TOP_K, similarity_threshold: SIMILARITY_THRESHOLD, max_context_characters: MAX_CONTEXT_CHARACTERS },
} }];`;
}

function ingestValidationCode(expectedProvider, roles, modules, roleModules) {
  return `const input = $input.first().json ?? {};
const securityConfig = input.security_config ?? {};
const MAX_REQUEST_AGE_SECONDS = Math.max(60, Math.min(600, Number(securityConfig.max_request_age_seconds ?? 300)));
const MAX_CLOCK_SKEW_SECONDS = Math.max(0, Math.min(60, Number(securityConfig.max_clock_skew_seconds ?? 30)));
const MAX_BATCH_DOCUMENTS = Math.max(1, Math.min(100, Number(securityConfig.max_batch_documents ?? 100)));
const MAX_RAW_BYTES = Math.max(65536, Math.min(1048576, Number(securityConfig.max_raw_bytes ?? 524288)));
const expectedProvider = ${JSON.stringify(expectedProvider)};
const roles = ${JSON.stringify(roles)};
const modules = ${JSON.stringify(modules)};
const roleModules = ${JSON.stringify(roleModules)};
const allowedKeys = ['request_id','provider','batch_index','batch_count','full_manifest','checksum','knowledge_version','document_count','documents','timestamp'];
const metadataKeys = ['entry_id','role','modules','locale','status','knowledge_version','requires_online','source'];
const fail = (statusCode) => ({ valid: false, status_code: statusCode, response: { ok: false, request_id: null, accepted: 0, rejected: 0, checksum: null, knowledge_version: null, activated: false } });
let rawBody;
try { rawBody = (await this.helpers.getBinaryDataBuffer(0, 'data')).toString('utf8'); } catch { return [{ json: fail(422) }]; }
if (Buffer.byteLength(rawBody, 'utf8') > MAX_RAW_BYTES) return [{ json: fail(422) }];
const headers = Object.fromEntries(Object.entries(input.headers ?? {}).map(([key, value]) => [key.toLowerCase(), String(value)]));
const requestIdHeader = headers['x-mediflow-request-id'];
const timestampHeader = headers['x-mediflow-timestamp'];
const signature = (headers['x-mediflow-signature'] ?? '').toLowerCase();
const assistantVersion = headers['x-mediflow-assistant-version'];
if (!String(headers['content-type'] ?? '').toLowerCase().startsWith('application/json')) return [{ json: fail(422) }];
if (!requestIdHeader || !timestampHeader || !assistantVersion || !/^[a-f0-9]{64}$/.test(signature)) return [{ json: fail(401) }];
let body;
try { body = JSON.parse(rawBody); } catch { return [{ json: fail(422) }]; }
if (!body || Array.isArray(body) || typeof body !== 'object' || Object.keys(body).length !== allowedKeys.length || Object.keys(body).some((key) => !allowedKeys.includes(key)) || allowedKeys.some((key) => !Object.hasOwn(body, key))) return [{ json: fail(422) }];
const uuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const timestampPattern = /^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:Z|[+-]\\d{2}:\\d{2})$/;
const parsedTimestamp = Date.parse(body.timestamp);
const ageSeconds = (Date.now() - parsedTimestamp) / 1000;
if (body.timestamp !== timestampHeader || !timestampPattern.test(body.timestamp) || !Number.isFinite(parsedTimestamp) || ageSeconds > MAX_REQUEST_AGE_SECONDS || ageSeconds < -MAX_CLOCK_SKEW_SECONDS) return [{ json: fail(401) }];
const scalarValid = uuid.test(body.request_id) && body.request_id === requestIdHeader && body.provider === expectedProvider && Number.isInteger(body.batch_index) && body.batch_index >= 0 && Number.isInteger(body.batch_count) && body.batch_count > 0 && body.batch_index < body.batch_count && typeof body.full_manifest === 'boolean' && /^[a-f0-9]{64}$/.test(body.checksum) && (Number.isInteger(body.knowledge_version) || typeof body.knowledge_version === 'string') && String(body.knowledge_version) === assistantVersion && Number.isInteger(body.document_count) && body.document_count > 0 && Array.isArray(body.documents) && body.documents.length > 0 && body.documents.length <= MAX_BATCH_DOCUMENTS;
if (!scalarValid) return [{ json: fail(422) }];
const seen = new Set();
for (const document of body.documents) {
  if (!document || Array.isArray(document) || Object.keys(document).length !== 3 || !Object.hasOwn(document, 'document_id') || !Object.hasOwn(document, 'content') || !Object.hasOwn(document, 'metadata')) return [{ json: fail(422) }];
  if (typeof document.document_id !== 'string' || document.document_id.length > 180 || seen.has(document.document_id) || typeof document.content !== 'string' || document.content.length < 1 || document.content.length > 12000) return [{ json: fail(422) }];
  seen.add(document.document_id);
  const metadata = document.metadata;
  if (!metadata || Array.isArray(metadata) || Object.keys(metadata).length !== metadataKeys.length || Object.keys(metadata).some((key) => !metadataKeys.includes(key)) || metadataKeys.some((key) => !Object.hasOwn(metadata, key))) return [{ json: fail(422) }];
  if (!roles.includes(metadata.role) || !Array.isArray(metadata.modules) || metadata.modules.length < 1 || metadata.modules.some((module) => !modules.includes(module) || !roleModules[metadata.role]?.includes(module)) || metadata.locale !== 'es-EC' || metadata.status !== 'active' || typeof metadata.requires_online !== 'boolean' || metadata.source !== 'knowledge-base.json' || typeof metadata.entry_id !== 'string' || !metadata.entry_id || (!Number.isInteger(metadata.knowledge_version) && typeof metadata.knowledge_version !== 'string')) return [{ json: fail(422) }];
}
return [{ json: {
  valid: true,
  signature_input: timestampHeader + '.' + rawBody,
  received_signature: signature,
  payload: body,
  config: { max_request_age_seconds: MAX_REQUEST_AGE_SECONDS, max_clock_skew_seconds: MAX_CLOCK_SKEW_SECONDS, max_batch_documents: MAX_BATCH_DOCUMENTS, max_raw_bytes: MAX_RAW_BYTES },
} }];`;
}

const compareSignatureCode = `const crypto = require('crypto');
const original = $('Validate Raw Request').first().json;
const expected = String($input.first().json.expected_signature ?? '').toLowerCase();
const received = String(original.received_signature ?? '').toLowerCase();
let signatureValid = false;
if (/^[a-f0-9]{64}$/.test(expected) && /^[a-f0-9]{64}$/.test(received)) {
  signatureValid = crypto.timingSafeEqual(Buffer.from(expected, 'hex'), Buffer.from(received, 'hex'));
}
const { signature_input, received_signature, ...safe } = original;
return [{ json: { ...safe, signature_valid: signatureValid, status_code: signatureValid ? 200 : 401, response: signatureValid ? undefined : ${JSON.stringify(responseObject(UNAUTHORIZED))} } }];`;
const ingestSignatureFailureCode = `{ ok: false, request_id: original.payload?.request_id ?? null, accepted: 0, rejected: Array.isArray(original.payload?.documents) ? original.payload.documents.length : 0, checksum: original.payload?.checksum ?? null, knowledge_version: original.payload?.knowledge_version ?? null, activated: false }`;
const compareIngestSignatureCode = compareSignatureCode.replace(JSON.stringify(responseObject(UNAUTHORIZED)), ingestSignatureFailureCode);

const queryNonceUnauthorizedCode = JSON.stringify(responseObject(UNAUTHORIZED));

const classifyNonceCode = `const original = $('Constant Time Signature Check').first().json;
const current = $input.first().json ?? {};
const errorText = JSON.stringify(current.error ?? current).toUpperCase();
const rateLimited = errorText.includes('MEDIFLOW_RATE_LIMITED');
const conflict = errorText.includes('DUPLICATE') || errorText.includes('23505') || errorText.includes('CONFLICT') || errorText.includes('ALREADY EXISTS');
const stored = current.request_id === original.payload.request_id;
const hasError = !stored || Boolean(current.error) || rateLimited || conflict;
const internalFailure = hasError && !rateLimited && !conflict;
return [{ json: {
  ...original,
  nonce_accepted: !hasError,
  status_code: rateLimited ? 429 : (conflict ? 409 : 200),
  response: hasError ? (internalFailure ? ${JSON.stringify(responseObject(FALLBACK, 0, true))} : ${JSON.stringify(responseObject(UNAUTHORIZED))}) : undefined,
  safe_error_code: rateLimited ? 'RATE_LIMITED' : (conflict ? 'REPLAY' : (hasError ? 'NONCE_ERROR' : null)),
} }];`;

const queryNonceFallbackCode = JSON.stringify(responseObject(FALLBACK, 0, true));
const ingestNonceFallbackCode = `{ ok: false, request_id: original.payload.request_id, accepted: 0, rejected: Array.isArray(original.payload.documents) ? original.payload.documents.length : 0, checksum: original.payload.checksum, knowledge_version: original.payload.knowledge_version, activated: false }`;
function withIngestNonceFailure(code) {
  const transformed = code
    .replaceAll(queryNonceFallbackCode, ingestNonceFallbackCode)
    .replaceAll(queryNonceUnauthorizedCode, ingestNonceFallbackCode);
  if (transformed === code) {
    throw new Error('No se pudo aplicar el contrato interno de error de ingesta.');
  }
  return transformed;
}

function supabaseNonceParameters(kind) {
  return {
    operation: 'create',
    tableId: 'assistant_request_nonces',
    fieldsUi: { fieldValues: [
      { fieldId: 'request_id', fieldValue: '={{ $json.payload.request_id }}' },
      { fieldId: 'expires_at', fieldValue: "={{ new Date(Date.now() + 600000).toISOString() }}" },
      { fieldId: 'workflow_type', fieldValue: kind },
      { fieldId: 'role', fieldValue: kind === 'query' ? '={{ $json.payload.role }}' : 'system' },
      { fieldId: 'status', fieldValue: 'accepted' },
    ] },
  };
}

function dataTableGetParameters(kind) {
  return {
    resource: 'row', operation: 'get',
    dataTableId: { __rl: true, value: 'MEDIFLOW_ASSISTANT_NONCES_DEV', mode: 'list', cachedResultName: 'MEDIFLOW_ASSISTANT_NONCES_DEV' },
    returnAll: true,
    filters: { conditions: [{ keyName: 'request_id', condition: 'eq', keyValue: '={{ $json.payload.request_id }}' }, { keyName: 'workflow_type', condition: 'eq', keyValue: kind }] },
  };
}

function dataTableClassifyCode() {
  return `const original = $('Constant Time Signature Check').first().json;
const items = $input.all().map((item) => item.json ?? {});
const failed = items.length === 0 || items.some((item) => item.error);
const rows = items.filter((row) => row.request_id);
const replay = rows.length > 0;
const accepted = !failed && !replay;
return [{ json: { ...original, nonce_accepted: accepted, status_code: failed ? 200 : (replay ? 409 : 200), response: failed ? ${JSON.stringify(responseObject(FALLBACK, 0, true))} : (replay ? ${JSON.stringify(responseObject(UNAUTHORIZED))} : undefined), safe_error_code: failed ? 'NONCE_STORE_ERROR' : (replay ? 'REPLAY' : null) } }];`;
}

function dataTableInsertParameters(kind) {
  return {
    resource: 'row', operation: 'insert',
    dataTableId: { __rl: true, value: 'MEDIFLOW_ASSISTANT_NONCES_DEV', mode: 'list', cachedResultName: 'MEDIFLOW_ASSISTANT_NONCES_DEV' },
    columns: { mappingMode: 'defineBelow', value: {
      request_id: '={{ $json.payload.request_id }}',
      received_at: '={{ new Date().toISOString() }}',
      expires_at: '={{ new Date(Date.now() + 600000).toISOString() }}',
      workflow_type: kind,
      status: 'accepted',
    }, matchingColumns: [], schema: [], attemptToConvertTypes: false, convertFieldsToString: false },
    options: {},
  };
}

function confirmDataTableInsertCode() {
  return `const original = $('Classify Anti-Replay Result').first().json;
const items = $input.all().map((item) => item.json ?? {});
const errorText = JSON.stringify(items).toUpperCase();
const conflict = errorText.includes('DUPLICATE') || errorText.includes('23505') || errorText.includes('CONFLICT') || errorText.includes('ALREADY EXISTS');
const recorded = items.some((item) => item.request_id === original.payload.request_id && !item.error) && !conflict;
return [{ json: { ...original, nonce_recorded: recorded, status_code: recorded ? 200 : (conflict ? 409 : 200), response: recorded ? undefined : (conflict ? ${JSON.stringify(responseObject(UNAUTHORIZED))} : ${JSON.stringify(responseObject(FALLBACK, 0, true))}), safe_error_code: recorded ? null : (conflict ? 'REPLAY' : 'NONCE_STORE_ERROR') } }];`;
}

function vectorConfig(modelProvider) {
  if (modelProvider === 'gemini') {
    return {
      table: 'assistant_documents_gemini_3072', query: 'match_assistant_documents_gemini', manifestProvider: 'gemini',
      dimensions: 3072,
      embeddingEndpoint: 'https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent',
      embeddingCredential: 'googlePalmApi',
      rpcName: 'upsert_assistant_document_gemini',
      tablePath: '/rest/v1/assistant_documents_gemini_3072',
      rpcPath: '/rest/v1/rpc/upsert_assistant_document_gemini',
      receiptPath: '/rest/v1/rpc/record_assistant_ingest_batch_receipt',
      embeddings: workflowNode('emb-gemini', 'Embeddings Gemini', '@n8n/n8n-nodes-langchain.embeddingsGoogleGemini', 1, [1240, 780], { modelName: 'models/gemini-embedding-001', options: {} }),
      model: workflowNode('model-gemini', 'Gemini Chat Model', '@n8n/n8n-nodes-langchain.lmChatGoogleGemini', 1.1, [2020, 780], { modelName: 'models/gemini-3.1-flash-lite', options: { temperature: 0.1, maxOutputTokens: 800 } }),
    };
  }
  return {
    table: 'assistant_documents_openai_1536', query: 'match_assistant_documents_openai', manifestProvider: 'openai',
    dimensions: 1536,
    embeddingEndpoint: 'https://api.openai.com/v1/embeddings',
    embeddingCredential: 'openAiApi',
    rpcName: 'upsert_assistant_document_openai',
    tablePath: '/rest/v1/assistant_documents_openai_1536',
    rpcPath: '/rest/v1/rpc/upsert_assistant_document_openai',
    receiptPath: '/rest/v1/rpc/record_assistant_ingest_batch_receipt',
    embeddings: workflowNode('emb-openai', 'Embeddings OpenAI', '@n8n/n8n-nodes-langchain.embeddingsOpenAi', 1.2, [1240, 780], { model: 'text-embedding-3-small', options: { timeout: 5 } }),
    model: workflowNode('model-openai', 'OpenAI Chat Model', '@n8n/n8n-nodes-langchain.lmChatOpenAi', 1.3, [2020, 780], { model: { __rl: true, value: 'gpt-4.1-mini', mode: 'list', cachedResultName: 'gpt-4.1-mini' }, options: { temperature: 0.1, maxTokens: 800, timeout: 5500, maxRetries: 1 } }),
  };
}

function defaultLoaderNode(id = 'loader', name = 'Default Data Loader') {
  return workflowNode(id, name, '@n8n/n8n-nodes-langchain.documentDefaultDataLoader', 1.1, [1500, 780], {
    dataType: 'json', jsonMode: 'expressionData', jsonData: '={{ $json.document?.content ?? $json.content }}', textSplittingMode: 'custom',
    options: { metadata: { metadataValues: [
      { name: 'document_id', value: '={{ $json.document?.document_id ?? $json.metadata.document_id }}' },
      { name: 'entry_id', value: '={{ $json.document?.metadata?.entry_id ?? $json.metadata.entry_id }}' },
      { name: 'role', value: '={{ $json.document?.metadata?.role ?? $json.metadata.role }}' },
      { name: 'modules', value: '={{ JSON.stringify($json.document?.metadata?.modules ?? $json.metadata.modules) }}' },
      { name: 'locale', value: '={{ $json.document?.metadata?.locale ?? $json.metadata.locale }}' },
      { name: 'status', value: '={{ $json.document?.metadata?.status ?? $json.metadata.status }}' },
      { name: 'knowledge_version', value: '={{ String($json.document?.metadata?.knowledge_version ?? $json.metadata.knowledge_version) }}' },
      { name: 'requires_online', value: '={{ String($json.document?.metadata?.requires_online ?? $json.metadata.requires_online) }}' },
      { name: 'source', value: '={{ $json.document?.metadata?.source ?? $json.metadata.source }}' },
      { name: 'checksum', value: '={{ $json.request?.checksum ?? $json.metadata.checksum }}' },
    ] } },
  });
}

function workflowBase(name, nodes, connections) {
  return {
    name,
    nodes,
    connections,
    pinData: {},
    active: false,
    settings: { executionOrder: 'v1', saveDataSuccessExecution: 'none', saveDataErrorExecution: 'none', saveManualExecutions: false, saveExecutionProgress: false },
    tags: [],
  };
}

function createQueryWorkflow({ persistent, modelProvider, systemPrompt, roleModules }) {
  const vector = vectorConfig(modelProvider);
  const explicitGeminiQuery = persistent && modelProvider === 'gemini';
  const suffix = persistent ? `Supabase ${modelProvider === 'gemini' ? 'Gemini' : 'OpenAI'}` : 'Simple Dev OpenAI';
  const nodes = [
    workflowNode('note-security', 'Security and credentials setup', 'n8n-nodes-base.stickyNote', 1, [-1240, -420], {
      content: persistent
        ? 'Asignación manual obligatoria: Crypto=MEDIFLOW_HMAC_SECRET, Supabase=MEDIFLOW_SUPABASE, chat=MEDIFLOW_AI_MODEL, embeddings=MEDIFLOW_EMBEDDINGS. No active este workflow antes de probar HMAC, nonce y filtros.'
        : 'Solo desarrollo: almacenamiento no persistente. Configure MEDIFLOW_HMAC_SECRET, MEDIFLOW_AI_MODEL, MEDIFLOW_EMBEDDINGS y Data Table MEDIFLOW_ASSISTANT_NONCES_DEV. La comprobación check-then-insert no es atómica.',
      width: 720, height: 220,
    }),
    workflowNode('webhook', 'Webhook Query', 'n8n-nodes-base.webhook', 2.1, [-1120, 0], { httpMethod: 'POST', path: 'mediflow-assistant-query', responseMode: 'responseNode', options: { rawBody: true } }),
    workflowNode('security-config', 'Security Configuration', 'n8n-nodes-base.code', 2, [-1010, 0], { mode: 'runOnceForAllItems', jsCode: securityConfigurationCode('query') }),
    workflowNode('validate', 'Validate Raw Request', 'n8n-nodes-base.code', 2, [-900, 0], { mode: 'runOnceForAllItems', jsCode: queryValidationCode(roleModules) }),
    workflowNode('if-valid', 'Payload Is Valid', 'n8n-nodes-base.if', 2.2, [-680, 0], ifParameters('={{ $json.valid }}')),
    workflowNode('respond-invalid', 'Respond Invalid Payload', 'n8n-nodes-base.respondToWebhook', 1.5, [-440, 260], queryRespondParameters(422)),
    workflowNode('crypto', 'Verify HMAC', 'n8n-nodes-base.crypto', 2, [-440, -40], cryptoParameters(), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
    workflowNode('compare', 'Constant Time Signature Check', 'n8n-nodes-base.code', 2, [-220, -40], { jsCode: compareSignatureCode }),
    workflowNode('if-signature', 'Signature Is Valid', 'n8n-nodes-base.if', 2.2, [0, -40], ifParameters('={{ $json.signature_valid }}')),
    workflowNode('respond-unauthorized', 'Respond Unauthorized', 'n8n-nodes-base.respondToWebhook', 1.5, [220, 260], queryRespondParameters(401)),
  ];
  const connections = {};
  addConnection(connections, 'Webhook Query', 'Security Configuration');
  addConnection(connections, 'Security Configuration', 'Validate Raw Request');
  addConnection(connections, 'Validate Raw Request', 'Payload Is Valid');
  addConnection(connections, 'Payload Is Valid', 'Verify HMAC', 0);
  addConnection(connections, 'Payload Is Valid', 'Respond Invalid Payload', 1);
  addConnection(connections, 'Verify HMAC', 'Constant Time Signature Check');
  addConnection(connections, 'Constant Time Signature Check', 'Signature Is Valid');
  addConnection(connections, 'Signature Is Valid', persistent ? 'Anti-Replay Supabase' : 'Anti-Replay Data Table Get', 0);
  addConnection(connections, 'Signature Is Valid', 'Respond Unauthorized', 1);

  if (persistent) {
    nodes.push(
      workflowNode('nonce-supabase', 'Anti-Replay Supabase', 'n8n-nodes-base.supabase', 1, [220, -120], supabaseNonceParameters('query'), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      workflowNode('nonce-classify', 'Classify Anti-Replay Result', 'n8n-nodes-base.code', 2, [440, -120], { jsCode: classifyNonceCode }),
    );
    addConnection(connections, 'Anti-Replay Supabase', 'Classify Anti-Replay Result');
  } else {
    nodes.push(
      workflowNode('nonce-table-get', 'Anti-Replay Data Table Get', 'n8n-nodes-base.dataTable', 1.1, [220, -120], dataTableGetParameters('query'), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      workflowNode('nonce-classify-dev', 'Classify Anti-Replay Result', 'n8n-nodes-base.code', 2, [440, -120], { jsCode: dataTableClassifyCode() }),
    );
    addConnection(connections, 'Anti-Replay Data Table Get', 'Classify Anti-Replay Result');
  }

  nodes.push(
    workflowNode('if-nonce', 'Nonce Is Fresh', 'n8n-nodes-base.if', 2.2, [660, -120], ifParameters('={{ $json.nonce_accepted }}')),
    workflowNode('respond-replay', 'Respond Replay Or Rate Limit', 'n8n-nodes-base.respondToWebhook', 1.5, [880, 260], queryRespondParameters(409)),
  );
  addConnection(connections, 'Classify Anti-Replay Result', 'Nonce Is Fresh');
  addConnection(connections, 'Nonce Is Fresh', persistent ? 'Prompt Injection Guard' : 'Record Nonce Data Table', 0);
  addConnection(connections, 'Nonce Is Fresh', 'Respond Replay Or Rate Limit', 1);

  if (!persistent) {
    nodes.push(
      workflowNode('nonce-table-insert', 'Record Nonce Data Table', 'n8n-nodes-base.dataTable', 1.1, [880, -120], dataTableInsertParameters('query'), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      workflowNode('nonce-table-confirm', 'Confirm Nonce Recorded', 'n8n-nodes-base.code', 2, [1020, -120], { jsCode: confirmDataTableInsertCode() }),
      workflowNode('if-nonce-recorded', 'Nonce Was Recorded', 'n8n-nodes-base.if', 2.2, [1100, -120], ifParameters('={{ $json.nonce_recorded }}')),
    );
    addConnection(connections, 'Record Nonce Data Table', 'Confirm Nonce Recorded');
    addConnection(connections, 'Confirm Nonce Recorded', 'Nonce Was Recorded');
    addConnection(connections, 'Nonce Was Recorded', 'Prompt Injection Guard', 0);
    addConnection(connections, 'Nonce Was Recorded', 'Respond Replay Or Rate Limit', 1);
  }

  nodes.push(
    workflowNode('if-injection', 'Prompt Injection Guard', 'n8n-nodes-base.if', 2.2, [1100, -120], ifParameters('={{ $json.blocked_by_guardrail }}')),
    workflowNode('build-denied', 'Build Role Guardrail Response', 'n8n-nodes-base.code', 2, [1320, 220], { jsCode: `const answer = typeof $json.blocked_answer === 'string' ? $json.blocked_answer : ${JSON.stringify(ROLE_DENIED)};
const suggestions = Array.isArray($json.blocked_suggestions) ? $json.blocked_suggestions.filter((item) => typeof item === 'string' && item.length > 0 && item.length <= 150).slice(0, 5) : [];
return [{ json: { status_code: 200, response: { answer, confidence: 0, steps: [], suggestions, can_escalate: false } } }];` }),
    workflowNode('respond-denied', 'Respond Role Guardrail', 'n8n-nodes-base.respondToWebhook', 1.5, [1540, 220], queryRespondParameters()),
  );
  addConnection(connections, 'Prompt Injection Guard', 'Build Role Guardrail Response', 0);
  addConnection(connections, 'Build Role Guardrail Response', 'Respond Role Guardrail');

  if (persistent) {
    if (explicitGeminiQuery) {
      nodes.push(
        workflowNode('query-endpoint-config', 'Query Endpoint Configuration', 'n8n-nodes-base.code', 2, [1260, -160], { mode: 'runOnceForAllItems', jsCode: queryEndpointConfigurationCode() }),
      );
    }
    nodes.push(
      workflowNode('manifest', 'Get Active Knowledge Manifest', 'n8n-nodes-base.supabase', 1, [explicitGeminiQuery ? 1400 : 1320, -160], {
      operation: 'getAll', tableId: 'assistant_knowledge_manifests', returnAll: false, limit: 1,
      filterType: 'manual', matchType: 'allFilters', filters: { conditions: [{ keyName: 'provider', condition: 'eq', keyValue: vector.manifestProvider }] },
      }, { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      workflowNode('validate-manifest', 'Validate Active Knowledge Manifest', 'n8n-nodes-base.code', 2, [explicitGeminiQuery ? 1540 : 1460, -160], { mode: 'runOnceForAllItems', jsCode: `const request = $('Constant Time Signature Check').first().json;
const rows = $input.all().map((item) => item.json ?? {});
const manifest = rows.find((row) => row.provider === ${JSON.stringify(vector.manifestProvider)} && /^[a-f0-9]{64}$/.test(String(row.active_checksum ?? '')) && Number(row.document_count) > 0 && String(row.knowledge_version) === String(request.payload.knowledge_version) && !row.error);
return [{ json: { ...request, manifest_valid: Boolean(manifest), active_checksum: manifest?.active_checksum ?? null } }];` }),
      workflowNode('if-manifest-valid', 'Active Manifest Is Valid', 'n8n-nodes-base.if', 2.2, [explicitGeminiQuery ? 1680 : 1600, -160], ifParameters('={{ $json.manifest_valid }}')),
    );
  }

  const vectorStoreName = persistent ? 'Supabase Vector Store' : 'Simple Vector Store';
  if (explicitGeminiQuery) {
    nodes.push(
      workflowNode('query-embedding', 'Generate Query Embedding', 'n8n-nodes-base.httpRequest', 4.2, [1840, -160], {
        method: 'POST',
        url: vector.embeddingEndpoint,
        authentication: 'predefinedCredentialType',
        nodeCredentialType: vector.embeddingCredential,
        sendBody: true,
        contentType: 'json',
        specifyBody: 'json',
        jsonBody: "={{ { model: 'models/gemini-embedding-001', content: { parts: [{ text: $json.payload.question }] }, taskType: 'RETRIEVAL_QUERY', outputDimensionality: 3072 } }}",
        options: { timeout: 10000, response: { response: { responseFormat: 'json' } } },
      }, { onError: 'continueErrorOutput' }),
      workflowNode('validate-query-embedding', 'Validate Query Embedding', 'n8n-nodes-base.code', 2, [2000, -160], { jsCode: `const original = $('Validate Active Knowledge Manifest').first().json;
const response = $input.first().json ?? {};
const values = response.embedding?.values;
const valid = Array.isArray(values) && values.length === 3072 && values.every((value) => typeof value === 'number' && Number.isFinite(value));
return [{ json: { ...original, query_embedding_valid: valid, query_embedding: valid ? values : null } }];` }),
      workflowNode('if-query-embedding-valid', 'Query Embedding Is Valid', 'n8n-nodes-base.if', 2.2, [2160, -160], ifParameters('={{ $json.query_embedding_valid }}')),
      workflowNode('query-rpc', 'Call Gemini Vector RPC', 'n8n-nodes-base.httpRequest', 4.2, [2320, -160], {
        method: 'POST',
        url: supabaseEndpointExpression('/rest/v1/rpc/match_assistant_documents_gemini', 'Query Endpoint Configuration'),
        authentication: 'predefinedCredentialType',
        nodeCredentialType: 'supabaseApi',
        sendBody: true,
        contentType: 'json',
        specifyBody: 'json',
        jsonBody: "={{ { query_embedding: $json.query_embedding, match_count: 30, filter: { role: $json.payload.role, status: 'active', locale: $json.payload.locale, checksum: $json.active_checksum } } }}",
        options: { timeout: 10000, response: { response: { responseFormat: 'json' } } },
      }, { onError: 'continueErrorOutput' }),
    );
  } else {
    nodes.push(
      persistent
      ? workflowNode('vector-supabase', vectorStoreName, '@n8n/n8n-nodes-langchain.vectorStoreSupabase', 1.3, [1540, -160], {
        mode: 'load',
        tableName: { __rl: true, value: vector.table, mode: 'list', cachedResultName: vector.table },
        prompt: "={{ $('Constant Time Signature Check').first().json.payload.question + ' | módulo: ' + $('Constant Time Signature Check').first().json.payload.module + ' | ruta: ' + ($('Constant Time Signature Check').first().json.payload.route || '') }}",
        topK: 30,
        includeDocumentMetadata: true,
        options: { queryName: vector.query, metadata: { metadataValues: [
          { name: 'role', value: "={{ $('Constant Time Signature Check').first().json.payload.role }}" },
          { name: 'status', value: 'active' },
          { name: 'locale', value: "={{ $('Constant Time Signature Check').first().json.payload.locale }}" },
          { name: 'checksum', value: "={{ $('Validate Active Knowledge Manifest').first().json.active_checksum }}" },
        ] } },
      }, { alwaysOutputData: true, onError: 'continueRegularOutput' })
      : workflowNode('vector-simple', vectorStoreName, '@n8n/n8n-nodes-langchain.vectorStoreInMemory', 1.3, [1540, -160], {
        mode: 'load',
        memoryKey: { __rl: true, mode: 'id', value: "={{ 'mediflow-assistant-phase4-' + $('Constant Time Signature Check').first().json.payload.role }}" },
        prompt: "={{ $('Constant Time Signature Check').first().json.payload.question + ' | módulo: ' + $('Constant Time Signature Check').first().json.payload.module + ' | ruta: ' + ($('Constant Time Signature Check').first().json.payload.route || '') }}",
        topK: 30,
      }, { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      vector.embeddings,
    );
  }
  nodes.push(
    workflowNode('filter-context', 'Filter Role Module Context', 'n8n-nodes-base.code', 2, [1760, -160], { mode: 'runOnceForAllItems', jsCode: `const request = $('Constant Time Signature Check').first().json;
const activeChecksum = ${persistent ? "$('Validate Active Knowledge Manifest').first().json.active_checksum" : 'null'};
const selected = [];
const rows = $input.all().flatMap((item) => {
  const value = item.json ?? {};
  return Array.isArray(value) ? value : (Array.isArray(value.data) ? value.data : [value]);
});
for (const json of rows) {
  const document = json.document ?? json;
  const metadata = document.metadata ?? json.metadata ?? {};
  let modules = metadata.modules ?? [];
  if (typeof modules === 'string') { try { modules = JSON.parse(modules); } catch { modules = [modules]; } }
  const pageContent = String(document.pageContent ?? document.content ?? json.pageContent ?? json.content ?? '');
  const scoreRaw = json.score ?? json.similarity ?? document.score ?? metadata.similarity;
  const score = scoreRaw === undefined && ${persistent ? 'false' : 'true'} ? 1 : Number(scoreRaw ?? 0);
  const moduleAllowed = Array.isArray(modules) && modules.length > 0 && modules.some((module) => request.payload.allowed_modules.includes(module));
  const checksumAllowed = ${persistent ? 'metadata.checksum === activeChecksum' : 'true'};
  if (metadata.role === request.payload.role && metadata.status === 'active' && metadata.locale === request.payload.locale && checksumAllowed && moduleAllowed && Number.isFinite(score) && score >= request.config.similarity_threshold && pageContent) selected.push({ pageContent, score, document_id: String(metadata.document_id ?? ''), role: metadata.role, modules });
}
selected.sort((a, b) => b.score - a.score);
const top = selected.slice(0, request.config.top_k);
let context = '';
for (const entry of top) {
  const candidate = context + (context ? '\\n\\n--- DOCUMENTO AUTORIZADO ---\\n' : '') + entry.pageContent;
  if (candidate.length > request.config.max_context_characters) break;
  context = candidate;
}
return [{ json: { ...request, context, context_sufficient: top.length > 0 && context.length > 0, retrieved_document_count: top.length, selected_documents: top.map(({ document_id, role, modules, score }) => ({ document_id, role, modules, score })) } }];` }),
    workflowNode('if-context', 'Context Is Sufficient', 'n8n-nodes-base.if', 2.2, [1980, -160], ifParameters('={{ $json.context_sufficient }}')),
    workflowNode('build-fallback', 'Build Context Fallback', 'n8n-nodes-base.code', 2, [2200, 220], { jsCode: `return [{ json: { status_code: 200, response: ${JSON.stringify(responseObject(FALLBACK, 0, true))}, fallback_used: true, safe_error_code: 'NO_CONTEXT' } }];` }),
    workflowNode('respond-fallback', 'Respond Context Fallback', 'n8n-nodes-base.respondToWebhook', 1.5, [2420, 220], queryRespondParameters()),
    workflowNode('chain', 'Basic LLM Chain', '@n8n/n8n-nodes-langchain.chainLlm', 1.9, [2200, -180], {
      promptType: 'define',
      text: `=ROL AUTORIZADO: {{$json.payload.role}}\nMÓDULO: {{$json.payload.module}}\nRUTA (solo contexto, no autorización): {{$json.payload.route || ''}}\nESTADO: {{$json.payload.connection_state}}\n\n<documentos_autorizados>\n{{$json.context}}\n</documentos_autorizados>\n\n<pregunta_no_confiable>\n{{$json.payload.question}}\n</pregunta_no_confiable>`,
      hasOutputParser: true,
      messages: { messageValues: [{ type: 'SystemMessagePromptTemplate', message: systemPrompt }] },
      batching: { batchSize: 1, delayBetweenBatches: 0 },
      options: {},
    }, { alwaysOutputData: true, onError: 'continueRegularOutput' }),
    vector.model,
    workflowNode('parser', 'Structured Output Parser', '@n8n/n8n-nodes-langchain.outputParserStructured', 1.3, [2260, 20], { schemaType: 'manual', inputSchema: JSON.stringify(EXPECTED_RESPONSE_SCHEMA), autoFix: false }),
    workflowNode('final-validator', 'Validate Final Response', 'n8n-nodes-base.code', 2, [2440, -180], { jsCode: `const request = $('Filter Role Module Context').first().json;
const raw = $input.first().json ?? {};
let candidate = raw.output ?? raw.response ?? raw;
if (typeof candidate === 'string') { try { candidate = JSON.parse(candidate); } catch { candidate = null; } }
const allowedKeys = ['answer','confidence','steps','suggestions','can_escalate'];
const textValues = candidate ? [candidate.answer, ...(Array.isArray(candidate.steps) ? candidate.steps : []), ...(Array.isArray(candidate.suggestions) ? candidate.suggestions : [])] : [];
const unsafe = /<[^>]+>|javascript:|https?:\\/\\/|www\\.|(?:^|\\s)(?:select|insert|update|delete|drop|alter|truncate)\\s+(?:from|into|table|database|\\*)|(?:npm|composer|php artisan|curl|wget|powershell|cmd\\.exe|bash)\\s|elud(?:e|ir).{0,20}permis|ignora.{0,20}(?:rol|permis)|\\x60{3}|\\[[^\\]]+\\]\\(/i;
const decode = (text) => String(text).replaceAll('&lt;', '<').replaceAll('&LT;', '<').replaceAll('&gt;', '>').replaceAll('&GT;', '>').replaceAll('&#60;', '<').replaceAll('&#x3c;', '<').replaceAll('&#62;', '>').replaceAll('&#x3e;', '>').normalize('NFKC');
const unsafeExpanded = (text) => {
  const normalized = decode(text).toLocaleLowerCase('es-EC');
  const forbiddenPaths = ['/etc', '/var', '/home', '/users', '/admin', '/api', '/storage'];
  return /(?:select|insert|update|delete|drop|alter|truncate|create|grant|revoke)(?: |[(]|$)|(?:npm|composer|php artisan|curl|wget|powershell|cmd.exe|bash|git|node|python|rm)(?: |$)|(?:[a-z]:[\\\\/])/i.test(normalized) || forbiddenPaths.some((path) => normalized.includes(path));
};
const forbiddenByRole = {
  medico: /(?:ingresos|cierre de caja|auditoría financiera|administrar usuarios|suscripciones)/i,
  caja_finanzas: /(?:historia clínica|diagnóstico|tratamiento|receta médica|consulta clínica)/i,
  recepcionista: /(?:diagnosticar|tratamiento médico|auditoría financiera|suscripciones)/i,
  administrador: /(?:gestionar todas las clínicas|suscripciones globales|super.?admin)/i,
  super_admin: /(?:diagnóstico|tratamiento|historia clínica de)/i,
};
const safeDenial = (text) => /(?:\bno (?:puedes|debes|está permitido|tienes permiso)|\btu rol no|\bno está disponible para tu rol)/i.test(decode(text));
const valid = candidate && !Array.isArray(candidate) && Object.keys(candidate).length === allowedKeys.length && Object.keys(candidate).every((key) => allowedKeys.includes(key)) && allowedKeys.every((key) => Object.hasOwn(candidate, key)) && typeof candidate.answer === 'string' && candidate.answer.trim().length >= 1 && candidate.answer.length <= 2000 && typeof candidate.confidence === 'number' && candidate.confidence >= 0 && candidate.confidence <= 1 && Array.isArray(candidate.steps) && candidate.steps.length <= 10 && candidate.steps.every((step) => typeof step === 'string' && step.length >= 1 && step.length <= 300) && Array.isArray(candidate.suggestions) && candidate.suggestions.length <= 5 && candidate.suggestions.every((suggestion) => typeof suggestion === 'string' && suggestion.length >= 1 && suggestion.length <= 150) && typeof candidate.can_escalate === 'boolean' && textValues.every((text) => typeof text === 'string' && !unsafe.test(decode(text)) && !unsafeExpanded(text)) && textValues.every((text) => !forbiddenByRole[request.payload.role]?.test(decode(text)));
return [{ json: { status_code: 200, response: valid ? candidate : ${JSON.stringify(responseObject(FALLBACK, 0, true))}, fallback_used: !valid, safe_error_code: valid ? null : 'INVALID_MODEL_RESPONSE', telemetry: { request_id: request.payload.request_id, timestamp: request.payload.timestamp, workflow_version: '4.0', role: request.payload.role, module: request.payload.module, status: valid ? 'success' : 'fallback', latency_ms: Math.max(0, Date.now() - Number(request.started_at_ms || Date.now())), retrieved_document_count: request.retrieved_document_count, confidence: valid ? candidate.confidence : 0, fallback_used: !valid } } }];` }),
    workflowNode('respond-success', 'Respond Success Or Safe Fallback', 'n8n-nodes-base.respondToWebhook', 1.5, [2660, -180], queryRespondParameters()),
  );

  addConnection(connections, 'Prompt Injection Guard', persistent ? (explicitGeminiQuery ? 'Query Endpoint Configuration' : 'Get Active Knowledge Manifest') : vectorStoreName, 1);
  if (persistent) {
    if (explicitGeminiQuery) {
      addConnection(connections, 'Query Endpoint Configuration', 'Get Active Knowledge Manifest');
    }
    addConnection(connections, 'Get Active Knowledge Manifest', 'Validate Active Knowledge Manifest');
    addConnection(connections, 'Validate Active Knowledge Manifest', 'Active Manifest Is Valid');
    addConnection(connections, 'Active Manifest Is Valid', explicitGeminiQuery ? 'Generate Query Embedding' : vectorStoreName, 0);
    addConnection(connections, 'Active Manifest Is Valid', 'Build Context Fallback', 1);
  }
  if (explicitGeminiQuery) {
    addConnection(connections, 'Generate Query Embedding', 'Validate Query Embedding', 0);
    addConnection(connections, 'Generate Query Embedding', 'Build Context Fallback', 1);
    addConnection(connections, 'Validate Query Embedding', 'Query Embedding Is Valid');
    addConnection(connections, 'Query Embedding Is Valid', 'Call Gemini Vector RPC', 0);
    addConnection(connections, 'Query Embedding Is Valid', 'Build Context Fallback', 1);
    addConnection(connections, 'Call Gemini Vector RPC', 'Filter Role Module Context', 0);
    addConnection(connections, 'Call Gemini Vector RPC', 'Build Context Fallback', 1);
  } else {
    addConnection(connections, vectorStoreName, 'Filter Role Module Context');
  }
  addConnection(connections, 'Filter Role Module Context', 'Context Is Sufficient');
  addConnection(connections, 'Context Is Sufficient', 'Basic LLM Chain', 0);
  addConnection(connections, 'Context Is Sufficient', 'Build Context Fallback', 1);
  addConnection(connections, 'Build Context Fallback', 'Respond Context Fallback');
  addConnection(connections, 'Basic LLM Chain', 'Validate Final Response');
  addConnection(connections, 'Validate Final Response', 'Respond Success Or Safe Fallback');
  if (!explicitGeminiQuery) {
    addConnection(connections, vector.embeddings.name, vectorStoreName, 0, 'ai_embedding');
  }
  addConnection(connections, vector.model.name, 'Basic LLM Chain', 0, 'ai_languageModel');
  addConnection(connections, 'Structured Output Parser', 'Basic LLM Chain', 0, 'ai_outputParser');

  return workflowBase(`MediFlow Assistant Query - ${suffix}`, nodes, connections);
}

function embeddingHttpParameters(vector) {
  const body = vector.manifestProvider === 'gemini'
    ? "={{ { model: 'models/gemini-embedding-001', content: { parts: [{ text: $json.document.content }] }, taskType: 'RETRIEVAL_DOCUMENT', outputDimensionality: 3072 } }}"
    : "={{ { model: 'text-embedding-3-small', input: $json.document.content, dimensions: 1536, encoding_format: 'float' } }}";

  return {
    method: 'POST',
    url: vector.embeddingEndpoint,
    authentication: 'predefinedCredentialType',
    nodeCredentialType: vector.embeddingCredential,
    sendBody: true,
    contentType: 'json',
    specifyBody: 'json',
    jsonBody: body,
    options: { timeout: 10000, response: { response: { responseFormat: 'json' } } },
  };
}

function ingestEndpointConfigurationCode() {
  return [
    "const supabaseBaseUrl = 'https://your-project.supabase.co';",
    "if (supabaseBaseUrl === 'https://your-project.supabase.co' || !/^https:\\/\\/[a-z0-9-]+\\.supabase\\.co$/i.test(supabaseBaseUrl)) {",
    "  throw new Error('MEDIFLOW_SUPABASE_BASE_URL_UNCONFIGURED');",
    "}",
    'return [{ json: { ...$json, supabase_base_url: supabaseBaseUrl } }];',
  ].join('\n');
}

function queryEndpointConfigurationCode() {
  return [
    "const supabaseBaseUrl = 'https://iewejmvldfqambfivnhj.supabase.co';",
    "if (!/^https:\\/\\/[a-z0-9-]+\\.supabase\\.co$/i.test(supabaseBaseUrl)) {",
    "  throw new Error('MEDIFLOW_SUPABASE_BASE_URL_UNCONFIGURED');",
    '}',
    'return [{ json: { ...$json, supabase_base_url: supabaseBaseUrl } }];',
  ].join('\n');
}

function supabaseEndpointExpression(path, configurationNode = 'Ingest Endpoint Configuration') {
  return "={{ $('" + configurationNode + "').first().json.supabase_base_url + " + JSON.stringify(path) + " }}";
}

function rpcHttpParameters(vector) {
  return {
    method: 'POST',
    url: supabaseEndpointExpression(vector.rpcPath),
    authentication: 'predefinedCredentialType',
    nodeCredentialType: 'supabaseApi',
    sendBody: true,
    contentType: 'json',
    specifyBody: 'json',
    jsonBody: "={{ { content: $json.document.content, metadata: { ...$json.document.metadata, document_id: $json.document.document_id, checksum: $json.request.checksum }, embedding: $json.embedding } }}",
    options: { timeout: 10000, response: { response: { responseFormat: 'json' } } },
  };
}

function receiptRpcHttpParameters(vector) {
  return {
    method: 'POST',
    url: supabaseEndpointExpression(vector.receiptPath),
    authentication: 'predefinedCredentialType',
    nodeCredentialType: 'supabaseApi',
    sendBody: true,
    contentType: 'json',
    specifyBody: 'json',
    jsonBody: "={{ { input_request_id: $json.request.request_id, input_provider: " + JSON.stringify(vector.manifestProvider) + ", input_checksum: $json.request.checksum, input_knowledge_version: String($json.request.knowledge_version), input_batch_index: $json.request.batch_index, input_batch_count: $json.request.batch_count, input_document_count: $json.request.document_count, input_accepted_count: $json.accepted, input_full_manifest: $json.request.full_manifest } }}",
    options: { timeout: 10000, response: { response: { responseFormat: 'json' } } },
  };
}

function documentLookupHttpParameters(vector) {
  return {
    method: 'GET',
    url: supabaseEndpointExpression(vector.tablePath),
    authentication: 'predefinedCredentialType',
    nodeCredentialType: 'supabaseApi',
    sendQuery: true,
    queryParameters: { parameters: [
      { name: 'select', value: 'document_id,knowledge_checksum' },
      { name: 'document_id', value: '={{ "eq." + $json.document.document_id }}' },
      { name: 'knowledge_checksum', value: '={{ "eq." + $json.request.checksum }}' },
      { name: 'limit', value: '1' },
    ] },
    options: {
      timeout: 10000,
      response: { response: { responseFormat: 'json' } },
    },
  };
}

function createIngestWorkflow({ persistent, modelProvider, roles, modules, roleModules }) {
  const vector = vectorConfig(modelProvider);
  const suffix = persistent ? `Supabase ${modelProvider === 'gemini' ? 'Gemini' : 'OpenAI'}` : 'Simple Dev OpenAI';
  const nodes = [
    workflowNode('note-ingest', 'Ingest security and credentials setup', 'n8n-nodes-base.stickyNote', 1, [-1240, -440], {
      content: persistent
        ? 'Asignación manual obligatoria: Crypto=MEDIFLOW_HMAC_INGEST_SECRET, Supabase=MEDIFLOW_SUPABASE y embeddings=MEDIFLOW_EMBEDDINGS. Antes de publicar, edita el único nodo Ingest Endpoint Configuration con el host privado autorizado. La activación ocurre solo desde el lote final completo.'
        : 'Solo desarrollo: almacenamiento no persistente. Configure MEDIFLOW_HMAC_INGEST_SECRET, MEDIFLOW_EMBEDDINGS y Data Table MEDIFLOW_ASSISTANT_NONCES_DEV. No usar en producción.',
      width: 720, height: 220,
    }),
    workflowNode('webhook-ingest', 'Webhook Ingest', 'n8n-nodes-base.webhook', 2.1, [-1120, 0], { httpMethod: 'POST', path: 'mediflow-assistant-ingest', responseMode: 'responseNode', options: { rawBody: true } }),
    workflowNode('security-config-ingest', 'Security Configuration', 'n8n-nodes-base.code', 2, [-1010, 0], { mode: 'runOnceForAllItems', jsCode: securityConfigurationCode('ingest') }),
    workflowNode('validate-ingest', 'Validate Raw Request', 'n8n-nodes-base.code', 2, [-900, 0], { mode: 'runOnceForAllItems', jsCode: ingestValidationCode(persistent ? 'supabase' : 'simple', roles, modules, roleModules) }),
    workflowNode('if-valid-ingest', 'Payload Is Valid', 'n8n-nodes-base.if', 2.2, [-680, 0], ifParameters('={{ $json.valid }}')),
    workflowNode('respond-invalid-ingest', 'Respond Invalid Payload', 'n8n-nodes-base.respondToWebhook', 1.5, [-440, 280], respondParameters(422)),
    workflowNode('crypto-ingest', 'Verify HMAC', 'n8n-nodes-base.crypto', 2, [-440, -40], cryptoParameters(), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
    workflowNode('compare-ingest', 'Constant Time Signature Check', 'n8n-nodes-base.code', 2, [-220, -40], { jsCode: compareIngestSignatureCode }),
    workflowNode('if-signature-ingest', 'Signature Is Valid', 'n8n-nodes-base.if', 2.2, [0, -40], ifParameters('={{ $json.signature_valid }}')),
    workflowNode('respond-unauthorized-ingest', 'Respond Unauthorized', 'n8n-nodes-base.respondToWebhook', 1.5, [220, 280], { ...respondParameters(401) }),
  ];
  const connections = {};
  addConnection(connections, 'Webhook Ingest', 'Security Configuration');
  addConnection(connections, 'Security Configuration', 'Validate Raw Request');
  addConnection(connections, 'Validate Raw Request', 'Payload Is Valid');
  addConnection(connections, 'Payload Is Valid', 'Verify HMAC', 0);
  addConnection(connections, 'Payload Is Valid', 'Respond Invalid Payload', 1);
  addConnection(connections, 'Verify HMAC', 'Constant Time Signature Check');
  addConnection(connections, 'Constant Time Signature Check', 'Signature Is Valid');
  addConnection(connections, 'Signature Is Valid', persistent ? 'Anti-Replay Supabase' : 'Anti-Replay Data Table Get', 0);
  addConnection(connections, 'Signature Is Valid', 'Respond Unauthorized', 1);

  if (persistent) {
    nodes.push(
      workflowNode('nonce-supabase-ingest', 'Anti-Replay Supabase', 'n8n-nodes-base.supabase', 1, [220, -120], supabaseNonceParameters('ingest'), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      workflowNode('nonce-classify-ingest', 'Classify Anti-Replay Result', 'n8n-nodes-base.code', 2, [440, -120], { jsCode: withIngestNonceFailure(classifyNonceCode) }),
    );
    addConnection(connections, 'Anti-Replay Supabase', 'Classify Anti-Replay Result');
  } else {
    nodes.push(
      workflowNode('nonce-table-get-ingest', 'Anti-Replay Data Table Get', 'n8n-nodes-base.dataTable', 1.1, [220, -120], dataTableGetParameters('ingest'), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      workflowNode('nonce-classify-ingest-dev', 'Classify Anti-Replay Result', 'n8n-nodes-base.code', 2, [440, -120], { jsCode: withIngestNonceFailure(dataTableClassifyCode()) }),
    );
    addConnection(connections, 'Anti-Replay Data Table Get', 'Classify Anti-Replay Result');
  }

  nodes.push(
    workflowNode('if-nonce-ingest', 'Nonce Is Fresh', 'n8n-nodes-base.if', 2.2, [660, -120], ifParameters('={{ $json.nonce_accepted }}')),
    workflowNode('respond-replay-ingest', 'Respond Replay Or Rate Limit', 'n8n-nodes-base.respondToWebhook', 1.5, [880, 280], respondParameters(409)),
  );
  addConnection(connections, 'Classify Anti-Replay Result', 'Nonce Is Fresh');
  addConnection(connections, 'Nonce Is Fresh', persistent ? 'Ingest Endpoint Configuration' : 'Record Nonce Data Table', 0);
  addConnection(connections, 'Nonce Is Fresh', 'Respond Replay Or Rate Limit', 1);

  if (!persistent) {
    nodes.push(
      workflowNode('nonce-table-insert-ingest', 'Record Nonce Data Table', 'n8n-nodes-base.dataTable', 1.1, [880, -120], dataTableInsertParameters('ingest'), { alwaysOutputData: true, onError: 'continueRegularOutput' }),
      workflowNode('nonce-table-confirm-ingest', 'Confirm Nonce Recorded', 'n8n-nodes-base.code', 2, [1020, -120], { jsCode: withIngestNonceFailure(confirmDataTableInsertCode()) }),
      workflowNode('if-nonce-recorded-ingest', 'Nonce Was Recorded', 'n8n-nodes-base.if', 2.2, [1100, -120], ifParameters('={{ $json.nonce_recorded }}')),
    );
    addConnection(connections, 'Record Nonce Data Table', 'Confirm Nonce Recorded');
    addConnection(connections, 'Confirm Nonce Recorded', 'Nonce Was Recorded');
    addConnection(connections, 'Nonce Was Recorded', 'Reset Simple Stores For Full Manifest', 0);
    addConnection(connections, 'Nonce Was Recorded', 'Respond Replay Or Rate Limit', 1);
  }

  if (!persistent) {
    nodes.push(
      workflowNode('if-reset-simple', 'Reset Simple Stores For Full Manifest', 'n8n-nodes-base.if', 2.2, [1180, -260], ifParameters('={{ $json.payload.full_manifest && $json.payload.batch_index === 0 }}')),
      workflowNode('build-reset-simple', 'Build Simple Store Reset Markers', 'n8n-nodes-base.code', 2, [1260, -360], { jsCode: `const request = $('Constant Time Signature Check').first().json;
const roles = ${JSON.stringify(roles)};
return roles.map((role) => ({ json: { content: 'MediFlow reset marker', metadata: { document_id: '__reset__:' + role + ':' + request.payload.checksum, entry_id: '__reset__', role, modules: ['support'], locale: 'es-EC', status: 'reset', knowledge_version: request.payload.knowledge_version, requires_online: false, source: 'knowledge-base.json', checksum: request.payload.checksum } } }));` }),
      workflowNode('loop-reset-simple', 'Loop Over Simple Store Resets', 'n8n-nodes-base.splitInBatches', 3, [1420, -360], { batchSize: 1, options: {} }),
      workflowNode('reset-vector-simple', 'Simple Vector Store Reset', '@n8n/n8n-nodes-langchain.vectorStoreInMemory', 1.3, [1580, -360], { mode: 'insert', memoryKey: { __rl: true, mode: 'id', value: "={{ 'mediflow-assistant-phase4-' + $json.metadata.role }}" }, clearStore: true }, { onError: 'continueErrorOutput' }),
      defaultLoaderNode('loader-reset', 'Reset Data Loader'),
      workflowNode('splitter-reset', 'Reset Marker Splitter', '@n8n/n8n-nodes-langchain.textSplitterRecursiveCharacterTextSplitter', 1, [1580, -560], { chunkSize: 12000, chunkOverlap: 0, options: {} }),
      workflowNode('check-reset-simple', 'Check Simple Store Reset', 'n8n-nodes-base.code', 2, [1740, -360], { mode: 'runOnceForAllItems', jsCode: `const request = $('Constant Time Signature Check').first().json; const results = $input.all(); const ok = results.length === ${roles.length} && !results.some((item) => item.json?.error); return [{ json: { ...request, simple_reset_ok: ok } }];` }),
      workflowNode('if-reset-ok-simple', 'Simple Store Reset Succeeded', 'n8n-nodes-base.if', 2.2, [1900, -360], ifParameters('={{ $json.simple_reset_ok }}')),
    );
  }
  nodes.push(
    workflowNode('convert-docs', 'Convert Documents', 'n8n-nodes-base.code', 2, [1100, -120], { mode: 'runOnceForAllItems', jsCode: convertDocumentsCode(vector.manifestProvider) }),
    workflowNode('loop-documents', 'Loop Over Documents', 'n8n-nodes-base.splitInBatches', 3, [1240, -120], { batchSize: 1, options: {} }),
    workflowNode('check-ingest', 'Check Ingest Result', 'n8n-nodes-base.code', 2, [2100, -120], { mode: 'runOnceForAllItems', jsCode: checkIngestResultCode() }),
    workflowNode('if-ingest-ok', 'Ingest Batch Succeeded', 'n8n-nodes-base.if', 2.2, [1800, -120], ifParameters('={{ $json.ingest_ok }}')),
    workflowNode('build-ingest-error', 'Build Ingest Safe Error', 'n8n-nodes-base.code', 2, [2580, 280], { jsCode: buildSafeErrorCode() }),
    workflowNode('respond-ingest-error', 'Respond Ingest Safe Error', 'n8n-nodes-base.respondToWebhook', 1.5, [2240, 280], respondParameters()),
    workflowNode('if-final', 'Final Batch', 'n8n-nodes-base.if', 2.2, [2020, -120], ifParameters('={{ $json.is_final_batch }}')),
    workflowNode('build-ingest-summary', 'Build Ingest Summary', 'n8n-nodes-base.code', 2, [2460, -40], { jsCode: buildSummaryCode(persistent) }),
    workflowNode('respond-ingest-summary', 'Respond Ingest Summary', 'n8n-nodes-base.respondToWebhook', 1.5, [2680, -40], respondParameters()),
  );
  if (persistent) {
    nodes.push(
      workflowNode('check-existing-document', 'Check Existing Document', 'n8n-nodes-base.httpRequest', 4.2, [1380, -300], documentLookupHttpParameters(vector), { alwaysOutputData: true, onError: 'continueErrorOutput' }),
      workflowNode('classify-existing-document', 'Classify Existing Document', 'n8n-nodes-base.code', 2, [1540, -300], { mode: 'runOnceForAllItems', jsCode: `const context = $('Loop Over Documents').item.json; const rawRows = $input.all().map((item) => item.json ?? {}); const rows = rawRows.flatMap((value) => Array.isArray(value) ? value : (Array.isArray(value?.data) ? value.data : [value])); const row = rows.length === 1 ? rows[0] : {}; const alreadyPresent = rows.length === 1 && row.document_id === context.document.document_id && row.knowledge_checksum === context.request.checksum; return [{ json: { ...context, already_present: alreadyPresent } }];` }),
      workflowNode('if-existing-document', 'Document Is Already Present', 'n8n-nodes-base.if', 2.2, [1700, -300], ifParameters('={{ $json.already_present === true }}')),
      workflowNode('build-existing-result', 'Build Already Present Result', 'n8n-nodes-base.code', 2, [1860, -380], { jsCode: buildDocumentResultCode('already_present', true) }),
      workflowNode('generate-embedding', 'Generate Document Embedding', 'n8n-nodes-base.httpRequest', 4.2, [1860, -220], embeddingHttpParameters(vector), { onError: 'continueErrorOutput' }),
      workflowNode('validate-embedding', 'Validate Document Embedding', 'n8n-nodes-base.code', 2, [2020, -220], { jsCode: embeddingValidationCode(vector.manifestProvider, vector.dimensions) }),
      workflowNode('if-embedding-valid', 'Embedding Is Valid', 'n8n-nodes-base.if', 2.2, [2180, -220], ifParameters('={{ $json.embedding_valid === true }}')),
      workflowNode('call-rpc', 'Call Idempotent Supabase RPC', 'n8n-nodes-base.httpRequest', 4.2, [2340, -220], rpcHttpParameters(vector), { onError: 'continueErrorOutput' }),
      workflowNode('validate-rpc', 'Validate RPC Result', 'n8n-nodes-base.code', 2, [2500, -220], { mode: 'runOnceForAllItems', jsCode: validateRpcResultCode() }),
      workflowNode('if-rpc-valid', 'RPC Result Is Valid', 'n8n-nodes-base.if', 2.2, [2660, -220], ifParameters('={{ $json.rpc_valid === true }}')),
      workflowNode('confirm-stored-document', 'Confirm Stored Document', 'n8n-nodes-base.httpRequest', 4.2, [2820, -220], documentLookupHttpParameters(vector), { alwaysOutputData: true, onError: 'continueErrorOutput' }),
      workflowNode('build-inserted-result', 'Build Inserted Result', 'n8n-nodes-base.code', 2, [2980, -220], { mode: 'runOnceForAllItems', jsCode: buildInsertedResultCode() }),
    );
  } else {
    nodes.push(
      workflowNode('insert-vector-simple', 'Simple Vector Store Insert', '@n8n/n8n-nodes-langchain.vectorStoreInMemory', 1.3, [1420, -120], { mode: 'insert', memoryKey: { __rl: true, mode: 'id', value: "={{ 'mediflow-assistant-phase4-' + $json.document.metadata.role }}" }, clearStore: false }, { onError: 'continueErrorOutput' }),
      workflowNode('emb-ingest', vector.embeddings.name, vector.embeddings.type, vector.embeddings.typeVersion, [1260, 120], vector.embeddings.parameters),
      defaultLoaderNode('loader-ingest'),
      workflowNode('splitter-ingest', 'Document Unit Splitter', '@n8n/n8n-nodes-langchain.textSplitterRecursiveCharacterTextSplitter', 1, [1500, 960], { chunkSize: 12000, chunkOverlap: 0, options: {} }),
      workflowNode('confirm-simple-document', 'Build Simple Inserted Result', 'n8n-nodes-base.code', 2, [1580, -120], { jsCode: linkedDocumentResultCode('Loop Over Documents', 'inserted', true) }),
    );
  }
  if (persistent) {
    nodes.push(
      workflowNode('ingest-endpoint-config', 'Ingest Endpoint Configuration', 'n8n-nodes-base.code', 2, [920, -120], { jsCode: ingestEndpointConfigurationCode() }),
    );
    addConnection(connections, 'Ingest Endpoint Configuration', 'Convert Documents');
  }
  nodes.push(
    workflowNode('build-failed-result', 'Build Failed Document Result', 'n8n-nodes-base.code', 2, [2820, 40], { jsCode: buildFailedResultCode() }),
    workflowNode('if-document-result', 'Document Result Confirmed', 'n8n-nodes-base.if', 2.2, [3140, -120], ifParameters("={{ ['inserted', 'already_present'].includes($json.result?.storage_status) && $json.result?.confirmed === true }}")),
  );
  addConnection(connections, 'Convert Documents', 'Loop Over Documents');
  addConnection(connections, 'Loop Over Documents', persistent ? 'Check Existing Document' : 'Simple Vector Store Insert', 1);
  if (persistent) {
    addConnection(connections, 'Check Existing Document', 'Classify Existing Document');
    addConnection(connections, 'Check Existing Document', 'Build Failed Document Result', 1);
    addConnection(connections, 'Classify Existing Document', 'Document Is Already Present');
    addConnection(connections, 'Document Is Already Present', 'Build Already Present Result', 0);
    addConnection(connections, 'Document Is Already Present', 'Generate Document Embedding', 1);
    addConnection(connections, 'Build Already Present Result', 'Document Result Confirmed');
    addConnection(connections, 'Generate Document Embedding', 'Validate Document Embedding');
    addConnection(connections, 'Generate Document Embedding', 'Build Failed Document Result', 1);
    addConnection(connections, 'Validate Document Embedding', 'Embedding Is Valid');
    addConnection(connections, 'Embedding Is Valid', 'Call Idempotent Supabase RPC', 0);
    addConnection(connections, 'Embedding Is Valid', 'Build Failed Document Result', 1);
    addConnection(connections, 'Call Idempotent Supabase RPC', 'Validate RPC Result');
    addConnection(connections, 'Call Idempotent Supabase RPC', 'Build Failed Document Result', 1);
    addConnection(connections, 'Validate RPC Result', 'RPC Result Is Valid');
    addConnection(connections, 'RPC Result Is Valid', 'Confirm Stored Document', 0);
    addConnection(connections, 'RPC Result Is Valid', 'Build Failed Document Result', 1);
    addConnection(connections, 'Confirm Stored Document', 'Build Inserted Result');
    addConnection(connections, 'Confirm Stored Document', 'Build Failed Document Result', 1);
    addConnection(connections, 'Build Inserted Result', 'Document Result Confirmed');
  } else {
    addConnection(connections, 'Simple Vector Store Insert', 'Build Simple Inserted Result');
    addConnection(connections, 'Simple Vector Store Insert', 'Build Failed Document Result', 1);
    addConnection(connections, 'Build Simple Inserted Result', 'Document Result Confirmed');
  }
  addConnection(connections, 'Build Failed Document Result', 'Document Result Confirmed');
  addConnection(connections, 'Document Result Confirmed', 'Loop Over Documents', 0);
  addConnection(connections, 'Document Result Confirmed', 'Build Ingest Safe Error', 1);
  addConnection(connections, 'Loop Over Documents', 'Check Ingest Result', 0);
  addConnection(connections, 'Check Ingest Result', 'Ingest Batch Succeeded');
  addConnection(connections, 'Ingest Batch Succeeded', persistent ? 'Record Ingest Batch Receipt' : 'Final Batch', 0);
  addConnection(connections, 'Ingest Batch Succeeded', 'Build Ingest Safe Error', 1);
  addConnection(connections, 'Build Ingest Safe Error', 'Respond Ingest Safe Error');

  if (!persistent) {
    addConnection(connections, vector.embeddings.name, 'Simple Vector Store Insert', 0, 'ai_embedding');
    addConnection(connections, 'Default Data Loader', 'Simple Vector Store Insert', 0, 'ai_document');
    addConnection(connections, 'Document Unit Splitter', 'Default Data Loader', 0, 'ai_textSplitter');
    addConnection(connections, 'Reset Simple Stores For Full Manifest', 'Build Simple Store Reset Markers', 0);
    addConnection(connections, 'Reset Simple Stores For Full Manifest', 'Convert Documents', 1);
    addConnection(connections, 'Build Simple Store Reset Markers', 'Loop Over Simple Store Resets');
    addConnection(connections, 'Loop Over Simple Store Resets', 'Simple Vector Store Reset', 1);
    addConnection(connections, 'Simple Vector Store Reset', 'Loop Over Simple Store Resets');
    addConnection(connections, 'Simple Vector Store Reset', 'Build Ingest Safe Error', 1);
    addConnection(connections, 'Loop Over Simple Store Resets', 'Check Simple Store Reset', 0);
    addConnection(connections, 'Check Simple Store Reset', 'Simple Store Reset Succeeded');
    addConnection(connections, 'Simple Store Reset Succeeded', 'Convert Documents', 0);
    addConnection(connections, 'Simple Store Reset Succeeded', 'Build Ingest Safe Error', 1);
    addConnection(connections, vector.embeddings.name, 'Simple Vector Store Reset', 0, 'ai_embedding');
    addConnection(connections, 'Reset Data Loader', 'Simple Vector Store Reset', 0, 'ai_document');
    addConnection(connections, 'Reset Marker Splitter', 'Reset Data Loader', 0, 'ai_textSplitter');
  }
  if (persistent) {
    nodes.push(
    workflowNode('record-batch', 'Record Ingest Batch Receipt', 'n8n-nodes-base.httpRequest', 4.2, [1940, -180], receiptRpcHttpParameters(vector), { alwaysOutputData: true, onError: 'continueErrorOutput' }),
    workflowNode('validate-batch', 'Validate Batch Receipt', 'n8n-nodes-base.code', 2, [2100, -180], { mode: 'runOnceForAllItems', jsCode: validateIngestReceiptCode(vector.manifestProvider) }),
    workflowNode('if-batch', 'Batch Receipt Was Stored', 'n8n-nodes-base.if', 2.2, [2420, -180], ifParameters('={{ $json.batch_receipt_stored }}')),
    // The receipt RPC returns the only trusted final activation state.
    // Its validated receipt drives the final branch below.
    workflowNode('if-manifest', 'Manifest Was Activated', 'n8n-nodes-base.if', 2.2, [2440, -180], ifParameters('={{ $json.manifest_activated }}')),
    );
    addConnection(connections, 'Record Ingest Batch Receipt', 'Validate Batch Receipt');
    addConnection(connections, 'Record Ingest Batch Receipt', 'Build Ingest Safe Error', 1);
    addConnection(connections, 'Validate Batch Receipt', 'Batch Receipt Was Stored');
    // Receipt RPC errors are routed to the safe error response above.
    // A receipt mismatch follows the false branch below.
    addConnection(connections, 'Batch Receipt Was Stored', 'Final Batch', 0);
    addConnection(connections, 'Batch Receipt Was Stored', 'Build Ingest Safe Error', 1);
    addConnection(connections, 'Final Batch', 'Manifest Was Activated', 0);
    addConnection(connections, 'Final Batch', 'Build Ingest Summary', 1);
    // The receipt RPC atomically reports the manifest activation state.
    // No secondary manifest read is allowed here.
    // Only the final batch may follow the activated branch.
    addConnection(connections, 'Manifest Was Activated', 'Build Ingest Summary', 0);
    addConnection(connections, 'Manifest Was Activated', 'Build Ingest Safe Error', 1);
  } else {
    addConnection(connections, 'Final Batch', 'Build Ingest Summary', 0);
    addConnection(connections, 'Final Batch', 'Build Ingest Summary', 1);
  }
  addConnection(connections, 'Build Ingest Summary', 'Respond Ingest Summary');

  return workflowBase(`MediFlow Assistant Ingest - ${suffix}`, nodes, connections);
}

export function buildAssistantWorkflows({ knowledgeBase, systemPrompt }) {
  const queryRoleModules = structuredClone(knowledgeBase.catalogs.role_modules ?? {});
  if (Object.keys(queryRoleModules).length === 0) throw new Error('Falta catalogs.role_modules en la fuente autoritativa.');
  for (const modules of Object.values(queryRoleModules)) modules.sort();
  const documentRoleModules = {};
  for (const [module, definition] of Object.entries(knowledgeBase.catalogs.modules)) {
    for (const role of definition.roles) (documentRoleModules[role] ??= []).push(module);
  }
  for (const modules of Object.values(documentRoleModules)) modules.sort();
  const roles = Object.keys(knowledgeBase.catalogs.roles).sort();
  const modules = Object.keys(knowledgeBase.catalogs.modules).sort();
  return {
    'mediflow-assistant-ingest-supabase.json': createIngestWorkflow({ persistent: true, modelProvider: 'openai', roles, modules, roleModules: documentRoleModules }),
    'mediflow-assistant-query-supabase.json': createQueryWorkflow({ persistent: true, modelProvider: 'openai', systemPrompt, roleModules: queryRoleModules }),
    'mediflow-assistant-ingest-simple.json': createIngestWorkflow({ persistent: false, modelProvider: 'openai', roles, modules, roleModules: documentRoleModules }),
    'mediflow-assistant-query-simple.json': createQueryWorkflow({ persistent: false, modelProvider: 'openai', systemPrompt, roleModules: queryRoleModules }),
    'mediflow-assistant-ingest-supabase-openai.json': createIngestWorkflow({ persistent: true, modelProvider: 'openai', roles, modules, roleModules: documentRoleModules }),
    'mediflow-assistant-query-supabase-openai.json': createQueryWorkflow({ persistent: true, modelProvider: 'openai', systemPrompt, roleModules: queryRoleModules }),
    'mediflow-assistant-ingest-supabase-gemini.json': createIngestWorkflow({ persistent: true, modelProvider: 'gemini', roles, modules, roleModules: documentRoleModules }),
    'mediflow-assistant-query-supabase-gemini.json': createQueryWorkflow({ persistent: true, modelProvider: 'gemini', systemPrompt, roleModules: queryRoleModules }),
  };
}
