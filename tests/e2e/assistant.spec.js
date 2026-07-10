import { test, expect } from '@playwright/test';
import { login, logout } from './helpers/auth.js';

const accounts = {
  admin: ['admin@mediflow.com', 'Admin123*'],
  reception: ['recepcionista@mediflow.com', 'Password123*'],
  cash: ['caja@mediflow.com', 'Password123*'],
  doctor: ['medico@mediflow.com', 'Password123*'],
  superAdmin: ['superadmin@mediflow.com', 'Password123*'],
};

async function loginAs(page, account) {
  const [email, password] = accounts[account];
  if (account !== 'superAdmin') {
    await login(page, email, password);
    return;
  }

  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/login') && response.request().method() === 'POST'),
    page.click('button[type="submit"]'),
  ]);
  await page.goto('/super-admin/clinics');
}

async function openAssistant(page) {
  const root = page.locator('#mediflow-assistant');
  await expect(root).toBeVisible();
  await root.locator('[data-assistant-launcher]').click();
  await expect(root.locator('[data-assistant-panel]')).toBeVisible();
  return root;
}

async function quickQuestionTexts(root) {
  return root.locator('[data-assistant-quick-list] button').allTextContents();
}

async function ask(root, question) {
  await root.locator('[data-assistant-input]').fill(question);
  await root.locator('[data-assistant-send]').click();
}

