import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth.js';
import { mockConnectionHealth, setMockConnectionHealth } from './helpers/connection.js';

const FALLBACK = 'No encontré una respuesta exacta para eso. Puedes revisar la guía del módulo o contactar al administrador.';

async function openRemoteAssistant(page) {
  const root = page.locator('#mediflow-assistant');
  await expect(root).toBeVisible();
  await root.locator('[data-assistant-launcher]').click();
  await expect(root.locator('[data-assistant-panel]')).toBeVisible();
  await root.evaluate((element) => { element.dataset.remoteEnabled = 'true'; });
  return root;
}

async function ask(root, question) {
  await root.locator('[data-assistant-input]').fill(question);
  await root.locator('[data-assistant-send]').click();
}

test.describe('Asistente MediFlow - Fase 3 remota', () => {
  test.beforeEach(async ({ page }) => {
    await mockConnectionHealth(page);
  });

  test.afterEach(async ({ context }) => {
    await context.setOffline(false);
  });

  test('respuesta conocida, pregunta rápida y ambigüedad permanecen locales', async ({ page }) => {
    let endpointCalls = 0;
    await page.route('**/assistant/message', async (route) => {
      endpointCalls += 1;
      await route.fulfill({ status: 500, contentType: 'application/json', body: '{}' });
    });
    await loginAs(page, 'doctor');
    await page.goto('/prescriptions');
    const root = await openRemoteAssistant(page);

    await ask(root, 'como creo una receta medica');
    await expect(root.locator('[data-assistant-messages]')).toContainText('Abre Recetas médicas y pulsa Nueva receta.');
    await root.locator('[data-assistant-quick-list] button').first().click();
    await ask(root, 'receta');
    await expect(root.locator('[data-assistant-messages]')).toContainText('¿Te refieres a alguna de estas opciones?');
    expect(endpointCalls).toBe(0);
  });

  test('pregunta desconocida online muestra respuesta, pasos y sugerencias remotas', async ({ page }) => {
    let sentPayload = null;
    await page.route('**/assistant/message', async (route) => {
      sentPayload = route.request().postDataJSON();
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ok: true,
          request_id: 'e2e-remote-request',
          answer: 'Esta es una respuesta remota segura.',
          steps: ['Abre la sección indicada.', 'Revisa la ayuda disponible.'],
          suggestions: ['¿Qué hago después?'],
          confidence: 0.91,
          source: 'remote',
          fallback_used: false,
        }),
      });
    });
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openRemoteAssistant(page);

    await ask(root, 'telemetría zafiro orbital');

    const lastAnswer = root.locator('.mediflow-assistant__message--assistant').last();
    await expect(lastAnswer).toContainText('Respuesta del asistente');
    await expect(lastAnswer).toContainText('Esta es una respuesta remota segura.');
    await expect(lastAnswer).toContainText('1. Abre la sección indicada.');
    await expect(lastAnswer).toContainText('2. Revisa la ayuda disponible.');
    await expect(lastAnswer.locator('[data-assistant-remote-suggestion]')).toHaveText('¿Qué hago después?');
    expect(sentPayload).toEqual(expect.objectContaining({
      question: 'telemetría zafiro orbital',
      current_route: 'dashboard',
      current_module: 'dashboard',
      connection_state: 'ONLINE',
      knowledge_version: 2,
    }));
    for (const key of ['user_id', 'role', 'clinic_id', 'name', 'email', 'history']) {
      expect(sentPayload).not.toHaveProperty(key);
    }
  });

  test('error HTTP y timeout de red usan el fallback seguro', async ({ page }) => {
    let mode = 'http';
    await page.route('**/assistant/message', async (route) => {
      if (mode === 'timeout') {
        await route.abort('timedout');
        return;
      }
      await route.fulfill({ status: 503, contentType: 'application/json', body: '{}' });
    });
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openRemoteAssistant(page);

    await ask(root, 'telemetría zafiro error remoto');
    await expect(root.locator('.mediflow-assistant__message--assistant').last()).toContainText(FALLBACK);

    mode = 'timeout';
    await ask(root, 'telemetría zafiro timeout remoto');
    await expect(root.locator('.mediflow-assistant__message--assistant').last()).toContainText(FALLBACK);
  });

  test('sin Internet y offline no llaman al endpoint', async ({ page, context }) => {
    let endpointCalls = 0;
    await page.route('**/assistant/message', async (route) => {
      endpointCalls += 1;
      await route.abort();
    });
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openRemoteAssistant(page);

    await setMockConnectionHealth(page, { serverReachable: true, internetReachable: false });
    await page.evaluate(() => window.MediFlowConnection.check(true));
    await ask(root, 'telemetría zafiro quasar uno');
    await expect(root.locator('.mediflow-assistant__message--assistant').last()).toContainText(FALLBACK);

    await context.setOffline(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));
    await expect.poll(() => root.locator('[data-assistant-connection-label]').textContent()).not.toBe('Conectado');
    await ask(root, 'telemetría zafiro quasar dos');
    await expect(root.locator('.mediflow-assistant__message--assistant').last()).toContainText(FALLBACK);
    expect(endpointCalls).toBe(0);
  });

  test('contenido sensible se bloquea localmente y nunca se envía', async ({ page }) => {
    let endpointCalls = 0;
    await page.route('**/assistant/message', async (route) => {
      endpointCalls += 1;
      await route.abort();
    });
    await loginAs(page, 'doctor');
    await page.goto('/prescriptions');
    const root = await openRemoteAssistant(page);

    await ask(root, 'Crea una receta para Juan Pérez con cédula 0912345678');

    await expect(root.locator('[data-assistant-messages]')).toContainText('No puedo procesar esa pregunta porque parece contener información sensible.');
    await expect(root.locator('[data-assistant-messages]')).not.toContainText('0912345678');
    expect(endpointCalls).toBe(0);
  });

  test('la UI bloquea doble clic y una respuesta antigua no sobrescribe la nueva', async ({ page }) => {
    await page.route('**/assistant/message', async (route) => {
      const question = route.request().postDataJSON().question;
      if (question.includes('primera')) {
        await new Promise((resolve) => setTimeout(resolve, 700));
      }
      try {
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({
            ok: true,
            request_id: question,
            answer: question.includes('primera') ? 'RESPUESTA ANTIGUA' : 'RESPUESTA NUEVA',
            steps: [],
            suggestions: [],
            source: 'remote',
            fallback_used: false,
          }),
        });
      } catch (_error) {
        // La primera solicitud fue cancelada correctamente por el navegador.
      }
    });
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openRemoteAssistant(page);
    const send = root.locator('[data-assistant-send]');
    const input = root.locator('[data-assistant-input]');

    await ask(root, 'telemetría zafiro primera');
    await expect(send).toBeDisabled();
    await expect(root.locator('[data-assistant-pending]')).toContainText('Buscando respuesta');

    await input.fill('telemetría zafiro segunda');
    await input.press('Enter');
    await expect(root.locator('[data-assistant-messages]')).toContainText('RESPUESTA NUEVA');
    await expect(send).toBeEnabled();
    await page.waitForTimeout(900);
    await expect(root.locator('[data-assistant-messages]')).not.toContainText('RESPUESTA ANTIGUA');
  });

  test('Blade expone sólo la disponibilidad remota', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = page.locator('#mediflow-assistant');

    await expect(root).toHaveAttribute('data-remote-enabled', /^(true|false)$/);
    await expect(root).not.toHaveAttribute('data-provider', /.+/);
    await expect(root).not.toHaveAttribute('data-webhook-url', /.+/);
    await expect(root).not.toHaveAttribute('data-secret', /.+/);
  });
});
