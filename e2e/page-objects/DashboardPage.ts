import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Dashboard Page Object
 */
export class DashboardPage extends BasePage {
  readonly welcomeMessage: Locator;
  readonly walletBalance: Locator;
  readonly recentActivity: Locator;
  readonly quickActions: Locator;
  readonly myListings: Locator;
  readonly myEvents: Locator;
  readonly notifications: Locator;
  readonly statsCards: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.welcomeMessage = page.locator('.welcome-message, h1');
    this.walletBalance = page.locator('.wallet-balance, [data-wallet-balance]');
    this.recentActivity = page.locator('.recent-activity, .activity-feed');
    this.quickActions = page.locator('.quick-actions, .action-buttons');
    this.myListings = page.locator('.my-listings, [data-my-listings]');
    this.myEvents = page.locator('.my-events, [data-my-events]');
    this.notifications = page.locator('.dashboard-notifications, .notification-list');
    this.statsCards = page.locator('.stats-card, .stat-box, .dashboard-stat');
  }

  /**
   * Navigate to dashboard
   */
  async navigate(): Promise<void> {
    await this.goto('dashboard');
  }

  /**
   * Get wallet balance
   */
  async getWalletBalance(): Promise<string> {
    const balance = await this.walletBalance.textContent();
    return balance?.trim() || '0';
  }

  /**
   * Get number of recent activities shown
   */
  async getActivityCount(): Promise<number> {
    const activities = this.recentActivity.locator('.activity-item, li');
    return await activities.count();
  }

  /**
   * Click quick action button
   */
  async clickQuickAction(actionName: string): Promise<void> {
    await this.quickActions.getByRole('button', { name: actionName }).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get stats card value by title
   */
  async getStatValue(statTitle: string): Promise<string> {
    const card = this.statsCards.filter({ hasText: statTitle }).first();
    const value = card.locator('.stat-value, .count, .number');
    return await value.textContent() || '0';
  }

  /**
   * Navigate to listings tab
   */
  async goToListingsTab(): Promise<void> {
    await this.page.click('a[href*="dashboard/listings"], [data-tab="listings"]');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Navigate to wallet tab
   */
  async goToWalletTab(): Promise<void> {
    await this.page.click('a[href*="dashboard/wallet"], [data-tab="wallet"]');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Navigate to events tab
   */
  async goToEventsTab(): Promise<void> {
    await this.page.click('a[href*="dashboard/events"], [data-tab="events"]');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if dashboard is loaded
   */
  async isDashboardLoaded(): Promise<boolean> {
    await expect(this.welcomeMessage).toBeVisible({ timeout: 5000 });
    return true;
  }
}
