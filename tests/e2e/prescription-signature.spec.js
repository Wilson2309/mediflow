import { test, expect } from '@playwright/test';
import { loginAs, logout } from './helpers/auth.js';

test.describe('Prescription Signature Flow', () => {

  test('Doctor can view prescriptions', async ({ page }) => {
    await loginAs(page, 'doctor');
    await page.goto('/prescriptions');
    await expect(page.getByRole('heading', { name: /Recetas/i })).toBeVisible();
    await logout(page);
  });

});
