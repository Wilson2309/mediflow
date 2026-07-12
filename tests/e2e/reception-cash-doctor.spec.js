import { test, expect } from '@playwright/test';
import { loginAs, logout } from './helpers/auth.js';
import { createUniquePatientData, createUniqueAppointmentData } from './helpers/data.js';

test.describe('Reception -> Cashier -> Doctor Flow', () => {
  test('Complete flow: create patient, pay, consult', async ({ page }) => {
    test.setTimeout(120000);

    const patientData = createUniquePatientData();
    const appointmentData = createUniqueAppointmentData();
    const futureDate = new Date(Date.now() + (90 + Math.floor(Math.random() * 90)) * 24 * 60 * 60 * 1000);
    const appointmentDate = futureDate.toISOString().slice(0, 10);

    await loginAs(page, 'reception');
    await page.goto('/patients');
    await page.click('text=Nuevo Paciente');

    await page.fill('input[name="first_name"]', patientData.first_name);
    await page.fill('input[name="last_name"]', patientData.last_name);
    await page.fill('input[name="identification_number"]', patientData.identification_number);
    await page.fill('input[name="email"]', patientData.email);
    await page.fill('input[name="phone"]', patientData.phone);
    await page.fill('input[name="birth_date"]', patientData.birth_date);
    await page.selectOption('select[name="gender"]', patientData.gender);
    await page.selectOption('select[name="blood_type"]', 'O+');
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/patients/);
    await page.fill('#search', patientData.identification_number);
    await page.click('button[type="submit"]');
    await expect(page.getByText(patientData.first_name).first()).toBeVisible();

    await page.goto('/appointments/create');
    await page.fill('#patient_search', patientData.identification_number);
    await page.getByRole('button', { name: new RegExp(patientData.first_name) }).click();

    const serviceOptions = await page.locator('#service_id option').evaluateAll((options) => options.map((option) => ({
      value: option.value,
      text: option.textContent?.trim() ?? '',
      price: option.getAttribute('data-price') ?? '0',
    })));
    const selectedService = serviceOptions.find((option) => option.text.includes('Consulta general E2E'))
      ?? serviceOptions.find((option) => option.value);
    expect(selectedService).toBeTruthy();
    const firstServiceValue = selectedService.value;
    const expectedServiceName = selectedService.text.split(' - $')[0];
    const selectedServicePrice = selectedService.text.match(/\$([0-9]+(?:\.[0-9]{2})?)/)?.[1] ?? selectedService.price;
    const expectedAmount = `$${Number(selectedServicePrice).toFixed(2)}`;
    await page.selectOption('#service_id', firstServiceValue);

    await page.fill('#doctor_search', 'Medico E2E');
    await page.getByRole('button', { name: /Medico E2E|M.dico E2E/i }).click();

    await page.selectOption('#service_id', '');
    await expect(page.locator('#doctor_search')).toBeDisabled();
    await expect(page.locator('#doctor_id')).toHaveValue('');
    await expect(page.locator('#start_time')).toHaveValue('');
    await expect(page.getByText(/Seleccione primero un servicio para ver m.dicos compatibles/i).first()).toBeVisible();

    await page.selectOption('#service_id', firstServiceValue);
    await page.fill('#doctor_search', 'Medico E2E');
    await page.getByRole('button', { name: /Medico E2E|M.dico E2E/i }).click();

    await page.fill('#appointment_date', appointmentDate);
    const firstAvailableSlot = page.getByRole('button', { name: /^\d{2}:\d{2}$/ }).first();
    await expect(firstAvailableSlot).toBeVisible({ timeout: 20_000 });
    const selectedSlot = (await firstAvailableSlot.textContent()).trim();
    await page.fill('textarea[name="reason"]', appointmentData.reason);
    await page.fill('textarea[name="notes"]', appointmentData.notes);
    await page.getByRole('button', { name: selectedSlot, exact: true }).click();
    await expect(page.locator('#start_time')).toHaveValue(selectedSlot);
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/appointments/);
    await page.goto('/daily-agenda?date=' + appointmentDate + '&search=' + encodeURIComponent(patientData.first_name));
    await expect(page.getByRole('heading', { name: /Agenda del d.a/i })).toBeVisible();
    await expect(page.getByText(patientData.first_name).first()).toBeVisible();
    await page.goto('/appointments/create');
    await page.fill('#patient_search', patientData.identification_number);
    await page.getByRole('button', { name: new RegExp(patientData.first_name) }).click();
    await page.selectOption('#service_id', firstServiceValue);
    await page.fill('#doctor_search', 'Medico E2E');
    await page.getByRole('button', { name: /Medico E2E|M.dico E2E/i }).click();
    await page.fill('#appointment_date', appointmentDate);
    await expect(page.getByRole('button', { name: selectedSlot })).toHaveCount(0);
    await logout(page);

    await loginAs(page, 'cash');
    await page.goto('/daily-agenda?date=' + appointmentDate + '&search=' + encodeURIComponent(patientData.first_name));
    await expect(page.getByRole('heading', { name: /Agenda del d.a/i })).toBeVisible();
    await expect(page.getByText(patientData.first_name).first()).toBeVisible();

    await page.goto('/payments');
    await expect(page.getByRole('heading', { name: /Pagos y Finanzas/i })).toBeVisible();
    const paymentRow = page.locator('tr', { hasText: patientData.first_name }).first();
    await expect(paymentRow).toBeVisible();
    await paymentRow.getByRole('link', { name: 'Cobrar' }).click();

    await expect(page.getByRole('heading', { name: /Editar pago/i })).toBeVisible();
    await expect(page.locator('#payment_date')).toHaveValue('');
    await page.selectOption('#payment_method', 'card');
    await page.selectOption('#payment_status', 'paid');
    await expect(page.locator('#payment_date')).not.toHaveValue('');
    await page.click('button[type="submit"]');

    await expect(page.getByRole('heading', { name: /REC-/i })).toBeVisible();
    await expect(page.getByText('Ficha de pago').first()).toBeVisible();
    await expect(page.getByText(patientData.first_name).first()).toBeVisible();
    await expect(page.getByText(expectedServiceName).first()).toBeVisible();
    await expect(page.getByText('Pagado').first()).toBeVisible();
    await expect(page.getByText(expectedAmount).first()).toBeVisible();
    await expect(page.getByText('Sin fecha registrada')).toHaveCount(0);

    const paymentId = page.url().match(/\/payments\/(\d+)/)?.[1];
    await expect(page.getByRole('link', { name: 'Descargar recibo PDF' })).toBeVisible();
    await page.getByRole('link', { name: 'Imprimir recibo' }).click();
    await expect(page.getByText('RECIBO DE PAGO')).toBeVisible();
    await expect(page.getByText(patientData.first_name).first()).toBeVisible();
    await expect(page.getByText(expectedAmount).first()).toBeVisible();
    await logout(page);

    await loginAs(page, 'doctor');
    const doctorReceiptResponse = await page.goto(`/payments/${paymentId}/receipt/print`);
    expect(doctorReceiptResponse.status()).toBe(403);
    await page.goto('/daily-agenda?date=' + appointmentDate + '&search=' + encodeURIComponent(patientData.first_name));
    await expect(page.getByRole('heading', { name: /Agenda del d.a/i })).toBeVisible();
    await expect(page.getByText('No puede iniciar consulta hasta que caja registre el pago.')).toHaveCount(0);
    await expect(page.locator('td', { hasText: 'Pagado' }).getByText('Pagado')).toHaveCount(1);
    await logout(page);

    await loginAs(page, 'admin');
    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: /Dashboard/i })).toBeVisible();
    await logout(page);
  });
});
