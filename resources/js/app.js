import Alpine from 'alpinejs';
import { initMediFlowAssistant } from './assistant/mediflow-assistant.js';

window.Alpine = Alpine;

const ONLINE_ONLY_DEFAULT = 'Esta acción requiere conexión con MediFlow.';
const FINANCE_BLOCK_MESSAGE = 'No se puede registrar ni modificar pagos sin conexión. Esta acción se habilitará cuando vuelva la conexión.';
const ADMIN_BLOCK_MESSAGE = 'No se pueden realizar cambios administrativos sin conexión.';
const SERVER_DOWN_MESSAGE = 'Servidor no disponible. Espere a que se restablezca la conexión.';
const OFFLINE_MESSAGE = 'Sin conexión. Algunas acciones fueron bloqueadas para evitar pérdida o duplicación de datos.';
const INTERNET_UNAVAILABLE_MESSAGE = 'Sin conexión a Internet. MediFlow local continúa disponible, pero algunas acciones están bloqueadas.';
const RESTORED_MESSAGE = 'Conexión restablecida.';
const DRAFT_DEFAULT_MESSAGE = 'No hay conexión. El formulario fue guardado como borrador local. Revísalo y envíalo cuando vuelva la conexión.';
const CLINICAL_DRAFT_MESSAGE = 'No hay conexión. El contenido fue guardado como borrador local. Revísalo y envíalo cuando vuelva la conexión.';
const DISABLED_CLASSES = ['opacity-50', 'cursor-not-allowed', 'pointer-events-none'];
const SENSITIVE_ACTION_PATTERNS = [
    { pattern: /\/payments(\/|$)/, message: FINANCE_BLOCK_MESSAGE },
    { pattern: /\/users(\/|$)/, message: 'No se pueden crear ni modificar usuarios sin conexión.' },
    { pattern: /\/settings\/clinic(\/|$)/, message: ADMIN_BLOCK_MESSAGE },
    { pattern: /\/super-admin(\/|$)/, message: ADMIN_BLOCK_MESSAGE },
    { pattern: /\/prescriptions\/\d+\/(sign|send-email)(\/|$)/, message: 'No se puede firmar ni enviar recetas sin conexión.' },
];

const CONNECTION_STATES = Object.freeze({
    ONLINE: 'ONLINE',
    INTERNET_UNAVAILABLE: 'INTERNET_UNAVAILABLE',
    SERVER_UNAVAILABLE: 'SERVER_UNAVAILABLE',
    OFFLINE: 'OFFLINE',
});
let connection = {
    state: navigator.onLine ? CONNECTION_STATES.ONLINE : CONNECTION_STATES.OFFLINE,
    serverReachable: null,
    internetReachable: null,
    browserOnline: navigator.onLine,
    lastCheckedAt: null,
};
let healthTimer = null;
let toastTimer = null;
let hadConnectivityIssue = false;
let healthCheckId = 0;
let healthController = null;

function appContext() {
    return {
        clinicId: document.body.dataset.activeClinicId || 'none',
        userId: document.body.dataset.userId || 'guest',
        role: document.body.dataset.userRole || 'guest',
    };
}

function draftKey(form) {
    const context = appContext();
    const formName = form.dataset.draftForm || 'form';
    const recordId = form.dataset.draftRecord || 'new';

    return `mediflow:draft:${context.clinicId}:${context.userId}:${context.role}:${formName}:${recordId}`;
}

function isOnlineReady() {
    return connection.state === CONNECTION_STATES.ONLINE;
}

function toast(message, tone = 'warning') {
    const container = document.getElementById('connection-toast-container');
    if (! container || ! message) {
        return;
    }

    window.clearTimeout(toastTimer);
    container.innerHTML = '';

    const card = document.createElement('div');
    const isSuccess = tone === 'success';
    card.className = [
        'translate-x-0 rounded-lg border px-4 py-3 text-sm font-semibold shadow-lg transition-all duration-300',
        isSuccess ? 'border-[#10B981]/30 bg-[#ECFDF5] text-[#047857]' : 'border-[#EF4444]/30 bg-[#FEF2F2] text-[#B91C1C]',
    ].join(' ');
    card.textContent = message;
    container.appendChild(card);

    toastTimer = window.setTimeout(() => {
        card.classList.add('translate-x-full', 'opacity-0');
        window.setTimeout(() => card.remove(), 300);
    }, isSuccess ? 3500 : 6000);
}

