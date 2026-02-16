import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Admin Dashboard Page Object (React Admin with HeroUI)
 */
export class AdminDashboardPage extends BasePage {
  readonly statsCards: Locator;
  readonly recentActivity: Locator;
  readonly quickActions: Locator;
  readonly userCount: Locator;
  readonly listingCount: Locator;
  readonly transactionCount: Locator;
  readonly sidebarNav: Locator;
  readonly pageHeader: Locator;
  readonly refreshButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    // React admin uses grid layout with StatCard components
    // StatCard is a HeroUI Card with specific structure
    this.statsCards = page.locator('[data-slot="base"]:has(p.text-sm.text-default-500), .grid > div:has(p.text-2xl.font-bold)');
    this.recentActivity = page.locator('.activity-list, [data-activity-log]');
    this.quickActions = page.locator('.page-header-actions, [data-quick-actions]');

    // Match actual StatCard labels from AdminDashboard.tsx
    this.userCount = page.locator('text=Total Users').locator('..').locator('p.text-2xl.font-bold');
    this.listingCount = page.locator('text=Total Listings').locator('..').locator('p.text-2xl.font-bold');
    this.transactionCount = page.locator('text=Total Transactions').locator('..').locator('p.text-2xl.font-bold');

    // React admin sidebar - nav element with links
    this.sidebarNav = page.locator('nav.admin-sidebar, nav:has(a[href*="/admin/"])');

