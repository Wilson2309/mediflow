import { test, expect } from '@playwright/test';
import { login, logout } from './helpers/auth.js';
import { createUniquePatientData, createUniqueAppointmentData } from './helpers/data.js';

test.describe('Reception -> Cashier -> Doctor Flow', () => {
  test('Complete flow: create patient, pay, consult', async ({ page }) => {
    test.setTimeout(120000);

    const patientData = createUniquePatientData();
    const appointmentData = createUniqueAppointmentData();
    const futureDate = new Date(Date.now() + (90 + Math.floor(Math.random() * 90)) * 24 * 60 * 60 * 1000);
    const appointmentDate = futureDate.toISOString().slice(0, 10);

    await login(page, 'recepcionista@mediflow.com', 'Password123*');
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

    await expect(page.locator('text=' + patientData.first_name).first()).toBeVisible();

    await page.goto('/appointments/create');
    await page.fill('#patient_search', patientData.identification_number);
    await page.getByRole('button', { name: new RegExp(patientData.first_name) }).click();

    const firstServiceValue = await page.locator('#service_id option').nth(1).getAttribute('value');
    await page.selectOption('#service_id', firstServiceValue);

    await page.fill('#doctor_search', 'Medico E2E');
    await page.getByRole('button', { name: /Medico E2E|Médico E2E/i }).click();

    await page.selectOption('#service_id', '');
    await expect(page.locator('#doctor_search')).toBeDisabled();
    await expect(page.locator('#doctor_id')).toHaveValue('');
    await expect(page.locator('#start_time')).toHaveValue('');
    await expect(page.getByText('Seleccione primero un servicio para ver médicos compatibles.').first()).toBeVisible();

    await page.selectOption('#service_id', firstServiceValue);
    await page.fill('#doctor_search', 'Medico E2E');
    await page.getByRole('button', { name: /Medico E2E|Médico E2E/i }).click();

    await page.fill('#appointment_date', appointmentDate);
    await expect(page.getByRole('button', { name: '08:00' })).toBeVisible();
    await page.getByRole('button', { name: '08:00' }).click();
    await expect(page.locator('#start_time')).toHaveValue('08:00');
    await page.fill('textarea[name="reason"]', appointmentData.reason);
    await page.fill('textarea[name="notes"]', appointmentData.notes);
    await page.click('button[type="submit"]');

    await expect(page).toHaveURL(/\/appointments/);
    await expect(page.getByText(patientData.first_name).first()).toBeVisible();
    await expect(page.getByText(appointmentData.reason).first()).toBeVisible();

    await page.goto('/appointments/create');
    await page.fill('#patient_search', patientData.identification_number);
    await page.getByRole('button', { name: new RegExp(patientData.first_name) }).click();
    await page.selectOption('#service_id', firstServiceValue);
    await page.fill('#doctor_search', 'Medico E2E');
    await page.getByRole('button', { name: /Medico E2E|Médico E2E/i }).click();
    await page.fill('#appointment_date', appointmentDate);
    await expect(page.getByRole('button', { name: '08:00' })).toHaveCount(0);
    await logout(page);

    await login(page, 'caja@mediflow.com', 'Password123*');
    await page.goto('/payments');
    await expect(page.getByRole('heading', { name: /Pagos y Finanzas/i })).toBeVisible();
    await logout(page);

    await login(page, 'medico@mediflow.com', 'Password123*');
    await page.goto('/daily-agenda?date=' + appointmentDate + '&search=' + encodeURIComponent(patientData.first_name));
    await expect(page.getByRole('heading', { name: /Agenda del d/i })).toBeVisible();
    await expect(page.getByText('No puede iniciar consulta hasta que caja registre el pago.')).toHaveCount(1);
    await expect(page.locator('td', { hasText: 'Pendiente de pago' }).getByText('Pendiente de pago')).toHaveCount(1);
    await logout(page);

    await login(page, 'admin@mediflow.com', 'Admin123*');
    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: /Dashboard/i })).toBeVisible();
    await logout(page);
  });
});
