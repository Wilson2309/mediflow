import { test, expect } from '@playwright/test';
import { loginAs, logout } from './helpers/auth.js';

const currentDate = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Guayaquil' }).format(new Date());

test.describe('Reports by role', () => {
  test('Cashier only sees financial reports, exports CSV and Excel, and can open financial audit', async ({ page }) => {
    await loginAs(page, 'cash');

    await page.goto('/dashboard');
    await expect(page.getByText(/siguiente fase|proxima fase/i)).toHaveCount(0);
    await expect(page.locator('main a[href$="/financial-audit"]')).toBeVisible();
    await expect(page.locator('a[href$="/audit-logs"]')).toHaveCount(0);

    await page.goto('/reports');
    await expect(page).toHaveURL(/\/reports\/financial/);
    await expect(page.getByRole('heading', { name: /Reporte financiero/i })).toBeVisible();

    await expect(page.locator('nav[aria-label="Secciones de reportes"] a[href$="/reports/financial"]')).toBeVisible();
    await expect(page.locator('nav[aria-label="Secciones de reportes"] a[href$="/reports/clinical"]')).toHaveCount(0);
    await expect(page.locator('nav[aria-label="Secciones de reportes"] a[href$="/reports/patients"]')).toHaveCount(0);
    await expect(page.locator('nav[aria-label="Secciones de reportes"] a[href$="/reports/doctors"]')).toHaveCount(0);
    await expect(page.locator('nav[aria-label="Secciones de reportes"] a[href$="/reports"]')).toHaveCount(0);

    const today = currentDate();
    await page.fill('#start_date', today);
    await page.fill('#end_date', today);
    await page.getByRole('button', { name: 'Aplicar filtros' }).click();
    await expect(page).toHaveURL(/start_date=/);

    const pdfLink = page.getByRole('link', { name: 'Exportar PDF' });
    const csvLink = page.getByRole('link', { name: 'Exportar CSV' });
    const excelLink = page.getByRole('link', { name: 'Exportar Excel' });
    const printLink = page.getByRole('link', { name: 'Imprimir' });
    await expect(pdfLink).toBeVisible();
    await expect(csvLink).toBeVisible();
    await expect(excelLink).toBeVisible();
    await expect(printLink).toBeVisible();
    await expect(csvLink).toHaveAttribute('href', /\/reports\/financial\/export\/csv/);
    await expect(excelLink).toHaveAttribute('href', /\/reports\/financial\/export\/xlsx/);

    const [download] = await Promise.all([
      page.waitForEvent('download'),
      csvLink.click(),
    ]);
    expect(download.suggestedFilename()).toMatch(/^reporte-financiero-\d{4}-\d{2}-\d{2}\.csv$/);

    const [excelDownload] = await Promise.all([
      page.waitForEvent('download'),
      excelLink.click(),
    ]);
    expect(excelDownload.suggestedFilename()).toMatch(/^reporte-financiero-[a-z0-9-]+-\d{4}-\d{2}-\d{2}\.xlsx$/);

    await printLink.click();
    await expect(page.getByText('Reporte financiero').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Imprimir' })).toBeVisible();

    await page.goto('/financial-audit');
    await expect(page.getByRole('heading', { name: /Registro de caja/i })).toBeVisible();
    await expect(page.getByText(/Auditoria financiera/i)).toBeVisible();
    await expect(page.locator('a[href$="/audit-logs"]')).toHaveCount(0);

    await logout(page);
  });

  test('Admin sees every report section and global audit remains separate', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/reports');

    await expect(page.getByRole('heading', { name: /Resumen ejecutivo/i })).toBeVisible();
    for (const href of ['/reports', '/reports/appointments', '/reports/clinical', '/reports/financial', '/reports/patients', '/reports/doctors', '/reports/services']) {
      await expect(page.locator(`nav[aria-label="Secciones de reportes"] a[href$="${href}"]`)).toBeVisible();
    }

    await page.goto('/financial-audit');
    await expect(page.getByRole('heading', { name: /Registro de caja/i })).toBeVisible();
    await page.goto('/audit-logs');
    await expect(page.getByRole('heading', { name: /Auditor/i })).toBeVisible();
    await logout(page);
  });

  test('Doctor can export own appointment reports but cannot open financial reports or audit', async ({ page }) => {
    await loginAs(page, 'doctor');

    await page.goto('/reports');
    await expect(page).toHaveURL(/\/reports\/appointments/);
    await expect(page.getByRole('heading', { name: /Reporte de citas/i })).toBeVisible();
    await expect(page.locator('nav[aria-label="Secciones de reportes"] a[href$="/reports/financial"]')).toHaveCount(0);
    await expect(page.locator('a[href*="/reports/financial/export"]')).toHaveCount(0);

    const doctorFilterOptions = page.locator('#doctor_id option');
    await expect(doctorFilterOptions).toHaveCount(2);

    const appointmentExcelLink = page.getByRole('link', { name: 'Exportar Excel' });
    await expect(appointmentExcelLink).toBeVisible();
    await expect(appointmentExcelLink).toHaveAttribute('href', /\/reports\/appointments\/export\/xlsx/);

    const [appointmentExcelDownload] = await Promise.all([
      page.waitForEvent('download'),
      appointmentExcelLink.click(),
    ]);
    expect(appointmentExcelDownload.suggestedFilename()).toMatch(/^reporte-citas-\d{4}-\d{2}-\d{2}\.xlsx$/);

    const financialResponse = await page.goto('/reports/financial');
    expect(financialResponse?.status()).toBe(403);

    const xlsxResponse = await page.goto('/reports/financial/export/xlsx');
    expect(xlsxResponse?.status()).toBe(403);

    const auditResponse = await page.goto('/financial-audit');
    expect(auditResponse?.status()).toBe(403);

    await logout(page);
  });
});
