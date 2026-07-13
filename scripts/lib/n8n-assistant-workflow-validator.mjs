const WEBHOOK_TYPE = 'n8n-nodes-base.webhook';
const RESPOND_TYPE = 'n8n-nodes-base.respondToWebhook';
const CRYPTO_TYPE = 'n8n-nodes-base.crypto';
const STICKY_NOTE_TYPE = 'n8n-nodes-base.stickyNote';
const LOOP_OVER_ITEMS_TYPE = 'n8n-nodes-base.splitInBatches';

export const EXPECTED_RESPONSE_SCHEMA = Object.freeze({
    type: 'object',
    additionalProperties: false,
    required: ['answer', 'confidence', 'steps', 'suggestions', 'can_escalate'],
    properties: {
        answer: { type: 'string', minLength: 1, maxLength: 2000 },
        confidence: { type: 'number', minimum: 0, maximum: 1 },
        steps: {
            type: 'array',
            maxItems: 10,
            items: { type: 'string', minLength: 1, maxLength: 300 },
        },
        suggestions: {
            type: 'array',
            maxItems: 5,
            items: { type: 'string', minLength: 1, maxLength: 150 },
        },
        can_escalate: { type: 'boolean' },
    },
});

const SECRET_KEY_PATTERN = /(?:^|_)(?:api_?key|access_?token|auth_?token|password|client_?secret|hmac_?secret|private_?key|secret)(?:$|_)/i;
const SECRET_VALUE_PATTERNS = [
    /\bsk-[a-z0-9_-]{16,}\b/i,
    /\bAIza[0-9A-Za-z_-]{20,}\b/,
    /\bgh[opusr]_[0-9A-Za-z]{20,}\b/,
    /\b(?:eyJ[a-zA-Z0-9_-]{8,}\.){2}[a-zA-Z0-9_-]{8,}\b/,
    /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/,
];

const PLACEHOLDER_PATTERN = /^(?:|placeholder|configure(?:_me)?|change(?:_me)?|replace(?:_me)?|your[_ -].+|mediflow_[a-z0-9_]+|assistant_[a-z0-9_]+|\$\{[^}]+\}|={{[\s\S]*}})$/i;

