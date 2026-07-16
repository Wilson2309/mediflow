import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { validateWorkflow } from '../../scripts/lib/n8n-assistant-workflow-validator.mjs';

const currentDirectory = path.dirname(fileURLToPath(import.meta.url));
const fixturesDirectory = path.join(currentDirectory, 'fixtures', 'n8n');
const workflowsDirectory = path.resolve(currentDirectory, '..', '..', 'n8n', 'workflows');

async function loadFixture(name) {
    const fixture = JSON.parse(await readFile(path.join(fixturesDirectory, name), 'utf8'));
    const workflow = JSON.parse(await readFile(path.join(workflowsDirectory, fixture.workflow), 'utf8'));

    switch (fixture.mutation) {
        case 'set-loop-version':
            workflow.nodes.find((node) => node.name === 'Loop Over Documents').typeVersion = fixture.typeVersion;
            return workflow;
        case 'remove-vector-return':
            workflow.connections['Document Result Confirmed'].main[0] = [];
            return workflow;
        case 'invert-loop-outputs': {
            const outputs = workflow.connections['Loop Over Documents'].main;
            [outputs[0], outputs[1]] = [outputs[1], outputs[0]];
            return workflow;
        }
        case 'disconnect-check':
            workflow.connections['Loop Over Documents'].main[0] = [];
            return workflow;
        case 'disconnect-convert':
            workflow.connections['Convert Documents'].main[0] = [];
            return workflow;
        case 'connect-vector-directly-to-check':
            workflow.connections['Call Idempotent Supabase RPC'].main[0] = [{ node: 'Check Ingest Result', type: 'main', index: 0 }];
            return workflow;
        default:
            throw new Error(`Mutacion de loop no soportada: ${fixture.mutation}`);
    }
}

function assertRejected(result, pattern) {
    assert.equal(result.valid, false, 'se esperaba un workflow invalido');
    assert.match(result.errors.join('\n'), pattern);
}

test('acepta los cuatro workflows de ingesta persistente generados con Loop Over Items v3', async () => {
    for (const workflowName of [
        'mediflow-assistant-ingest-supabase.json',
        'mediflow-assistant-ingest-supabase-openai.json',
        'mediflow-assistant-ingest-supabase-gemini.json',
        'mediflow-assistant-ingest-simple.json',
    ]) {
        const workflow = JSON.parse(await readFile(path.join(workflowsDirectory, workflowName), 'utf8'));
        assert.equal(workflow.nodes.find((node) => node.name === 'Loop Over Documents').typeVersion, 3);
        assert.equal(validateWorkflow(workflow, { source: workflowName }).valid, true);
    }
});

test('rechaza el fixture con typeVersion 3.4 en Loop Over Documents', async () => {
    assertRejected(validateWorkflow(await loadFixture('loop-version-3-4.json'), { source: 'loop-version-3-4.json' }), /solo admite 1, 2 o 3|debe usar typeVersion 3/i);
});

test('rechaza el fixture sin retorno del resultado confirmado al loop', async () => {
    assertRejected(validateWorkflow(await loadFixture('loop-missing-vector-return.json'), { source: 'loop-missing-vector-return.json' }), /resultado explícito|ciclo|salida 0/i);
});

test('rechaza el fixture con salidas loop y done invertidas', async () => {
    assertRejected(validateWorkflow(await loadFixture('loop-inverted-outputs.json'), { source: 'loop-inverted-outputs.json' }), /salida loop|salida done/i);
});

test('rechaza el fixture con Check Ingest Result desconectado', async () => {
    assertRejected(validateWorkflow(await loadFixture('loop-check-disconnected.json'), { source: 'loop-check-disconnected.json' }), /Check Ingest Result.*salida done|salida done.*Check Ingest Result/i);
});

test('rechaza el fixture sin Convert Documents hacia el loop', async () => {
    assertRejected(validateWorkflow(await loadFixture('loop-convert-disconnected.json'), { source: 'loop-convert-disconnected.json' }), /Convert Documents debe conectar/i);
});

test('rechaza el fixture que conecta la RPC directamente con Check Ingest Result', async () => {
    assertRejected(validateWorkflow(await loadFixture('loop-vector-direct-check.json'), { source: 'loop-vector-direct-check.json' }), /flujo explícito|salida done/i);
});
