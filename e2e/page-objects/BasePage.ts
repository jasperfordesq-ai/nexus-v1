import { Page, Locator, expect } from '@playwright/test';
import { DEFAULT_TENANT, waitForPageLoad } from '../helpers/test-utils';

/**
 * Base Page Object for all pages
 * Contains common elements and methods shared across pages
 */
export class BasePage {
  readonly page: Page;
  readonly tenant: string;

  // Common navigation elements
  readonly header: Locator;
  readonly footer: Locator;
  readonly mainNav: Locator;
  readonly userMenu: Locator;
  readonly searchInput: Locator;
  readonly notificationBell: Locator;

  constructor(page: Page, tenant: string = DEFAULT_TENANT) {
    this.page = page;
    this.tenant = tenant;

    // Initialize common locators
    this.header = page.locator('header, .header, .govuk-header');
    this.footer = page.locator('footer, .footer, .govuk-footer');
    this.mainNav = page.locator('nav, .main-nav, .govuk-header__navigation');
    this.userMenu = page.locator('[data-user-menu], .user-dropdown, .user-menu');
    this.searchInput = page.locator('input[type="search"], .search-input, #global-search');
    this.notificationBell = page.locator('.notification-bell, [data-notifications]');
  }

  /**
   * Dismiss development notice modal if present
   * The modal blocks all interactions until dismissed
   */
  async dismissDevNoticeModal(): Promise<void> {
    const continueBtn = this.page.locator('#dev-notice-continue');
    if (await continueBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
      await continueBtn.click();
      await this.page.waitForTimeout(300);
    }
  }

  /**
   * Navigate to a page within the tenant
   */
  async goto(path: string): Promise<void> {
    const url = `/${this.tenant}/${path.replace(/^\//, '')}`;
    await this.page.goto(url);
    await this.dismissDevNoticeModal();
    await waitForPageLoad(this.page);
  }

  /**
   * Get page title
   */
  async getTitle(): Promise<string> {
    return this.page.title();
  }

  /**
   * Get current URL path
   */
  getCurrentPath(): string {
    return new URL(this.page.url()).pathname;
  }

  /**
   * Check if user is logged in
   */
  async isLoggedIn(): Promise<boolean> {
    const logoutLink = this.page.locator('a[href*="/logout"]');
    return await logoutLink.count() > 0;
  }

  /**
   * Click on user menu
   */
  async openUserMenu(): Promise<void> {
    await this.userMenu.click();
    await this.page.waitForTimeout(200);
  }

  /**
   * Navigate via main navigation
   */
  async navigateTo(linkText: string): Promise<void> {
    await this.mainNav.getByRole('link', { name: linkText }).click();
    await waitForPageLoad(this.page);
  }

  /**
   * Perform global search
   */
  async search(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.searchInput.press('Enter');
    await waitForPageLoad(this.page);
  }

  /**
   * Get notification count
   */
  async getNotificationCount(): Promise<number> {
    const badge = this.page.locator('.notification-count, .badge-count');
    if (await badge.count() > 0) {
      const text = await badge.textContent();
      return parseInt(text || '0', 10);
    }
    return 0;
  }

  /**
   * Wait for flash/toast message
   */
  async waitForFlashMessage(type: 'success' | 'error' | 'warning' | 'info' = 'success'): Promise<string> {
    const flashSelectors = [
      `.flash-${type}`,
      `.alert-${type}`,
      `.notification-${type}`,
      `.govuk-notification-banner--${type}`,
    ];

    const flash = this.page.locator(flashSelectors.join(', ')).first();
    await expect(flash).toBeVisible({ timeout: 5000 });
    return await flash.textContent() || '';
  }

  /**
   * Check for error messages on page
   */
  async hasErrors(): Promise<boolean> {
    const errorSelectors = [
      '.error',
      '.alert-danger',
      '.govuk-error-summary',
      '.form-error',
      '[role="alert"]',
    ];

    return await this.page.locator(errorSelectors.join(', ')).count() > 0;
  }

  /**
   * Get all error messages
   */
  async getErrors(): Promise<string[]> {
    const errorSelectors = [
      '.error-message',
      '.field-error',
      '.govuk-error-message',
      '.invalid-feedback',
    ];

    const errors = await this.page.locator(errorSelectors.join(', ')).allTextContents();
    return errors.filter(e => e.trim());
  }

  /**
   * Scroll to bottom of page
   */
  async scrollToBottom(): Promise<void> {
    await this.page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await this.page.waitForTimeout(300);
  }

  /**
   * Scroll to top of page
   */
  async scrollToTop(): Promise<void> {
    await this.page.evaluate(() => window.scrollTo(0, 0));
    await this.page.waitForTimeout(300);
  }

  /**
   * Check if page has loaded correctly (no 404/500)
   */
  async isPageHealthy(): Promise<boolean> {
    const url = this.page.url();
    const title = await this.page.title();

    // Check for error pages
    if (title.includes('404') || title.includes('Not Found')) return false;
    if (title.includes('500') || title.includes('Error')) return false;
    if (url.includes('/error')) return false;

    return true;
  }

  /**
   * Take screenshot with automatic naming
   */
  async screenshot(name: string): Promise<void> {
    const safeName = name.replace(/[^a-z0-9]/gi, '-').toLowerCase();
    await this.page.screenshot({
      path: `e2e/screenshots/${safeName}.png`,
      fullPage: true,
    });
  }

  /**
   * Wait for network to be idle
   */
  async waitForNetworkIdle(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
  }
}
