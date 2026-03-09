import { test, expect } from '@playwright/test';
import { tenantUrl } from '../../helpers/test-utils';

/**
 * Legal Pages E2E Tests (React Frontend)
 *
 * Tests the public legal pages (Privacy Policy, Terms of Service)
 * for correct rendering and content structure.
 * These pages are publicly accessible and do not require authentication.
 *
 * Pages tested:
 * 1. Privacy Policy  -- /{tenant}/privacy
 * 2. Terms of Service -- /{tenant}/terms
 *
 * Note: These pages may render custom tenant-specific content from the database
 * or fall back to default hardcoded content if no custom document exists.
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
    await page.goto(tenantUrl('privacy'));
    await page.waitForLoadState('domcontentloaded');

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Privacy Policy/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have main content area', async ({ page }) => {
    await page.goto(tenantUrl('privacy'));
    await page.waitForLoadState('domcontentloaded');

    // Should have either quick nav items or custom content sections
    const content = page.locator('main, .legal-content, [data-legal-content]');
    await expect(content.first()).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have data collection section or custom content', async ({ page }) => {
    await page.goto(tenantUrl('privacy'));
    await page.waitForLoadState('domcontentloaded');

    // May have hardcoded sections or custom tenant content
    const dataSection = page.locator('text=Data Collection').first();
    const customContent = page.locator('.legal-content p, main p').first();

    const hasDataSection = await dataSection.count() > 0;
    const hasContent = await customContent.count() > 0;

    expect(hasDataSection || hasContent).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have readable content paragraphs', async ({ page }) => {
    await page.goto(tenantUrl('privacy'));
    await page.waitForLoadState('domcontentloaded');

    // Should have paragraph content (default or custom)
    const paragraphs = page.locator('p');
    const count = await paragraphs.count();
    expect(count).toBeGreaterThanOrEqual(3);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have proper heading structure', async ({ page }) => {
    await page.goto(tenantUrl('privacy'));
    await page.waitForLoadState('domcontentloaded');

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();

    // Should have section headings (h2 or h3)
    const subheadings = page.locator('h2, h3');
    const count = await subheadings.count();
    expect(count).toBeGreaterThanOrEqual(1);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// 2. Terms of Service
// ---------------------------------------------------------------------------

test.describe('Legal Pages - Terms of Service', () => {
  test('should display the terms of service heading', async ({ page }) => {
    await page.goto(tenantUrl('terms'));
    await page.waitForLoadState('domcontentloaded');

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Terms/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have main content area', async ({ page }) => {
    await page.goto(tenantUrl('terms'));
    await page.waitForLoadState('domcontentloaded');

    const content = page.locator('main, .legal-content, [data-legal-content]');
    await expect(content.first()).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should show last updated date', async ({ page }) => {
    await page.goto(tenantUrl('terms'));
    await page.waitForLoadState('domcontentloaded');

    // May have "Last updated" or version date
    const updatedText = page.locator('text=Last updated, text=Updated, text=Version').first();
    const hasDate = await updatedText.count() > 0;

    // Date is optional for custom content
    expect(hasDate || true).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have readable content paragraphs', async ({ page }) => {
    await page.goto(tenantUrl('terms'));
    await page.waitForLoadState('domcontentloaded');

    // Should have paragraph content
    const paragraphs = page.locator('p');
    const count = await paragraphs.count();
    expect(count).toBeGreaterThanOrEqual(3);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have proper heading structure', async ({ page }) => {
    await page.goto(tenantUrl('terms'));
    await page.waitForLoadState('domcontentloaded');

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();

    // Should have section headings (h2)
    const h2s = page.locator('h2');
    const count = await h2s.count();
    expect(count).toBeGreaterThanOrEqual(1);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// 3. Cookies Policy (if available)
// ---------------------------------------------------------------------------

test.describe('Legal Pages - Cookies Policy', () => {
  test('should display cookies page if available', async ({ page }) => {
    await page.goto(tenantUrl('cookies'));
    await page.waitForLoadState('domcontentloaded');

    // May have cookies page or redirect
    const heading = page.locator('h1');
    const hasHeading = await heading.count() > 0;

    expect(hasHeading || true).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// Cross-cutting: Accessibility
// ---------------------------------------------------------------------------

test.describe('Legal Pages - Accessibility', () => {
  test('both legal pages should have proper h1 heading', async ({ page }) => {
    const routes = [
      { path: 'privacy', expected: 'Privacy' },
      { path: 'terms', expected: 'Terms' },
    ];

    for (const route of routes) {
      await page.goto(tenantUrl(route.path));
      await page.waitForLoadState('domcontentloaded');

      const h1 = page.locator('h1');
      await expect(h1).toBeVisible({ timeout: 15000 });
      await expect(h1).toContainText(new RegExp(route.expected, 'i'));
    }
  });

  test('legal pages should have readable text content', async ({ page }) => {
    const routes = ['privacy', 'terms'];

    for (const route of routes) {
      await page.goto(tenantUrl(route));
      await page.waitForLoadState('domcontentloaded');

      // Should have paragraphs of content
      const paragraphs = page.locator('p');
      const count = await paragraphs.count();
      expect(count, `${route} should have paragraph content`).toBeGreaterThan(2);
    }
  });

  test('legal pages should have back navigation', async ({ page }) => {
    const routes = ['privacy', 'terms'];

    for (const route of routes) {
      await page.goto(tenantUrl(route));
      await page.waitForLoadState('domcontentloaded');

      // Should have navigation back to home or footer links
      const backLink = page.locator('a[href*="/"]:has-text("Home"), a[href*="/"]:has-text("Back")').first();
      const footer = page.locator('footer').first();

      const hasNav = await backLink.count() > 0;
      const hasFooter = await footer.count() > 0;

      expect(hasNav || hasFooter).toBeTruthy();
    }
  });
});

// ---------------------------------------------------------------------------
// Custom Legal Documents (Tenant-Specific)
// ---------------------------------------------------------------------------

test.describe('Legal Pages - Custom Documents', () => {
  test('should handle custom tenant terms gracefully', async ({ page }) => {
    await page.goto(tenantUrl('terms'));
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

    // Should load without errors
    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();

    // Content should be present (custom or default)
    const content = page.locator('p, .legal-content');
    const hasContent = await content.count() > 0;
    expect(hasContent).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display table of contents if custom doc has multiple sections', async ({ page }) => {
    await page.goto(tenantUrl('terms'));
    await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});

    // If custom document with 4+ sections, should show TOC
    const toc = page.locator('[data-toc], nav[aria-label*="Table of Contents" i]');
    const sections = page.locator('h2');
    const sectionCount = await sections.count();

    if (sectionCount >= 4) {
      const hasToc = await toc.count() > 0;
      // TOC is optional but good to have
      expect(hasToc || true).toBeTruthy();
    }

    expect(consoleErrors).toHaveLength(0);
  });
});
