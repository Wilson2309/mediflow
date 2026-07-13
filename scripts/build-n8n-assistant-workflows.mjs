import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildAssistantWorkflows } from './lib/n8n-assistant-workflows.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const knowledgePath = path.join(root, 'resources', 'assistant', 'knowledge-base.json');
const promptPath = path.join(root, 'n8n', 'prompts', 'mediflow-assistant-system.md');
const outputDirectory = path.join(root, 'n8n', 'workflows');

const [knowledgeBase, systemPrompt] = await Promise.all([
  readFile(knowledgePath, 'utf8').then(JSON.parse),
  readFile(promptPath, 'utf8'),
]);
const workflows = buildAssistantWorkflows({ knowledgeBase, systemPrompt: systemPrompt.trim() });

await mkdir(outputDirectory, { recursive: true });
for (const [filename, workflow] of Object.entries(workflows)) {
  await writeFile(path.join(outputDirectory, filename), `${JSON.stringify(workflow, null, 2)}\n`, 'utf8');
}

console.log(`Workflows n8n generados: ${Object.keys(workflows).length}`);
for (const filename of Object.keys(workflows)) console.log(`- n8n/workflows/${filename}`);
