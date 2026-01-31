import { Page, expect, Locator } from '@playwright/test';

/**
 * Test Utilities for Project NEXUS E2E Tests
 */

// Default tenant for tests
export const DEFAULT_TENANT = process.env.E2E_TENANT || 'hour-timebank';
export const BASE_URL = process.env.E2E_BASE_URL || 'http://staging.timebank.local';

/**
 * Build a tenant-scoped URL
 */
export function tenantUrl(path: string, tenant: string = DEFAULT_TENANT): string {
  const cleanPath = path.startsWith('/') ? path.slice(1) : path;
  return `/${tenant}/${cleanPath}`;
}

/**
 * Navigate to a tenant-scoped page
 */
export async function goToTenantPage(page: Page, path: string, tenant: string = DEFAULT_TENANT): Promise<void> {
  await page.goto(tenantUrl(path, tenant));
  await dismissBlockingModals(page);
}

/**
 * Dismiss the development notice modal if present
 * This modal blocks all interactions until dismissed
 */
export async function dismissDevNoticeModal(page: Page): Promise<void> {
  const continueBtn = page.locator('#dev-notice-continue');
  if (await continueBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
    await continueBtn.click();
    await page.waitForTimeout(300);
  }
}

/**
 * Dismiss the cookie consent dialog if present
 * This dialog blocks all interactions until dismissed
 */
export async function dismissCookieConsent(page: Page): Promise<void> {
  const acceptBtn = page.locator('button:has-text("Accept All"), button:has-text("Accept all cookies")');
  if (await acceptBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
    await acceptBtn.first().click();
    await page.waitForTimeout(300);
  }
}

/**
 * Dismiss all blocking modals (dev notice + cookie consent)
 */
export async function dismissBlockingModals(page: Page): Promise<void> {
  await dismissDevNoticeModal(page);
  await dismissCookieConsent(page);
}

/**
 * Wait for page to be fully loaded
 * Uses 'domcontentloaded' instead of 'networkidle' because the platform has
 * persistent connections (real-time notifications, Pusher) that prevent networkidle.
 * Also waits for skeleton loaders to hydrate.
 */
export async function waitForPageLoad(page: Page): Promise<void> {
  await page.waitForLoadState('domcontentloaded');

  // Wait for skeleton loaders to hydrate (content loaded via AJAX)
  // The platform uses .skeleton-container.hydrated and .actual-content.hydrated classes
  try {
    // Wait for either: no skeletons, or skeletons are hydrated, or actual content is visible
    await Promise.race([
      page.waitForSelector('.actual-content.hydrated', { timeout: 5000 }),
      page.waitForSelector('.skeleton-container.hydrated', { timeout: 5000 }),
      page.waitForFunction(() => !document.querySelector('.skeleton-container:not(.hydrated)'), { timeout: 5000 }),
      page.waitForTimeout(2000) // Fallback timeout
    ]);
  } catch {
    // If no skeleton system found, just wait a brief moment
    await page.waitForTimeout(500);
  }
}

/**
 * Get CSRF token from the page
 */
export async function getCsrfToken(page: Page): Promise<string | null> {
  const token = await page.locator('input[name="csrf_token"], meta[name="csrf-token"]').first();
  if (await token.count() > 0) {
    const value = await token.getAttribute('value') || await token.getAttribute('content');
    return value;
  }
  return null;
}

/**
 * Fill and submit a form with CSRF protection
 */
export async function submitForm(
  page: Page,
  formData: Record<string, string>,
  submitSelector: string = 'button[type="submit"]'
): Promise<void> {
  for (const [name, value] of Object.entries(formData)) {
    const input = page.locator(`[name="${name}"]`);
    if (await input.count() > 0) {
      const tagName = await input.evaluate(el => el.tagName.toLowerCase());
      if (tagName === 'select') {
        await input.selectOption(value);
      } else if (tagName === 'textarea') {
        await input.fill(value);
      } else {
        const type = await input.getAttribute('type');
        if (type === 'checkbox' || type === 'radio') {
          if (value === 'true' || value === '1') {
            await input.check();
          }
        } else {
          await input.fill(value);
        }
      }
    }
  }

  await page.click(submitSelector);
}

/**
 * Wait for a toast/flash notification and verify its content
 */
export async function expectNotification(
  page: Page,
  type: 'success' | 'error' | 'warning' | 'info',
  messageContains?: string
): Promise<void> {
  const notificationSelectors = [
    `.flash-${type}`,
    `.alert-${type}`,
    `.notification-${type}`,
    `.toast-${type}`,
    `[data-notification="${type}"]`,
    `.govuk-notification-banner--${type}`,
  ];

  const notification = page.locator(notificationSelectors.join(', ')).first();
  await expect(notification).toBeVisible({ timeout: 5000 });

  if (messageContains) {
    await expect(notification).toContainText(messageContains);
  }
}

/**
 * Check if user is authenticated
 */
