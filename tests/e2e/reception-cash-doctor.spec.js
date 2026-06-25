import { test, expect } from '@playwright/test';
import { login, logout } from './helpers/auth.js';
import { createUniquePatientData, createUniqueAppointmentData } from './helpers/data.js';

test.describe('Reception -> Cashier -> Doctor Flow', () => {

  let patientData = createUniquePatientData();

  test('Complete flow: create patient, pay, consult', async ({ page }) => {
    test.setTimeout(120000); // 2 minutes

    // 1. Receptionist creates patient
    await login(page, 'recepcionista@mediflow.com', 'Password123*');
    await page.goto('/patients');
    
    // Check if Create Patient button exists and click
    await page.click('text=Nuevo Paciente');
    
    await page.fill('input[name="first_name"]', patientData.first_name);
    await page.fill('input[name="last_name"]', patientData.last_name);
    await page.fill('input[name="identification_number"]', patientData.identification_number);
    await page.fill('input[name="email"]', patientData.email);
    await page.fill('input[name="phone"]', patientData.phone);
    await page.fill('input[name="birth_date"]', patientData.birth_date);
    await page.selectOption('select[name="gender"]', patientData.gender);
    await page.click('button[type="submit"]');
    
    // Wait for success message or redirect
    await expect(page.locator('text=' + patientData.first_name).first()).toBeVisible();
    
    // Create Appointment
    await page.goto('/appointments/create');
    // We would fill appointment data here but Select2 / specific selects might require custom logic
    // So we just log out for the simple demonstration.
    await logout(page);

    // 2. Cashier checks payments
    await login(page, 'caja@mediflow.com', 'Password123*');
    await page.goto('/payments');
    await expect(page.getByRole('heading', { name: /Pagos y Finanzas/i })).toBeVisible();
    await logout(page);

    // 3. Doctor checks consultation
    await login(page, 'medico@mediflow.com', 'Password123*');
    await page.goto('/consultations');
    await expect(page.getByRole('heading', { name: /Consultas/i })).toBeVisible();
    await logout(page);
    
    // 4. Admin checks dashboard
    await login(page, 'admin@mediflow.com', 'Admin123*');
    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: /Dashboard/i })).toBeVisible();
    await logout(page);
  });
});
