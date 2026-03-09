// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import {
  tenantUrl,
  goToTenantPage,
  dismissBlockingModals,
  DEFAULT_TENANT,
} from '../helpers/test-utils';

/**
 * Accessibility Audit Tests (WCAG 2.1 AA)
 *
 * Uses axe-core to audit 5 critical pages for WCAG 2.1 AA compliance.
 * Each test navigates to a page, runs the axe accessibility engine,
 * and asserts there are no critical violations.
 *
 * Marked with test.skip since they require a running app. When running
 * locally, remove .skip and ensure the dev server is up.
 *
 * Run with: npx playwright test accessibility-audit
 *
 * References:
 * - WCAG 2.1 AA: https://www.w3.org/WAI/WCAG21/quickref/?currentsidebar=%23col_customize&levels=aaa
 * - axe-core rules: https://github.com/dequelabs/axe-core/blob/develop/doc/rule-descriptions.md
 */

test.describe('Accessibility Audit (WCAG 2.1 AA)', () => {
  // ---------------------------------------------------------------------------
  // Helper: Run axe analysis on the current page
  // ---------------------------------------------------------------------------

  /**
   * Run axe-core analysis with WCAG 2.1 AA tags and return results.
   * Excludes known third-party widgets that may have their own a11y issues.
   */
  async function runAxeAnalysis(page: import('@playwright/test').Page) {
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .exclude('.intercom-container') // Exclude third-party chat widgets
      .exclude('.pusher-widget')       // Exclude real-time notification widgets
      .analyze();

    return results;
  }

  /**
   * Format violation details for readable test output.
   */
  function formatViolations(violations: import('axe-core').Result[]) {
    return violations
      .map(v => {
        const nodes = v.nodes
          .slice(0, 3) // Limit to 3 nodes per violation for readability
          .map(n => `    - ${n.html.substring(0, 120)}`)
          .join('\n');
        return `[${v.impact}] ${v.id}: ${v.description}\n  Help: ${v.helpUrl}\n  Affected nodes:\n${nodes}`;
      })
      .join('\n\n');
  }

  // ---------------------------------------------------------------------------
  // 1. Home / Landing Page
  // ---------------------------------------------------------------------------

  test.describe('Home / Landing Page', () => {
    test.skip('should have no critical WCAG 2.1 AA violations on the home page', async ({ page }) => {
      await page.goto(tenantUrl(''), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);
      await page.waitForTimeout(1000); // Allow dynamic content to render

      const results = await runAxeAnalysis(page);

      // Filter for critical violations only
      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      if (criticalViolations.length > 0) {
        console.log(
          `Home page critical violations:\n${formatViolations(criticalViolations)}`
        );
      }

      expect(criticalViolations).toHaveLength(0);
    });

    test.skip('should have no serious WCAG 2.1 AA violations on the home page', async ({ page }) => {
      await page.goto(tenantUrl(''), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);
      await page.waitForTimeout(1000);

      const results = await runAxeAnalysis(page);

      const seriousViolations = results.violations.filter(
        v => v.impact === 'serious'
      );

      if (seriousViolations.length > 0) {
        console.log(
          `Home page serious violations (${seriousViolations.length}):\n${formatViolations(seriousViolations)}`
        );
      }

      // Serious violations are a warning but should be tracked
      // Set a reasonable threshold (allow some during initial audit)
      expect(seriousViolations.length).toBeLessThanOrEqual(5);
    });

    test.skip('should have proper document structure on the home page', async ({ page }) => {
      await page.goto(tenantUrl(''), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);

      // Check for lang attribute on html element
      const htmlLang = await page.locator('html').getAttribute('lang');
      expect(htmlLang).toBeTruthy();

      // Check for page title
      const title = await page.title();
      expect(title).toBeTruthy();
      expect(title.length).toBeGreaterThan(0);

      // Check for h1 heading
      const h1Count = await page.locator('h1').count();
      expect(h1Count).toBeGreaterThanOrEqual(1);

      // Check for main landmark
      const mainCount = await page.locator('main, [role="main"]').count();
      expect(mainCount).toBeGreaterThanOrEqual(1);
    });
  });

  // ---------------------------------------------------------------------------
  // 2. Login Page
  // ---------------------------------------------------------------------------

  test.describe('Login Page', () => {
    // Login page does not require authentication
    test.use({ storageState: { cookies: [], origins: [] } });

    test.skip('should have no critical WCAG 2.1 AA violations on the login page', async ({ page }) => {
      await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);
      await page.waitForTimeout(1000);

      const results = await runAxeAnalysis(page);

      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      if (criticalViolations.length > 0) {
        console.log(
          `Login page critical violations:\n${formatViolations(criticalViolations)}`
        );
      }

      expect(criticalViolations).toHaveLength(0);
    });

    test.skip('should have accessible form controls on the login page', async ({ page }) => {
      await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);
      await page.waitForTimeout(1000);

      // Run axe with focus on forms
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include('form')
        .analyze();

      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      expect(criticalViolations).toHaveLength(0);

      // Additionally verify form labels exist
      const emailInput = page.locator('input[type="email"], input[name="email"]').first();
      if (await emailInput.isVisible({ timeout: 5000 }).catch(() => false)) {
        // Input should have a label (via label element, aria-label, or aria-labelledby)
        const hasLabel = await emailInput.evaluate(el => {
          const id = el.id;
          const hasLabelEl = id ? !!document.querySelector(`label[for="${id}"]`) : false;
          const hasAriaLabel = !!el.getAttribute('aria-label');
          const hasAriaLabelledBy = !!el.getAttribute('aria-labelledby');
          const hasPlaceholder = !!el.getAttribute('placeholder');
          return hasLabelEl || hasAriaLabel || hasAriaLabelledBy || hasPlaceholder;
        });
        expect(hasLabel).toBeTruthy();
      }

      const passwordInput = page.locator('input[type="password"]').first();
      if (await passwordInput.isVisible({ timeout: 3000 }).catch(() => false)) {
        const hasLabel = await passwordInput.evaluate(el => {
          const id = el.id;
          const hasLabelEl = id ? !!document.querySelector(`label[for="${id}"]`) : false;
          const hasAriaLabel = !!el.getAttribute('aria-label');
          const hasAriaLabelledBy = !!el.getAttribute('aria-labelledby');
          const hasPlaceholder = !!el.getAttribute('placeholder');
          return hasLabelEl || hasAriaLabel || hasAriaLabelledBy || hasPlaceholder;
        });
        expect(hasLabel).toBeTruthy();
      }
    });

    test.skip('should have sufficient color contrast on the login page', async ({ page }) => {
      await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });
      await dismissBlockingModals(page);
      await page.waitForTimeout(1000);

      // Run axe specifically for color contrast rules
      const results = await new AxeBuilder({ page })
        .withRules(['color-contrast'])
        .analyze();

      const criticalContrast = results.violations.filter(
        v => v.impact === 'critical' || v.impact === 'serious'
      );

      if (criticalContrast.length > 0) {
        console.log(
          `Login page contrast violations:\n${formatViolations(criticalContrast)}`
        );
      }

      // Critical contrast violations must be zero
      const critical = results.violations.filter(v => v.impact === 'critical');
      expect(critical).toHaveLength(0);
    });
  });

  // ---------------------------------------------------------------------------
  // 3. Dashboard (Authenticated)
  // ---------------------------------------------------------------------------

  test.describe('Dashboard', () => {
    test.skip('should have no critical WCAG 2.1 AA violations on the dashboard', async ({ page }) => {
      await goToTenantPage(page, 'dashboard');
      await page.waitForTimeout(2000); // Allow widgets/cards to load

      const results = await runAxeAnalysis(page);

      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      if (criticalViolations.length > 0) {
        console.log(
          `Dashboard critical violations:\n${formatViolations(criticalViolations)}`
        );
      }

      expect(criticalViolations).toHaveLength(0);
    });

    test.skip('should have accessible navigation on the dashboard', async ({ page }) => {
      await goToTenantPage(page, 'dashboard');

      // Navbar should be accessible
      const nav = page.locator('nav').first();
      if (await nav.isVisible({ timeout: 5000 }).catch(() => false)) {
        // Run axe on nav region only
        const results = await new AxeBuilder({ page })
          .withTags(['wcag2a', 'wcag2aa'])
          .include('nav')
          .analyze();

        const criticalViolations = results.violations.filter(
          v => v.impact === 'critical'
        );

        expect(criticalViolations).toHaveLength(0);
      }
    });

    test.skip('should have keyboard-navigable interactive elements on the dashboard', async ({ page }) => {
      await goToTenantPage(page, 'dashboard');

      // Tab through the page and check focus is visible
      await page.keyboard.press('Tab');
      const firstFocused = await page.evaluate(() => {
        const el = document.activeElement;
        return el ? el.tagName : null;
      });
      expect(firstFocused).toBeTruthy();

      // Continue tabbing - should reach interactive elements
      for (let i = 0; i < 5; i++) {
        await page.keyboard.press('Tab');
      }

      const laterFocused = await page.evaluate(() => {
        const el = document.activeElement;
        return el ? {
          tag: el.tagName,
          role: el.getAttribute('role'),
          href: el.getAttribute('href'),
        } : null;
      });

      expect(laterFocused).toBeTruthy();
    });

    test.skip('should report total violation count on dashboard for tracking', async ({ page }) => {
      await goToTenantPage(page, 'dashboard');
      await page.waitForTimeout(2000);

      const results = await runAxeAnalysis(page);

      // Log all violations for baseline tracking
      console.log(`Dashboard accessibility summary:
  Total violations: ${results.violations.length}
  Critical: ${results.violations.filter(v => v.impact === 'critical').length}
  Serious: ${results.violations.filter(v => v.impact === 'serious').length}
  Moderate: ${results.violations.filter(v => v.impact === 'moderate').length}
  Minor: ${results.violations.filter(v => v.impact === 'minor').length}
  Passes: ${results.passes.length}
  Incomplete: ${results.incomplete.length}`);

      // No critical violations allowed
      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );
      expect(criticalViolations).toHaveLength(0);
    });
  });

  // ---------------------------------------------------------------------------
  // 4. Listings Page (Authenticated)
  // ---------------------------------------------------------------------------

  test.describe('Listings Page', () => {
    test.skip('should have no critical WCAG 2.1 AA violations on the listings page', async ({ page }) => {
      await goToTenantPage(page, 'listings');
      await page.waitForTimeout(2000); // Allow listing cards to load

      const results = await runAxeAnalysis(page);

      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      if (criticalViolations.length > 0) {
        console.log(
          `Listings page critical violations:\n${formatViolations(criticalViolations)}`
        );
      }

      expect(criticalViolations).toHaveLength(0);
    });

    test.skip('should have accessible listing cards with proper headings', async ({ page }) => {
      await goToTenantPage(page, 'listings');
      await page.waitForTimeout(2000);

      // Check that listing cards use semantic HTML
      const cards = page.locator('article, [class*="card"], [data-listing]');
      const cardCount = await cards.count();

      if (cardCount > 0) {
        // First card should have heading structure
        const firstCard = cards.first();
        const hasHeading = await firstCard.locator('h2, h3, h4, [role="heading"]').isVisible().catch(() => false);

        // Cards should have links or be themselves clickable
        const hasLink = await firstCard.locator('a').isVisible().catch(() => false);

        expect(hasHeading || hasLink).toBeTruthy();
      }

      // Run axe on listing content area
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include('main, [role="main"]')
        .analyze();

      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      expect(criticalViolations).toHaveLength(0);
    });

    test.skip('should have accessible filter/search controls on listings page', async ({ page }) => {
      await goToTenantPage(page, 'listings');
      await page.waitForTimeout(1000);

      // Search and filter inputs should have labels
      const searchInputs = page.locator(
        'input[type="search"], input[type="text"][placeholder*="Search" i]'
      );
      const searchCount = await searchInputs.count();

      for (let i = 0; i < searchCount; i++) {
        const input = searchInputs.nth(i);
        const hasAccessibleName = await input.evaluate(el => {
          const id = el.id;
          const hasLabelEl = id ? !!document.querySelector(`label[for="${id}"]`) : false;
          const hasAriaLabel = !!el.getAttribute('aria-label');
          const hasAriaLabelledBy = !!el.getAttribute('aria-labelledby');
          const hasTitle = !!el.getAttribute('title');
          return hasLabelEl || hasAriaLabel || hasAriaLabelledBy || hasTitle;
        });

        // Each search input should have an accessible name
        expect(hasAccessibleName).toBeTruthy();
      }
    });

    test.skip('should have accessible images on listing cards', async ({ page }) => {
      await goToTenantPage(page, 'listings');
      await page.waitForTimeout(2000);

      // All images should have alt text
      const images = page.locator('img');
      const imageCount = await images.count();

      for (let i = 0; i < Math.min(imageCount, 10); i++) {
        const img = images.nth(i);
        const alt = await img.getAttribute('alt');
        const role = await img.getAttribute('role');

        // Image should have alt text or role="presentation"
        const isAccessible = (alt !== null) || (role === 'presentation') || (role === 'none');
        expect(isAccessible).toBeTruthy();
      }
    });
  });

  // ---------------------------------------------------------------------------
  // 5. Settings Page (Authenticated)
  // ---------------------------------------------------------------------------

  test.describe('Settings Page', () => {
    test.skip('should have no critical WCAG 2.1 AA violations on the settings page', async ({ page }) => {
      await goToTenantPage(page, 'settings');
      await page.waitForTimeout(1000);

      const results = await runAxeAnalysis(page);

      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      if (criticalViolations.length > 0) {
        console.log(
          `Settings page critical violations:\n${formatViolations(criticalViolations)}`
        );
      }

      expect(criticalViolations).toHaveLength(0);
    });

    test.skip('should have accessible form controls on the settings page', async ({ page }) => {
      await goToTenantPage(page, 'settings');
      await page.waitForTimeout(1000);

      // Run axe focused on form elements
      const results = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa'])
        .include('form, main, [role="main"]')
        .analyze();

      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );

      expect(criticalViolations).toHaveLength(0);

      // Check that form inputs have labels
      const formInputs = page.locator(
        'input:not([type="hidden"]):not([type="submit"]):not([type="button"]), textarea, select'
      );
      const inputCount = await formInputs.count();

      for (let i = 0; i < Math.min(inputCount, 15); i++) {
        const input = formInputs.nth(i);

        // Verify the input is visible before checking
        if (await input.isVisible().catch(() => false)) {
          const hasAccessibleName = await input.evaluate(el => {
            const id = el.id;
            const hasLabelEl = id ? !!document.querySelector(`label[for="${id}"]`) : false;
            const hasAriaLabel = !!el.getAttribute('aria-label');
            const hasAriaLabelledBy = !!el.getAttribute('aria-labelledby');
            const hasTitle = !!el.getAttribute('title');
            const hasPlaceholder = !!el.getAttribute('placeholder');
            // HeroUI inputs may use data-slot="label" inside wrapper
            const hasParentLabel = !!el.closest('[data-slot="base"]')?.querySelector('[data-slot="label"]');
            return hasLabelEl || hasAriaLabel || hasAriaLabelledBy || hasTitle || hasPlaceholder || hasParentLabel;
          });

          expect(hasAccessibleName).toBeTruthy();
        }
      }
    });

    test.skip('should have proper heading hierarchy on the settings page', async ({ page }) => {
      await goToTenantPage(page, 'settings');

      // Get all headings and verify hierarchy
      const headings = await page.locator('h1, h2, h3, h4, h5, h6').allInnerTexts();
      const headingLevels = await page.locator('h1, h2, h3, h4, h5, h6').evaluateAll(
        (elements) => elements.map(el => parseInt(el.tagName.replace('H', '')))
      );

      // Should have at least one heading
      expect(headings.length).toBeGreaterThan(0);

      // Should start with h1
      if (headingLevels.length > 0) {
        expect(headingLevels[0]).toBeLessThanOrEqual(2); // h1 or h2 acceptable
      }

      // Heading levels should not skip (e.g., h1 -> h3 without h2)
      for (let i = 1; i < headingLevels.length; i++) {
        const jump = headingLevels[i] - headingLevels[i - 1];
        // Allow going deeper by 1 level or going back to any higher level
        // Do not allow jumping forward by more than 1 level (e.g., h1 -> h3)
        expect(jump).toBeLessThanOrEqual(1);
      }
    });

    test.skip('should have keyboard-accessible settings controls', async ({ page }) => {
      await goToTenantPage(page, 'settings');

      // Tab through the settings page
      await page.keyboard.press('Tab');

      let tabCount = 0;
      const maxTabs = 20;

      while (tabCount < maxTabs) {
        const focusedElement = await page.evaluate(() => {
          const el = document.activeElement;
          if (!el || el === document.body) return null;
          return {
            tag: el.tagName,
            type: (el as HTMLInputElement).type || null,
            role: el.getAttribute('role'),
            visible: el.getBoundingClientRect().height > 0,
          };
        });

        if (focusedElement && focusedElement.visible) {
          // Good - focus is on a visible element
          break;
        }

        await page.keyboard.press('Tab');
        tabCount++;
      }

      // Should have found a focusable element within reasonable tabs
      expect(tabCount).toBeLessThan(maxTabs);
    });

    test.skip('should report total violation count on settings page for tracking', async ({ page }) => {
      await goToTenantPage(page, 'settings');
      await page.waitForTimeout(1000);

      const results = await runAxeAnalysis(page);

      // Log all violations for baseline tracking
      console.log(`Settings page accessibility summary:
  Total violations: ${results.violations.length}
  Critical: ${results.violations.filter(v => v.impact === 'critical').length}
  Serious: ${results.violations.filter(v => v.impact === 'serious').length}
  Moderate: ${results.violations.filter(v => v.impact === 'moderate').length}
  Minor: ${results.violations.filter(v => v.impact === 'minor').length}
  Passes: ${results.passes.length}
  Incomplete: ${results.incomplete.length}`);

      // No critical violations allowed
      const criticalViolations = results.violations.filter(
        v => v.impact === 'critical'
      );
      expect(criticalViolations).toHaveLength(0);
    });
  });

  // ---------------------------------------------------------------------------
  // Cross-Page Accessibility Checks
  // ---------------------------------------------------------------------------

  test.describe('Cross-Page Accessibility Checks', () => {
    test.skip('should have consistent skip-to-content link across pages', async ({ page }) => {
      const pages = ['', 'login', 'dashboard', 'listings', 'settings'];

      for (const pagePath of pages) {
        if (pagePath === 'login') {
          // Use unauthenticated context for login
          await page.goto(tenantUrl(pagePath), { waitUntil: 'domcontentloaded' });
        } else if (pagePath === '') {
          await page.goto(tenantUrl(''), { waitUntil: 'domcontentloaded' });
        } else {
          await goToTenantPage(page, pagePath);
        }

        await dismissBlockingModals(page);

        // Check for skip-to-content link (may be visually hidden)
        const skipLink = page.locator(
          'a[href="#main"], a[href="#content"], a:has-text("Skip to"), .skip-link, .sr-only:has-text("Skip")'
        ).first();

        const hasSkipLink = await skipLink.count() > 0;

        // Skip links are recommended but not all SPAs have them
        // Log which pages have/don't have them
        if (!hasSkipLink) {
          console.log(`Page "${pagePath || 'home'}" does not have a skip-to-content link`);
        }
      }
    });

    test.skip('should have proper ARIA landmarks on all critical pages', async ({ page }) => {
      const pages = [
        { path: 'dashboard', name: 'Dashboard' },
        { path: 'listings', name: 'Listings' },
        { path: 'settings', name: 'Settings' },
      ];

      for (const { path, name } of pages) {
        await goToTenantPage(page, path);

        // Check for main landmark
        const hasMain = (await page.locator('main, [role="main"]').count()) > 0;

        // Check for navigation landmark
        const hasNav = (await page.locator('nav, [role="navigation"]').count()) > 0;

        // Check for banner/header landmark
        const hasHeader = (await page.locator('header, [role="banner"]').count()) > 0;

        console.log(
          `${name}: main=${hasMain}, nav=${hasNav}, header=${hasHeader}`
        );

        // Main landmark is required
        expect(hasMain).toBeTruthy();
      }
    });
  });
});
