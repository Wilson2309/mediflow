import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import { validateWorkflowJson } from './lib/n8n-assistant-workflow-validator.mjs';

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(scriptDirectory, '..');
const workflowsDirectory = path.join(projectRoot, 'n8n', 'workflows');

async function workflowFiles(directory) {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        const target = path.join(directory, entry.name);
        if (entry.isDirectory()) {
            files.push(...await workflowFiles(target));
        } else if (entry.isFile() && entry.name.endsWith('.json')) {
            files.push(target);
        }
    }

    return files.sort((left, right) => left.localeCompare(right));
}

let files;

try {
    files = await workflowFiles(workflowsDirectory);
} catch (error) {
    console.error(`No se pudo leer ${path.relative(projectRoot, workflowsDirectory)}: ${error.message}`);
    process.exitCode = 1;
    process.exit();
}

if (files.length === 0) {
    console.error('No se encontraron workflows JSON para validar.');
    process.exitCode = 1;
    process.exit();
}

let invalidCount = 0;

for (const file of files) {
    const source = path.relative(projectRoot, file).replaceAll('\\', '/');
    const result = validateWorkflowJson(await readFile(file, 'utf8'), { source });

    if (result.valid) {
        console.log(`OK  ${source} (${result.kind})`);
        continue;
    }

    invalidCount += 1;
    console.error(`FAIL ${source} (${result.kind})`);
    for (const error of result.errors) {
        console.error(`  - ${error}`);
    }
}

if (invalidCount > 0) {
    console.error(`\n${invalidCount} de ${files.length} workflow(s) no superaron la validación.`);
    process.exitCode = 1;
} else {
    console.log(`\n${files.length} workflow(s) n8n validados correctamente.`);
}
