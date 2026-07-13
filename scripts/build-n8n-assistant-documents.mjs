import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildAssistantDocuments } from './lib/n8n-assistant-documents.mjs';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const sourcePath = path.join(projectRoot, 'resources', 'assistant', 'knowledge-base.json');
const outputPath = path.join(projectRoot, 'n8n', 'knowledge', 'assistant-documents.json');

const knowledgeBase = JSON.parse(await readFile(sourcePath, 'utf8'));
const assistantDocuments = buildAssistantDocuments(knowledgeBase);

await mkdir(path.dirname(outputPath), { recursive: true });
await writeFile(outputPath, `${JSON.stringify(assistantDocuments, null, 2)}\n`, 'utf8');

console.log(`Documentos n8n generados: ${assistantDocuments.document_count}.`);
console.log(`Checksum SHA-256: ${assistantDocuments.checksum}.`);