function normalizeText(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

function stable(value) {
    if (Array.isArray(value)) {
        return value.map(stable);
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(
            Object.keys(value).sort().map((key) => [key, stable(value[key])]),
        );
    }

    return value;
}

function equalSchema(actual) {
    return JSON.stringify(stable(actual)) === JSON.stringify(stable(EXPECTED_RESPONSE_SCHEMA));
}

function parseSchema(value) {
    if (value && typeof value === 'object' && !Array.isArray(value)) {
        return value;
    }

    if (typeof value !== 'string' || value.trim() === '' || value.trim().startsWith('=')) {
        return null;
    }

    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
}

function isPrivateHostname(hostname) {
    const host = hostname.toLowerCase();

    if (host === 'localhost' || host.endsWith('.local') || host.endsWith('.internal')) {
        return true;
    }

    if (/^(?:127\.|0\.|10\.|192\.168\.)/.test(host)) {
        return true;
    }

    const match = host.match(/^172\.(\d{1,3})\./);
    return match ? Number(match[1]) >= 16 && Number(match[1]) <= 31 : false;
}

function scanSensitiveLiterals(value, errors, path = '$') {
    if (Array.isArray(value)) {
        value.forEach((item, index) => scanSensitiveLiterals(item, errors, `${path}[${index}]`));
        return;
    }

    if (!value || typeof value !== 'object') {
        if (typeof value === 'string') {
            for (const pattern of SECRET_VALUE_PATTERNS) {
                if (pattern.test(value)) {
                    errors.push(`Se detectó un secreto o token literal en ${path}.`);
                    break;
                }
            }

            for (const candidate of value.matchAll(/https?:\/\/[^\s"'<>]+/gi)) {
                try {
                    const url = new URL(candidate[0].replace(/[),.;]+$/, ''));
                    if (isPrivateHostname(url.hostname)) {
                        errors.push(`Se detectó una URL privada en ${path}.`);
                    }
                } catch {
                    // A malformed URL is handled by n8n when importing the field.
                }
            }
        }

        return;
    }

    for (const [key, child] of Object.entries(value)) {
        const childPath = `${path}.${key}`;

        if (SECRET_KEY_PATTERN.test(key) && typeof child === 'string' && !PLACEHOLDER_PATTERN.test(child.trim())) {
            errors.push(`Se detectó un secreto literal en ${childPath}.`);
        }

        if (key === 'webhookId' && typeof child === 'string' && child.trim() !== '') {
            errors.push(`No se permite un webhookId persistido en ${childPath}.`);
        }

        scanSensitiveLiterals(child, errors, childPath);
    }
}

function validateCredentials(nodes, errors) {
    for (const node of nodes) {
        if (!node.credentials || typeof node.credentials !== 'object') {
            continue;
        }

        for (const [credentialType, credential] of Object.entries(node.credentials)) {
            const id = credential && typeof credential === 'object' ? String(credential.id ?? '').trim() : '';
            if (id && !PLACEHOLDER_PATTERN.test(id)) {
                errors.push(`El nodo "${node.name}" contiene un ID real de credencial (${credentialType}).`);
            }
        }
    }
}

function flattenedConnections(connectionGroup) {
    if (!connectionGroup || typeof connectionGroup !== 'object') {
        return [];
    }

    return Object.values(connectionGroup).flatMap((outputs) => (
        Array.isArray(outputs)
            ? outputs.flatMap((connections) => Array.isArray(connections) ? connections : [])
            : []
    ));
}

function mainOutputGroups(workflow, sourceName) {
    const outputs = workflow.connections?.[sourceName]?.main;
    return Array.isArray(outputs) ? outputs : [];
}

function mainDestinations(workflow, sourceName) {
    return mainOutputGroups(workflow, sourceName)
        .flatMap((connections) => Array.isArray(connections) ? connections : [])
        .map((connection) => connection?.node)
        .filter(Boolean);
}

function validateConnections(workflow, nodeMap, errors) {
    const incident = new Set();

    for (const [sourceName, groups] of Object.entries(workflow.connections ?? {})) {
        if (!nodeMap.has(sourceName)) {
            errors.push(`La conexión usa un nodo origen inexistente: "${sourceName}".`);
            continue;
        }

        for (const connection of flattenedConnections(groups)) {
            if (!connection || typeof connection.node !== 'string' || !nodeMap.has(connection.node)) {
                errors.push(`La conexión desde "${sourceName}" apunta a un nodo inexistente.`);
                continue;
            }

            incident.add(sourceName);
            incident.add(connection.node);
        }
    }

    for (const node of nodeMap.values()) {
        if (node.type !== STICKY_NOTE_TYPE && !incident.has(node.name)) {
            errors.push(`El nodo "${node.name}" está desconectado.`);
        }
    }
}

function validateAllMainBranches(workflow, webhookNodes, nodeMap, errors) {
    const reported = new Set();

    const report = (message) => {
        if (!reported.has(message)) {
            reported.add(message);
            errors.push(message);
        }
    };

    const controlledLoopHasRespondingExit = (loopName) => {
        const reachesRespond = (nodeName, visited) => {
            const node = nodeMap.get(nodeName);
            if (!node || visited.has(nodeName)) {
                return false;
            }

            if (node.type === RESPOND_TYPE) {
                return true;
            }

            const nextVisited = new Set(visited);
            nextVisited.add(nodeName);

            return mainDestinations(workflow, nodeName)
                .some((destination) => reachesRespond(destination, nextVisited));
        };

        return mainDestinations(workflow, loopName)
            .some((destination) => reachesRespond(destination, new Set([loopName])));
    };

    const walk = (nodeName, ancestry) => {
        const node = nodeMap.get(nodeName);
        if (!node) {
            report(`Una rama principal apunta al nodo inexistente "${nodeName}".`);
            return;
        }

        if (node.type === RESPOND_TYPE) {
            return;
        }

        if (ancestry.has(nodeName)) {
            if (node.type === LOOP_OVER_ITEMS_TYPE && controlledLoopHasRespondingExit(nodeName)) {
                return;
            }

            report(`La rama principal contiene un ciclo sin respuesta en "${nodeName}".`);
            return;
        }

        const outputGroups = mainOutputGroups(workflow, nodeName);
        const expectedOutputs = [LOOP_OVER_ITEMS_TYPE, 'n8n-nodes-base.if'].includes(node.type) ? 2 : null;

        if (expectedOutputs !== null && outputGroups.length < expectedOutputs) {
            report(`El nodo condicional "${nodeName}" no conecta todas sus ramas a una respuesta.`);
        }

        if (outputGroups.length === 0) {
            report(`La rama principal termina en "${nodeName}" sin Respond to Webhook.`);
            return;
        }

        const nextAncestry = new Set(ancestry);
        nextAncestry.add(nodeName);

        outputGroups.forEach((connections, outputIndex) => {
            if (!Array.isArray(connections) || connections.length === 0) {
                report(`La salida ${outputIndex} de "${nodeName}" termina sin Respond to Webhook.`);
                return;
            }

            for (const connection of connections) {
                if (connection?.type && connection.type !== 'main') {
                    report(`La rama principal de "${nodeName}" declara un tipo de conexión inválido.`);
                    continue;
                }
                walk(connection?.node, nextAncestry);
            }
        });
    };

    for (const webhook of webhookNodes) {
        walk(webhook.name, new Set());
    }
}

function workflowKind(workflow, source, webhookNodes) {
    const haystack = normalizeText([
        source,
        workflow.name,
        ...webhookNodes.map((node) => node.parameters?.path),
    ].join(' '));

    return /(?:query|consulta)/.test(haystack) ? 'query' : 'ingest';
}

function includesAll(haystack, words) {
    const normalized = normalizeText(haystack);
    return words.every((word) => normalized.includes(normalizeText(word)));
}

function findNodes(nodes, predicate) {
    return nodes.filter((node) => predicate(node, normalizeText(`${node.name} ${node.type} ${JSON.stringify(node.parameters ?? {})}`)));
}

function resourceLocatorValue(value) {
    return value && typeof value === 'object' && !Array.isArray(value) ? value.value : value;
}

function hasControlledErrorOutput(node) {
    return ['continueRegularOutput', 'continueErrorOutput'].includes(node?.onError)
        || node?.continueOnFail === true;
}

function isConnectedTo(workflow, sourceName, connectionType, destinationNames) {
    return flattenedConnections({ [connectionType]: workflow.connections?.[sourceName]?.[connectionType] })
        .some((connection) => destinationNames.has(connection.node));
}

export function validateWorkflow(workflow, { source = 'workflow.json' } = {}) {
    const errors = [];

    if (!workflow || typeof workflow !== 'object' || Array.isArray(workflow)) {
        return { valid: false, errors: ['El archivo no contiene un objeto de workflow.'], kind: 'unknown', source };
    }

    if (typeof workflow.name !== 'string' || workflow.name.trim() === '') {
        errors.push('El workflow no tiene un nombre importable.');
    }

    if (!Array.isArray(workflow.nodes) || workflow.nodes.length === 0) {
        return { valid: false, errors: [...errors, 'El workflow no contiene nodos.'], kind: 'unknown', source };
    }

    if (!workflow.connections || typeof workflow.connections !== 'object' || Array.isArray(workflow.connections)) {
        errors.push('El workflow no contiene un mapa de conexiones válido.');
    }

    if (workflow.active === true) {
        errors.push('El workflow debe permanecer inactivo al importarlo.');
    }

    for (const setting of ['saveDataSuccessExecution', 'saveDataErrorExecution']) {
        if (workflow.settings?.[setting] !== 'none') {
            errors.push(`El setting ${setting} debe ser "none" para no persistir contenido de ejecuciones.`);
        }
    }

    const names = new Set();
    const ids = new Set();
    const nodeMap = new Map();

    for (const [index, node] of workflow.nodes.entries()) {
        if (!node || typeof node !== 'object') {
            errors.push(`El nodo en índice ${index} no es válido.`);
            continue;
        }

        if (typeof node.name !== 'string' || node.name.trim() === '') {
            errors.push(`El nodo en índice ${index} no tiene nombre.`);
        } else if (names.has(node.name)) {
            errors.push(`El nombre de nodo "${node.name}" está duplicado.`);
        } else {
            names.add(node.name);
            nodeMap.set(node.name, node);
        }

        if (typeof node.id !== 'string' || node.id.trim() === '') {
            errors.push(`El nodo "${node.name ?? index}" no tiene ID.`);
        } else if (ids.has(node.id)) {
            errors.push(`El ID de nodo "${node.id}" está duplicado.`);
        } else {
            ids.add(node.id);
        }

        if (typeof node.type !== 'string' || node.type.trim() === '') {
            errors.push(`El nodo "${node.name ?? index}" no tiene tipo.`);
        }

        if (!Array.isArray(node.position) || node.position.length !== 2 || !node.position.every(Number.isFinite)) {
            errors.push(`El nodo "${node.name ?? index}" no tiene una posición importable.`);
        }

        if (node.disabled === true && node.type !== STICKY_NOTE_TYPE) {
            errors.push(`El nodo crítico "${node.name ?? index}" está deshabilitado.`);
        }
    }

    const nodes = [...nodeMap.values()];
    const webhookNodes = nodes.filter((node) => node.type === WEBHOOK_TYPE);
    const respondNodes = nodes.filter((node) => node.type === RESPOND_TYPE);
    const kind = workflowKind(workflow, source, webhookNodes);

    if (webhookNodes.length === 0) {
        errors.push('Falta el nodo Webhook.');
    }

    for (const webhook of webhookNodes) {
        if (String(webhook.parameters?.httpMethod ?? '').toUpperCase() !== 'POST') {
            errors.push(`El Webhook "${webhook.name}" debe usar POST.`);
        }

        if (webhook.parameters?.responseMode !== 'responseNode') {
            errors.push(`El Webhook "${webhook.name}" debe responder mediante Respond to Webhook.`);
        }

        const rawBody = webhook.parameters?.options?.rawBody ?? webhook.parameters?.rawBody;
        if (rawBody !== true) {
            errors.push(`El Webhook "${webhook.name}" debe activar Raw Body.`);
        }
    }

    if (respondNodes.length === 0) {
        errors.push('Falta Respond to Webhook.');
    }

    for (const respond of respondNodes) {
        const body = respond.parameters?.responseBody ?? respond.parameters?.jsonBody;
        if (body === undefined || body === null || (typeof body === 'string' && body.trim() === '') || JSON.stringify(body) === '{}') {
            errors.push(`Respond to Webhook "${respond.name}" tiene una respuesta vacía.`);
        }
    }

    const cryptoNodes = nodes.filter((node) => node.type === CRYPTO_TYPE);
    const validHmacNodes = cryptoNodes.filter((node) => {
        const parameters = node.parameters ?? {};
        return normalizeText(parameters.action) === 'hmac'
            && normalizeText(parameters.type).replace(/[^a-z0-9]/g, '') === 'sha256'
            && normalizeText(parameters.encoding || 'hex') === 'hex';
    });

    if (validHmacNodes.length === 0) {
        errors.push('Falta un nodo Crypto configurado como HMAC SHA-256 hexadecimal.');
    }
    for (const node of validHmacNodes) {
        if (!hasControlledErrorOutput(node)) {
            errors.push(`El nodo Crypto crítico "${node.name}" debe continuar por una salida de error controlada.`);
        }
    }

    const allText = JSON.stringify({ name: workflow.name, nodes: nodes.map((node) => ({ name: node.name, type: node.type, parameters: node.parameters })) });
    if (!includesAll(allText, ['x-mediflow-timestamp', 'x-mediflow-request-id', 'x-mediflow-signature'])) {
        errors.push('Falta validar timestamp, request ID o firma de los headers MediFlow.');
    }

    const signatureCheckNodes = findNodes(nodes, (_node, text) => (
        text.includes('signature') && (text.includes('constant time') || text.includes('constant_time') || text.includes('timingsafe') || text.includes('xor'))
    ));
    if (signatureCheckNodes.length === 0) {
        errors.push('Falta una comparación de firma en tiempo constante.');
    }

    const timestampNodes = findNodes(nodes, (_node, text) => text.includes('timestamp') && (text.includes('expired') || text.includes('max_age') || text.includes('future') || text.includes('date.parse')));
    if (timestampNodes.length === 0) {
        errors.push('Falta la validación estricta de antigüedad/futuro del timestamp.');
    } else if (!timestampNodes.some((node) => /(?:fail|status(?:_code)?)\s*[(=:]\s*401\b/i.test(JSON.stringify(node.parameters ?? {})))) {
        errors.push('La validación de timestamp vencido o futuro debe producir HTTP 401.');
    }

    const replayNodes = findNodes(nodes, (node, text) => (
        (text.includes('anti-replay') || text.includes('antireplay') || text.includes('replay') || text.includes('nonce'))
        && (node.type.includes('supabase') || node.type.includes('dataTable') || node.type.includes('code'))
    ));
    if (replayNodes.length === 0) {
        errors.push('Falta protección antireplay persistente o de desarrollo mediante nonce/request_id.');
    }

    if (kind === 'ingest') {
        const nonceFailureCode = replayNodes
            .filter((node) => node.type.includes('code'))
            .map((node) => String(node.parameters?.jsCode ?? ''))
            .join('\n');
        if (!includesAll(nonceFailureCode, ['ok: false', 'accepted: 0', 'rejected:', 'activated: false'])) {
            errors.push('Los fallos internos de nonce de ingesta deben conservar el contrato tecnico de ingesta.');
        }
    }

    const vectorNodes = nodes.filter((node) => normalizeText(node.type).includes('vectorstore'));
    const embeddingNodes = nodes.filter((node) => normalizeText(node.type).includes('embeddings'));
    if (vectorNodes.length === 0) {
        errors.push('Falta un Vector Store.');
    }
    if (embeddingNodes.length === 0) {
        errors.push('Falta un nodo de embeddings.');
    }
    for (const node of vectorNodes) {
        if (!hasControlledErrorOutput(node)) {
            errors.push(`El Vector Store crítico "${node.name}" debe continuar por una salida de error controlada.`);
        }
    }

    const simpleVectorNodes = vectorNodes.filter((node) => normalizeText(node.type).includes('vectorstoreinmemory'));
    for (const node of simpleVectorNodes) {
        const memoryKey = node.parameters?.memoryKey;
        if (!memoryKey || typeof memoryKey !== 'object' || Array.isArray(memoryKey) || memoryKey.__rl !== true || memoryKey.mode !== 'id' || typeof memoryKey.value !== 'string' || memoryKey.value.trim() === '') {
            errors.push(`El nodo Simple Vector Store "${node.name}" debe usar memoryKey como Resource Locator.`);
        }
    }

    if (kind === 'ingest') {
        const dataLoaders = nodes.filter((node) => normalizeText(node.type).includes('documentdefaultdataloader'));
        const customDataLoaders = dataLoaders.filter((node) => node.parameters?.textSplittingMode === 'custom');
        const documentUnitSplitters = nodes.filter((node) => (
            normalizeText(node.type).includes('textsplitter')
            && includesAll(node.name, ['document', 'unit'])
            && Number(node.parameters?.chunkSize) >= 12000
            && Number(node.parameters?.chunkOverlap) === 0
        ));
        const loaderNames = new Set(customDataLoaders.map((node) => node.name));
        const splitterConnected = documentUnitSplitters.some((node) => isConnectedTo(workflow, node.name, 'ai_textSplitter', loaderNames));

        if (customDataLoaders.length === 0 || documentUnitSplitters.length === 0 || !splitterConnected) {
            errors.push('La ingesta debe preservar cada documento como unidad mediante Data Loader custom y Document Unit Splitter conectado.');
        }

        const persistentIngest = vectorNodes.some((node) => normalizeText(node.type).includes('vectorstoresupabase'));
        if (persistentIngest) {
            const supabaseNodes = nodes.filter((node) => node.type === 'n8n-nodes-base.supabase');
            const batchReceipts = supabaseNodes.filter((node) => (
                normalizeText(node.parameters?.operation) === 'create'
                && resourceLocatorValue(node.parameters?.tableId) === 'assistant_ingest_batches'
            ));
            const directManifestUpdates = supabaseNodes.filter((node) => (
                normalizeText(node.parameters?.operation).includes('update')
                && resourceLocatorValue(node.parameters?.tableId) === 'assistant_knowledge_manifests'
            ));
            const codeUpdatesManifest = nodes.some((node) => (
                node.type === 'n8n-nodes-base.code'
                && /\bupdate\s+assistant_knowledge_manifests\b/i.test(String(node.parameters?.jsCode ?? ''))
            ));

            if (batchReceipts.length === 0) {
                errors.push('La ingesta Supabase debe registrar el lote en assistant_ingest_batches para activar el manifest mediante trigger transaccional.');
            }
            if (directManifestUpdates.length > 0 || codeUpdatesManifest) {
                errors.push('La ingesta Supabase no puede hacer UPDATE directo de assistant_knowledge_manifests.');
            }
        }
    }

    if (kind === 'query') {
        const modelNodes = nodes.filter((node) => /(?:lmchat|chatmodel|languagemodel)/.test(normalizeText(node.type)));
        if (modelNodes.length === 0) {
            errors.push('Falta un modelo de lenguaje intercambiable.');
        }

        const modelExecutionNodes = nodes.filter((node) => normalizeText(node.type).includes('chainllm'));
        for (const node of modelExecutionNodes) {
            if (!hasControlledErrorOutput(node)) {
                errors.push(`La ejecución de modelo crítica "${node.name}" debe continuar por una salida de error controlada.`);
            }
        }

        const filterNodes = findNodes(nodes, (node, text) => (
            node.type.includes('code') && text.includes('filter') && text.includes('module')
        ));
        const retrievalFilterText = JSON.stringify([
            ...vectorNodes.map((node) => node.parameters ?? {}),
            ...filterNodes.map((node) => node.parameters ?? {}),
        ]);

        for (const field of ['role', 'status', 'locale', 'module']) {
            if (!normalizeText(retrievalFilterText).includes(field)) {
                errors.push(`Falta el filtro documental obligatorio por ${field}.`);
            }
        }

        const persistentQuery = vectorNodes.some((node) => normalizeText(node.type).includes('vectorstoresupabase'));
        if (persistentQuery) {
            const manifestGet = nodeMap.get('Get Active Knowledge Manifest');
            const manifestValidator = nodeMap.get('Validate Active Knowledge Manifest');
            const manifestGate = nodeMap.get('Active Manifest Is Valid');
            const contextFilterCode = filterNodes.map((node) => String(node.parameters?.jsCode ?? '')).join('\n');
            const vectorNames = new Set(vectorNodes.map((node) => node.name));

            const validManifestFlow = manifestGet && manifestValidator && manifestGate
                && mainDestinations(workflow, manifestGet.name).includes(manifestValidator.name)
                && mainDestinations(workflow, manifestValidator.name).includes(manifestGate.name)
                && mainOutputGroups(workflow, manifestGate.name)[0]?.some((connection) => vectorNames.has(connection.node))
                && mainOutputGroups(workflow, manifestGate.name)[1]?.some((connection) => connection.node === 'Build Context Fallback');

            if (!manifestGet || !manifestValidator || !manifestGate) {
                errors.push('La consulta Supabase debe validar un manifiesto activo antes de recuperar documentos.');
            } else if (!validManifestFlow) {
                errors.push('El manifiesto activo debe fallar de forma cerrada hacia el fallback antes del Vector Store.');
            }
            if (!includesAll(manifestValidator?.parameters?.jsCode ?? '', ['active_checksum', 'knowledge_version', 'document_count', 'manifest_valid'])) {
                errors.push('La validación del manifiesto debe exigir checksum, versión y conteo documental.');
            }
            if (!includesAll(contextFilterCode, ['metadata.checksum', 'activechecksum', 'modules.every', 'purelygeneral'])) {
                errors.push('El postfiltro debe volver a comprobar checksum y aceptar globalmente solo módulos puramente generales.');
            }
            if (!vectorNodes.filter((node) => normalizeText(node.type).includes('vectorstoresupabase')).every((node) => Number(node.parameters?.topK) >= 20)) {
                errors.push('La recuperación Supabase debe obtener un conjunto candidato suficiente antes del Top K final por módulo.');
            }
        }

        const parserNodes = nodes.filter((node) => normalizeText(node.type).includes('outputparserstructured'));
        if (parserNodes.length === 0) {
            errors.push('Falta Structured Output Parser.');
        } else if (!parserNodes.some((node) => equalSchema(parseSchema(node.parameters?.inputSchema)))) {
            errors.push('El esquema del Structured Output Parser no coincide exactamente con el contrato MediFlow.');
        }

        const finalValidatorNodes = findNodes(nodes, (node, text) => (
            node.type.includes('code') && text.includes('validate') && text.includes('final') && text.includes('answer')
        ));
        if (finalValidatorNodes.length === 0) {
            errors.push('Falta la validación final independiente de la respuesta.');
        } else {
            const finalCode = finalValidatorNodes.map((node) => String(node.parameters?.jsCode ?? '')).join('\n');
            const codeWithoutSafeDrivePattern = finalCode.replaceAll('[a-z]:[\\\\/]', '');
            if (finalCode.includes('unsafeExpanded')
                && (!finalCode.includes('[\\\\/]')
                    || /\[a-z\]:\)?\/i/.test(codeWithoutSafeDrivePattern))) {
                errors.push('El guardrail de rutas debe detectar unidades Windows completas sin rechazar texto normal con dos puntos.');
            }
            if (finalCode.includes('safeDenial(text) ||')) {
                errors.push('Una denegacion no debe eximir el resto del texto del filtro por rol.');
            }
            if (finalCode.includes('forbiddenByRole') && !finalCode.includes('safeDenial')) {
                errors.push('El guardrail por rol debe permitir denegaciones seguras explícitas.');
            }
        }
    }

    validateConnections(workflow, nodeMap, errors);
    validateAllMainBranches(workflow, webhookNodes, nodeMap, errors);
    validateCredentials(nodes, errors);
    scanSensitiveLiterals(workflow, errors);

    return {
        valid: errors.length === 0,
        errors: [...new Set(errors)],
        kind,
        source,
    };
}

export function validateWorkflowJson(json, { source = 'workflow.json' } = {}) {
    let workflow;

    try {
        workflow = JSON.parse(json);
    } catch (error) {
        return {
            valid: false,
            errors: [`JSON inválido: ${error.message}`],
            kind: 'unknown',
            source,
        };
    }

    return validateWorkflow(workflow, { source });
}
