import {
    KNOWLEDGE_BASE_META,
    MEDIFLOW_KNOWLEDGE_BASE,
    MODULE_LABELS,
    ROLE_LABELS,
    UNKNOWN_ANSWER,
    isOfflineEntry as isOfflineKnowledgeEntry,
    quickQuestionsFor as quickQuestionsForRole,
    searchKnowledge as searchKnowledgeBase,
} from './knowledge-base.js';

const HISTORY_LIMIT = 50;
const REMOTE_ENDPOINT = '/assistant/message';
const REMOTE_TIMEOUT_MS = 17000;
const SENSITIVE_ANSWER = 'No puedo procesar esa pregunta porque parece contener información sensible. Reformúlala sin nombres de pacientes, identificaciones ni datos clínicos.';
const POSITION_MARGIN = 8;
const MIN_SEARCH_SCORE = 62;
const MIN_AMBIGUITY_SCORE = 30;
const AMBIGUITY_DELTA = 10;
const STOP_WORDS = new Set([
    'como', 'puedo', 'podria', 'quiero', 'necesito', 'una', 'un', 'el', 'la',
    'los', 'las', 'para', 'por', 'favor', 'me', 'se',
]);
const PHRASE_EQUIVALENCES = new Map([
    ['receta medica', 'receta'],
    ['usuario clinico', 'paciente'],
    ['registrar pago', 'crear pago'],
    ['confirmar pago', 'crear pago'],
    ['sin conexion', 'conexion'],
]);
const WORD_EQUIVALENCES = new Map([
    ['creo', 'crear'], ['hacer', 'crear'], ['hago', 'crear'], ['generar', 'crear'],
    ['genero', 'crear'], ['elaborar', 'crear'], ['emitir', 'crear'], ['registro', 'crear'],
    ['registrar', 'crear'], ['agendar', 'crear'], ['agendo', 'crear'],
    ['prescripcion', 'receta'], ['recetario', 'receta'],
    ['turno', 'cita'], ['agendamiento', 'cita'],
    ['cobrar', 'crear pago'], ['cobro', 'crear pago'],
    ['descargar', 'exportar'], ['bajar', 'exportar'],
    ['informe', 'reporte'],
    ['internet', 'conexion'], ['red', 'conexion'], ['offline', 'conexion'],
]);
const SAFE_SINGULARS = new Map([
    ['recetas', 'receta'], ['prescripciones', 'receta'], ['citas', 'cita'],
    ['turnos', 'cita'], ['pagos', 'pago'], ['pacientes', 'paciente'],
    ['recibos', 'recibo'], ['reportes', 'reporte'], ['informes', 'reporte'],
    ['consultas', 'consulta'], ['usuarios', 'usuario'], ['borradores', 'borrador'],
    ['historias', 'historia'], ['clinicas', 'clinica'], ['acciones', 'accion'],
]);
const MODULE_TOKENS = Object.freeze({
    patients: ['paciente'],
    appointments: ['cita'],
    payments: ['pago', 'recibo'],
    prescriptions: ['receta'],
    reports: ['reporte'],
    consultations: ['consulta'],
    'medical-records': ['historia', 'historial'],
    users: ['usuario'],
    settings: ['configuracion', 'clinica'],
    audit: ['auditoria'],
    'super-admin': ['clinica', 'suscripcion'],
    connection: ['conexion'],
});
const SENSITIVE_INPUT = [
    /\b(?:password|contrase(?:n|ñ)a|token|cvv|cvc|api[ _-]?key|clave secreta)\b/i,
    /\b(?:tarjeta|card)\b[^\n]{0,20}\d/i,
    /\b\d{7,}\b/,
    /(?:\+?\d[\d\s().-]{8,}\d)/,
    /\b(?:paciente|diagn[oó]stico|pago|monto|c[eé]dula|identificaci[oó]n)\s*[:=]/i,
    /\b(?:diagn[oó]stico|historia cl[ií]nica|receta)\s+de\s+[^?.,]{2,}/i,
    /\b(?:paciente|c[eé]dula)\s+(?!m[eé]dico|nuevo|nueva|sin\b|con\b|debo\b|puedo\b|se\b|est[aá]\b)[A-ZÁÉÍÓÚÑ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ'-]+(?:\s+[A-ZÁÉÍÓÚÑ][A-Za-zÁÉÍÓÚÜÑáéíóúüñ'-]+)+/,
    /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i,
];

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

function safeKeyPart(value, fallback) {
    const normalized = String(value || fallback).replace(/[^a-zA-Z0-9_-]/g, '_');
    return normalized || fallback;
}

