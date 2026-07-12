import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

export const DEFAULT_KNOWLEDGE_PATH = path.resolve('resources/assistant/knowledge-base.json');

const REQUIRED_FIELDS = [
    'id', 'version', 'status', 'locale', 'title', 'roles', 'modules', 'routes',
    'permissions', 'intent', 'action', 'entity', 'question', 'aliases', 'keywords',
    'answer', 'steps', 'related_route', 'requires_online', 'online_restrictions',
    'sensitive', 'tags', 'category', 'escalation', 'evidence',
];

function normalized(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

export function expandEntries(document) {
    const defaults = document?.defaults || {};
    return (document?.entries || []).map((entry) => ({
        ...defaults,
        ...entry,
        escalation: { ...(defaults.escalation || {}), ...(entry.escalation || {}) },
    }));
}

function isStringArray(value, { allowEmpty = true } = {}) {
    return Array.isArray(value)
        && (allowEmpty || value.length > 0)
        && value.every((item) => typeof item === 'string' && item.trim() !== '');
}

function routeExists(pattern, validRoutes) {
    if (! pattern.includes('*')) {
        return validRoutes.has(pattern);
    }
    const expression = pattern
        .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        .replace(/\\\*/g, '.*');
    return [...validRoutes].some((route) => new RegExp(`^${expression}$`).test(route));
}

export function validateKnowledgeDocument(document) {
    const errors = [];
    if (! document || typeof document !== 'object' || Array.isArray(document)) {
        return ['La raíz debe ser un objeto JSON.'];
    }
    if (! Number.isInteger(document.schema_version) || document.schema_version < 1) {
        errors.push('schema_version debe ser un entero positivo.');
    }
    if (! document.catalogs || typeof document.catalogs !== 'object') {
        return [...errors, 'Falta catalogs.'];
    }
    if (! Array.isArray(document.entries)) {
        return [...errors, 'entries debe ser un arreglo.'];
    }

    const validRoles = new Set(Object.keys(document.catalogs.roles || {}));
    const validModules = new Set(Object.keys(document.catalogs.modules || {}));
    const validStatuses = new Set(document.catalogs.statuses || []);
    const validLocales = new Set(document.catalogs.locales || []);
    const validRoutes = new Set(document.catalogs.routes || []);
    const validPermissions = new Set(document.catalogs.permissions || []);
    const forbidden = (document.catalogs.forbidden_phrases || []).map(normalized).filter(Boolean);
    const ids = new Map();
    const aliasOwners = new Map();

    expandEntries(document).forEach((entry, index) => {
        const prefix = `entries[${index}]${entry.id ? ` (${entry.id})` : ''}`;
        REQUIRED_FIELDS.forEach((field) => {
            if (! Object.prototype.hasOwnProperty.call(entry, field)) {
                errors.push(`${prefix}: falta el campo obligatorio ${field}.`);
            }
        });

        if (typeof entry.id !== 'string' || ! /^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(entry.id)) {
            errors.push(`${prefix}: id inválido; usa kebab-case.`);
        } else if (ids.has(entry.id)) {
            errors.push(`${prefix}: ID duplicado; también aparece en entries[${ids.get(entry.id)}].`);
        } else {
            ids.set(entry.id, index);
        }
        if (! Number.isInteger(entry.version) || entry.version < 1) {
            errors.push(`${prefix}: version debe ser un entero positivo.`);
        }
        if (! validStatuses.has(entry.status)) {
            errors.push(`${prefix}: status inválido (${entry.status}).`);
        }
        if (! validLocales.has(entry.locale)) {
            errors.push(`${prefix}: locale inválido (${entry.locale}).`);
        }
        if (typeof entry.title !== 'string' || entry.title.trim() === '') {
            errors.push(`${prefix}: title no puede estar vacío.`);
        }
        if (typeof entry.question !== 'string' || entry.question.trim() === '') {
            errors.push(`${prefix}: question no puede estar vacía.`);
        }
        if (typeof entry.answer !== 'string' || entry.answer.trim() === '') {
            errors.push(`${prefix}: answer no puede estar vacía.`);
        }
        if (! isStringArray(entry.roles, { allowEmpty: false })) {
            errors.push(`${prefix}: roles debe contener al menos un rol.`);
        } else {
            entry.roles.forEach((role) => {
                if (! validRoles.has(role)) {
                    errors.push(`${prefix}: rol inválido (${role}).`);
                }
            });
        }
        if (! isStringArray(entry.modules, { allowEmpty: false })) {
            errors.push(`${prefix}: modules debe contener al menos un módulo.`);
        } else {
            entry.modules.forEach((module) => {
                if (! validModules.has(module)) {
                    errors.push(`${prefix}: módulo inválido (${module}).`);
                }
            });
        }
        if (isStringArray(entry.roles, { allowEmpty: false }) && isStringArray(entry.modules, { allowEmpty: false })) {
            entry.roles.filter((role) => validRoles.has(role)).forEach((role) => {
                const hasCompatibleModule = entry.modules.some((module) =>
                    document.catalogs.modules?.[module]?.roles?.includes(role));
                if (! hasCompatibleModule) {
                    errors.push(`${prefix}: ningún módulo declarado está habilitado para el rol ${role}.`);
                }
            });
        }
        if (! isStringArray(entry.routes)) {
            errors.push(`${prefix}: routes debe ser un arreglo de strings.`);
        } else {
            entry.routes.forEach((route) => {
                if (! routeExists(route, validRoutes)) {
                    errors.push(`${prefix}: ruta no catalogada (${route}).`);
                }
            });
        }
        if (entry.related_route !== null && (typeof entry.related_route !== 'string' || ! validRoutes.has(entry.related_route))) {
            errors.push(`${prefix}: related_route debe ser una ruta catalogada o null.`);
        }
        if (entry.related_path !== null && (typeof entry.related_path !== 'string' || ! entry.related_path.startsWith('/'))) {
            errors.push(`${prefix}: related_path debe empezar con / o ser null.`);
        }
        if (! isStringArray(entry.permissions)) {
            errors.push(`${prefix}: permissions debe ser un arreglo de strings.`);
        } else {
            entry.permissions.forEach((permission) => {
                if (! validPermissions.has(permission)) {
                    errors.push(`${prefix}: permiso no catalogado (${permission}).`);
                }
            });
        }
        if (! isStringArray(entry.aliases)) {
            errors.push(`${prefix}: aliases debe ser un arreglo de strings.`);
        } else {
            const localAliases = new Set();
            entry.aliases.forEach((alias) => {
                const key = normalized(alias);
                if (localAliases.has(key)) {
                    errors.push(`${prefix}: alias duplicado dentro de la entrada (${alias}).`);
                }
                localAliases.add(key);
                const owners = aliasOwners.get(key) || [];
                const dangerousOwner = owners.find((owner) => owner.roles.some((role) => entry.roles.includes(role)));
                if (dangerousOwner) {
                    errors.push(`${prefix}: alias duplicado peligroso (${alias}) con ${dangerousOwner.id}.`);
                }
                owners.push({ id: entry.id, roles: entry.roles || [] });
                aliasOwners.set(key, owners);
            });
        }
        if (! isStringArray(entry.keywords, { allowEmpty: false })) {
            errors.push(`${prefix}: keywords debe contener al menos una frase.`);
        }
        if (! Array.isArray(entry.steps) || ! entry.steps.every((step) => typeof step === 'string' && step.trim() !== '')) {
            errors.push(`${prefix}: steps debe ser un arreglo de strings no vacíos.`);
        }
        if (typeof entry.requires_online !== 'boolean') {
            errors.push(`${prefix}: requires_online debe ser booleano.`);
        }
        if (! isStringArray(entry.online_restrictions)) {
            errors.push(`${prefix}: online_restrictions debe ser un arreglo de strings.`);
        }
        if (typeof entry.sensitive !== 'boolean') {
            errors.push(`${prefix}: sensitive debe ser booleano.`);
        }
        if (! isStringArray(entry.tags) || ! isStringArray(entry.evidence, { allowEmpty: false })) {
            errors.push(`${prefix}: tags debe ser un arreglo y evidence no puede estar vacío.`);
        }
        if (! entry.escalation || typeof entry.escalation.allowed !== 'boolean' || typeof entry.escalation.message !== 'string') {
            errors.push(`${prefix}: escalation debe incluir allowed booleano y message string.`);
        }

        const searchableText = normalized([
            entry.title, entry.question, entry.answer,
            ...(entry.aliases || []), ...(entry.keywords || []), ...(entry.steps || []),
        ].join(' '));
        forbidden.forEach((phrase) => {
            if (searchableText.includes(phrase)) {
                errors.push(`${prefix}: contiene función o término prohibido no implementado (${phrase}).`);
            }
        });
    });

    return errors;
}

export async function validateKnowledgeFile(filePath = DEFAULT_KNOWLEDGE_PATH) {
    let document;
    try {
        document = JSON.parse(await readFile(filePath, 'utf8'));
    } catch (error) {
        return { document: null, entries: [], errors: [`JSON inválido en ${filePath}: ${error.message}`] };
    }

    return {
        document,
        entries: expandEntries(document),
        errors: validateKnowledgeDocument(document),
    };
}

async function runCli() {
    const filePath = path.resolve(process.argv[2] || DEFAULT_KNOWLEDGE_PATH);
    const result = await validateKnowledgeFile(filePath);
    if (result.errors.length) {
        console.error(`Base de conocimiento inválida (${result.errors.length} error(es)):`);
        result.errors.forEach((error) => console.error(`- ${error}`));
        process.exitCode = 1;
        return;
    }

    const roleCounts = Object.keys(result.document.catalogs.roles).map((role) =>
        `${role}: ${result.entries.filter((entry) => entry.roles.includes(role)).length}`).join(', ');
    console.log(`Base de conocimiento válida: ${result.entries.length} entradas.`);
    console.log(`Roles: ${roleCounts}.`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
    await runCli();
}
