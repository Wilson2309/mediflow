import { mockConnectionHealth } from './connection.js';
import { e2eRole } from './roles.js';

/**
 * Logs in a user.
 * @param {import('@playwright/test').Page} page
 * @param {string} email
 * @param {string} password
 * @param {string|RegExp} [expectedUrl]
 */
export async function login(page, email, password, expectedUrl = '**/dashboard') {
  await mockConnectionHealth(page);
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await Promise.all([
    page.waitForURL(expectedUrl),
    page.click('button[type="submit"]'),
  ]);
  await page.waitForLoadState('domcontentloaded');
}

/**
 * Logs in with centrally configured E2E credentials for a supported role.
 * @param {import('@playwright/test').Page} page
 * @param {string} role
 */
export async function loginAs(page, role) {
  const { email, password, expectedUrl } = e2eRole(role);

  await login(page, email, password, expectedUrl);
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