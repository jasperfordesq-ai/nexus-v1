import { test, expect } from '@playwright/test';

/**
 * Legal Pages E2E Tests
 *
 * Tests the public legal pages (Privacy Policy, Terms of Service)
 * for correct rendering and content structure.
 * These pages are publicly accessible and do not require authentication.
 *
 * Pages tested:
 * 1. Privacy Policy  -- /privacy
 * 2. Terms of Service -- /terms
 */

// Collect console errors per test for verification
let consoleErrors: string[] = [];

test.beforeEach(async ({ page }) => {
  consoleErrors = [];
  page.on('pageerror', (error) => {
    consoleErrors.push(error.message);
  });
});

// ---------------------------------------------------------------------------
// 1. Privacy Policy
// ---------------------------------------------------------------------------

test.describe('Legal Pages - Privacy Policy', () => {
  test('should display the privacy policy heading', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Privacy Policy/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have data collection section', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    // The privacy page has quick nav items including "Data Collection"
    const dataSection = page.locator('text=Data Collection').first();
    await expect(dataSection).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have data usage section', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    const usageSection = page.locator('text=How We Use Data').or(
      page.locator('text=Data Usage')
    ).first();
    await expect(usageSection).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have your rights section', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    const rightsSection = page.locator('text=Your Rights').first();
    await expect(rightsSection).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have cookies section', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    const cookiesSection = page.locator('text=Cookies').first();
    await expect(cookiesSection).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have quick navigation items', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    // The PrivacyPage renders 4 quick nav items: Data Collection, How We Use Data, Your Rights, Cookies
    const navLabels = ['Data Collection', 'How We Use Data', 'Your Rights', 'Cookies'];
    let found = 0;
    for (const label of navLabels) {
      const el = page.locator(`text=${label}`);
      if (await el.count() > 0) {
        found++;
      }
    }
    expect(found).toBeGreaterThanOrEqual(4);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have a data collection table', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    // The privacy page renders data collection info in a table or card layout
    // Check for known data types from the page component
    const dataTypes = ['Account Information', 'Profile Information'];
    let found = 0;
    for (const type of dataTypes) {
      const el = page.locator(`text=${type}`);
      if (await el.count() > 0) {
        found++;
      }
    }
    expect(found).toBeGreaterThanOrEqual(1);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have proper heading structure', async ({ page }) => {
    await page.goto('/privacy');
    await page.waitForLoadState('domcontentloaded');

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();

    // Should have section headings (h2 or h3)
    const subheadings = page.locator('h2, h3');
    const count = await subheadings.count();
    expect(count).toBeGreaterThanOrEqual(2);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// 2. Terms of Service
// ---------------------------------------------------------------------------

test.describe('Legal Pages - Terms of Service', () => {
  test('should display the terms of service heading', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Terms of Service/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should show last updated date', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    // The terms page has a "Last updated: January 2026" text
    const updatedText = page.locator('text=Last updated');
    await expect(updatedText).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have service description section', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    const section = page.locator('text=Service Description').first();
    await expect(section).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have user accounts section', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    const section = page.locator('text=User Accounts').first();
    await expect(section).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have acceptable use section', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    const section = page.locator('text=Acceptable Use').first();
    await expect(section).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have time credits section', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    const section = page.locator('text=Time Credits').first();
    await expect(section).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have liability section', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    const section = page.locator('text=Liability').first();
    await expect(section).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have proper heading structure', async ({ page }) => {
    await page.goto('/terms');
    await page.waitForLoadState('domcontentloaded');

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();

    // Should have section headings (h2)
    const h2s = page.locator('h2');
    const count = await h2s.count();
    // The terms page has at least 6 sections
    expect(count).toBeGreaterThanOrEqual(5);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// Cross-cutting: Accessibility
// ---------------------------------------------------------------------------

test.describe('Legal Pages - Accessibility', () => {
  test('both legal pages should have proper h1 heading', async ({ page }) => {
    const routes = [
      { path: '/privacy', expected: 'Privacy Policy' },
      { path: '/terms', expected: 'Terms of Service' },
    ];

    for (const route of routes) {
      await page.goto(route.path);
      await page.waitForLoadState('domcontentloaded');

      const h1 = page.locator('h1');
      await expect(h1).toBeVisible({ timeout: 15000 });
      await expect(h1).toContainText(route.expected);
    }
  });

  test('legal pages should have readable text content', async ({ page }) => {
    const routes = ['/privacy', '/terms'];

    for (const route of routes) {
      await page.goto(route);
      await page.waitForLoadState('domcontentloaded');

      // Should have paragraphs of content
      const paragraphs = page.locator('p');
      const count = await paragraphs.count();
      expect(count, `${route} should have paragraph content`).toBeGreaterThan(3);
    }
  });
});
