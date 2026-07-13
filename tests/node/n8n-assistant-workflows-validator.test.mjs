import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import {
    validateWorkflow,
    validateWorkflowJson,
} from '../../scripts/lib/n8n-assistant-workflow-validator.mjs';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const fixturesDirectory = path.join(currentDirectory, 'fixtures', 'n8n');

async function readJson(name) {
    return JSON.parse(await readFile(path.join(fixturesDirectory, name), 'utf8'));
}

function replaceToken(value, token, replacement) {
    if (Array.isArray(value)) {
        return value.map((item) => replaceToken(item, token, replacement));
    }

    if (value && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, child]) => [
            key.replaceAll(token, replacement),
            replaceToken(child, token, replacement),
        ]));
    }

    return typeof value === 'string' ? value.replaceAll(token, replacement) : value;
}

async function loadFixture(name) {
    const fixture = await readJson(name);
    if (!fixture.base) {
        return fixture;
    }

    let workflow = structuredClone(await readJson(fixture.base));

    if (fixture.mutation === 'remove-node') {
        workflow.nodes = workflow.nodes.filter((node) => node.name !== fixture.node);
        delete workflow.connections[fixture.node];

        for (const groups of Object.values(workflow.connections)) {
            for (const outputs of Object.values(groups)) {
                for (const connections of outputs) {
                    if (Array.isArray(connections)) {
                        connections.splice(0, connections.length, ...connections.filter((connection) => connection.node !== fixture.node));
                    }
                }
            }
        }

        if (fixture.connect) {
            const [source, destination] = fixture.connect;
            workflow.connections[source] = {
                main: [[{ node: destination, type: 'main', index: 0 }]],
            };
        }
    } else if (fixture.mutation === 'remove-filter-token') {
        workflow = replaceToken(workflow, fixture.token, 'permission');
    } else if (fixture.mutation === 'set-secret') {
        workflow.testMetadata = { [fixture.key]: fixture.value };
    } else if (fixture.mutation === 'set-parser-max-answer') {
        const parser = workflow.nodes.find((node) => node.name === 'Structured Output Parser');
        parser.parameters.inputSchema.properties.answer.maxLength = fixture.value;
    } else {
        throw new Error(`Mutación de fixture no soportada: ${fixture.mutation}`);
    }

    return workflow;
}

function assertError(result, pattern) {
    assert.equal(result.valid, false, 'se esperaba que el workflow fuera inválido');
    assert.match(result.errors.join('\n'), pattern);
}

