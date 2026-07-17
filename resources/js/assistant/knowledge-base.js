import knowledgeDocument from '../../assistant/knowledge-base.json';

const MIN_SEARCH_SCORE = 62;
const MIN_AMBIGUITY_SCORE = 30;
const AMBIGUITY_DELTA = 10;
const STOP_WORDS = new Set([
    'como', 'puedo', 'podria', 'quiero', 'necesito', 'una', 'un', 'el', 'la',
    'los', 'las', 'para', 'por', 'favor', 'me', 'se', 'que', 'del', 'de', 'y',
]);
const PHRASE_EQUIVALENCES = new Map([
    ['receta medica', 'receta'],
    ['usuario clinico', 'paciente'],
    ['registrar pago', 'crear pago'],
    ['confirmar pago', 'crear pago'],
    ['sin internet', 'conexion'],
    ['sin conexion', 'conexion'],
    ['servidor no disponible', 'servidor conexion'],
]);
const WORD_EQUIVALENCES = new Map([
    ['creo', 'crear'], ['hacer', 'crear'], ['hago', 'crear'], ['generar', 'crear'],
    ['genero', 'crear'], ['elaborar', 'crear'], ['emitir', 'crear'], ['registro', 'crear'],
    ['registrar', 'crear'], ['agendar', 'crear'], ['agendo', 'crear'],
    ['prescripcion', 'receta'], ['recetario', 'receta'],
    ['turno', 'cita'], ['agendamiento', 'cita'],
    ['cobrar', 'crear pago'], ['cobro', 'crear pago'],
    ['descargar', 'exportar'], ['bajar', 'exportar'],
    ['informe', 'reporte'], ['excel', 'xlsx'],
    ['internet', 'conexion'], ['red', 'conexion'], ['offline', 'conexion'],
]);
const SAFE_SINGULARS = new Map([
    ['recetas', 'receta'], ['prescripciones', 'receta'], ['citas', 'cita'],
    ['turnos', 'cita'], ['pagos', 'pago'], ['pacientes', 'paciente'],
    ['recibos', 'recibo'], ['reportes', 'reporte'], ['informes', 'reporte'],
    ['consultas', 'consulta'], ['usuarios', 'usuario'], ['borradores', 'borrador'],
    ['historias', 'historia'], ['clinicas', 'clinica'], ['acciones', 'accion'],
    ['medicos', 'medico'], ['servicios', 'servicio'], ['permisos', 'permiso'],
]);
const MODULE_TOKENS = Object.freeze({
    patients: ['paciente'],
    doctors: ['medico'],
    services: ['servicio'],
    appointments: ['cita'],
    daily_agenda: ['agenda', 'cita'],
    payments: ['pago', 'cobro'],
    receipts: ['recibo', 'comprobante'],
    prescriptions: ['receta'],
    reports: ['reporte', 'xlsx'],
    consultations: ['consulta'],
    medical_records: ['historia', 'historial'],
    users: ['usuario', 'rol'],
    clinic_settings: ['configuracion', 'clinica'],
    clinic_switch: ['clinica', 'sucursal'],
    audit: ['auditoria'],
    financial_audit: ['caja', 'financiero'],
    super_admin_clinics: ['clinica', 'global'],
    subscriptions: ['suscripcion', 'plan'],
    onboarding: ['onboarding', 'alta'],
    permissions: ['permiso', '403', 'boton'],
    offline: ['conexion', 'borrador', 'servidor'],
    support: ['soporte', 'ayuda'],
});

export const UNKNOWN_ANSWER = 'No encontré una respuesta exacta para eso. Puedes revisar la guía del módulo o contactar al administrador.';
export const ROLE_DENIED = 'No puedo acceder a esa función porque no está permitida para tu rol.';

export function expandKnowledgeDocument(document = knowledgeDocument) {
    const defaults = document?.defaults || {};

    return (document?.entries || []).map((entry) => ({
        ...defaults,
        ...entry,
        aliases: [...(entry.aliases ?? defaults.aliases ?? [])],
        keywords: [...(entry.keywords ?? defaults.keywords ?? [])],
        steps: [...(entry.steps ?? defaults.steps ?? [])],
        online_restrictions: [...(entry.online_restrictions ?? defaults.online_restrictions ?? [])],
        tags: [...(entry.tags ?? defaults.tags ?? [])],
        escalation: { ...(defaults.escalation || {}), ...(entry.escalation || {}) },
        suggestedRoute: entry.related_path ?? defaults.related_path ?? null,
        requiresConnection: entry.requires_online ?? defaults.requires_online ?? false,
        ambiguityLabel: entry.ambiguity_label ?? null,
        module: entry.modules?.[0] || 'support',
    }));
}

