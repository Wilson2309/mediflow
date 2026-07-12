import assert from 'node:assert/strict';
import { mkdtemp, rm, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { validateKnowledgeFile } from '../../scripts/validate-assistant-knowledge.mjs';

function fixtureDocument() {
    return {
        schema_version: 2,
        source: 'fixture.json',
        defaults: {
            version: 1,
            status: 'active',
            locale: 'es-EC',
            aliases: [],
            steps: [],
            related_route: null,
            related_path: null,
            requires_online: false,
            online_restrictions: [],
            sensitive: false,
            tags: [],
            category: 'guide',
            escalation: { allowed: false, message: '' },
        },
        catalogs: {
            roles: { administrador: 'Administración' },
            modules: { patients: { label: 'Pacientes', roles: ['administrador'] } },
            statuses: ['active'],
            locales: ['es-EC'],
            routes: ['patients.index'],
            permissions: ['patients.view'],
            forbidden_phrases: ['laboratorio'],
        },
        entries: [{
            id: 'patients-view',
            title: 'Ver pacientes',
            roles: ['administrador'],
            modules: ['patients'],
            routes: ['patients.index'],
            permissions: ['patients.view'],
            intent: 'view',
            action: 'ver',
            entity: 'paciente',
            question: '¿Cómo veo pacientes?',
            aliases: ['buscar pacientes'],
            keywords: ['ver pacientes'],
            answer: 'Abre Pacientes.',
            evidence: ['route:patients.index'],
        }],
    };
}

async function validateFixture(mutator, { invalidJson = false } = {}) {
    const directory = await mkdtemp(path.join(os.tmpdir(), 'mediflow-kb-'));
    const file = path.join(directory, 'knowledge.json');
    try {
        if (invalidJson) {
            await writeFile(file, '{"entries": [', 'utf8');
        } else {
            const document = fixtureDocument();
            mutator(document);
            await writeFile(file, JSON.stringify(document), 'utf8');
        }
        return await validateKnowledgeFile(file);
    } finally {
        await rm(directory, { recursive: true, force: true });
    }
}

test('detecta ID duplicado', async () => {
    const result = await validateFixture((document) => document.entries.push({ ...document.entries[0] }));
    assert.match(result.errors.join('\n'), /ID duplicado/i);
});

test('detecta rol inválido', async () => {
    const result = await validateFixture((document) => { document.entries[0].roles = ['rol_inexistente']; });
    assert.match(result.errors.join('\n'), /rol inválido/i);
});

test('detecta campo obligatorio ausente', async () => {
    const result = await validateFixture((document) => { delete document.entries[0].answer; });
    assert.match(result.errors.join('\n'), /answer no puede estar vacía|falta el campo obligatorio answer/i);
});

test('detecta alias duplicado peligroso para el mismo rol', async () => {
    const result = await validateFixture((document) => document.entries.push({
        ...document.entries[0],
        id: 'patients-search',
        question: '¿Dónde busco pacientes?',
        keywords: ['buscar paciente'],
    }));
    assert.match(result.errors.join('\n'), /alias duplicado peligroso/i);
});

test('detecta JSON inválido y elimina su fixture temporal', async () => {
    const result = await validateFixture(() => {}, { invalidJson: true });
    assert.match(result.errors.join('\n'), /JSON inválido/i);
});