async function persistentIngestWorkflow() {
    const workflow = await loadFixture('valid-query-workflow.json');
    workflow.name = 'MediFlow Assistant Ingest Supabase Fixture';
    const vectorStore = workflow.nodes.find((node) => node.name === 'Supabase Vector Store');
    vectorStore.type = '@n8n/n8n-nodes-langchain.vectorStoreSupabase';
    delete vectorStore.parameters.memoryKey;
    workflow.nodes.find((node) => node.name === 'Webhook Query').parameters.path = 'mediflow-assistant-ingest-fixture';
    workflow.nodes.push(
        {
            id: 'nonce-ingest-contract',
            name: 'Classify Nonce Ingest Contract',
            type: 'n8n-nodes-base.code',
            typeVersion: 2,
            position: [880, -160],
            parameters: { jsCode: 'const original = $input.first().json; return [{ json: { ...original, nonce_failure: { ok: false, request_id: original.request_id, accepted: 0, rejected: 1, activated: false } } }];' },
        },
        {
            id: 'batch-receipt',
            name: 'Record Ingest Batch Receipt',
            type: 'n8n-nodes-base.supabase',
            typeVersion: 1,
            position: [990, -160],
            onError: 'continueRegularOutput',
            parameters: { operation: 'create', tableId: 'assistant_ingest_batches' },
        },
        {
            id: 'data-loader',
            name: 'Default Data Loader',
            type: '@n8n/n8n-nodes-langchain.documentDefaultDataLoader',
            typeVersion: 1.1,
            position: [1100, 320],
            parameters: {
                dataType: 'json',
                jsonMode: 'expressionData',
                jsonData: '={{$json.content}}',
                textSplittingMode: 'custom',
            },
        },
        {
            id: 'document-unit-splitter',
            name: 'Document Unit Splitter',
            type: '@n8n/n8n-nodes-langchain.textSplitterRecursiveCharacterTextSplitter',
            typeVersion: 1,
            position: [880, 320],
            parameters: { chunkSize: 12000, chunkOverlap: 0, options: {} },
        },
    );
    workflow.connections['Anti-Replay Nonce'] = {
        main: [[{ node: 'Classify Nonce Ingest Contract', type: 'main', index: 0 }]],
    };
    workflow.connections['Classify Nonce Ingest Contract'] = {
        main: [[{ node: 'Record Ingest Batch Receipt', type: 'main', index: 0 }]],
    };
    workflow.connections['Record Ingest Batch Receipt'] = {
        main: [[{ node: 'Supabase Vector Store', type: 'main', index: 0 }]],
    };
    workflow.connections['Document Unit Splitter'] = {
        ai_textSplitter: [[{ node: 'Default Data Loader', type: 'ai_textSplitter', index: 0 }]],
    };
    workflow.connections['Default Data Loader'] = {
        ai_document: [[{ node: 'Supabase Vector Store', type: 'ai_document', index: 0 }]],
    };

    return workflow;
}

test('acepta un workflow de consulta válido y sin credenciales embebidas', async () => {
    const result = validateWorkflow(await loadFixture('valid-query-workflow.json'), {
        source: 'valid-query-workflow.json',
    });

    assert.deepEqual(result.errors, []);
    assert.equal(result.valid, true);
    assert.equal(result.kind, 'query');
});

test('rechaza un workflow sin HMAC SHA-256', async () => {
    const result = validateWorkflow(await loadFixture('missing-hmac.json'), { source: 'missing-hmac-query.json' });
    assertError(result, /HMAC SHA-256/i);
});

test('rechaza un workflow sin antireplay', async () => {
    const result = validateWorkflow(await loadFixture('missing-antireplay.json'), { source: 'missing-antireplay-query.json' });
    assertError(result, /antireplay/i);
});

test('rechaza un workflow sin filtro documental de rol', async () => {
    const result = validateWorkflow(await loadFixture('missing-role-filter.json'), { source: 'missing-role-filter-query.json' });
    assertError(result, /filtro documental obligatorio por role/i);
});

test('rechaza secretos o tokens literales', async () => {
    const result = validateWorkflow(await loadFixture('contains-secret.json'), { source: 'contains-secret-query.json' });
    assertError(result, /secreto|token/i);
});

test('rechaza un workflow sin Respond to Webhook', async () => {
    const result = validateWorkflow(await loadFixture('missing-respond.json'), { source: 'missing-respond-query.json' });
    assertError(result, /Respond to Webhook/i);
});

test('rechaza un esquema distinto al contrato exacto', async () => {
    const result = validateWorkflow(await loadFixture('invalid-parser.json'), { source: 'invalid-parser-query.json' });
    assertError(result, /esquema.*no coincide/i);
});

test('rechaza JSON mal formado antes de inspeccionar nodos', () => {
    const result = validateWorkflowJson('{"name":', { source: 'broken.json' });
    assertError(result, /JSON inválido/i);
});

test('rechaza nombres e IDs de nodo duplicados', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    workflow.nodes.push(structuredClone(workflow.nodes[0]));
    const result = validateWorkflow(workflow, { source: 'duplicate-query.json' });
    assertError(result, /duplicado/i);
});