export function validateKnowledgeEntries(document = knowledgeDocument) {
    const errors = [];
    const roleIds = new Set(Object.keys(document?.catalogs?.roles || {}));
    const moduleIds = new Set(Object.keys(document?.catalogs?.modules || {}));
    const ids = new Set();

    expandKnowledgeDocument(document).forEach((entry, index) => {
        const prefix = `entries[${index}]`;
        if (! entry.id || ids.has(entry.id)) {
            errors.push(`${prefix}: id ausente o duplicado (${entry.id || 'vacío'}).`);
        }
        ids.add(entry.id);
        if (! entry.question?.trim() || ! entry.answer?.trim()) {
            errors.push(`${prefix}: pregunta o respuesta vacía.`);
        }
        if (! entry.roles?.length || entry.roles.some((role) => ! roleIds.has(role))) {
            errors.push(`${prefix}: rol inválido.`);
        }
        if (! entry.modules?.length || entry.modules.some((module) => ! moduleIds.has(module))) {
            errors.push(`${prefix}: módulo inválido.`);
        }
    });

    return errors;
}

const runtimeErrors = validateKnowledgeEntries();
if (runtimeErrors.length) {
    throw new Error(`Base de conocimiento inválida: ${runtimeErrors.join(' ')}`);
}

export const MEDIFLOW_KNOWLEDGE_BASE = Object.freeze(
    expandKnowledgeDocument().filter((entry) => entry.status === 'active').map(Object.freeze),
);
export const ROLE_LABELS = Object.freeze({ ...knowledgeDocument.catalogs.roles });
export const MODULE_LABELS = Object.freeze(Object.fromEntries(
    Object.entries(knowledgeDocument.catalogs.modules).map(([id, config]) => [id, config.label]),
));
export const KNOWLEDGE_BASE_META = Object.freeze({
    source: knowledgeDocument.source,
    schemaVersion: knowledgeDocument.schema_version,
    entries: MEDIFLOW_KNOWLEDGE_BASE.length,
});
export const KNOWLEDGE_INDEX_BY_ROLE = Object.freeze(Object.fromEntries(
    Object.keys(ROLE_LABELS).map((role) => [
        role,
        Object.freeze(MEDIFLOW_KNOWLEDGE_BASE.filter((entry) => entry.roles.includes(role))),
    ]),
));

export function normalizeAssistantText(value = '') {
    let normalized = String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    PHRASE_EQUIVALENCES.forEach((replacement, phrase) => {
        normalized = normalized.replace(new RegExp(`\\b${phrase}\\b`, 'g'), replacement);
    });

    const tokens = normalized
        .split(' ')
        .flatMap((token) => String(WORD_EQUIVALENCES.get(token) || token).split(' '))
        .map((token) => SAFE_SINGULARS.get(token) || token)
        .filter((token) => token && ! STOP_WORDS.has(token));

    return tokens.filter((token, index) => token !== tokens[index - 1]).join(' ');
}

export function normalizeConnectionState(value) {
    const states = {
        connected: 'ONLINE',
        online: 'ONLINE',
        offline: 'OFFLINE',
        server_down: 'SERVER_UNAVAILABLE',
        server_unavailable: 'SERVER_UNAVAILABLE',
        internet_unavailable: 'INTERNET_UNAVAILABLE',
    };
    const normalized = String(value || '').toLowerCase();
    return states[normalized] || (String(value || '').toUpperCase() || 'ONLINE');
}

export function isOfflineEntry(entry) {
    return entry.category === 'offline' || entry.modules.includes('offline');
}

function tokensFor(value) {
    return new Set(normalizeAssistantText(value).split(' ').filter((token) => token.length > 1));
}

