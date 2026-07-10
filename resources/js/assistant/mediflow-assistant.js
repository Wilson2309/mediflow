import {
    MEDIFLOW_KNOWLEDGE_BASE,
    MODULE_LABELS,
    ROLE_LABELS,
    UNKNOWN_ANSWER,
} from './knowledge-base.js';

const HISTORY_LIMIT = 50;
const POSITION_MARGIN = 8;
const MIN_SEARCH_SCORE = 40;
const SENSITIVE_INPUT = [
    /\b(?:password|contrase(?:n|ñ)a|token|cvv|cvc|api[ _-]?key|clave secreta)\b/i,
    /\b(?:tarjeta|card)\b[^\n]{0,20}\d/i,
    /\b\d{7,}\b/,
    /\b(?:paciente|diagn[oó]stico|pago|monto|c[eé]dula|identificaci[oó]n)\s*[:=]/i,
    /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i,
];

export function normalizeAssistantText(value = '') {
    return String(value)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
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
    return entry.id.startsWith('offline-');
}

function tokenSimilarity(question, keyword) {
    const questionTokens = new Set(question.split(' ').filter((token) => token.length > 2));
    const keywordTokens = keyword.split(' ').filter((token) => token.length > 2);

    if (! keywordTokens.length) {
        return 0;
    }

    const matched = keywordTokens.filter((token) => questionTokens.has(token)).length;
    return matched / keywordTokens.length;
}

export function findKnowledgeAnswer(question, context, connectionStatus = 'connected') {
    const normalizedQuestion = normalizeAssistantText(question);
    if (! normalizedQuestion) {
        return null;
    }

    const disconnected = connectionStatus !== 'connected';
    let bestMatch = null;

    MEDIFLOW_KNOWLEDGE_BASE
        .filter((entry) => entry.roles.includes(context.role))
        .filter((entry) => disconnected || ! isOfflineEntry(entry))
        .forEach((entry) => {
            let score = 0;
            let textScore = 0;
            const normalizedKnownQuestion = normalizeAssistantText(entry.question);

            if (normalizedQuestion === normalizedKnownQuestion) {
                textScore = 120;
            } else if (normalizedKnownQuestion.includes(normalizedQuestion) && normalizedQuestion.length >= 8) {
                textScore = Math.max(textScore, 52);
            }

            entry.keywords.forEach((keyword) => {
                const normalizedKeyword = normalizeAssistantText(keyword);
                if (normalizedQuestion === normalizedKeyword) {
                    textScore = Math.max(textScore, 100);
                } else if (normalizedQuestion.includes(normalizedKeyword)) {
                    textScore = Math.max(textScore, 70);
                } else {
                    const similarity = tokenSimilarity(normalizedQuestion, normalizedKeyword);
                    if (similarity === 1 && normalizedKeyword.length >= 6) {
                        textScore = Math.max(textScore, 54);
                    } else if (similarity >= 0.66) {
                        textScore = Math.max(textScore, 35);
                    }
                }
            });

            if (textScore === 0) {
                return;
            }

            score += textScore;
            if (entry.modules.includes(context.module)) {
                score += 24;
            }
            if (entry.routes.some((pattern) => wildcardRouteMatches(pattern, context.route))) {
                score += 16;
            }
            if (disconnected && isOfflineEntry(entry)) {
                score += 32;
            }

            if (! bestMatch || score > bestMatch.score) {
                bestMatch = { entry, score };
            }
        });

    return bestMatch && bestMatch.score >= MIN_SEARCH_SCORE ? bestMatch.entry : null;
}

export function quickQuestionsFor(context, connectionStatus = 'connected') {
    const disconnected = connectionStatus !== 'connected';
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
    if (window.MediFlowConnection?.status) {
        return window.MediFlowConnection.status;
    }
    return navigator.onLine ? 'connected' : 'offline';
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
    const lines = [entry.answer];
    entry.steps.forEach((step, index) => lines.push(`${index + 1}. ${step}`));
    if (entry.requiresConnection && connectionStatus !== 'connected') {
        lines.push('Esta guía describe una acción que requiere conexión. Puedes revisarla ahora y continuar cuando MediFlow vuelva a estar conectado.');
    }
    return lines.join('\n');
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
            form: root.querySelector('[data-assistant-form]'),
            input: root.querySelector('[data-assistant-input]'),
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
            this.updateConnection(event.detail?.status || connectionSnapshot());
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
        this.history.forEach((message) => this.renderMessage(message));
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
        const content = createElement('p', '', message.content);
        bubble.appendChild(content);

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

    submitQuestion(rawQuestion, directEntry = null) {
        const question = String(rawQuestion || '').trim().slice(0, 500);
        if (! question) {
            return;
        }

        this.elements.escalation.hidden = true;
        this.lastQuestion = containsSensitiveInput(question) ? '[Pregunta omitida por seguridad]' : question;
        this.elements.input.value = '';
        this.elements.input.style.height = 'auto';

        if (containsSensitiveInput(question)) {
            this.addMessage('user', '[Contenido omitido por seguridad]');
            this.addMessage('assistant', 'Por seguridad, formula la pregunta sin nombres, identificaciones, diagnósticos, montos, datos de pago, credenciales ni otra información sensible.');
            return;
        }

        this.addMessage('user', question);
        const entry = directEntry || findKnowledgeAnswer(question, this.context, this.connectionStatus);
        if (! entry) {
            this.addMessage('assistant', UNKNOWN_ANSWER);
            this.elements.escalation.hidden = false;
            return;
        }

        this.addMessage('assistant', buildAnswerContent(entry, this.connectionStatus), { entry });
    }

    renderQuickQuestions() {
        this.elements.quickList.replaceChildren();
        quickQuestionsFor(this.context, this.connectionStatus).forEach((entry) => {
            const button = createElement('button', 'mediflow-assistant__quick-button', entry.question);
            button.type = 'button';
            button.dataset.knowledgeId = entry.id;
            button.addEventListener('click', () => this.submitQuestion(entry.question, entry));
            this.elements.quickList.appendChild(button);
        });
    }

    updateConnection(status) {
        const normalizedStatus = ['connected', 'offline', 'server_down'].includes(status) ? status : 'connected';
        this.connectionStatus = normalizedStatus;
        const states = {
            connected: { label: 'Conectado', tone: 'connected' },
            offline: { label: 'Sin conexión', tone: 'offline' },
            server_down: { label: 'Servidor no disponible', tone: 'server-down' },
        };
        const state = states[normalizedStatus];
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
            && ! isOfflineEntry(candidate));

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
            connected: 'Conectado',
            offline: 'Sin conexión',
            server_down: 'Servidor no disponible',
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
