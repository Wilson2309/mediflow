import { test, expect } from '@playwright/test';
import { login, logout } from './helpers/auth.js';

test.describe('Role Based Permissions', () => {

  test('Doctor cannot access new patient and payment routes', async ({ page }) => {
    await login(page, 'medico@mediflow.com', 'Password123*');
    
    // Attempt to access patients creation
    const response = await page.goto('/patients/create');
    expect(response?.status()).toBe(403);
    
    // Attempt to access payments
    const payResponse = await page.goto('/payments');
    expect(payResponse?.status()).toBe(403);

    await logout(page);
  });

  test('Cashier cannot access consultations', async ({ page }) => {
    await login(page, 'caja@mediflow.com', 'Password123*');
    
    const response = await page.goto('/consultations');
    expect(response?.status()).toBe(403);

    await logout(page);
  });

  test('Receptionist cannot access prescriptions', async ({ page }) => {
    await login(page, 'recepcionista@mediflow.com', 'Password123*');
    
    const response = await page.goto('/prescriptions');
    expect(response?.status()).toBe(403);

    await logout(page);
  });

  test('Unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/.*login/);
  });

});
