import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { DEFAULT_KNOWLEDGE_PATH, validateKnowledgeFile } from './validate-assistant-knowledge.mjs';

const OUTPUT_DIR = path.resolve('docs/assistant');
const ROLE_MANUALS = {
    administrador: 'manual-admin.md',
    recepcionista: 'manual-recepcion.md',
    caja_finanzas: 'manual-finanzas.md',
    medico: 'manual-medico.md',
    super_admin: 'manual-superadmin.md',
};

function list(values, empty = 'Ninguno') {
    return values?.length ? values.join(', ') : empty;
}

function entryMarkdown(entry, document, { includeRoles = true } = {}) {
    const roleLabels = entry.roles.map((role) => document.catalogs.roles[role] || role);
    const moduleLabels = entry.modules.map((module) => document.catalogs.modules[module]?.label || module);
    const lines = [
        `## ${entry.title}`,
        '',
        ...(includeRoles ? [`- Rol: ${list(roleLabels)}`] : []),
        `- Módulo: ${list(moduleLabels)}`,
        `- Pregunta: ${entry.question}`,
        `- Requiere conexión: ${entry.requires_online ? 'Sí' : 'No'}`,
        `- Ruta relacionada: ${entry.related_route || 'Ninguna'}`,
        '',
        entry.answer,
    ];

    if (entry.steps.length) {
        lines.push('', 'Pasos:', '');
        entry.steps.forEach((step, index) => lines.push(`${index + 1}. ${step}`));
    }
    lines.push('', `Restricciones: ${entry.online_restrictions.length ? entry.online_restrictions.join(' ') : 'Sin restricciones adicionales documentadas.'}`);
    if (entry.escalation.allowed) {
        lines.push('', `Escalado: ${entry.escalation.message}`);
    }
    lines.push('');
    return lines.join('\n');
}

function generatedHeader(title, description) {
    return [
        `# ${title}`,
        '',
        '> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.',
        '',
        description,
        '',
    ].join('\n');
}

async function writeRoleManual(role, fileName, document, entries) {
    const label = document.catalogs.roles[role];
    const selected = entries.filter((entry) => entry.roles.includes(role));
    const content = generatedHeader(`Manual del Asistente MediFlow — ${label}`, `Entradas autorizadas para el rol \`${role}\`.`)
        + selected.map((entry) => entryMarkdown(entry, document, { includeRoles: false })).join('\n');
    await writeFile(path.join(OUTPUT_DIR, fileName), `${content.trim()}\n`, 'utf8');
}

async function writeSpecialManual(fileName, title, description, selected, document) {
    const content = generatedHeader(title, description)
        + selected.map((entry) => entryMarkdown(entry, document)).join('\n');
    await writeFile(path.join(OUTPUT_DIR, fileName), `${content.trim()}\n`, 'utf8');
}

function coverageMarkdown(document, entries) {
    const lines = [
        '# Cobertura del Asistente MediFlow',
        '',
        '> Archivo generado. No editar manualmente: su fuente es `resources/assistant/knowledge-base.json`.',
        '',
        `Total de entradas: ${entries.length}.`,
        '',
        '| Rol | Módulo | Entradas | Guías | Errores | Offline | Preguntas principales |',
        '|---|---|---:|---:|---:|---:|---|',
    ];
    const gaps = [];

    Object.entries(document.catalogs.roles).forEach(([role, roleLabel]) => {
        Object.entries(document.catalogs.modules).forEach(([module, moduleConfig]) => {
            if (! moduleConfig.roles.includes(role)) {
                return;
            }
            const selected = entries.filter((entry) => entry.roles.includes(role) && entry.modules.includes(module));
            const guides = selected.filter((entry) => entry.category === 'guide').length;
            const errors = selected.filter((entry) => entry.category === 'error').length;
            const offline = selected.filter((entry) => entry.category === 'offline').length;
            const questions = selected.slice(0, 4).map((entry) => entry.question.replace(/\|/g, '\\|')).join('<br>') || '—';
            lines.push(`| ${roleLabel} | ${moduleConfig.label} | ${selected.length} | ${guides} | ${errors} | ${offline} | ${questions} |`);
            if (! selected.length) {
                gaps.push(`${roleLabel}: ${moduleConfig.label}`);
            }
        });
    });

    lines.push('', '## Módulos sin documentación', '');
    if (gaps.length) {
        gaps.forEach((gap) => lines.push(`- ${gap}`));
    } else {
        lines.push('- No se detectaron huecos para las combinaciones rol/módulo catalogadas.');
    }

    lines.push('', '## Límites confirmados', '');
    lines.push('- Recepción no tiene permisos de reportes en el catálogo real; no se documentan exportaciones para ese rol.');
    lines.push('- Las vistas de pacientes, médicos y servicios no tienen rutas dedicadas de exportación.');
    lines.push('- El onboarding global es el formulario de alta de clínica y administrador; no existe un flujo posterior independiente.');
    lines.push('- Los planes de suscripción son campos administrativos libres; no existe un catálogo global ni cobro automático.');
    lines.push('- La base no documenta funciones no implementadas incluidas en el catálogo de frases prohibidas.');
    lines.push('');
    return lines.join('\n');
}

async function main() {
    const { document, entries, errors } = await validateKnowledgeFile(DEFAULT_KNOWLEDGE_PATH);
    if (errors.length) {
        console.error('No se generaron manuales porque la base es inválida:');
        errors.forEach((error) => console.error(`- ${error}`));
        process.exitCode = 1;
        return;
    }

    await mkdir(OUTPUT_DIR, { recursive: true });
    await Promise.all(Object.entries(ROLE_MANUALS).map(([role, fileName]) =>
        writeRoleManual(role, fileName, document, entries)));
    await writeSpecialManual(
        'manual-offline.md',
        'Manual de conexión y borradores',
        'Entradas sobre estados de conexión, almacenamiento local y acciones bloqueadas.',
        entries.filter((entry) => entry.category === 'offline' || entry.modules.includes('offline')),
        document,
    );
    await writeSpecialManual(
        'errores-comunes.md',
        'Errores comunes del Asistente MediFlow',
        'Causas posibles, comprobaciones seguras y escalado para usuarios finales.',
        entries.filter((entry) => entry.category === 'error'),
        document,
    );
    await writeFile(path.join(OUTPUT_DIR, 'coverage.md'), coverageMarkdown(document, entries), 'utf8');

    console.log(`Manuales generados: ${Object.keys(ROLE_MANUALS).length + 3} archivos desde ${entries.length} entradas.`);
}

await main();
