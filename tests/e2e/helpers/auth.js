import { mockConnectionHealth } from './connection.js';

/**
 * Logs in a user.
 * @param {import('@playwright/test').Page} page
 * @param {string} email
 * @param {string} password
 */
export async function login(page, email, password) {
  await mockConnectionHealth(page);
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  // Wait for navigation or a specific element that shows login success
  await page.waitForURL('**/dashboard*');
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Logs out the current user.
 * @param {import('@playwright/test').Page} page
 */
export async function logout(page) {
  // Use Playwright's native context clearing for a 100% robust logout
  await page.context().clearCookies();
  await page.goto('/login');
}