test('rechaza Raw Body desactivado y nodos críticos deshabilitados', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    workflow.nodes.find((node) => node.name === 'Webhook Query').parameters.options.rawBody = false;
    workflow.nodes.find((node) => node.name === 'Verify HMAC').disabled = true;
    const result = validateWorkflow(workflow, { source: 'disabled-query.json' });
    assertError(result, /Raw Body/i);
    assert.match(result.errors.join('\n'), /deshabilitado/i);
});

test('rechaza credential IDs reales, pero acepta placeholders sin bloque credentials', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    workflow.nodes.find((node) => node.name === 'Chat Model').credentials = {
        openAiApi: { id: '1234567890-real-id', name: 'Personal' },
    };
    const result = validateWorkflow(workflow, { source: 'credential-query.json' });
    assertError(result, /ID real de credencial/i);

    const placeholderWorkflow = await loadFixture('valid-query-workflow.json');
    placeholderWorkflow.nodes.find((node) => node.name === 'Chat Model').credentials = {
        openAiApi: { id: 'PLACEHOLDER', name: 'MEDIFLOW_OPENAI' },
    };
    assert.equal(validateWorkflow(placeholderWorkflow, { source: 'placeholder-query.json' }).valid, true);
});

test('rechaza URLs privadas', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    workflow.testUrl = 'http://192.168.1.20:5678/webhook/private';
    const result = validateWorkflow(workflow, { source: 'private-url-query.json' });
    assertError(result, /URL privada/i);
});

test('rechaza una rama condicional que no llega a Respond to Webhook', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    const signatureNode = workflow.nodes.find((node) => node.name === 'Constant Time Signature Check');
    signatureNode.type = 'n8n-nodes-base.if';
    workflow.connections['Constant Time Signature Check'].main.push([]);
    const result = validateWorkflow(workflow, { source: 'incomplete-branch-query.json' });
    assertError(result, /salida 1.*sin Respond to Webhook/i);
});

test('acepta el ciclo controlado de Loop Over Items cuando la salida done responde', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    workflow.nodes.push(
        {
            id: 'loop-documents',
            name: 'Loop Over Documents',
            type: 'n8n-nodes-base.splitInBatches',
            typeVersion: 3.4,
            position: [990, -160],
            parameters: { batchSize: 1, options: {} },
        },
        {
            id: 'loop-body',
            name: 'Process One Document',
            type: 'n8n-nodes-base.code',
            typeVersion: 2,
            position: [1100, -280],
            parameters: { jsCode: 'return $input.all();' },
        },
    );
    workflow.connections['Anti-Replay Nonce'] = {
        main: [[{ node: 'Loop Over Documents', type: 'main', index: 0 }]],
    };
    workflow.connections['Loop Over Documents'] = {
        main: [
            [{ node: 'Supabase Vector Store', type: 'main', index: 0 }],
            [{ node: 'Process One Document', type: 'main', index: 0 }],
        ],
    };
    workflow.connections['Process One Document'] = {
        main: [[{ node: 'Loop Over Documents', type: 'main', index: 0 }]],
    };

    const result = validateWorkflow(workflow, { source: 'controlled-loop-query.json' });
    assert.deepEqual(result.errors, []);
    assert.equal(result.valid, true);
});

test('continúa rechazando ciclos arbitrarios sin un Loop Over Items controlado', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    workflow.connections['Validate Final Response'].main[0][0].node = 'Filter Role Module Context';
    const result = validateWorkflow(workflow, { source: 'arbitrary-cycle-query.json' });
    assertError(result, /ciclo sin respuesta/i);
});

test('acepta una ingesta persistente con recibo transaccional y splitter documental', async () => {
    const result = validateWorkflow(await persistentIngestWorkflow(), { source: 'valid-ingest-supabase.json' });
    assert.deepEqual(result.errors, []);
    assert.equal(result.valid, true);
    assert.equal(result.kind, 'ingest');
});

