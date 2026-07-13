import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { buildAssistantDocuments } from '../../scripts/lib/n8n-assistant-documents.mjs';
import { validateWorkflow } from '../../scripts/lib/n8n-assistant-workflow-validator.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

async function readJson(...segments) {
    return JSON.parse(await readFile(path.join(root, ...segments), 'utf8'));
}

test('el exportador rechaza evidencia tecnica interna aunque provenga de la fuente autoritativa', async () => {
    const knowledge = await readJson('resources', 'assistant', 'knowledge-base.json');
    const candidate = structuredClone(knowledge);
    candidate.entries[0].answer = 'Lee resources/views/private.php para completar esta accion.';

    assert.throws(
        () => buildAssistantDocuments(candidate),
        /evidencia tecnica interna/i,
    );
});

test('el validador rechaza que una denegacion exima contenido residual de otro rol', async () => {
    const candidate = await readJson('n8n', 'workflows', 'mediflow-assistant-query-supabase-openai.json');
    const validator = candidate.nodes.find((node) => node.name === 'Validate Final Response');
    const secureExpression = 'textValues.every((text) => !forbiddenByRole';

    assert.ok(validator.parameters.jsCode.includes(secureExpression));
    assert.doesNotMatch(validator.parameters.jsCode, /safeDenial\(text\)\s*\|\|/);
    validator.parameters.jsCode = validator.parameters.jsCode.replace(
        secureExpression,
        'textValues.every((text) => safeDenial(text) || !forbiddenByRole',
    );

    const result = validateWorkflow(candidate, { source: 'mixed-safe-denial-query.json' });

    assert.equal(result.valid, false);
    assert.match(result.errors.join('\n'), /denegacion/i);
});

test('los fallos internos de nonce de ingesta conservan el contrato de ingesta', async () => {
    for (const name of [
        'mediflow-assistant-ingest-supabase-openai.json',
        'mediflow-assistant-ingest-simple.json',
    ]) {
        const candidate = await readJson('n8n', 'workflows', name);
        const nonceCode = candidate.nodes
            .filter((node) => ['Classify Anti-Replay Result', 'Confirm Nonce Recorded'].includes(node.name))
            .map((node) => String(node.parameters?.jsCode ?? ''))
            .join('\n');

        assert.match(nonceCode, /ok:\s*false/);
        assert.match(nonceCode, /request_id:\s*original\.payload\.request_id/);
        assert.match(nonceCode, /accepted:\s*0/);
        assert.match(nonceCode, /rejected:/);
        assert.match(nonceCode, /activated:\s*false/);
        assert.doesNotMatch(nonceCode, /No encontr/i);

        const result = validateWorkflow(candidate, { source: name });
        assert.equal(result.valid, true, result.errors.join('\n'));
    }
});
