// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect } from '@playwright/test';
import { loginAsUser, logout, signUp } from '../helpers/auth';
import { testUsers, testTenant, generateTestData, selectors } from '../helpers/fixtures';

test.describe('Authentication Flows', () => {
  test.beforeEach(async ({ page }) => {
    // Clear cookies and local storage before each test
    await page.context().clearCookies();
    await page.goto('/');
  });

  test('should display login page @smoke @critical', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/login`);

    await expect(page.locator(selectors.loginForm)).toBeVisible();
    await expect(page.locator(selectors.emailInput)).toBeVisible();
    await expect(page.locator(selectors.passwordInput)).toBeVisible();
    await expect(page.locator(selectors.submitButton)).toBeVisible();
  });

  test('should login with valid credentials @smoke @critical', async ({ page }) => {
    await loginAsUser(page, testUsers.primary.email, testUsers.primary.password, testTenant.slug);

    // Verify successful login
    await expect(page).toHaveURL(new RegExp(`/${testTenant.slug}/dashboard`));

    // Verify user menu is visible
    await expect(page.locator('[data-testid="user-menu"], .user-avatar, button:has-text("Profile")')).toBeVisible();
  });

  test('should show error with invalid credentials @critical', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/login`);

    await page.fill(selectors.emailInput, 'invalid@example.com');
    await page.fill(selectors.passwordInput, 'wrongpassword');
    await page.click(selectors.submitButton);

    // Wait for error message
    await expect(page.locator('text=/invalid credentials|login failed|incorrect/i')).toBeVisible({ timeout: 5000 });

    // Should still be on login page
    await expect(page).toHaveURL(new RegExp('/login'));
  });

  test('should logout successfully @smoke @critical', async ({ page }) => {
    // Login first
    await loginAsUser(page, testUsers.primary.email, testUsers.primary.password, testTenant.slug);

    // Logout
    await logout(page);

    // Verify redirect to home or login
    await expect(page).toHaveURL(/\/(login|$|hour-timebank\/?$)/);

    // Verify user menu is not visible
    await expect(page.locator('[data-testid="user-menu"], .user-avatar')).not.toBeVisible();
  });

  test('should display registration page @smoke', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/register`);

    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[name="firstName"], input[name="first_name"]')).toBeVisible();
    await expect(page.locator('input[name="lastName"], input[name="last_name"]')).toBeVisible();
    await expect(page.locator(selectors.emailInput)).toBeVisible();
    await expect(page.locator(selectors.passwordInput)).toBeVisible();
  });

  test('should register new user @critical', async ({ page }) => {
    const newUser = generateTestData().user;

    await signUp(page, newUser, testTenant.slug);

    // Verify successful registration (redirect to dashboard or onboarding)
    await expect(page).toHaveURL(/\/(dashboard|onboarding)/);
  });

  test('should show validation errors for empty fields @regression', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/login`);

    // Try to submit empty form
    await page.click(selectors.submitButton);

    // Check for HTML5 validation or custom error messages
    const emailInput = page.locator(selectors.emailInput);
    const isInvalid = await emailInput.evaluate((el: HTMLInputElement) => !el.validity.valid);

    expect(isInvalid).toBeTruthy();
  });

  test('should redirect to login when accessing protected route @critical', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/dashboard`);

    // Should redirect to login
    await expect(page).toHaveURL(new RegExp('/login'), { timeout: 5000 });
  });

  test('should display password reset link @smoke', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/login`);

    // Look for forgot password link
    const forgotPasswordLink = page.locator('a:has-text("Forgot"), a:has-text("Reset")');
    await expect(forgotPasswordLink).toBeVisible();
  });

  test('should navigate to registration from login @regression', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/login`);

    // Click register link
    await page.click('a:has-text("Sign up"), a:has-text("Register"), a:has-text("Create account")');

    // Should navigate to registration page
    await expect(page).toHaveURL(new RegExp('/register'));
  });
});