function updateIndicator() {
    const indicator = document.getElementById('connection-status');
    if (! indicator) {
        return;
    }

    const label = indicator.querySelector('[data-connection-label]');
    const dot = indicator.querySelector('[data-connection-dot]');
    const states = {
        ONLINE: {
            text: 'Conectado',
            className: 'hidden items-center gap-2 rounded-full border border-[#10B981]/20 bg-[#10B981]/10 px-3 py-2 text-xs font-bold text-[#047857] sm:inline-flex',
            dotClass: 'h-2 w-2 rounded-full bg-[#10B981]',
        },
        INTERNET_UNAVAILABLE: {
            text: 'Sin Internet',
            className: 'hidden items-center gap-2 rounded-full border border-[#F59E0B]/30 bg-[#F59E0B]/10 px-3 py-2 text-xs font-bold text-[#B45309] sm:inline-flex',
            dotClass: 'h-2 w-2 rounded-full bg-[#F59E0B]',
        },
        OFFLINE: {
            text: 'Sin conexión',
            className: 'hidden items-center gap-2 rounded-full border border-[#EF4444]/20 bg-[#EF4444]/10 px-3 py-2 text-xs font-bold text-[#B91C1C] sm:inline-flex',
            dotClass: 'h-2 w-2 rounded-full bg-[#EF4444]',
        },
        SERVER_UNAVAILABLE: {
            text: 'Servidor no disponible',
            className: 'hidden items-center gap-2 rounded-full border border-[#F59E0B]/30 bg-[#F59E0B]/10 px-3 py-2 text-xs font-bold text-[#B45309] sm:inline-flex',
            dotClass: 'h-2 w-2 rounded-full bg-[#F59E0B]',
        },
    };
    const state = states[connection.state] || states.ONLINE;

    indicator.dataset.status = connection.state.toLowerCase();
    indicator.className = state.className;
    if (label) {
        label.textContent = state.text;
    }
    if (dot) {
        dot.className = state.dotClass;
    }
}

function legacyConnectionStatus(state) {
    if (state === CONNECTION_STATES.ONLINE) {
        return 'connected';
    }
    if (state === CONNECTION_STATES.SERVER_UNAVAILABLE) {
        return 'server_down';
    }
    return 'offline';
}

function publishConnection() {
    window.dispatchEvent(new CustomEvent('mediflow:connection-change', {
        detail: {
            ...connection,
            status: legacyConnectionStatus(connection.state),
        },
    }));
}

function setConnection(nextConnection, notify = true) {
    const previousState = connection.state;
    const stateChanged = previousState !== nextConnection.state;
    connection = { ...nextConnection };
    updateIndicator();
    updateOnlineOnlyElements();
    publishConnection();

    if (connection.state !== CONNECTION_STATES.ONLINE) {
        hadConnectivityIssue = true;
    }

    if (! notify || ! stateChanged) {
        return;
    }

    if (connection.state === CONNECTION_STATES.INTERNET_UNAVAILABLE) {
        toast(INTERNET_UNAVAILABLE_MESSAGE);
    } else if (connection.state === CONNECTION_STATES.OFFLINE) {
        toast(OFFLINE_MESSAGE);
    } else if (connection.state === CONNECTION_STATES.SERVER_UNAVAILABLE) {
        toast(SERVER_DOWN_MESSAGE);
    } else if (previousState !== CONNECTION_STATES.ONLINE || hadConnectivityIssue) {
        hadConnectivityIssue = false;
        toast(RESTORED_MESSAGE, 'success');
    }
}

function deriveConnectionState(browserOnline, serverReachable, internetReachable) {
    if (serverReachable && internetReachable) {
        return CONNECTION_STATES.ONLINE;
    }
    if (serverReachable) {
        return CONNECTION_STATES.INTERNET_UNAVAILABLE;
    }
    if (! browserOnline) {
        return CONNECTION_STATES.OFFLINE;
    }
    return CONNECTION_STATES.SERVER_UNAVAILABLE;
}

async function readHealth(url, signal) {
    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
            signal,
        });
        return response.ok ? await response.json() : null;
    } catch (_error) {
        return null;
    }
}

