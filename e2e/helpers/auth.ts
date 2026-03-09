// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Page, expect } from '@playwright/test';

/**
 * Login helper - authenticates a user via the login form
 */
export async function loginAsUser(
  page: Page,
  email: string,
  password: string,
  tenantSlug: string = process.env.E2E_TENANT_SLUG || 'hour-timebank'
): Promise<void> {
  await page.goto(`/${tenantSlug}/login`);

  // Wait for login form to be visible
  await page.waitForSelector('form', { state: 'visible' });

  // Fill in credentials
  await page.fill('input[name="email"], input[type="email"]', email);
  await page.fill('input[name="password"], input[type="password"]', password);

  // Submit form
  await page.click('button[type="submit"]');

  // Wait for redirect to dashboard
  await page.waitForURL(`**/${tenantSlug}/dashboard`, { timeout: 10000 });

  // Verify we're logged in by checking for user menu or avatar
  await expect(page.locator('[data-testid="user-menu"], .user-avatar, button:has-text("Profile")')).toBeVisible({ timeout: 5000 });
}

/**
 * Logout helper - logs out the current user
 */
export async function logout(page: Page): Promise<void> {
  // Click user menu/avatar
  const userMenu = page.locator('[data-testid="user-menu"], .user-avatar, button:has-text("Profile")').first();
  await userMenu.click();

  // Click logout button
  await page.click('button:has-text("Logout"), a:has-text("Logout")');

  // Wait for redirect to home or login
  await page.waitForURL(/\/(login|$)/, { timeout: 5000 });
}

/**
 * Sign up helper - creates a new user account
 */
export async function signUp(
  page: Page,
  userData: {
    email: string;
    password: string;
    firstName: string;
    lastName: string;
  },
  tenantSlug: string = process.env.E2E_TENANT_SLUG || 'hour-timebank'
): Promise<void> {
  await page.goto(`/${tenantSlug}/register`);

  // Wait for registration form
  await page.waitForSelector('form', { state: 'visible' });

  // Fill in registration details
  await page.fill('input[name="firstName"], input[name="first_name"]', userData.firstName);
  await page.fill('input[name="lastName"], input[name="last_name"]', userData.lastName);
  await page.fill('input[name="email"], input[type="email"]', userData.email);
  await page.fill('input[name="password"], input[type="password"]', userData.password);

  // Accept terms if checkbox exists
  const termsCheckbox = page.locator('input[type="checkbox"][name*="terms"], input[type="checkbox"][name*="agree"]');
  if (await termsCheckbox.isVisible()) {
    await termsCheckbox.check();
  }

  // Submit form
  await page.click('button[type="submit"]');

  // Wait for successful registration (redirect to dashboard or onboarding)
  await page.waitForURL(/\/(dashboard|onboarding)/, { timeout: 10000 });
}

/**
 * Check if user is logged in
 */
export async function isLoggedIn(page: Page): Promise<boolean> {
  try {
    await expect(page.locator('[data-testid="user-menu"], .user-avatar, button:has-text("Profile")')).toBeVisible({ timeout: 3000 });
    return true;
  } catch {
    return false;
  }
}

/**
 * Ensure user is logged in (login if not already)
 */
export async function ensureLoggedIn(
  page: Page,
  email: string = process.env.E2E_TEST_USER_EMAIL || '',
  password: string = process.env.E2E_TEST_USER_PASSWORD || ''
): Promise<void> {
  if (!(await isLoggedIn(page))) {
    await loginAsUser(page, email, password);
  }
}
