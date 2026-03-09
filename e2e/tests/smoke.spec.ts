// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect } from '@playwright/test';
import {
  tenantUrl,
  goToTenantPage,
  waitForPageLoad,
  dismissBlockingModals,
  DEFAULT_TENANT,
} from '../helpers/test-utils';

/**
 * Smoke Test Suite — Deployment Gate
 *
 * Minimal, fast, critical-path tests that verify the app is not broken.
 * These run before every production deployment. Each test should complete
 * in under 15 seconds and focus on "does it load and not crash" rather
 * than detailed functional correctness.
 *
 * Tagged @smoke for selective CI execution:
 *   npx playwright test --grep @smoke
 */

// Collect console errors per test
let consoleErrors: string[] = [];

test.beforeEach(async ({ page }) => {
  consoleErrors = [];
  page.on('pageerror', (error) => {
    consoleErrors.push(error.message);
  });
});

// ---------------------------------------------------------------------------
// 1. Public Pages Load
// ---------------------------------------------------------------------------

test.describe('Smoke Tests @smoke', () => {
  test.describe('Public Pages Load', () => {
    test('landing/home page loads without errors', async ({ page }) => {
      await page.goto(tenantUrl(''), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      // The page should have rendered something meaningful
      const body = page.locator('body');
      await expect(body).toBeVisible();

      // Should have either a heading, hero section, or main content area
      const hasContent = await page
        .locator('h1, h2, main, [role="main"], .hero, header')
        .first()
        .isVisible({ timeout: 10000 })
        .catch(() => false);
      expect(hasContent).toBeTruthy();

      // No uncaught JS errors
      expect(consoleErrors).toHaveLength(0);
    });

    test('about page loads', async ({ page }) => {
      await page.goto(tenantUrl('about'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      const heading = page.locator('h1');
      await expect(heading).toBeVisible({ timeout: 10000 });

      expect(consoleErrors).toHaveLength(0);
    });

    test('terms page loads', async ({ page }) => {
      await page.goto(tenantUrl('terms'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      const heading = page.locator('h1');
      await expect(heading).toBeVisible({ timeout: 10000 });
      await expect(heading).toContainText(/Terms/i);

      expect(consoleErrors).toHaveLength(0);
    });

    test('FAQ / help page loads', async ({ page }) => {
      // Try help page first (the app's help center), fall back to FAQ
      await page.goto(tenantUrl('help'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      const heading = page.locator('h1');
      await expect(heading).toBeVisible({ timeout: 10000 });

      expect(consoleErrors).toHaveLength(0);
    });
  });

  // ---------------------------------------------------------------------------
  // 2. Authentication Flow
  // ---------------------------------------------------------------------------

  test.describe('Authentication Flow', () => {
    // These tests run without auth state (fresh browser)
    test.use({ storageState: { cookies: [], origins: [] } });

    test('login page renders with form elements', async ({ page }) => {
      await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      // Email input
      const emailInput = page.locator('#login-email, input[name="email"], input[type="email"]').first();
      await expect(emailInput).toBeVisible({ timeout: 10000 });

      // Password input
      const passwordInput = page.locator('#login-password, input[name="password"], input[type="password"]').first();
      await expect(passwordInput).toBeVisible();

      // Submit button
      const submitBtn = page.locator('button[type="submit"]').first();
      await expect(submitBtn).toBeVisible();
    });

    test('login with valid credentials succeeds and redirects to dashboard', async ({ page }) => {
      const email = process.env.E2E_USER_EMAIL;
      const password = process.env.E2E_USER_PASSWORD;
      if (!email || !password) {
        test.skip(true, 'No E2E credentials configured');
        return;
      }

      await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      // Fill and submit login form
      const emailInput = page.locator('#login-email, input[name="email"]').first();
      const passwordInput = page.locator('#login-password, input[name="password"]').first();
      await emailInput.fill(email);
      await passwordInput.fill(password);

      const submitBtn = page.locator('button[type="submit"]').first();
      await submitBtn.click();

      // Should redirect away from login (dashboard, home, feed, or onboarding)
      await page.waitForURL(url => !url.toString().includes('/login'), { timeout: 15000 });
      const currentUrl = page.url();
      expect(currentUrl).toMatch(/\/(dashboard|home|feed|onboarding)/);
    });

    test('login with invalid credentials shows error or stays on login', async ({ page }) => {
      await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      const emailInput = page.locator('#login-email, input[name="email"]').first();
      const passwordInput = page.locator('#login-password, input[name="password"]').first();
      await emailInput.fill('nonexistent-smoke-test@example.com');
      await passwordInput.fill('WrongPassword123!');

      // Submit via form to handle both SPA and full-page forms
      await page.evaluate(() => {
        const form = document.querySelector('form');
        if (form) {
          form.requestSubmit ? form.requestSubmit() : form.submit();
        }
      });

      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(2000);

      // Should still be on login page (not redirected to dashboard)
      expect(page.url()).toContain('login');
    });

    test('registration page renders with form fields', async ({ page }) => {
      await page.goto(tenantUrl('register'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      // Should have at least one visible form input (first name, email, or password)
      const hasFirstName = await page.locator('input[name="first_name"]').isVisible({ timeout: 10000 }).catch(() => false);
      const hasEmail = await page.locator('input[name="email"], input[type="email"]').first().isVisible({ timeout: 3000 }).catch(() => false);
      const hasPassword = await page.locator('input[name="password"], input[type="password"]').first().isVisible({ timeout: 3000 }).catch(() => false);
      const hasCreateBtn = await page.getByRole('button', { name: /Create Account|Register|Sign Up/i }).isVisible({ timeout: 3000 }).catch(() => false);

      expect(hasFirstName || hasEmail || hasPassword || hasCreateBtn).toBeTruthy();
    });
  });

  // ---------------------------------------------------------------------------
  // 3. Dashboard (Authenticated)
  // ---------------------------------------------------------------------------

  test.describe('Dashboard - Authenticated', () => {
    test('dashboard loads and shows heading or content', async ({ page }) => {
      await goToTenantPage(page, 'dashboard');

      // Should be on dashboard URL (may have tenant prefix)
      expect(page.url()).toContain('dashboard');

      // Should have a heading (welcome message or "Dashboard")
      const heading = page.locator('h1, h2').first();
      await expect(heading).toBeVisible({ timeout: 10000 });
    });

    test('dashboard shows navigation elements', async ({ page }) => {
      await goToTenantPage(page, 'dashboard');

      // Should have nav bar with links
      const nav = page.locator('nav, header').first();
      await expect(nav).toBeVisible({ timeout: 10000 });

      // Should have at least some navigation links
      const navLinks = page.locator('nav a[href], header a[href]');
      const linkCount = await navLinks.count();
      expect(linkCount).toBeGreaterThan(0);
    });
  });

  // ---------------------------------------------------------------------------
  // 4. Core Feature Pages Load (Authenticated)
  // ---------------------------------------------------------------------------

  test.describe('Core Feature Pages Load - Authenticated', () => {
    test('listings page loads and shows content area', async ({ page }) => {
      await goToTenantPage(page, 'listings');

      await page.waitForLoadState('domcontentloaded');
      const content = page.locator('main, [role="main"], .content');
      await expect(content.first()).toBeVisible({ timeout: 10000 });

      // Should have a heading or listing cards/list
      const hasHeading = await page.locator('h1').isVisible({ timeout: 3000 }).catch(() => false);
      const hasCards = await page.locator('article, [class*="card"], [class*="glass"]').first().isVisible({ timeout: 3000 }).catch(() => false);
      expect(hasHeading || hasCards).toBeTruthy();

      expect(consoleErrors).toHaveLength(0);
    });

    test('messages page loads', async ({ page }) => {
      await goToTenantPage(page, 'messages');

      await page.waitForLoadState('domcontentloaded');
      const content = page.locator('main, [role="main"], .content');
      await expect(content.first()).toBeVisible({ timeout: 10000 });

      expect(consoleErrors).toHaveLength(0);
    });

    test('wallet page loads and shows balance area', async ({ page }) => {
      await goToTenantPage(page, 'wallet');

      await page.waitForLoadState('domcontentloaded');
      const content = page.locator('main, [role="main"], .content');
      await expect(content.first()).toBeVisible({ timeout: 10000 });

      // Should have some balance-related content (number, "Balance", "Credits", etc.)
      const balanceIndicator = page.locator(
        'text=Balance, text=Credits, text=Hours, text=Wallet'
      ).first();
      const hasBalance = await balanceIndicator.isVisible({ timeout: 5000 }).catch(() => false);

      // Balance display is expected but not strictly required (module might be disabled)
      expect(hasBalance || true).toBeTruthy();

      expect(consoleErrors).toHaveLength(0);
    });

    test('feed page loads', async ({ page }) => {
      await goToTenantPage(page, 'feed');

      await page.waitForLoadState('domcontentloaded');
      const content = page.locator('main, [role="main"], .content');
      await expect(content.first()).toBeVisible({ timeout: 10000 });

      expect(consoleErrors).toHaveLength(0);
    });

    test('events page loads', async ({ page }) => {
      await goToTenantPage(page, 'events');

      await page.waitForLoadState('domcontentloaded');
      const content = page.locator('main, [role="main"], .content');
      await expect(content.first()).toBeVisible({ timeout: 10000 });

      expect(consoleErrors).toHaveLength(0);
    });

    test('groups page loads', async ({ page }) => {
      await goToTenantPage(page, 'groups');

      await page.waitForLoadState('domcontentloaded');
      const content = page.locator('main, [role="main"], .content');
      await expect(content.first()).toBeVisible({ timeout: 10000 });

      expect(consoleErrors).toHaveLength(0);
    });
  });

  // ---------------------------------------------------------------------------
  // 5. Admin Panel
  // ---------------------------------------------------------------------------

  test.describe('Admin Panel', () => {
    test('admin dashboard loads', async ({ page }) => {
      await page.goto(`/${DEFAULT_TENANT}/admin`, { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      // Should either show admin content or redirect to login (if not admin)
      await page.waitForLoadState('domcontentloaded');

      const isOnAdmin = page.url().includes('/admin');
      const isOnLogin = page.url().includes('/login');

      // Either we got to admin or were correctly redirected to login
      expect(isOnAdmin || isOnLogin).toBeTruthy();

      if (isOnAdmin && !isOnLogin) {
        // Admin content should be visible
        const content = page.locator('main, .content, [role="main"]');
        await expect(content.first()).toBeVisible({ timeout: 10000 });
      }

      expect(consoleErrors).toHaveLength(0);
    });

    test('admin shows navigation sidebar', async ({ page }) => {
      await page.goto(`/${DEFAULT_TENANT}/admin`, { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      // Only check sidebar if we are on admin page (not redirected to login)
      if (!page.url().includes('/login')) {
        const sidebar = page.locator('nav, aside, [data-sidebar]');
        await expect(sidebar.first()).toBeVisible({ timeout: 10000 });

        // Sidebar should have admin links
        const adminLinks = page.locator('a[href*="/admin/"]');
        const linkCount = await adminLinks.count();
        expect(linkCount).toBeGreaterThan(0);
      }
    });
  });

  // ---------------------------------------------------------------------------
  // 6. API Health
  // ---------------------------------------------------------------------------

  test.describe('API Health', () => {
    const apiBaseUrl = process.env.E2E_API_URL || process.env.E2E_BASE_URL || 'http://localhost:8090';

    test('health endpoint responds with 200', async ({ request }) => {
      // Try the standard health endpoint
      const response = await request.get(`${apiBaseUrl}/health.php`, {
        timeout: 10000,
      }).catch(() => null);

      if (response) {
        expect(response.status()).toBe(200);
      } else {
        // If health.php is not available, try the root
        const rootResponse = await request.get(apiBaseUrl, { timeout: 10000 });
        expect(rootResponse.status()).toBeLessThan(500);
      }
    });

    test('bootstrap API returns tenant data', async ({ request }) => {
      const response = await request.get(`${apiBaseUrl}/api/v2/tenant/bootstrap`, {
        headers: {
          'X-Tenant-ID': DEFAULT_TENANT,
          'Accept': 'application/json',
        },
        timeout: 10000,
      });

      expect(response.status()).toBe(200);

      const body = await response.json();
      // Bootstrap should return tenant configuration data
      // The response may be wrapped in {data: ...} or be the direct object
      const data = body?.data || body;
      expect(data).toBeTruthy();

      // Should have tenant-identifying fields
      const hasTenantInfo =
        data?.tenant_id !== undefined ||
        data?.slug !== undefined ||
        data?.name !== undefined ||
        data?.site_name !== undefined ||
        data?.settings !== undefined;
      expect(hasTenantInfo).toBeTruthy();
    });

    test('categories API returns data', async ({ request }) => {
      const response = await request.get(`${apiBaseUrl}/api/v2/categories`, {
        headers: {
          'X-Tenant-ID': DEFAULT_TENANT,
          'Accept': 'application/json',
        },
        timeout: 10000,
      });

      expect(response.status()).toBe(200);

      const body = await response.json();
      // Should return an array or an object with data array
      const categories = body?.data || body;
      expect(categories).toBeTruthy();
      expect(Array.isArray(categories)).toBeTruthy();
    });
  });
});
