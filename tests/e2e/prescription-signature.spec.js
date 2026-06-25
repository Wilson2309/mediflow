import { test, expect } from '@playwright/test';
import { login, logout } from './helpers/auth.js';

test.describe('Prescription Signature Flow', () => {

  test('Doctor can view prescriptions', async ({ page }) => {
    await login(page, 'medico@mediflow.com', 'Password123*');
    await page.goto('/prescriptions');
    await expect(page.getByRole('heading', { name: /Recetas/i })).toBeVisible();
    await logout(page);
  });

});