export async function isAuthenticated(page: Page): Promise<boolean> {
  // Check for common auth indicators
  const authIndicators = [
    '[data-user-menu]',
    '.user-avatar',
    '.user-dropdown',
    'a[href*="/logout"]',
    'a[href*="/profile/me"]',
  ];

  for (const selector of authIndicators) {
    if (await page.locator(selector).count() > 0) {
      return true;
    }
  }

  return false;
}

/**
 * Wait for AJAX request to complete
 */
export async function waitForAjax(page: Page, urlPattern: string | RegExp): Promise<void> {
  await page.waitForResponse(
    response => {
      const url = response.url();
      if (typeof urlPattern === 'string') {
        return url.includes(urlPattern);
      }
      return urlPattern.test(url);
    },
    { timeout: 10000 }
  );
}

/**
 * Check if element is in viewport
 */
export async function isInViewport(locator: Locator): Promise<boolean> {
  const boundingBox = await locator.boundingBox();
  if (!boundingBox) return false;

  const viewportSize = await locator.page().viewportSize();
  if (!viewportSize) return false;

  return (
    boundingBox.x >= 0 &&
    boundingBox.y >= 0 &&
    boundingBox.x + boundingBox.width <= viewportSize.width &&
    boundingBox.y + boundingBox.height <= viewportSize.height
  );
}

/**
 * Scroll element into view and wait
 */
export async function scrollIntoView(locator: Locator): Promise<void> {
  await locator.scrollIntoViewIfNeeded();
  await locator.page().waitForTimeout(300); // Wait for scroll animation
}

/**
 * Take a named screenshot for visual comparison
 */
export async function takeScreenshot(page: Page, name: string): Promise<void> {
  await page.screenshot({
    path: `e2e/screenshots/${name}.png`,
    fullPage: true,
  });
}

/**
 * Mock an API response
 */
export async function mockApiResponse(
  page: Page,
  urlPattern: string | RegExp,
  response: object,
  status: number = 200
): Promise<void> {
  await page.route(urlPattern, route => {
    route.fulfill({
      status,
      contentType: 'application/json',
      body: JSON.stringify(response),
    });
  });
}

/**
 * Get current theme from page
 */
export async function getCurrentTheme(page: Page): Promise<'modern' | 'civicone'> {
  const isCivicOne = await page.locator('.govuk-template, [data-theme="civicone"]').count() > 0;
  return isCivicOne ? 'civicone' : 'modern';
}

/**
 * Wait for modal to open
 */
export async function waitForModal(page: Page): Promise<Locator> {
  const modalSelectors = [
    '.modal.show',
    '[role="dialog"][aria-modal="true"]',
    '.govuk-modal',
    '.drawer.open',
  ];

  const modal = page.locator(modalSelectors.join(', ')).first();
  await expect(modal).toBeVisible({ timeout: 5000 });
  return modal;
}

/**
 * Close any open modal
 */
export async function closeModal(page: Page): Promise<void> {
  const closeButtons = [
    '.modal .close',
    '[data-dismiss="modal"]',
    '.modal-close',
    '[aria-label="Close"]',
  ];

  const closeButton = page.locator(closeButtons.join(', ')).first();
  if (await closeButton.isVisible()) {
    await closeButton.click();
    await page.waitForTimeout(300); // Wait for close animation
  }
}

/**
 * Generate unique test data
 */
export function generateTestData() {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(7);

  return {
    email: `test-${timestamp}@example.com`,
    username: `testuser-${random}`,
    title: `Test Title ${timestamp}`,
    description: `Test description created at ${new Date().toISOString()}`,
    uniqueId: `${timestamp}-${random}`,
  };
}

/**
 * Check accessibility (basic checks)
 */
export async function checkBasicA11y(page: Page): Promise<void> {
  // Check for page title
  const title = await page.title();
  expect(title).toBeTruthy();

  // Check for main landmark
  const main = page.locator('main, [role="main"]');
  expect(await main.count()).toBeGreaterThan(0);

  // Check for h1
  const h1 = page.locator('h1');
  expect(await h1.count()).toBeGreaterThan(0);

  // Check images have alt text
  const imagesWithoutAlt = page.locator('img:not([alt])');
  expect(await imagesWithoutAlt.count()).toBe(0);

  // Check form inputs have labels
  const inputsWithoutLabels = page.locator('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([aria-label]):not([aria-labelledby])');
  for (const input of await inputsWithoutLabels.all()) {
    const id = await input.getAttribute('id');
    if (id) {
      const label = page.locator(`label[for="${id}"]`);
      expect(await label.count()).toBeGreaterThan(0);
    }
  }
}

/**
 * Intercept and log all API calls (for debugging)
 */
export async function logApiCalls(page: Page): Promise<void> {
  page.on('request', request => {
    if (request.url().includes('/api/')) {
      console.log(`🌐 API Request: ${request.method()} ${request.url()}`);
    }
  });

  page.on('response', response => {
    if (response.url().includes('/api/')) {
      console.log(`📥 API Response: ${response.status()} ${response.url()}`);
    }
  });
}