function inferredIntent(tokens) {
    const intentTokens = [
        ['create', ['crear', 'nuevo', 'nueva']],
        ['sign', ['firmar', 'firma']],
        ['send', ['enviar', 'correo', 'compartir']],
        ['export', ['exportar', 'imprimir', 'xlsx']],
        ['update', ['editar', 'actualizar', 'corregir', 'cambiar', 'asignar']],
        ['cancel', ['cancelar', 'anular']],
        ['restore', ['restaurar', 'recuperar', 'borrador']],
        ['view', ['ver', 'revisar', 'consultar', 'buscar', 'mostrar']],
        ['troubleshoot', ['no', 'bloqueado', 'bloqueada', 'conexion', 'error', '403']],
    ];
    return intentTokens.find(([, candidates]) => candidates.some((token) => tokens.has(token)))?.[0] || null;
}

function queryModules(tokens) {
    return Object.entries(MODULE_TOKENS)
        .filter(([, candidates]) => candidates.some((token) => tokens.has(token)))
        .map(([module]) => module);
}

function routeNameMatches(pattern, routeName) {
    if (! pattern || ! routeName) {
        return false;
    }

    const expression = pattern
        .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        .replace(/\\\*/g, '.*');
    return new RegExp(`^${expression}$`).test(routeName);
}

function candidateScore(entry, normalizedQuestion, questionTokens, context, disconnected) {
    const knownQuestions = [entry.question, ...(entry.aliases || [])].map(normalizeAssistantText);
    const keywords = (entry.keywords || []).map(normalizeAssistantText);
    const searchable = [...knownQuestions, ...keywords].filter(Boolean);
    const identifiedModules = queryModules(questionTokens);
    const moduleMatched = identifiedModules.some((module) => entry.modules.includes(module));
    const questionIntent = inferredIntent(questionTokens);
    const entryAction = normalizeAssistantText(entry.action || '').split(' ')[0];
    const actionMatched = entryAction && questionTokens.has(entryAction);
    let score = 0;

    if (knownQuestions.includes(normalizedQuestion)) {
        score = 140;
    } else if (keywords.includes(normalizedQuestion)) {
        score = 108;
    } else {
        if (entry.intent && questionIntent === entry.intent && moduleMatched) {
            score = Math.max(score, 84);
        } else if (actionMatched && moduleMatched) {
            score = Math.max(score, 74);
        }

        searchable.forEach((knownText) => {
            const knownTokens = tokensFor(knownText);
            const overlap = [...knownTokens].filter((token) => questionTokens.has(token)).length;
            const coverage = knownTokens.size ? overlap / knownTokens.size : 0;

            if ((knownText.includes(normalizedQuestion) || normalizedQuestion.includes(knownText))
                && knownTokens.size >= 2
                && questionTokens.size >= 2) {
                score = Math.max(score, 76);
            } else if (overlap >= 2 && coverage >= 0.66) {
                score = Math.max(score, 58);
            } else if (moduleMatched && overlap >= 1) {
                score = Math.max(score, 46);
            }
        });

        if (moduleMatched) {
            score = Math.max(score, 30);
        }
    }

    if (score > 0 && entry.modules.includes(context.module)) {
        score += 10;
    }
    if (score > 0 && entry.routes.some((pattern) => routeNameMatches(pattern, context.routeName))) {
        score += 5;
    }
    if (score > 0 && disconnected && isOfflineEntry(entry)) {
        score += 12;
    }

    return score;
}