function wildcardRouteMatches(pattern, route) {
    if (! pattern) {
        return false;
    }

    const expression = pattern
        .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
        .replace(/\\\*/g, '[^/]+');

    return new RegExp(`^${expression}(?:/)?$`).test(route);
}

function isOfflineEntry(entry) {
    return entry.id.startsWith('offline-') || entry.id.includes('-offline-');
}

function normalizeConnectionState(value) {
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

function tokensFor(value) {
    return new Set(normalizeAssistantText(value).split(' ').filter((token) => token.length > 1));
}

function inferredIntent(tokens) {
    const intentTokens = [
        ['create', ['crear', 'nuevo', 'nueva']],
        ['sign', ['firmar', 'firma']],
        ['send', ['enviar', 'correo', 'compartir']],
        ['export', ['exportar', 'imprimir']],
        ['update', ['editar', 'actualizar', 'corregir', 'cambiar']],
        ['cancel', ['cancelar', 'anular']],
        ['restore', ['restaurar', 'recuperar']],
        ['view', ['ver', 'revisar', 'consultar', 'buscar']],
        ['troubleshoot', ['no', 'bloqueado', 'bloqueada', 'conexion']],
    ];
    return intentTokens.find(([, candidates]) => candidates.some((token) => tokens.has(token)))?.[0] || null;
}

function entryModule(entry) {
    return entry.module || entry.modules[0] || 'general';
}

function queryModules(tokens) {
    return Object.entries(MODULE_TOKENS)
        .filter(([, candidates]) => candidates.some((token) => tokens.has(token)))
        .map(([module]) => module);
}

function candidateScore(entry, normalizedQuestion, questionTokens, context, disconnected) {
    const knownQuestions = [entry.question, ...(entry.aliases || [])].map(normalizeAssistantText);
    const keywords = (entry.keywords || []).map(normalizeAssistantText);
    const searchable = [...knownQuestions, ...keywords].filter(Boolean);
    const module = entryModule(entry);
    const identifiedModules = queryModules(questionTokens);
    const moduleMatched = identifiedModules.some((identified) => identified === module || entry.modules.includes(identified));
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
    if (score > 0 && entry.routes.some((pattern) => wildcardRouteMatches(pattern, context.route))) {
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
    const candidates = MEDIFLOW_KNOWLEDGE_BASE
        .filter((entry) => entry.roles.includes(context.role))
        .filter((entry) => disconnected || ! isOfflineEntry(entry))
        .map((entry) => ({ entry, score: candidateScore(entry, normalizedQuestion, questionTokens, context, disconnected) }))
        .filter((candidate) => candidate.score > 0)
        .sort((left, right) => right.score - left.score);

    const best = candidates[0];
    if (! best) {
        return { entry: null, alternatives: [] };
    }

    const closeCandidates = candidates.filter((candidate) =>
        candidate.score >= MIN_AMBIGUITY_SCORE
        && best.score - candidate.score <= AMBIGUITY_DELTA);
    const ambiguous = closeCandidates.length > 1 && best.score < 130;

    if (ambiguous || (best.score < MIN_SEARCH_SCORE && closeCandidates.length > 1)) {
        return { entry: null, alternatives: closeCandidates.slice(0, 3).map((candidate) => candidate.entry) };
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
    const allowed = MEDIFLOW_KNOWLEDGE_BASE.filter((entry) => entry.roles.includes(context.role));
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
    addFrom(allowed.filter((entry) => ! isOfflineEntry(entry) && entry.modules.some((module) => ['dashboard', 'general'].includes(module))), 5);
    if (disconnected) {
        addFrom(allowed.filter(isOfflineEntry), 6);
    }
    addFrom(allowed.filter((entry) => ! isOfflineEntry(entry)), 6);

    return selected.slice(0, 6);
}

function containsSensitiveInput(value) {
    return SENSITIVE_INPUT.some((pattern) => pattern.test(value));
}

function readableTime(value) {
    try {
        return new Intl.DateTimeFormat('es-EC', {
            hour: '2-digit',
            minute: '2-digit',
        }).format(new Date(value));
    } catch (_error) {
        return '';
    }
}

function connectionSnapshot() {
    if (window.MediFlowConnection?.state) {
        return window.MediFlowConnection.state;
    }
    if (window.MediFlowConnection?.status) {
        return normalizeConnectionState(window.MediFlowConnection.status);
    }
    return navigator.onLine ? 'ONLINE' : 'OFFLINE';
}

function storageRead(key, fallback) {
    try {
        const raw = window.localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch (_error) {
        return fallback;
    }
}

function storageWrite(key, value) {
    try {
        window.localStorage.setItem(key, JSON.stringify(value));
    } catch (_error) {
        // El asistente sigue funcionando aunque el navegador bloquee almacenamiento local.
    }
}

function buildAnswerContent(entry, connectionStatus) {
    const state = normalizeConnectionState(connectionStatus);
    const roleConnectionAnswers = {
        'payments-offline': 'Actualmente no hay conexión a Internet. MediFlow bloquea los pagos y movimientos financieros para evitar duplicaciones o inconsistencias.',
        'prescriptions-offline': 'Actualmente no hay conexión a Internet. Puedes conservar borradores clínicos, pero MediFlow bloquea la firma y el envío de recetas hasta recuperar la conexión.',
        'admin-offline': 'Actualmente no hay conexión a Internet. MediFlow local continúa disponible, pero las acciones administrativas y financieras críticas permanecen bloqueadas.',
        'super-admin-offline': 'Actualmente no hay conexión a Internet. Las acciones globales críticas permanecen bloqueadas hasta recuperar la conexión.',
    };
    let answer = entry.answer;

    if (state === 'INTERNET_UNAVAILABLE' && roleConnectionAnswers[entry.id]) {
        answer = roleConnectionAnswers[entry.id];
    } else if (state === 'SERVER_UNAVAILABLE' && isOfflineKnowledgeEntry(entry)) {
        answer = 'No se puede establecer comunicación con el servidor MediFlow.';
    }

    const lines = [answer];
    (entry.steps || []).forEach((step, index) => lines.push(`${index + 1}. ${step}`));
    if (entry.online_restrictions?.length) {
        lines.push('');
        lines.push(`Restricciones: ${entry.online_restrictions.join(' ')}`);
    }
    if (entry.requiresConnection && state !== 'ONLINE') {
        const connectionNotes = {
            INTERNET_UNAVAILABLE: 'Esta acción requiere Internet. Puedes revisar la guía ahora y continuar cuando la conexión se restablezca.',
            SERVER_UNAVAILABLE: 'Esta acción requiere comunicación con el servidor MediFlow. Inténtala nuevamente cuando el servidor responda.',
            OFFLINE: 'Esta acción requiere conexión. Puedes revisar la guía ahora y continuar cuando MediFlow vuelva a estar conectado.',
        };
        lines.push(connectionNotes[state] || connectionNotes.OFFLINE);
    }
    return lines.join('\n');
}

function ambiguityContent(entries) {
    const options = entries.map((entry) => `- ${entry.ambiguityLabel || entry.question.replace(/[¿?]/g, '')}`);
    return ['¿Te refieres a alguna de estas opciones?', '', ...options].join('\n');
}

function createElement(tag, className, text) {
    const element = document.createElement(tag);
    if (className) {
        element.className = className;
    }
    if (typeof text === 'string') {
        element.textContent = text;
    }
    return element;
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function remoteAnswerContent(payload) {
    if (! payload || typeof payload.answer !== 'string' || ! payload.answer.trim()) {
        return null;
    }

    const answer = payload.answer.trim().slice(0, 2000);
    const steps = Array.isArray(payload.steps)
        ? payload.steps.filter((step) => typeof step === 'string' && step.trim()).slice(0, 10)
        : [];
    const suggestions = Array.isArray(payload.suggestions)
        ? payload.suggestions.filter((suggestion) => typeof suggestion === 'string' && suggestion.trim()).slice(0, 5)
        : [];
    const lines = [answer];
    steps.forEach((step, index) => lines.push(`${index + 1}. ${step.trim().slice(0, 300)}`));

    return {
        content: lines.join('\n'),
        suggestions: suggestions.map((suggestion) => suggestion.trim().slice(0, 150)),
    };
}

class MediFlowAssistant {
    constructor(root) {
        this.root = root;
        this.context = Object.freeze({
            userId: root.dataset.userId,
            role: root.dataset.role,
            clinicId: root.dataset.clinicId,
            route: root.dataset.currentRoute || '/',
            routeName: root.dataset.currentRouteName || '',
            module: root.dataset.currentModule || 'general',
        });
        this.keys = Object.freeze({
            position: `mediflow:assistant:position:${safeKeyPart(this.context.clinicId, 'none')}:${safeKeyPart(this.context.userId, 'guest')}:${safeKeyPart(this.context.role, 'sin_rol')}`,
            history: `mediflow:assistant:history:${safeKeyPart(this.context.clinicId, 'none')}:${safeKeyPart(this.context.userId, 'guest')}:${safeKeyPart(this.context.role, 'sin_rol')}`,
            suggestion: `mediflow:assistant:suggestion:${safeKeyPart(this.context.clinicId, 'none')}:${safeKeyPart(this.context.userId, 'guest')}:${safeKeyPart(this.context.module, 'general')}`,
        });

        this.elements = {
            launcher: root.querySelector('[data-assistant-launcher]'),
            notification: root.querySelector('[data-assistant-notification]'),
            suggestion: root.querySelector('[data-assistant-suggestion]'),
            panel: root.querySelector('[data-assistant-panel]'),
            content: root.querySelector('[data-assistant-content]'),
            messages: root.querySelector('[data-assistant-messages]'),
            quickList: root.querySelector('[data-assistant-quick-list]'),
            moduleLabel: root.querySelector('[data-assistant-module-label]'),
            connection: root.querySelector('[data-assistant-connection]'),
            connectionDot: root.querySelector('[data-assistant-connection-dot]'),
            connectionLabel: root.querySelector('[data-assistant-connection-label]'),
            escalation: root.querySelector('[data-assistant-escalation]'),
            escalationMessage: root.querySelector('[data-assistant-escalation-message]'),
            form: root.querySelector('[data-assistant-form]'),
            input: root.querySelector('[data-assistant-input]'),
            send: root.querySelector('[data-assistant-send]'),
            minimize: root.querySelector('[data-assistant-minimize]'),
            maximize: root.querySelector('[data-assistant-maximize]'),
        };

        this.connectionStatus = connectionSnapshot();
        this.history = this.loadHistory();
        this.lastQuestion = '';
        this.anchor = this.loadPosition();
        this.dragState = null;
        this.suppressLauncherClick = false;
        this.resizeTimer = null;
        this.suggestionTimer = null;
        this.requestSequence = 0;
        this.remoteController = null;
        this.remoteTimeoutId = null;
        this.pendingMessage = null;
    }

    init() {
        this.bindEvents();
        this.renderHistory();
        this.renderQuickQuestions();
        this.updateConnection(this.connectionStatus);
        this.elements.moduleLabel.textContent = MODULE_LABELS[this.context.module] || 'MediFlow';

        requestAnimationFrame(() => {
            this.applyPosition(false);
            this.showContextSuggestionOnce();
        });

        window.MediFlowAssistant = Object.freeze({
            context: this.context,
            storageKeys: this.keys,
            knowledgeBase: KNOWLEDGE_BASE_META,
            get connectionStatus() {
                return connectionSnapshot();
            },
        });
    }

    bindEvents() {
        this.elements.launcher.addEventListener('click', (event) => {
            if (this.suppressLauncherClick) {
                event.preventDefault();
                this.suppressLauncherClick = false;
                return;
            }
            this.setState('open');
        });
        this.root.querySelector('[data-assistant-close]').addEventListener('click', () => this.setState('closed'));
        this.elements.minimize.addEventListener('click', () => {
            this.setState(this.root.dataset.state === 'minimized' ? 'open' : 'minimized');
        });
        this.elements.maximize.addEventListener('click', () => {
            this.setState(this.root.dataset.state === 'maximized' ? 'open' : 'maximized');
        });
        this.root.querySelector('[data-assistant-suggestion-open]').addEventListener('click', () => {
            this.hideSuggestion();
            this.setState('open');
        });
        this.root.querySelector('[data-assistant-suggestion-close]').addEventListener('click', () => this.hideSuggestion());
        this.elements.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitQuestion(this.elements.input.value);
        });
        this.elements.input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && ! event.shiftKey) {
                event.preventDefault();
                this.elements.form.requestSubmit();
            }
        });
        this.elements.input.addEventListener('input', () => {
            this.elements.input.style.height = 'auto';
            this.elements.input.style.height = `${Math.min(this.elements.input.scrollHeight, 104)}px`;
        });
        this.root.querySelector('[data-assistant-clear]').addEventListener('click', () => this.clearHistory());
        this.root.querySelector('[data-assistant-contact-admin]').addEventListener('click', () => this.contactAdministrator());
        this.root.querySelector('[data-assistant-copy-support]').addEventListener('click', () => this.copySupportDetails());
        this.root.querySelector('[data-assistant-module-guide]').addEventListener('click', () => this.showModuleGuide());

        this.bindDrag(this.elements.launcher, true);
        this.bindDrag(this.root.querySelector('[data-assistant-drag-handle]'), false);

        window.addEventListener('mediflow:connection-change', (event) => {
            this.updateConnection(event.detail?.state || event.detail?.status || connectionSnapshot());
        });
        window.addEventListener('offline', () => queueMicrotask(() => this.updateConnection(connectionSnapshot())));
        window.addEventListener('online', () => window.setTimeout(() => this.updateConnection(connectionSnapshot()), 0));
        window.addEventListener('resize', () => {
            window.clearTimeout(this.resizeTimer);
            this.resizeTimer = window.setTimeout(() => this.applyPosition(true), 120);
        });
    }

    loadHistory() {
        const stored = storageRead(this.keys.history, []);
        if (! Array.isArray(stored)) {
            return [];
        }

        return stored
            .filter((message) => ['user', 'assistant'].includes(message?.type) && typeof message?.content === 'string')
            .map((message) => ({
                type: message.type,
                content: message.content.slice(0, 2200),
                timestamp: message.timestamp || new Date().toISOString(),
                route: String(message.route || this.context.route).slice(0, 250),
                module: String(message.module || this.context.module).slice(0, 80),
                source: message.source === 'remote' ? 'remote' : null,
            }))
            .slice(-HISTORY_LIMIT);
    }

    saveHistory() {
        this.history = this.history.slice(-HISTORY_LIMIT);
        storageWrite(this.keys.history, this.history);
    }

    addMessage(type, content, options = {}) {
        const message = {
            type,
            content: String(content).slice(0, 2200),
            timestamp: new Date().toISOString(),
            route: this.context.route,
            module: this.context.module,
            source: options.source === 'remote' ? 'remote' : null,
        };
        this.history.push(message);
        this.saveHistory();
        this.renderMessage(message, options);
        return message;
    }

    renderHistory() {
        this.elements.messages.replaceChildren();
        if (! this.history.length) {
            this.renderWelcome();
            return;
        }
        this.history.forEach((message) => this.renderMessage(message, { source: message.source }));
        this.scrollMessages();
    }

    renderWelcome() {
        const moduleLabel = MODULE_LABELS[this.context.module] || 'MediFlow';
        const roleLabel = ROLE_LABELS[this.context.role] || 'tu rol';
        this.renderMessage({
            type: 'assistant',
            content: `Hola. Puedo orientarte sobre ${moduleLabel} con la información permitida para ${roleLabel}. Mis respuestas son locales y no ejecutan acciones.`,
            timestamp: new Date().toISOString(),
        }, { transient: true });
    }

    renderMessage(message, options = {}) {
        const item = createElement('article', `mediflow-assistant__message mediflow-assistant__message--${message.type}`);
        item.dataset.messageType = message.type;
        if (options.transient) {
            item.dataset.transient = 'true';
        }

        const bubble = createElement('div', 'mediflow-assistant__bubble');
        if ((options.source || message.source) === 'remote') {
            const source = createElement('span', 'mediflow-assistant__source', 'Respuesta del asistente');
            source.dataset.assistantResponseSource = 'remote';
            bubble.appendChild(source);
        }
        const content = createElement('p', '', message.content);
        bubble.appendChild(content);

        if (options.suggestions?.length) {
            const suggestions = createElement('div', 'mediflow-assistant__remote-suggestions');
            options.suggestions.forEach((suggestion) => {
                const button = createElement('button', 'mediflow-assistant__quick-button', suggestion);
                button.type = 'button';
                button.dataset.assistantRemoteSuggestion = 'true';
                button.addEventListener('click', () => this.submitQuestion(suggestion));
                suggestions.appendChild(button);
            });
            bubble.appendChild(suggestions);
        }

        if (options.entry?.suggestedRoute && String(options.entry.suggestedRoute).startsWith('/')) {
            const link = createElement('a', 'mediflow-assistant__module-link', 'Ir al módulo');
            link.href = options.entry.suggestedRoute;
            link.setAttribute('data-assistant-module-link', options.entry.id);
            const arrow = createElement('span', '', '→');
            arrow.setAttribute('aria-hidden', 'true');
            link.appendChild(arrow);
            bubble.appendChild(link);
        }

        const time = createElement('time', 'mediflow-assistant__message-time', readableTime(message.timestamp));
        time.dateTime = message.timestamp;
        item.append(bubble, time);
        this.elements.messages.appendChild(item);
        this.scrollMessages();
    }

    scrollMessages() {
        requestAnimationFrame(() => {
            this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
        });
    }

    async submitQuestion(rawQuestion, directEntry = null) {
        const question = String(rawQuestion || '').trim().slice(0, 500);
        if (! question) {
            return;
        }
        const requestSequence = this.startSubmission();

        this.elements.escalation.hidden = true;
        if (this.elements.escalationMessage) {
            this.elements.escalationMessage.textContent = 'No se enviará información fuera de MediFlow.';
        }
        this.lastQuestion = containsSensitiveInput(question) ? '[Pregunta omitida por seguridad]' : question;
        this.elements.input.value = '';
        this.elements.input.style.height = 'auto';

        if (containsSensitiveInput(question)) {
            this.addMessage('user', '[Contenido omitido por seguridad]');
            this.addMessage('assistant', SENSITIVE_ANSWER);
            return;
        }

        this.addMessage('user', question);
        const result = directEntry
            ? { entry: directEntry, alternatives: [] }
            : searchKnowledgeBase(question, this.context, this.connectionStatus);
        const entry = result.entry;
        if (! entry) {
            if (result.alternatives.length) {
                this.addMessage('assistant', ambiguityContent(result.alternatives));
                return;
            }
            if (! this.remoteEnabled() || normalizeConnectionState(this.connectionStatus) !== 'ONLINE') {
                this.showFallback();
                return;
            }

            await this.requestRemoteAnswer(question, requestSequence);
            return;
        }

        this.addMessage('assistant', buildAnswerContent(entry, this.connectionStatus), { entry });
        if (entry.escalation?.allowed) {
            if (this.elements.escalationMessage) {
                this.elements.escalationMessage.textContent = entry.escalation.message;
            }
            this.elements.escalation.hidden = false;
        }
    }

    startSubmission() {
        this.requestSequence += 1;
        this.remoteController?.abort();
        window.clearTimeout(this.remoteTimeoutId);
        this.pendingMessage?.remove();
        this.remoteController = null;
        this.remoteTimeoutId = null;
        this.pendingMessage = null;
        this.setRemoteBusy(false);

        return this.requestSequence;
    }

    remoteEnabled() {
        return this.root.dataset.remoteEnabled === 'true';
    }

    setRemoteBusy(busy) {
        this.elements.send.disabled = busy;
        this.elements.form.setAttribute('aria-busy', String(busy));
    }

    showFallback() {
        this.addMessage('assistant', UNKNOWN_ANSWER);
        this.elements.escalation.hidden = false;
    }

    renderPendingMessage() {
        const item = createElement('article', 'mediflow-assistant__message mediflow-assistant__message--assistant');
        item.dataset.assistantPending = 'true';
        const bubble = createElement('div', 'mediflow-assistant__bubble', 'Buscando respuesta…');
        item.appendChild(bubble);
        this.elements.messages.appendChild(item);
        this.scrollMessages();

        return item;
    }

    async requestRemoteAnswer(question, requestSequence) {
        const controller = new AbortController();
        const pending = this.renderPendingMessage();
        this.remoteController = controller;
        this.pendingMessage = pending;
        this.setRemoteBusy(true);
        const timeoutId = window.setTimeout(() => controller.abort(), REMOTE_TIMEOUT_MS);
        this.remoteTimeoutId = timeoutId;

        try {
            const response = await fetch(REMOTE_ENDPOINT, {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    question,
                    current_route: this.context.routeName || null,
                    current_module: Object.prototype.hasOwnProperty.call(MODULE_LABELS, this.context.module)
                        ? this.context.module
                        : null,
                    connection_state: normalizeConnectionState(this.connectionStatus),
                    knowledge_version: KNOWLEDGE_BASE_META.schemaVersion,
                }),
            });
            let payload = null;
            try {
                payload = await response.json();
            } catch (_error) {
                payload = null;
            }

            if (requestSequence !== this.requestSequence) {
                return;
            }

            const remoteAnswer = remoteAnswerContent(payload);
            if (! response.ok) {
                if (remoteAnswer && ['SENSITIVE_CONTENT', 'RATE_LIMITED'].includes(payload?.code)) {
                    this.addMessage('assistant', remoteAnswer.content);
                } else {
                    this.showFallback();
                }
                return;
            }

            if (! remoteAnswer) {
                this.showFallback();
                return;
            }

            const isRemote = payload.source === 'remote' && payload.fallback_used !== true;
            this.addMessage('assistant', remoteAnswer.content, {
                source: isRemote ? 'remote' : null,
                suggestions: isRemote ? remoteAnswer.suggestions : [],
            });
            if (payload.can_escalate) {
                this.elements.escalationMessage.textContent = 'Puedes contactar al administrador si necesitas más ayuda.';
                this.elements.escalation.hidden = false;
            }
        } catch (_error) {
            if (requestSequence === this.requestSequence) {
                this.showFallback();
            }
        } finally {
            window.clearTimeout(timeoutId);
            pending.remove();
            if (requestSequence === this.requestSequence) {
                this.remoteController = null;
                this.remoteTimeoutId = null;
                this.pendingMessage = null;
                this.setRemoteBusy(false);
            }
        }
    }

    renderQuickQuestions() {
        this.elements.quickList.replaceChildren();
        quickQuestionsForRole(this.context, this.connectionStatus).forEach((entry) => {
            const button = createElement('button', 'mediflow-assistant__quick-button', entry.question);
            button.type = 'button';
            button.dataset.knowledgeId = entry.id;
            button.addEventListener('click', () => this.submitQuestion(entry.question, entry));
            this.elements.quickList.appendChild(button);
        });
    }

    updateConnection(status) {
        const normalizedStatus = normalizeConnectionState(status);
        this.connectionStatus = normalizedStatus;
        const states = {
            ONLINE: { label: 'Conectado', tone: 'connected' },
            INTERNET_UNAVAILABLE: { label: 'Sin Internet', tone: 'internet-unavailable' },
            SERVER_UNAVAILABLE: { label: 'Servidor no disponible', tone: 'server-down' },
            OFFLINE: { label: 'Sin conexión', tone: 'offline' },
        };
        const state = states[normalizedStatus] || states.ONLINE;
        this.elements.connection.dataset.status = state.tone;
        this.elements.connectionLabel.textContent = state.label;
        this.renderQuickQuestions();
    }

    setState(state) {
        const allowed = ['closed', 'open', 'minimized', 'maximized'];
        const nextState = allowed.includes(state) ? state : 'open';
        const isClosed = nextState === 'closed';
        this.root.dataset.state = nextState;
        this.elements.panel.hidden = isClosed;
        this.elements.launcher.hidden = ! isClosed;
        this.elements.launcher.setAttribute('aria-expanded', String(! isClosed));
        this.elements.minimize.setAttribute('aria-label', nextState === 'minimized' ? 'Restaurar asistente' : 'Minimizar asistente');
        this.elements.maximize.setAttribute('aria-label', nextState === 'maximized' ? 'Restaurar tamaño del asistente' : 'Maximizar asistente');
        this.hideSuggestion();

        requestAnimationFrame(() => {
            this.applyPosition(true);
            if (nextState === 'open' || nextState === 'maximized') {
                this.elements.input.focus({ preventScroll: true });
            } else if (nextState === 'closed') {
                this.elements.launcher.focus({ preventScroll: true });
            }
        });
    }

    clearHistory() {
        if (! window.confirm('¿Deseas limpiar el historial local del Asistente MediFlow?')) {
            return;
        }
        this.history = [];
        this.lastQuestion = '';
        this.saveHistory();
        this.elements.escalation.hidden = true;
        this.renderHistory();
    }

    showContextSuggestionOnce() {
        if (['general', 'profile'].includes(this.context.module)) {
            return;
        }

        try {
            if (window.sessionStorage.getItem(this.keys.suggestion)) {
                return;
            }
            window.sessionStorage.setItem(this.keys.suggestion, 'shown');
        } catch (_error) {
            return;
        }

        this.elements.suggestion.hidden = false;
        this.elements.notification.hidden = false;
        this.suggestionTimer = window.setTimeout(() => this.hideSuggestion(), 8000);
    }

    hideSuggestion() {
        window.clearTimeout(this.suggestionTimer);
        this.elements.suggestion.hidden = true;
        this.elements.notification.hidden = true;
    }

    showModuleGuide() {
        const entry = MEDIFLOW_KNOWLEDGE_BASE.find((candidate) =>
            candidate.roles.includes(this.context.role)
            && candidate.modules.includes(this.context.module)
            && ! isOfflineKnowledgeEntry(candidate));

        if (! entry) {
            this.addMessage('assistant', 'No hay una guía general adicional para este módulo y tu rol. Contacta al administrador si necesitas orientación autorizada.');
        } else {
            this.addMessage('assistant', buildAnswerContent(entry, this.connectionStatus), { entry });
        }
        this.elements.escalation.hidden = true;
    }

    contactAdministrator() {
        this.addMessage('assistant', 'Contacta al administrador de tu clínica e indica el módulo, la ruta y lo que intentabas consultar. No compartas datos de pacientes, diagnósticos, pagos ni credenciales.');
        this.elements.escalation.hidden = true;
    }

    async copySupportDetails() {
        const statusLabels = {
            ONLINE: 'Conectado',
            INTERNET_UNAVAILABLE: 'Sin Internet',
            SERVER_UNAVAILABLE: 'Servidor no disponible',
            OFFLINE: 'Sin conexión',
        };
        const details = [
            `Rol: ${this.context.role}`,
            `Módulo: ${this.context.module}`,
            `Ruta: ${this.context.route}`,
            `Conexión: ${statusLabels[this.connectionStatus] || this.connectionStatus}`,
            `Fecha y hora: ${new Date().toLocaleString('es-EC')}`,
            `Pregunta: ${this.lastQuestion || '[No especificada]'}`,
        ].join('\n');

        try {
            await navigator.clipboard.writeText(details);
            this.addMessage('assistant', 'Detalles técnicos seguros copiados. Revísalos antes de compartirlos con soporte.');
        } catch (_error) {
            const textarea = createElement('textarea');
            textarea.value = details;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
            this.addMessage('assistant', 'Detalles técnicos seguros copiados. Revísalos antes de compartirlos con soporte.');
        }
    }

    loadPosition() {
        const stored = storageRead(this.keys.position, null);
        if (stored && Number.isFinite(stored.x) && Number.isFinite(stored.y)) {
            return { x: stored.x, y: stored.y };
        }
        return { x: window.innerWidth - 20, y: window.innerHeight - 20 };
    }

    savePosition() {
        storageWrite(this.keys.position, {
            x: Math.round(this.anchor.x),
            y: Math.round(this.anchor.y),
        });
    }

    applyPosition(shouldSave) {
        const width = this.root.offsetWidth;
        const height = this.root.offsetHeight;
        if (! width || ! height) {
            return;
        }

        const maxX = Math.max(width + POSITION_MARGIN, window.innerWidth - POSITION_MARGIN);
        const maxY = Math.max(height + POSITION_MARGIN, window.innerHeight - POSITION_MARGIN);
        this.anchor.x = Math.min(Math.max(this.anchor.x, width + POSITION_MARGIN), maxX);
        this.anchor.y = Math.min(Math.max(this.anchor.y, height + POSITION_MARGIN), maxY);

        const left = Math.min(Math.max(POSITION_MARGIN, this.anchor.x - width), Math.max(POSITION_MARGIN, window.innerWidth - width - POSITION_MARGIN));
        const top = Math.min(Math.max(POSITION_MARGIN, this.anchor.y - height), Math.max(POSITION_MARGIN, window.innerHeight - height - POSITION_MARGIN));
        this.root.style.left = `${Math.round(left)}px`;
        this.root.style.top = `${Math.round(top)}px`;
        this.anchor = { x: left + width, y: top + height };

        if (shouldSave) {
            this.savePosition();
        }
    }

    bindDrag(handle, launcher) {
        handle.addEventListener('pointerdown', (event) => {
            if (! launcher && event.target.closest('button, a, input, textarea, select')) {
                return;
            }
            if (this.root.dataset.state === 'maximized' || event.button !== 0) {
                return;
            }

            const rect = this.root.getBoundingClientRect();
            this.dragState = {
                pointerId: event.pointerId,
                startX: event.clientX,
                startY: event.clientY,
                left: rect.left,
                top: rect.top,
                moved: false,
                launcher,
            };
            handle.setPointerCapture?.(event.pointerId);
            this.root.dataset.dragging = 'true';
        });

        handle.addEventListener('pointermove', (event) => {
            if (! this.dragState || this.dragState.pointerId !== event.pointerId) {
                return;
            }

            const deltaX = event.clientX - this.dragState.startX;
            const deltaY = event.clientY - this.dragState.startY;
            if (! this.dragState.moved && Math.hypot(deltaX, deltaY) < 4) {
                return;
            }
            this.dragState.moved = true;
            event.preventDefault();

            const width = this.root.offsetWidth;
            const height = this.root.offsetHeight;
            const left = Math.min(
                Math.max(POSITION_MARGIN, this.dragState.left + deltaX),
                Math.max(POSITION_MARGIN, window.innerWidth - width - POSITION_MARGIN),
            );
            const top = Math.min(
                Math.max(POSITION_MARGIN, this.dragState.top + deltaY),
                Math.max(POSITION_MARGIN, window.innerHeight - height - POSITION_MARGIN),
            );
            this.root.style.left = `${Math.round(left)}px`;
            this.root.style.top = `${Math.round(top)}px`;
            this.anchor = { x: left + width, y: top + height };
        });

        const finishDrag = (event) => {
            if (! this.dragState || this.dragState.pointerId !== event.pointerId) {
                return;
            }
            const wasMoved = this.dragState.moved;
            const wasLauncher = this.dragState.launcher;
            this.dragState = null;
            delete this.root.dataset.dragging;
            if (wasMoved) {
                this.savePosition();
                if (wasLauncher) {
                    this.suppressLauncherClick = true;
                }
            }
            handle.releasePointerCapture?.(event.pointerId);
        };

        handle.addEventListener('pointerup', finishDrag);
        handle.addEventListener('pointercancel', finishDrag);
    }
}

export function initMediFlowAssistant() {
    const root = document.getElementById('mediflow-assistant');
    if (! root || root.dataset.initialized === 'true') {
        return;
    }
    root.dataset.initialized = 'true';
    new MediFlowAssistant(root).init();
}
