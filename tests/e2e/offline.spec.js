import { test, expect } from '@playwright/test';
import { loginAs, logout } from './helpers/auth.js';
import { createUniquePatientData } from './helpers/data.js';
import { mockConnectionHealth, setMockConnectionHealth } from './helpers/connection.js';

async function goOffline(page) {
  await setMockConnectionHealth(page, { serverReachable: false, internetReachable: false });
  await page.context().setOffline(true);
  await page.evaluate(() => window.dispatchEvent(new Event('offline')));
  await expect(page.locator('#connection-status')).toContainText('Sin conexión');
}

async function goOnline(page) {
  await setMockConnectionHealth(page, { serverReachable: true, internetReachable: true });
  await page.context().setOffline(false);
  await page.evaluate(async () => {
    window.dispatchEvent(new Event('online'));
    await window.MediFlowConnection?.check?.(true);
  });
  await expect(page.getByText('Conexión restablecida.')).toBeVisible();
  await expect(page.locator('#connection-status')).toContainText('Conectado');
}

async function goWithoutInternet(page) {
  await setMockConnectionHealth(page, { serverReachable: true, internetReachable: false });
  await page.evaluate(() => window.MediFlowConnection?.check?.(true));
  await expect(page.locator('#connection-status')).toContainText('Sin Internet');
}

test.describe('Offline protection', () => {
  test.beforeEach(async ({ page }) => {
    await mockConnectionHealth(page);
  });

  test.afterEach(async ({ context }) => {
    await context.setOffline(false);
  });

  test('app-health and internet-health available show detailed online state', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    await page.evaluate(() => window.MediFlowConnection?.check?.(false));

    await expect(page.locator('#connection-status')).toContainText('Conectado');
    const connection = await page.evaluate(() => ({
      state: window.MediFlowConnection.state,
      serverReachable: window.MediFlowConnection.serverReachable,
      internetReachable: window.MediFlowConnection.internetReachable,
      browserOnline: window.MediFlowConnection.browserOnline,
      lastCheckedAt: window.MediFlowConnection.lastCheckedAt,
    }));
    expect(connection).toEqual(expect.objectContaining({
      state: 'ONLINE',
      serverReachable: true,
      internetReachable: true,
      browserOnline: true,
      lastCheckedAt: expect.any(String),
    }));
  });

  test('app available without internet shows Sin Internet only once per state', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');

    await goWithoutInternet(page);
    await expect(page.getByText(/MediFlow local continúa disponible/i)).toBeVisible();
    await page.evaluate(() => window.MediFlowConnection?.check?.(true));
    await expect(page.getByText(/MediFlow local continúa disponible/i)).toHaveCount(1);
  });

  test('app-health failure while browser is online shows server unavailable', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');

    await setMockConnectionHealth(page, { serverReachable: false, internetReachable: false });
    await page.evaluate(() => window.MediFlowConnection?.check?.(true));

    await expect(page.locator('#connection-status')).toContainText('Servidor no disponible');
    expect(await page.evaluate(() => window.MediFlowConnection.state)).toBe('SERVER_UNAVAILABLE');
  });

  test('shows offline and restored connection status', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    await expect(page.locator('#connection-status')).toContainText('Conectado');

    await goOffline(page);
    await expect(page.getByText(/Sin conexión\. Algunas acciones fueron bloqueadas/i)).toBeVisible();

    await goOnline(page);
    await logout(page);
  });

  test('cashier cannot create or submit payments offline', async ({ page }) => {
    await loginAs(page, 'cash');
    await page.goto('/payments');

    await goWithoutInternet(page);
    await expect(page.locator('a[href$="/payments/create"]')).toHaveAttribute('aria-disabled', 'true');

    await page.goto('/payments/create');
    await goWithoutInternet(page);

    const paymentForm = page.locator('form[action$="/payments"]').first();
    await expect(paymentForm.locator('button[type="submit"]')).toBeDisabled();
    const submitted = await paymentForm.evaluate((form) => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));

    expect(submitted).toBe(false);
    await expect(page.getByText(/No se puede registrar ni modificar pagos sin conexión/i)).toBeVisible();
    await goOnline(page);
    await logout(page);
  });

  test('reception saves and restores patient draft offline', async ({ page }) => {
    const patientData = createUniquePatientData();

    await loginAs(page, 'reception');
    await page.goto('/patients/create');
    await goWithoutInternet(page);

    await page.fill('#first_name', patientData.first_name);
    await page.fill('#last_name', patientData.last_name);
    await page.locator('form[action$="/patients"]').evaluate((form) => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));

    await expect(page.getByText(/No hay conexión\. El formulario fue guardado como borrador local/i)).toBeVisible();
    await expect(page.getByText('Hay un borrador guardado de este formulario.')).toBeVisible();

    const draftKeys = await page.evaluate(() => Object.keys(localStorage).filter((key) => key.startsWith('mediflow:draft:') && key.includes(':patients:new')));
    expect(draftKeys.length).toBeGreaterThan(0);

    await page.fill('#first_name', '');
    await page.getByRole('button', { name: 'Restaurar borrador' }).click();
    await expect(page.locator('#first_name')).toHaveValue(patientData.first_name);
    await page.getByRole('button', { name: 'Descartar borrador' }).click();
    await goOnline(page);
    await logout(page);
  });

  test('doctor saves clinical drafts and cannot run official prescription actions offline', async ({ page }) => {
    await loginAs(page, 'doctor');
    await page.goto('/consultations/create');
    await goWithoutInternet(page);

    await page.fill('#diagnosis', 'Borrador clínico E2E sin conexión');
    await page.locator('form[action$="/consultations"]').evaluate((form) => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));

    await expect(page.getByText(/No hay conexión\. El contenido fue guardado como borrador local/i)).toBeVisible();
    await expect(page.getByText('Hay un borrador guardado de este formulario.')).toBeVisible();

    const draftKeys = await page.evaluate(() => Object.keys(localStorage).filter((key) => key.startsWith('mediflow:draft:') && key.includes(':consultations:new')));
    expect(draftKeys.length).toBeGreaterThan(0);

    await page.evaluate(() => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/prescriptions/1/sign';
      form.dataset.requiresOnline = 'true';
      form.dataset.offlineBlockMessage = 'No se puede firmar ni enviar recetas sin conexión.';
      form.innerHTML = '<button type="submit">Firmar receta</button>';
      document.body.appendChild(form);
      window.dispatchEvent(new Event('offline'));
    });

    const signButton = page.getByRole('button', { name: 'Firmar receta' });
    await expect(signButton).toBeDisabled();
    const submitted = await page.locator('form[action$="/prescriptions/1/sign"]').evaluate((form) => form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(submitted).toBe(false);
    await expect(page.getByText(/No se puede firmar ni enviar recetas sin conexión/i)).toBeVisible();

    await goOnline(page);
    await expect(signButton).not.toBeDisabled();
    await logout(page);
  });
});