async function checkConnection(notify = true) {
    const checkId = ++healthCheckId;
    healthController?.abort();
    healthController = new AbortController();
    const controller = healthController;
    const timeout = window.setTimeout(() => controller.abort(), 6500);
    const browserOnline = navigator.onLine;

    try {
        const [appHealth, internetHealth] = await Promise.all([
            readHealth('/app-health', controller.signal),
            readHealth('/internet-health', controller.signal),
        ]);

        if (checkId !== healthCheckId) {
            return connection;
        }

        const serverReachable = appHealth?.ok === true;
        const internetReachable = serverReachable
            && internetHealth?.ok === true
            && internetHealth?.internet === true;
        setConnection({
            state: deriveConnectionState(browserOnline, serverReachable, internetReachable),
            serverReachable,
            internetReachable,
            browserOnline,
            lastCheckedAt: new Date().toISOString(),
        }, notify);
    } finally {
        window.clearTimeout(timeout);
        if (checkId === healthCheckId) {
            healthController = null;
        }
    }

    return connection;
}

function markSensitiveElements() {
    document.querySelectorAll('form[action], a[href]').forEach((element) => {
        const target = element.getAttribute('action') || element.getAttribute('href') || '';
        const match = SENSITIVE_ACTION_PATTERNS.find((entry) => entry.pattern.test(target));

        if (! match || element.dataset.offlineDraft === 'true') {
            return;
        }

        element.dataset.requiresOnline = element.dataset.requiresOnline || 'true';
        element.dataset.offlineBlockMessage = element.dataset.offlineBlockMessage || match.message;
    });

    document.querySelectorAll('a[href*="/reports/"][href*="/export"], a[href*="/reports/"][href*="/print"]').forEach((element) => {
        element.dataset.requiresOnline = element.dataset.requiresOnline || 'true';
        element.dataset.offlineBlockMessage = element.dataset.offlineBlockMessage || 'No se pueden generar reportes sin conexión.';
    });
}

function setDisabled(element, disabled) {
    if (disabled) {
        if (! element.dataset.originalTabIndex && element.hasAttribute('tabindex')) {
            element.dataset.originalTabIndex = element.getAttribute('tabindex');
        }
        element.dataset.offlineDisabled = 'true';
        element.setAttribute('aria-disabled', 'true');
        element.setAttribute('tabindex', '-1');
        if ('disabled' in element) {
            element.disabled = true;
        }
        element.classList.add(...DISABLED_CLASSES);
        return;
    }

    if (element.dataset.offlineDisabled !== 'true') {
        return;
    }

    element.removeAttribute('aria-disabled');
    if (element.dataset.originalTabIndex) {
        element.setAttribute('tabindex', element.dataset.originalTabIndex);
        delete element.dataset.originalTabIndex;
    } else {
        element.removeAttribute('tabindex');
    }
    if ('disabled' in element) {
        element.disabled = false;
    }
    element.classList.remove(...DISABLED_CLASSES);
    delete element.dataset.offlineDisabled;
}

function updateOnlineOnlyElements() {
    const shouldDisable = ! isOnlineReady();
    document.querySelectorAll('[data-requires-online="true"]').forEach((element) => {
        setDisabled(element, shouldDisable);
        if (element.matches('form')) {
            element.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => setDisabled(button, shouldDisable));
        }
    });
}

function blockMessageFor(element) {
    return element.dataset.offlineBlockMessage || element.closest('[data-offline-block-message]')?.dataset.offlineBlockMessage || ONLINE_ONLY_DEFAULT;
}

function preventOnlineOnlyAction(event) {
    const target = event.target instanceof Element ? event.target : null;
    const element = target?.closest('[data-requires-online="true"]');

    if (! element || isOnlineReady()) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    toast(blockMessageFor(element));
}

function fieldsForDraft(form) {
    return Array.from(form.elements).filter((field) => {
        if (! field.name || field.disabled) {
            return false;
        }

        const type = (field.type || '').toLowerCase();
        const forbidden = ['password', 'file', 'submit', 'button', 'reset'];
        const sensitiveName = /(_token|csrf|password|card|tarjeta|cvv|security_code)/i.test(field.name);

        return ! forbidden.includes(type) && ! sensitiveName;
    });
}

function serializeDraft(form) {
    const values = {};

    fieldsForDraft(form).forEach((field) => {
        const type = (field.type || '').toLowerCase();
        if (type === 'checkbox') {
            values[field.name] = field.checked;
            return;
        }
        if (type === 'radio') {
            if (field.checked) {
                values[field.name] = field.value;
            }
            return;
        }
        values[field.name] = field.value;
    });

    return {
        key: draftKey(form),
        savedAt: new Date().toISOString(),
        values,
    };
}

