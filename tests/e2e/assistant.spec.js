import { test, expect } from '@playwright/test';
import { loginAs, logout } from './helpers/auth.js';
import { mockConnectionHealth, setMockConnectionHealth } from './helpers/connection.js';

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

async function gotoWithAbortRetry(page, url) {
  try {
    return await page.goto(url);
  } catch (error) {
    if (!String(error).includes('net::ERR_ABORTED')) {
      throw error;
    }
    await page.waitForTimeout(150);
    return page.goto(url);
  }
}

test.describe('Asistente MediFlow - Fase 2', () => {
  test.beforeEach(async ({ page }) => {
    await mockConnectionHealth(page);
  });

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

    await gotoWithAbortRetry(page, '/');
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

    await expect(page).toHaveURL(/\/super-admin\/clinics(?:[/?#]|$)/);
    const root = await openAssistant(page);
    await expect(root).toHaveAttribute('data-role', 'super_admin');
    const questions = (await quickQuestionTexts(root)).join(' ');

    expect(questions).toMatch(/clínica|onboarding|suscripción|activar|administración global/i);
    expect(questions).not.toMatch(/consulta médica|receta|diagnóstico|procedimiento clínico/i);
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

    await setMockConnectionHealth(page, { serverReachable: true, internetReachable: false });
    await page.evaluate(() => window.MediFlowConnection.check(true));
    await expect(root.locator('[data-assistant-connection-label]')).toHaveText('Sin Internet');
    await ask(root, '¿Por qué no puedo cobrar?');
    await expect(root.locator('[data-assistant-messages]')).toContainText('Actualmente no hay conexión a Internet. MediFlow bloquea los pagos y movimientos financieros para evitar duplicaciones o inconsistencias.');
  });

  test('18. una pregunta desconocida usa la respuesta segura y ofrece escalado', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    const root = await openAssistant(page);

    await ask(root, '¿Cómo configuro una nave espacial en MediFlow?');
    await expect(root.locator('[data-assistant-messages]')).toContainText('No encontré una respuesta exacta para eso. Puedes revisar la guía del módulo o contactar al administrador.');
    await expect(root.locator('[data-assistant-escalation]')).toBeVisible();
    await expect(root.getByRole('button', { name: 'Copiar detalles para soporte' })).toBeVisible();

    await ask(root, 'crear');
    await expect(root.locator('[data-assistant-messages]')).not.toContainText('Para registrar un paciente sigue estos pasos:');
  });

  test('18a. reconoce variantes informales de recetas, citas, pagos y recibos', async ({ page }) => {
    test.setTimeout(60_000);
    await loginAs(page, 'doctor');
    await page.goto('/prescriptions');
    let root = await openAssistant(page);

    for (const question of ['como creo una receta medica', 'quiero hacer una receta', 'donde genero una prescripcion']) {
      await ask(root, question);
    }
    await expect(root.locator('[data-assistant-messages]')).toContainText('Abre Recetas médicas y pulsa Nueva receta.');

    await logout(page);
    await loginAs(page, 'reception');
    await page.goto('/appointments');
    root = await openAssistant(page);
    for (const question of ['cómo agendo un turno', 'quiero registrar una cita']) {
      await ask(root, question);
    }
    await expect(root.locator('[data-assistant-messages]')).toContainText('Para agendar una cita sigue estos pasos:');

    await logout(page);
    await loginAs(page, 'cash');
    await page.goto('/payments');
    root = await openAssistant(page);
    await ask(root, 'como cobro un pago');
    await ask(root, 'donde descargo el recibo');
    await expect(root.locator('[data-assistant-messages]')).toContainText('Para registrar un cobro sigue estos pasos:');
    await expect(root.locator('[data-assistant-messages]')).toContainText('Abre el detalle del pago y utiliza la opción Recibo o Imprimir.');
  });

  test('18b. el rol sigue siendo un filtro obligatorio para preguntas escritas', async ({ page }) => {
    test.setTimeout(60_000);
    await loginAs(page, 'doctor');
    await page.goto('/dashboard');
    let root = await openAssistant(page);
    await ask(root, 'como cobro un pago');
    await expect(root.locator('[data-assistant-messages]')).toContainText('No encontré una respuesta exacta para eso.');

    await logout(page);
    await loginAs(page, 'cash');
    await page.goto('/payments');
    root = await openAssistant(page);
    await ask(root, 'como creo una receta medica');
    await expect(root.locator('[data-assistant-messages]')).toContainText('No encontré una respuesta exacta para eso.');

    await logout(page);
    await loginAs(page, 'reception');
    await page.goto('/patients');
    root = await openAssistant(page);
    await ask(root, 'como consulto una historia clinica');
    await expect(root.locator('[data-assistant-messages]')).toContainText('No encontré una respuesta exacta para eso.');
  });

  test('18c. una pregunta ambigua muestra opciones controladas', async ({ page }) => {
    await loginAs(page, 'doctor');
    await page.goto('/prescriptions');
    const root = await openAssistant(page);

    await ask(root, 'receta');

    const messages = root.locator('[data-assistant-messages]');
    await expect(messages).toContainText('¿Te refieres a alguna de estas opciones?');
    await expect(messages).toContainText('Crear una receta');
    await expect(messages).toContainText('Firmar una receta');
    await expect(messages).toContainText('Enviar una receta por correo');
  });

  test('18d. estado sin Internet orienta problemas de firma y guardado sin decir que el servidor cayó', async ({ page }) => {
    await loginAs(page, 'doctor');
    await page.goto('/prescriptions');
    const root = await openAssistant(page);
    await setMockConnectionHealth(page, { serverReachable: true, internetReachable: false });
    await page.evaluate(() => window.MediFlowConnection.check(true));

    await ask(root, 'no me deja firmar la receta');
    await ask(root, 'estoy sin internet');
    await expect(root.locator('[data-assistant-messages]')).toContainText('Actualmente no hay conexión a Internet.');
    await expect(root.locator('[data-assistant-messages]')).not.toContainText('No se puede establecer comunicación con el servidor MediFlow.');
  });

  test('18e. estado de servidor no disponible usa el diagnóstico correcto', async ({ page }) => {
    await loginAs(page, 'cash');
    await page.goto('/payments');
    const root = await openAssistant(page);
    await setMockConnectionHealth(page, { serverReachable: false, internetReachable: false });
    await page.evaluate(() => window.MediFlowConnection.check(true));

    await ask(root, 'por que no puedo guardar');
    await expect(root.locator('[data-assistant-messages]')).toContainText('No se puede establecer comunicación con el servidor MediFlow.');
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

    const clinicSwitcher = page.locator('button:visible').filter({ hasText: 'Sucursal activa' }).first();
    test.skip(await clinicSwitcher.count() === 0, 'Requiere dos clínicas E2E asociadas al administrador.');
    await clinicSwitcher.click();
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

  test('23. carga la fuente JSON centralizada y expone metadatos seguros', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/dashboard');

    const metadata = await page.evaluate(() => window.MediFlowAssistant.knowledgeBase);
    expect(metadata).toEqual({
      source: 'resources/assistant/knowledge-base.json',
      schemaVersion: 2,
      entries: 75,
    });
  });

  test('24. muestra el aviso explícito de privacidad', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/patients');
    const root = await openAssistant(page);

    await expect(root.locator('.mediflow-assistant__privacy-note')).toContainText(
      'No escribas nombres de pacientes, identificaciones ni información clínica sensible.',
    );
  });

  test('25. una guía muestra pasos, restricciones y enlace solo cuando corresponde', async ({ page }) => {
    await loginAs(page, 'reception');
    await page.goto('/patients');
    const root = await openAssistant(page);

    await ask(root, '¿Cómo registro un paciente?');
    let answer = root.locator('.mediflow-assistant__message--assistant').last();
    await expect(answer).toContainText('1. Abre Pacientes.');
    await expect(answer).toContainText('Restricciones:');
    await expect(answer.locator('[data-assistant-module-link]')).toHaveAttribute('href', '/patients/create');

    await ask(root, '¿Qué significan los estados de conexión?');
    answer = root.locator('.mediflow-assistant__message--assistant').last();
    await expect(answer).toContainText('Conectado indica');
    await expect(answer.locator('[data-assistant-module-link]')).toHaveCount(0);
  });

  test('26. admin y SuperAdmin obtienen ayuda escrita dentro de su alcance', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/users');
    let root = await openAssistant(page);
    await ask(root, '¿Cómo creo o edito usuarios y roles?');
    await expect(root.locator('[data-assistant-messages]')).toContainText('En Usuarios y Roles puedes crear o editar cuentas');

    await logout(page);
    await loginAs(page, 'superAdmin');
    root = await openAssistant(page);
    await ask(root, '¿Cómo creo una clínica y su administrador principal?');
    await expect(root.locator('[data-assistant-messages]')).toContainText('Nueva clínica crea la organización');
    await expect(root.locator('[data-assistant-messages]')).not.toContainText('iniciar una consulta');
  });
});