    // PageHeader component
    this.pageHeader = page.locator('h1, [data-page-header]');
    this.refreshButton = page.locator('button:has-text("Refresh")');
  }

  /**
   * Navigate to admin dashboard
   */
  async navigate(): Promise<void> {
    await this.page.goto(this.tenantUrl('/admin'));
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get stat value by label
   */
  async getStatValue(statLabel: string): Promise<string> {
    const statCard = this.page.locator(`text=${statLabel}`).locator('..').locator('p.text-2xl.font-bold');
    return await statCard.textContent() || '0';
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

  /**
   * Click refresh button
   */
  async refresh(): Promise<void> {
    await this.refreshButton.click();
    await this.page.waitForLoadState('networkidle');
  }
}

/**
 * Admin Users Page Object (React Admin)
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

    // React admin uses HeroUI Table component
    this.userTable = page.locator('table, [role="table"]');
    this.userRows = page.locator('tbody tr, [role="row"]');

    // Search may be inline or global search (Cmd+K)
    this.searchInput = page.locator('input[type="search"], input[placeholder*="Search" i]');

    // HeroUI Select component for filters
    this.filterDropdown = page.locator('button[role="combobox"], select');

    this.bulkActions = page.locator('[data-bulk-actions]');

    // Create button - HeroUI Button with primary color
    this.createUserButton = page.locator('button:has-text("Create"), button:has-text("Add User"), a[href*="create"]');

    // HeroUI Pagination component
    this.pagination = page.locator('nav[aria-label*="pagination" i], [data-pagination]');
  }

  /**
   * Navigate to admin users page
   */
  async navigate(): Promise<void> {
    await this.page.goto(this.tenantUrl('/admin/users'));
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get user count
   */
  async getUserCount(): Promise<number> {
    await this.page.waitForLoadState('domcontentloaded');
    return await this.userRows.count();
  }

  /**
   * Search users
   */
  async searchUsers(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.searchInput.press('Enter');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Filter users (if dropdown exists)
   */
  async filterUsers(filter: string): Promise<void> {
    await this.filterDropdown.click();
    await this.page.locator(`text=${filter}`).click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Click on a user row
   */
  async clickUser(index: number = 0): Promise<void> {
    await this.userRows.nth(index).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get user emails from table
   */
  async getUserEmails(): Promise<string[]> {
    const emails = await this.userRows.locator('td:nth-child(2), [data-email]').allTextContents();
    return emails.map(e => e.trim()).filter(e => e.length > 0);
  }
}

/**
 * Admin Listings Page Object (React Admin)
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

    this.listingTable = page.locator('table, [role="table"]');
    this.listingRows = page.locator('tbody tr, [role="row"]');
    this.searchInput = page.locator('input[type="search"], input[placeholder*="Search" i]');

    // Status filter - HeroUI Select or button group
    this.statusFilter = page.locator('button[role="combobox"][aria-label*="status" i], select[name="status"]');

    // Action buttons - HeroUI Button components
    this.approveButton = page.locator('button:has-text("Approve")');
    this.deleteButton = page.locator('button:has-text("Delete"), button[aria-label*="delete" i]');
  }

  /**
   * Navigate to admin listings page
   */
  async navigate(): Promise<void> {
    await this.page.goto(this.tenantUrl('/admin/listings'));
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get listing count
   */
  async getListingCount(): Promise<number> {
    await this.page.waitForLoadState('domcontentloaded');
    return await this.listingRows.count();
  }

  /**
   * Filter by status
   */
  async filterByStatus(status: 'all' | 'pending' | 'approved' | 'rejected'): Promise<void> {
    await this.statusFilter.click();
    await this.page.locator(`text=${status}`).click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Approve a listing
   */
  async approveListing(index: number = 0): Promise<void> {
    const row = this.listingRows.nth(index);
    await row.locator('button:has-text("Approve")').click();

    // Handle confirmation modal if present
    const confirmButton = this.page.locator('button:has-text("Confirm"), button[type="submit"]');
    if (await confirmButton.isVisible({ timeout: 1000 }).catch(() => false)) {
      await confirmButton.click();
    }

    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Delete a listing
   */
  async deleteListing(index: number = 0): Promise<void> {
    const row = this.listingRows.nth(index);
    await row.locator('button:has-text("Delete"), button[aria-label*="delete" i]').click();

    // Handle confirmation modal (ConfirmModal component)
    const confirmButton = this.page.locator('button:has-text("Delete"), button:has-text("Confirm")').last();
    if (await confirmButton.isVisible({ timeout: 1000 }).catch(() => false)) {
      await confirmButton.click();
    }

    await this.page.waitForLoadState('networkidle');
  }
}

/**
 * Admin Settings Page Object (React Admin)
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

    // HeroUI Tabs component
    this.settingsTabs = page.locator('[role="tablist"]');
    this.generalTab = page.locator('[role="tab"]:has-text("General")');
    this.featuresTab = page.locator('[role="tab"]:has-text("Features")');
    this.emailTab = page.locator('[role="tab"]:has-text("Email")');

    // HeroUI Input components
    this.siteNameInput = page.locator('input[name="site_name"], input[label*="Site Name" i]');
    this.siteDescriptionInput = page.locator('textarea[name="site_description"], textarea[label*="Description" i]');
    this.logoUpload = page.locator('input[type="file"][accept*="image"]');

    // HeroUI Switch components for feature toggles
    this.featureToggles = page.locator('button[role="switch"], input[type="checkbox"][role="switch"]');

    // HeroUI Button with primary color
    this.saveButton = page.locator('button[type="submit"]:has-text("Save")');

    // Toast notification or alert
    this.successMessage = page.locator('[role="alert"]:has-text("success"), .toast:has-text("success")');
  }

  /**
   * Navigate to admin settings
   */
  async navigate(): Promise<void> {
    await this.page.goto(this.tenantUrl('/admin/settings'));
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
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Toggle a feature
   */
  async toggleFeature(featureName: string, enabled: boolean): Promise<void> {
    const toggle = this.page.locator(`[role="switch"][aria-label*="${featureName}" i]`);

    const isChecked = await toggle.getAttribute('aria-checked') === 'true';

    if ((enabled && !isChecked) || (!enabled && isChecked)) {
      await toggle.click();
    }

    await this.saveButton.click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Check if save was successful
   */
  async isSaveSuccessful(): Promise<boolean> {
    return await this.successMessage.isVisible({ timeout: 5000 }).catch(() => false);
  }
}

/**
 * Admin Timebanking Page Object (React Admin)
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

    this.transactionTable = page.locator('table, [role="table"]');
    this.transactionRows = page.locator('tbody tr, [role="row"]');

    // Stat card or metric display
    this.totalCreditsCirculating = page.locator('text=Total Credits').locator('..').locator('p.text-2xl.font-bold');

    this.adjustBalanceButton = page.locator('button:has-text("Adjust Balance"), a[href*="adjust"]');

    // HeroUI Select and Input components
    this.userSelect = page.locator('button[role="combobox"][aria-label*="user" i], select[name="user_id"]');
    this.amountInput = page.locator('input[name="amount"], input[type="number"]');
    this.reasonInput = page.locator('textarea[name="reason"], input[name="reason"]');
  }

  /**
   * Navigate to admin timebanking
   */
  async navigate(): Promise<void> {
    await this.page.goto(this.tenantUrl('/admin/timebanking'));
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

    // Select user (may be autocomplete or select)
    await this.userSelect.click();
    await this.page.locator(`text=${userId}`).click();

    await this.amountInput.fill(amount);
    await this.reasonInput.fill(reason);

    await this.page.locator('button[type="submit"]').click();
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * View user report
   */
  async viewUserReport(index: number = 0): Promise<void> {
    const row = this.transactionRows.nth(index);
    await row.locator('a[href*="report"], button:has-text("View")').click();
    await this.page.waitForLoadState('domcontentloaded');
  }
}
