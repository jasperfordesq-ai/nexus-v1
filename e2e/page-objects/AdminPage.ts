import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Admin Dashboard Page Object
 */
export class AdminDashboardPage extends BasePage {
  readonly statsCards: Locator;
  readonly recentActivity: Locator;
  readonly quickActions: Locator;
  readonly userCount: Locator;
  readonly listingCount: Locator;
  readonly eventCount: Locator;
  readonly transactionCount: Locator;
  readonly sidebarNav: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.statsCards = page.locator('.admin-stat-card, .admin-stat, .stats-card, [data-stat]');
    this.recentActivity = page.locator('.admin-activity-log, .recent-activity, .activity-log');
    this.quickActions = page.locator('.admin-page-header-actions, .quick-actions, .admin-actions');
    this.userCount = page.locator('.admin-stat-card:has-text("Members") .admin-stat-value, [data-stat="users"], .user-count');
    this.listingCount = page.locator('.admin-stat-card:has-text("Listings") .admin-stat-value, [data-stat="listings"], .listing-count');
    this.eventCount = page.locator('.admin-stat-card:has-text("Events") .admin-stat-value, [data-stat="events"], .event-count');
    this.transactionCount = page.locator('.admin-stat-card:has-text("Transactions") .admin-stat-value, [data-stat="transactions"], .transaction-count');
    this.sidebarNav = page.locator('.admin-sidebar, .admin-nav, .sidebar-nav, nav');
  }

  /**
   * Navigate to admin dashboard
   */
  async navigate(): Promise<void> {
    await this.page.goto('/admin');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get stat value by name
   */
  async getStatValue(statName: string): Promise<string> {
    const stat = this.statsCards.filter({ hasText: statName }).first();
    const value = stat.locator('.stat-value, .count, .number');
    return await value.textContent() || '0';
  }

  /**
   * Navigate to admin section via sidebar
   */
  async navigateToSection(sectionName: string): Promise<void> {
    await this.sidebarNav.getByRole('link', { name: sectionName }).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if admin dashboard is loaded
   */
  async isDashboardLoaded(): Promise<boolean> {
    return await this.statsCards.count() > 0;
  }
}

/**
 * Admin Users Page Object
 */
export class AdminUsersPage extends BasePage {
  readonly userTable: Locator;
  readonly userRows: Locator;
  readonly searchInput: Locator;
  readonly filterDropdown: Locator;
  readonly bulkActions: Locator;
  readonly createUserButton: Locator;
  readonly pagination: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.userTable = page.locator('.admin-table, .user-table, table');
    this.userRows = page.locator('.admin-table tbody tr, tbody tr, .user-row');
    this.searchInput = page.locator('.admin-search-input, input[name="search"], input[name="q"], input[placeholder*="Search"]');
    this.filterDropdown = page.locator('.admin-filter-select, select[name="filter"], select[name="status"], [data-filter]');
    this.bulkActions = page.locator('.admin-bulk-actions, .bulk-actions, [data-bulk]');
    this.createUserButton = page.locator('.admin-btn-primary:has-text("Create"), .create-user-btn, a[href*="create"]');
    this.pagination = page.locator('.admin-pagination, .pagination');
  }

  /**
   * Navigate to admin users page
   */
  async navigate(): Promise<void> {
    await this.page.goto('/admin/users');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get user count
   */
  async getUserCount(): Promise<number> {
    return await this.userRows.count();
  }

  /**
   * Search users
   */
  async searchUsers(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.searchInput.press('Enter');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Filter users
   */
  async filterUsers(filter: string): Promise<void> {
    await this.filterDropdown.selectOption(filter);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click on a user row
   */
  async clickUser(index: number = 0): Promise<void> {
    await this.userRows.nth(index).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get user emails
   */
  async getUserEmails(): Promise<string[]> {
    const emails = await this.userRows.locator('.email, td:nth-child(2)').allTextContents();
    return emails.map(e => e.trim());
  }
}

/**
 * Admin Listings Page Object
 */
export class AdminListingsPage extends BasePage {
  readonly listingTable: Locator;
  readonly listingRows: Locator;
  readonly searchInput: Locator;
  readonly statusFilter: Locator;
  readonly approveButton: Locator;
  readonly deleteButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.listingTable = page.locator('.listing-table, table');
    this.listingRows = page.locator('tbody tr, .listing-row');
    this.searchInput = page.locator('input[name="search"]');
    this.statusFilter = page.locator('select[name="status"]');
    this.approveButton = page.locator('.approve-btn, [data-approve]');
    this.deleteButton = page.locator('.delete-btn, [data-delete]');
  }

  /**
   * Navigate to admin listings page
   */
  async navigate(): Promise<void> {
    await this.page.goto('/admin/listings');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get listing count
   */
  async getListingCount(): Promise<number> {
    return await this.listingRows.count();
  }

  /**
   * Filter by status
   */
  async filterByStatus(status: 'all' | 'pending' | 'approved' | 'rejected'): Promise<void> {
    await this.statusFilter.selectOption(status);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Approve a listing
   */
  async approveListing(index: number = 0): Promise<void> {
    const row = this.listingRows.nth(index);
    await row.locator('.approve-btn, [data-approve]').click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Delete a listing
   */
  async deleteListing(index: number = 0): Promise<void> {
    const row = this.listingRows.nth(index);
    await row.locator('.delete-btn, [data-delete]').click();
    // Handle confirmation
    const confirm = this.page.locator('.confirm-delete, [data-confirm]');
    if (await confirm.isVisible()) {
      await confirm.click();
    }
    await this.page.waitForLoadState('domcontentloaded');
  }
}

/**
 * Admin Settings Page Object
 */
export class AdminSettingsPage extends BasePage {
  readonly settingsTabs: Locator;
  readonly generalTab: Locator;
  readonly featuresTab: Locator;
  readonly emailTab: Locator;

  readonly siteNameInput: Locator;
  readonly siteDescriptionInput: Locator;
  readonly logoUpload: Locator;

  readonly featureToggles: Locator;
  readonly saveButton: Locator;
  readonly successMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.settingsTabs = page.locator('.settings-tabs, .nav-tabs');
    this.generalTab = page.locator('[data-tab="general"], a[href*="general"]');
    this.featuresTab = page.locator('[data-tab="features"], a[href*="features"]');
    this.emailTab = page.locator('[data-tab="email"], a[href*="email"]');

    this.siteNameInput = page.locator('input[name="site_name"]');
    this.siteDescriptionInput = page.locator('textarea[name="site_description"]');
    this.logoUpload = page.locator('input[type="file"][name="logo"]');

    this.featureToggles = page.locator('.feature-toggle, input[type="checkbox"]');
    this.saveButton = page.locator('button[type="submit"], .save-btn');
    this.successMessage = page.locator('.success, .alert-success');
  }

  /**
   * Navigate to admin settings
   */
  async navigate(): Promise<void> {
    await this.page.goto('/admin/settings');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Go to features tab
   */
  async goToFeaturesTab(): Promise<void> {
    await this.featuresTab.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Update site name
   */
  async updateSiteName(name: string): Promise<void> {
    await this.siteNameInput.fill(name);
    await this.saveButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Toggle a feature
   */
  async toggleFeature(featureName: string, enabled: boolean): Promise<void> {
    const toggle = this.page.locator(`input[name="${featureName}"], input[data-feature="${featureName}"]`);
    if (enabled) {
      await toggle.check();
    } else {
      await toggle.uncheck();
    }
    await this.saveButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if save was successful
   */
  async isSaveSuccessful(): Promise<boolean> {
    return await this.successMessage.isVisible();
  }
}

/**
 * Admin Timebanking Page Object
 */
export class AdminTimebankingPage extends BasePage {
  readonly transactionTable: Locator;
  readonly transactionRows: Locator;
  readonly totalCreditsCirculating: Locator;
  readonly adjustBalanceButton: Locator;
  readonly userSelect: Locator;
  readonly amountInput: Locator;
  readonly reasonInput: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.transactionTable = page.locator('.transaction-table, table');
    this.transactionRows = page.locator('tbody tr');
    this.totalCreditsCirculating = page.locator('.total-credits, [data-total-credits]');
    this.adjustBalanceButton = page.locator('.adjust-balance-btn, a[href*="adjust"]');
    this.userSelect = page.locator('select[name="user_id"]');
    this.amountInput = page.locator('input[name="amount"]');
    this.reasonInput = page.locator('textarea[name="reason"], input[name="reason"]');
  }

  /**
   * Navigate to admin timebanking
   */
  async navigate(): Promise<void> {
    await this.page.goto('/admin/timebanking');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get total credits circulating
   */
  async getTotalCredits(): Promise<string> {
    return await this.totalCreditsCirculating.textContent() || '0';
  }

  /**
   * Adjust user balance
   */
  async adjustBalance(userId: string, amount: string, reason: string): Promise<void> {
    await this.adjustBalanceButton.click();
    await this.page.waitForLoadState('domcontentloaded');

    await this.userSelect.selectOption(userId);
    await this.amountInput.fill(amount);
    await this.reasonInput.fill(reason);

    await this.page.click('button[type="submit"]');
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * View user report
   */
  async viewUserReport(index: number = 0): Promise<void> {
    const row = this.transactionRows.nth(index);
    await row.locator('a[href*="user-report"]').click();
    await this.page.waitForLoadState('domcontentloaded');
  }
}