export function searchKnowledge(question, context, connectionStatus = 'ONLINE') {
    const normalizedQuestion = normalizeAssistantText(question);
    if (! normalizedQuestion) {
        return { entry: null, alternatives: [] };
    }

    const connectionState = normalizeConnectionState(connectionStatus);
    const disconnected = connectionState !== 'ONLINE';
    const questionTokens = tokensFor(normalizedQuestion);

    const restrictedByRole = {
        recepcionista: /(?:auditoria|financiero|finanzas|reporte financiero|cierre de caja|ingreso|suscripcion|super admin)/,
        caja_finanzas: /(?:historia|historial|diagnostico|tratamiento|receta|consulta clinica)/,
        medico: /(?:auditoria financiera|cierre de caja|ingreso|administrar usuario|suscripcion|super admin)/,
        administrador: /(?:suscripcion global|super admin|todas las clinicas)/,
        super_admin: /(?:historia|historial|diagnostico|tratamiento|receta|consulta clinica|pago clinica)/,
    };
    if (restrictedByRole[context.role]?.test(normalizedQuestion)) {
        return { entry: null, alternatives: [], restricted: true };
    }

    const requestsPaymentWrite = questionTokens.has('pago') && questionTokens.has('crear');
    if (requestsPaymentWrite && ! ['administrador', 'caja_finanzas'].includes(context.role)) {
        return { entry: null, alternatives: [], restricted: true };
    }

    const requestsClinicalContent = ['historia', 'historial', 'consulta', 'receta', 'diagnostico']
        .some((token) => questionTokens.has(token));
    if (requestsClinicalContent && ! ['administrador', 'medico'].includes(context.role)) {
        return { entry: null, alternatives: [], restricted: true };
    }

    const asksAppointmentStatus = context.role === 'recepcionista'
        && questionTokens.has('cita')
        && questionTokens.has('estado')
        && ['consultar', 'revisar', 'ver'].some((token) => questionTokens.has(token));
    if (asksAppointmentStatus) {
        return { entry: null, alternatives: [], restricted: false };
    }

    const asksWithoutContext = /\b(?:eso|esto|aquello)\b/.test(normalizedQuestion)
        && queryModules(questionTokens).length === 0;
    if (asksWithoutContext) {
        const preferredByRole = {
            recepcionista: ['appointments-create', 'daily-agenda-guide', 'patients-edit-search'],
            caja_finanzas: ['appointments-basic-payment'],
            medico: ['daily-agenda-guide'],
            administrador: ['daily-agenda-guide', 'patients-edit-search'],
            super_admin: ['support-safe'],
        };
        const preferred = preferredByRole[context.role] || [];
        const alternatives = preferred
            .map((id) => (KNOWLEDGE_INDEX_BY_ROLE[context.role] || []).find((entry) => entry.id === id))
            .filter(Boolean);
        return { entry: null, alternatives, restricted: false };
    }

    // El rol se aplica antes de puntuar; nunca se busca primero en toda la base.
    const roleEntries = KNOWLEDGE_INDEX_BY_ROLE[context.role] || [];
    const candidates = roleEntries
        .map((entry) => ({ entry, score: candidateScore(entry, normalizedQuestion, questionTokens, context, disconnected) }))
        .filter((candidate) => candidate.score > 0)
        .sort((left, right) => right.score - left.score || left.entry.id.localeCompare(right.entry.id));

    const best = candidates[0];
    if (! best) {
        return { entry: null, alternatives: [] };
    }

    const closeCandidates = candidates.filter((candidate) =>
        candidate.score >= MIN_AMBIGUITY_SCORE
        && best.score - candidate.score <= AMBIGUITY_DELTA);
    const ambiguous = closeCandidates.length > 1 && best.score < 130;

    if (ambiguous || (best.score < MIN_SEARCH_SCORE && closeCandidates.length > 1)) {
        const alternatives = [...closeCandidates]
            .sort((left, right) => Number(Boolean(right.entry.ambiguityLabel)) - Number(Boolean(left.entry.ambiguityLabel)))
            .slice(0, 3)
            .map((candidate) => candidate.entry);
        return { entry: null, alternatives };
    }

    return best.score >= MIN_SEARCH_SCORE
        ? { entry: best.entry, alternatives: [] }
        : { entry: null, alternatives: [] };
}

export function findKnowledgeAnswer(question, context, connectionStatus = 'ONLINE') {
    return searchKnowledge(question, context, connectionStatus).entry;
}

export function quickQuestionsFor(context, connectionStatus = 'ONLINE') {
    const disconnected = normalizeConnectionState(connectionStatus) !== 'ONLINE';
    const allowed = KNOWLEDGE_INDEX_BY_ROLE[context.role] || [];
    const selected = [];
    const seen = new Set();

    const addFrom = (entries, limit) => {
        entries.forEach((entry) => {
            if (selected.length >= limit || seen.has(entry.id)) {
                return;
            }
            seen.add(entry.id);
            selected.push(entry);
        });
    };

    addFrom(allowed.filter((entry) => ! isOfflineEntry(entry) && entry.modules.includes(context.module)), 3);
    addFrom(allowed.filter((entry) => ! isOfflineEntry(entry) && entry.modules.includes('dashboard')), 5);
    if (disconnected) {
        addFrom(allowed.filter(isOfflineEntry), 6);
    }
    addFrom(allowed.filter((entry) => ! isOfflineEntry(entry)), 6);

    return selected.slice(0, 6);
}
