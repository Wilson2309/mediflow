import { test, expect } from '@playwright/test';
import { loginAs, logout } from './helpers/auth.js';

test.describe('Daily Agenda', () => {

  test('Admin sees daily agenda', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/daily-agenda');
    await expect(page.getByRole('heading', { name: /Agenda del d/i })).toBeVisible();
    // Ensure the table or list is present
    await expect(page.locator('text=Estado de pago')).toBeVisible();
    await logout(page);
  });

  test('Doctor sees their daily agenda', async ({ page }) => {
    await loginAs(page, 'doctor');
    await page.goto('/daily-agenda');
    await expect(page.getByRole('heading', { name: /Agenda del d/i })).toBeVisible();
    await logout(page);
  });

});