test.describe('Asistente MediFlow - Fase 1', () => {
  test.afterEach(async ({ context }) => {
    await context.setOffline(false);
  });

  test('1. aparece en páginas autenticadas y expone solo contexto seguro', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');

    const root = page.locator('#mediflow-assistant');
    await expect(root).toBeVisible();
    await expect(root).toHaveAttribute('data-user-id', /\d+/);
    await expect(root).toHaveAttribute('data-role', 'administrador');
    await expect(root).toHaveAttribute('data-clinic-id', /\d+/);
    await expect(root).toHaveAttribute('data-current-route', '/dashboard');
    await expect(root).toHaveAttribute('data-current-module', 'dashboard');
    expect(await root.getAttribute('data-token')).toBeNull();
  });

  test('2. no aparece en login ni en rutas públicas', async ({ page }) => {
    await page.goto('/login');
    await expect(page.locator('#mediflow-assistant')).toHaveCount(0);

    await page.goto('/');
    await expect(page.locator('#mediflow-assistant')).toHaveCount(0);
  });

  test('3. puede abrirse', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openAssistant(page);

    await expect(root).toHaveAttribute('data-state', 'open');
    await expect(root.locator('#mediflow-assistant-title')).toBeVisible();
    await expect(root.locator('[data-assistant-input]')).toBeFocused();
  });

  test('4. puede minimizarse y maximizarse', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openAssistant(page);

    await root.locator('[data-assistant-minimize]').click();
    await expect(root).toHaveAttribute('data-state', 'minimized');
    await expect(root.locator('[data-assistant-content]')).not.toBeVisible();

    await root.locator('[data-assistant-minimize]').click();
    await root.locator('[data-assistant-maximize]').click();
    await expect(root).toHaveAttribute('data-state', 'maximized');
    const box = await root.locator('[data-assistant-panel]').boundingBox();
    expect(box.width).toBeGreaterThan(700);
  });

  test('5. puede cerrarse', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openAssistant(page);

    await root.locator('[data-assistant-close]').click();
    await expect(root).toHaveAttribute('data-state', 'closed');
    await expect(root.locator('[data-assistant-panel]')).not.toBeVisible();
    await expect(root.locator('[data-assistant-launcher]')).toBeVisible();
  });

  test('6. se puede arrastrar sin salir del área visible', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const launcher = page.locator('[data-assistant-launcher]');
    const before = await launcher.boundingBox();

    await page.mouse.move(before.x + before.width / 2, before.y + before.height / 2);
    await page.mouse.down();
    await page.mouse.move(before.x - 180, before.y - 140, { steps: 8 });
    await page.mouse.up();

    const after = await launcher.boundingBox();
    expect(Math.abs(after.x - before.x)).toBeGreaterThan(60);
    expect(after.x).toBeGreaterThanOrEqual(8);
    expect(after.y).toBeGreaterThanOrEqual(8);
  });

  test('7. conserva la posición al recargar', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const launcher = page.locator('[data-assistant-launcher]');
    const before = await launcher.boundingBox();

    await page.mouse.move(before.x + 20, before.y + 20);
    await page.mouse.down();
    await page.mouse.move(before.x - 150, before.y - 100, { steps: 8 });
    await page.mouse.up();

    const moved = await launcher.boundingBox();
    const position = await page.evaluate(() => {
      const key = window.MediFlowAssistant.storageKeys.position;
      return JSON.parse(localStorage.getItem(key));
    });
    expect(position).toEqual(expect.objectContaining({ x: expect.any(Number), y: expect.any(Number) }));

    await page.reload();
    const restored = await page.locator('[data-assistant-launcher]').boundingBox();
    expect(Math.abs(restored.x - moved.x)).toBeLessThanOrEqual(3);
    expect(Math.abs(restored.y - moved.y)).toBeLessThanOrEqual(3);
  });

  test('8. recepción ve preguntas de pacientes y citas', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/patients');
    let root = await openAssistant(page);
    expect((await quickQuestionTexts(root)).join(' ')).toMatch(/paciente/i);

    await page.goto('/appointments');
    root = await openAssistant(page);
    expect((await quickQuestionTexts(root)).join(' ')).toMatch(/cita|disponibilidad/i);
  });

  test('9. recepción no ve ayuda financiera completa ni clínica sensible', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/appointments');
    const root = await openAssistant(page);
    const questions = (await quickQuestionTexts(root)).join(' ');

    expect(questions).not.toMatch(/reporte financiero|registrar cobro|firmar receta|iniciar consulta/i);
  });

  test('10. caja ve preguntas financieras', async ({ page }) => {
    await loginAs(page, 'cash');
    await page.goto('/payments');
    const root = await openAssistant(page);
    const questions = (await quickQuestionTexts(root)).join(' ');

    expect(questions).toMatch(/pago|cobro|recibo/i);
  });

  test('11. caja no ve preguntas clínicas', async ({ page }) => {
    await loginAs(page, 'cash');
    await page.goto('/payments');
    const root = await openAssistant(page);
    const questions = (await quickQuestionTexts(root)).join(' ');

    expect(questions).not.toMatch(/consulta|historia clínica|receta|diagnóstico/i);
  });

  test('12. médico ve preguntas de consultas y recetas', async ({ page }) => {
    await loginAs(page, 'doctor');
    await page.goto('/consultations');
    let root = await openAssistant(page);
    expect((await quickQuestionTexts(root)).join(' ')).toMatch(/consulta/i);

    await page.goto('/prescriptions');
    root = await openAssistant(page);
    expect((await quickQuestionTexts(root)).join(' ')).toMatch(/receta/i);
  });

  test('13. médico no ve preguntas financieras', async ({ page }) => {
    await loginAs(page, 'doctor');
    await page.goto('/consultations');
    const root = await openAssistant(page);
    const questions = (await quickQuestionTexts(root)).join(' ');

    expect(questions).not.toMatch(/cobro|pago pendiente|reporte financiero|recibo/i);
  });

  test('14. admin ve preguntas administrativas', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/users');
    const root = await openAssistant(page);
    const questions = (await quickQuestionTexts(root)).join(' ');

    expect(questions).toMatch(/usuario|rol/i);
    expect(questions).not.toMatch(/crear una clínica|suscripción/i);
  });

  test('15. SuperAdmin ve preguntas SaaS y no procedimientos médicos', async ({ page }) => {
    await loginAs(page, 'superAdmin');
    const root = await openAssistant(page);
    const questions = (await quickQuestionTexts(root)).join(' ');

    expect(questions).toMatch(/clínica|onboarding|suscripción/i);
    expect(questions).not.toMatch(/consulta médica|receta|diagnóstico/i);
  });

  test('16. las preguntas cambian según la ruta', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/patients');
    let root = await openAssistant(page);
    const patientQuestions = await quickQuestionTexts(root);
    await expect(root).toHaveAttribute('data-current-module', 'patients');

    await page.goto('/appointments');
    root = await openAssistant(page);
    const appointmentQuestions = await quickQuestionTexts(root);
    await expect(root).toHaveAttribute('data-current-module', 'appointments');

    expect(appointmentQuestions).not.toEqual(patientQuestions);
  });

  test('17. offline muestra la explicación correcta según el rol', async ({ page }) => {
    await loginAs(page, 'cash');
    await page.goto('/payments');
    const root = await openAssistant(page);

    await page.context().setOffline(true);
    await page.evaluate(() => window.dispatchEvent(new Event('offline')));
    await expect(root.locator('[data-assistant-connection-label]')).toHaveText('Sin conexión');
    await ask(root, '¿Por qué no puedo cobrar?');
    await expect(root.locator('[data-assistant-messages]')).toContainText('MediFlow bloquea los pagos y movimientos financieros sin conexión');
  });

  test('18. una pregunta desconocida usa la respuesta segura y ofrece escalado', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openAssistant(page);

    await ask(root, '¿Cómo configuro una nave espacial en MediFlow?');
    await expect(root.locator('[data-assistant-messages]')).toContainText('No encontré una respuesta exacta para eso. Puedes revisar la guía del módulo o contactar al administrador.');
    await expect(root.locator('[data-assistant-escalation]')).toBeVisible();
    await expect(root.getByRole('button', { name: 'Copiar detalles para soporte' })).toBeVisible();
  });

  test('19. el historial se conserva al recargar', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/patients');
    let root = await openAssistant(page);
    const question = '¿Cómo registro un paciente?';
    await ask(root, question);
    await expect(root.locator('[data-assistant-messages]')).toContainText('Para registrar un paciente');

    await page.reload();
    root = await openAssistant(page);
    await expect(root.locator('[data-assistant-messages]')).toContainText(question);
    await expect(root.locator('[data-assistant-messages]')).toContainText('Para registrar un paciente');
  });

  test('20. limpiar historial pide confirmación y elimina el almacenamiento local', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/patients');
    const root = await openAssistant(page);
    await ask(root, '¿Cómo registro un paciente?');

    page.once('dialog', (dialog) => dialog.accept());
    await root.locator('[data-assistant-clear]').click();
    await expect(root.locator('[data-assistant-messages]')).not.toContainText('¿Cómo registro un paciente?');
    const stored = await page.evaluate(() => localStorage.getItem(window.MediFlowAssistant.storageKeys.history));
    expect(JSON.parse(stored)).toEqual([]);
  });

  test('21. el historial no se mezcla entre usuarios', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/patients');
    let root = await openAssistant(page);
    await ask(root, '¿Cómo registro un paciente?');
    const receptionKey = await page.evaluate(() => window.MediFlowAssistant.storageKeys.history);
    expect(await page.evaluate((key) => localStorage.getItem(key), receptionKey)).not.toBeNull();

    await logout(page);
    await loginAs(page, 'cash');
    await page.goto('/payments');
    root = await openAssistant(page);
    const cashKey = await page.evaluate(() => window.MediFlowAssistant.storageKeys.history);

    expect(cashKey).not.toBe(receptionKey);
    expect(await page.evaluate((key) => localStorage.getItem(key), cashKey)).toBeNull();
    await expect(root.locator('[data-assistant-messages]')).not.toContainText('¿Cómo registro un paciente?');
  });

  test('22. el historial no se mezcla entre clínicas', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    let root = await openAssistant(page);
    await ask(root, '¿Cómo creo un usuario?');
    const firstKey = await page.evaluate(() => window.MediFlowAssistant.storageKeys.history);
    const firstClinic = await root.getAttribute('data-clinic-id');

    await page.locator('button:visible').filter({ hasText: 'Sucursal activa' }).first().click();
    const switchButton = page.locator('form[action*="/switch-clinic/"] button:visible').first();
    await expect(switchButton).toBeVisible();
    await switchButton.click();
    await page.waitForLoadState('domcontentloaded');

    root = page.locator('#mediflow-assistant');
    const secondClinic = await root.getAttribute('data-clinic-id');
    const secondKey = await page.evaluate(() => window.MediFlowAssistant.storageKeys.history);
    expect(secondClinic).not.toBe(firstClinic);
    expect(secondKey).not.toBe(firstKey);
    expect(await page.evaluate((key) => localStorage.getItem(key), secondKey)).toBeNull();

    await page.locator('button:visible').filter({ hasText: 'Sucursal activa' }).first().click();
    const switchBack = page.locator('form[action*="/switch-clinic/"] button:visible').first();
    await switchBack.click();
    await page.waitForLoadState('domcontentloaded');
  });
});