test('exige desactivar persistencia de ejecuciones exitosas y fallidas', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    delete workflow.settings.saveDataSuccessExecution;
    workflow.settings.saveDataErrorExecution = 'all';
    const result = validateWorkflow(workflow, { source: 'unsafe-execution-storage-query.json' });
    assertError(result, /saveDataSuccessExecution.*none/i);
    assert.match(result.errors.join('\n'), /saveDataErrorExecution.*none/i);
});

test('exige Resource Locator para memoryKey de Simple Vector Store', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    const vector = workflow.nodes.find((node) => node.name === 'Supabase Vector Store');
    vector.name = 'Simple Vector Store';
    vector.type = '@n8n/n8n-nodes-langchain.vectorStoreInMemory';
    vector.parameters.memoryKey = 'mediflow-assistant-phase4-medico';
    workflow.connections['Simple Vector Store'] = workflow.connections['Supabase Vector Store'];
    delete workflow.connections['Supabase Vector Store'];
    for (const groups of Object.values(workflow.connections)) {
        for (const outputs of Object.values(groups)) {
            for (const connections of outputs) {
                for (const connection of connections) {
                    if (connection.node === 'Supabase Vector Store') connection.node = 'Simple Vector Store';
                }
            }
        }
    }
    const result = validateWorkflow(workflow, { source: 'invalid-query-simple.json' });
    assertError(result, /memoryKey.*Resource Locator/i);
});

test('exige HTTP 401 específicamente para timestamp vencido o futuro', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    const validator = workflow.nodes.find((node) => node.name === 'Validate Raw Request');
    validator.parameters.jsCode = validator.parameters.jsCode.replaceAll('401', '422');
    const result = validateWorkflow(workflow, { source: 'timestamp-422-query.json' });
    assertError(result, /timestamp.*HTTP 401/i);
});

test('exige salida controlada en Crypto, Vector Store y ejecución del modelo', async () => {
    const workflow = await loadFixture('valid-query-workflow.json');
    delete workflow.nodes.find((node) => node.name === 'Verify HMAC').onError;
    delete workflow.nodes.find((node) => node.name === 'Supabase Vector Store').onError;
    delete workflow.nodes.find((node) => node.name === 'Basic LLM Chain').onError;
    const result = validateWorkflow(workflow, { source: 'uncontrolled-errors-query.json' });
    assertError(result, /Crypto crítico.*salida de error controlada/i);
    assert.match(result.errors.join('\n'), /Vector Store crítico.*salida de error controlada/i);
    assert.match(result.errors.join('\n'), /ejecución de modelo crítica.*salida de error controlada/i);
});

test('ingesta persistente exige assistant_ingest_batches y prohíbe UPDATE directo del manifest', async () => {
    const missingReceipt = await persistentIngestWorkflow();
    missingReceipt.nodes.find((node) => node.name === 'Record Ingest Batch Receipt').parameters.tableId = 'other_table';
    assertError(validateWorkflow(missingReceipt, { source: 'missing-receipt-ingest-supabase.json' }), /assistant_ingest_batches/i);

    const directUpdate = await persistentIngestWorkflow();
    const receipt = directUpdate.nodes.find((node) => node.name === 'Record Ingest Batch Receipt');
    receipt.parameters.operation = 'update';
    receipt.parameters.tableId = 'assistant_knowledge_manifests';
    const result = validateWorkflow(directUpdate, { source: 'direct-manifest-update-ingest-supabase.json' });
    assertError(result, /no puede hacer UPDATE directo/i);
});

test('ingesta exige Data Loader custom y Document Unit Splitter conectado', async () => {
    const workflow = await persistentIngestWorkflow();
    workflow.nodes = workflow.nodes.filter((node) => !['Default Data Loader', 'Document Unit Splitter'].includes(node.name));
    delete workflow.connections['Default Data Loader'];
    delete workflow.connections['Document Unit Splitter'];
    const result = validateWorkflow(workflow, { source: 'missing-splitter-ingest-supabase.json' });
    assertError(result, /Data Loader custom.*Document Unit Splitter/i);
});
