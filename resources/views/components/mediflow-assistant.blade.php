@php
    $assistantUser = auth()->user();
    $assistantRole = $assistantUser && method_exists($assistantUser, 'getRoleNames')
        ? ($assistantUser->getRoleNames()->first() ?: 'sin_rol')
        : 'sin_rol';
    $assistantClinicId = $assistantUser?->activeClinicId();
    $assistantRouteName = request()->route()?->getName() ?? '';
    $assistantRoute = request()->getPathInfo();

    $assistantModule = match (true) {
        str_starts_with($assistantRouteName, 'patients.') => 'patients',
        str_starts_with($assistantRouteName, 'doctors.') => 'doctors',
        str_starts_with($assistantRouteName, 'appointments.') => 'appointments',
        str_starts_with($assistantRouteName, 'daily-agenda.') => 'daily_agenda',
        str_starts_with($assistantRouteName, 'consultations.') => 'consultations',
        str_starts_with($assistantRouteName, 'medical-records.') => 'medical_records',
        str_starts_with($assistantRouteName, 'prescriptions.') => 'prescriptions',
        str_starts_with($assistantRouteName, 'payments.') => 'payments',
        str_starts_with($assistantRouteName, 'financial-audit.') => 'financial_audit',
        str_starts_with($assistantRouteName, 'reports.') => 'reports',
        str_starts_with($assistantRouteName, 'users.') => 'users',
        str_starts_with($assistantRouteName, 'settings.') => 'clinic_settings',
        str_starts_with($assistantRouteName, 'audit-logs.') => 'audit',
        str_starts_with($assistantRouteName, 'services.') => 'services',
        str_starts_with($assistantRouteName, 'super-admin.') => 'super_admin_clinics',
        str_starts_with($assistantRouteName, 'demo-requests.') => 'demo-requests',
        str_starts_with($assistantRouteName, 'profile.') => 'profile',
        $assistantRouteName === 'dashboard' => 'dashboard',
        default => 'general',
    };
@endphp

<div
    id="mediflow-assistant"
    class="mediflow-assistant"
    data-user-id="{{ $assistantUser->id }}"
    data-role="{{ $assistantRole }}"
    data-clinic-id="{{ $assistantClinicId ?? 'none' }}"
    data-current-route="{{ $assistantRoute }}"
    data-current-route-name="{{ $assistantRouteName }}"
    data-current-module="{{ $assistantModule }}"
    data-remote-enabled="{{ config('assistant.remote_enabled', false) ? 'true' : 'false' }}"
    data-state="closed"
    aria-live="polite"