function saveDraft(form) {
    const payload = serializeDraft(form);
    localStorage.setItem(payload.key, JSON.stringify(payload));
    showDraftBanner(form);
    return payload.key;
}

function restoreDraft(form) {
    const raw = localStorage.getItem(draftKey(form));
    if (! raw) {
        return;
    }

    const payload = JSON.parse(raw);
    fieldsForDraft(form).forEach((field) => {
        if (! Object.prototype.hasOwnProperty.call(payload.values, field.name)) {
            return;
        }

        const type = (field.type || '').toLowerCase();
        if (type === 'checkbox') {
            field.checked = Boolean(payload.values[field.name]);
        } else if (type === 'radio') {
            field.checked = field.value === payload.values[field.name];
        } else {
            field.value = payload.values[field.name] ?? '';
        }
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
    });

    toast('Borrador restaurado. Revísalo y envíalo manualmente cuando corresponda.', 'success');
}

function discardDraft(form) {
    localStorage.removeItem(draftKey(form));
    form.querySelector('[data-draft-banner]')?.remove();
    toast('Borrador descartado.', 'success');
}

function showDraftBanner(form) {
    if (! localStorage.getItem(draftKey(form)) || form.querySelector('[data-draft-banner]')) {
        return;
    }

    const banner = document.createElement('div');
    banner.dataset.draftBanner = 'true';
    banner.className = 'm-5 rounded-lg border border-[#F59E0B]/30 bg-[#FFFBEB] px-4 py-3 text-sm text-[#92400E]';
    banner.innerHTML = `
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="font-semibold">Hay un borrador guardado de este formulario.</p>
            <div class="flex gap-2">
                <button type="button" data-draft-restore class="rounded-lg bg-[#0F172A] px-3 py-2 text-xs font-semibold text-white">Restaurar borrador</button>
                <button type="button" data-draft-discard class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-xs font-semibold text-[#475569]">Descartar borrador</button>
            </div>
        </div>
    `;
    form.prepend(banner);
    banner.querySelector('[data-draft-restore]')?.addEventListener('click', () => restoreDraft(form));
    banner.querySelector('[data-draft-discard]')?.addEventListener('click', () => discardDraft(form));
}

function initDraftForm(form) {
    showDraftBanner(form);

    let saveTimer = null;
    const scheduleSave = () => {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(() => saveDraft(form), 500);
    };

    form.addEventListener('input', scheduleSave);
    form.addEventListener('change', scheduleSave);
    form.addEventListener('submit', (event) => {
        if (isOnlineReady()) {
            localStorage.removeItem(draftKey(form));
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        saveDraft(form);
        toast(form.dataset.offlineDraftMessage || DRAFT_DEFAULT_MESSAGE);
    });
}

function initOfflineProtection() {
    markSensitiveElements();
    updateIndicator();
    updateOnlineOnlyElements();

    document.querySelectorAll('form[data-offline-draft="true"]').forEach(initDraftForm);
    document.addEventListener('click', preventOnlineOnlyAction, true);
    document.addEventListener('submit', preventOnlineOnlyAction, true);

    window.addEventListener('offline', () => checkConnection(true));
    window.addEventListener('online', () => checkConnection(true));
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            checkConnection(true);
        }
    });

    checkConnection(false);
    healthTimer = window.setInterval(() => checkConnection(true), 15000);
}

document.addEventListener('DOMContentLoaded', () => {
    initOfflineProtection();
    initMediFlowAssistant();
});
window.addEventListener('beforeunload', () => window.clearInterval(healthTimer));
window.MediFlowConnection = {
    check: checkConnection,
    states: CONNECTION_STATES,
    get state() {
        return connection.state;
    },
    get status() {
        return legacyConnectionStatus(connection.state);
    },
    get serverReachable() {
        return connection.serverReachable;
    },
    get internetReachable() {
        return connection.internetReachable;
    },
    get browserOnline() {
        return connection.browserOnline;
    },
    get lastCheckedAt() {
        return connection.lastCheckedAt;
    },
    saveDraft,
    draftKey,
    messages: {
        draft: DRAFT_DEFAULT_MESSAGE,
        clinicalDraft: CLINICAL_DRAFT_MESSAGE,
        financeBlocked: FINANCE_BLOCK_MESSAGE,
    },
};

Alpine.start();