>
    <div class="mediflow-assistant__suggestion" data-assistant-suggestion hidden>
        <button type="button" class="mediflow-assistant__suggestion-main" data-assistant-suggestion-open>
            <span class="mediflow-assistant__suggestion-icon" aria-hidden="true">?</span>
            <span>¿Necesitas ayuda con este módulo?</span>
        </button>
        <button type="button" class="mediflow-assistant__suggestion-close" data-assistant-suggestion-close aria-label="Cerrar sugerencia">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="m6 6 8 8M14 6l-8 8" />
            </svg>
        </button>
    </div>

    <button
        type="button"
        class="mediflow-assistant__launcher"
        data-assistant-launcher
        aria-label="Abrir Asistente MediFlow"
        aria-controls="mediflow-assistant-panel"
        aria-expanded="false"
        title="Asistente MediFlow"
    >
        <span class="mediflow-assistant__launcher-ring" aria-hidden="true"></span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 18.25 4 20v-4.15A7.75 7.75 0 0 1 3 12C3 7.58 7.03 4 12 4s9 3.58 9 8-4.03 8-9 8a9.8 9.8 0 0 1-4.5-1.75Z" />
            <path stroke-linecap="round" d="M8.25 10.25h.01M12 10.25h.01M15.75 10.25h.01" />
        </svg>
        <span class="mediflow-assistant__notification" data-assistant-notification hidden></span>
        <span class="mediflow-assistant__tooltip" role="tooltip">Asistente MediFlow</span>
    </button>

    <section
        id="mediflow-assistant-panel"
        class="mediflow-assistant__panel"
        data-assistant-panel
        role="dialog"
        aria-labelledby="mediflow-assistant-title"
        aria-modal="false"
        hidden
    >
        <header class="mediflow-assistant__header" data-assistant-drag-handle>
            <div class="mediflow-assistant__brand" data-assistant-drag-label>
                <span class="mediflow-assistant__brand-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 18.25 4 20v-4.15A7.75 7.75 0 0 1 3 12C3 7.58 7.03 4 12 4s9 3.58 9 8-4.03 8-9 8a9.8 9.8 0 0 1-4.5-1.75Z" />
                        <path stroke-linecap="round" d="M8.25 10.25h.01M12 10.25h.01M15.75 10.25h.01" />
                    </svg>
                </span>
                <span>
                    <strong id="mediflow-assistant-title">Asistente MediFlow</strong>
                    <span class="mediflow-assistant__connection" data-assistant-connection>
                        <i data-assistant-connection-dot></i>
                        <span data-assistant-connection-label>Conectado</span>
                    </span>
                </span>
            </div>

            <div class="mediflow-assistant__window-actions">
                <button type="button" data-assistant-minimize aria-label="Minimizar asistente" title="Minimizar">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M5 10h10" />
                    </svg>
                </button>
                <button type="button" data-assistant-maximize aria-label="Maximizar asistente" title="Maximizar">
                    <svg data-assistant-maximize-icon viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                        <rect x="4.5" y="4.5" width="11" height="11" rx="1.5" />
                    </svg>
                </button>
                <button type="button" data-assistant-close aria-label="Cerrar asistente" title="Cerrar">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="m6 6 8 8M14 6l-8 8" />
                    </svg>
                </button>
            </div>
        </header>

        <div class="mediflow-assistant__content" data-assistant-content>
            <div class="mediflow-assistant__privacy-note">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 2.75 16 5v4.4c0 3.7-2.4 6.6-6 7.85-3.6-1.25-6-4.15-6-7.85V5l6-2.25Z" />
                    <path stroke-linecap="round" d="m7.5 10 1.6 1.6 3.4-3.7" />
                </svg>
                <span>No escribas nombres de pacientes, identificaciones ni información clínica sensible.</span>
            </div>

            <div class="mediflow-assistant__messages mediflow-scrollbar" data-assistant-messages tabindex="0"></div>

            <div class="mediflow-assistant__quick-area" data-assistant-quick-area>
                <div class="mediflow-assistant__section-title">
                    <span>Preguntas rápidas</span>
                    <span data-assistant-module-label></span>
                </div>
                <div class="mediflow-assistant__quick-list mediflow-scrollbar" data-assistant-quick-list></div>
            </div>

            <div class="mediflow-assistant__escalation" data-assistant-escalation hidden>
                <p data-assistant-escalation-message>No se enviará información fuera de MediFlow.</p>
                <div>
                    <button type="button" data-assistant-contact-admin>Contactar administrador</button>
                    <button type="button" data-assistant-copy-support>Copiar detalles para soporte</button>
                    <button type="button" data-assistant-module-guide>Ver guía general del módulo</button>
                </div>
            </div>

            <form class="mediflow-assistant__composer" data-assistant-form autocomplete="off">
                <label for="mediflow-assistant-input" class="sr-only">Escribe una pregunta sobre el uso de MediFlow</label>
                <textarea
                    id="mediflow-assistant-input"
                    data-assistant-input
                    rows="1"
                    maxlength="500"
                    placeholder="Escribe una pregunta sobre MediFlow..."
                    aria-describedby="mediflow-assistant-input-help"
                ></textarea>
                <button type="submit" data-assistant-send aria-label="Enviar pregunta" title="Enviar">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m3.5 3.5 13 6.5-13 6.5 2-6.5-2-6.5Zm2 6.5h6" />
                    </svg>
                </button>
            </form>
            <div class="mediflow-assistant__footer">
                <span id="mediflow-assistant-input-help">Solo explica el uso del sistema; no ejecuta acciones.</span>
                <button type="button" data-assistant-clear>Limpiar historial</button>
            </div>
        </div>
    </section>
</div>
