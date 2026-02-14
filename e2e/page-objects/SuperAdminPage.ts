import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Super Admin Page Object
 *
 * Provides navigation and element access for the 9 Super Admin pages:
 * 1. SuperDashboard — /admin/super
 * 2. TenantList — /admin/super/tenants
 * 3. TenantForm (create) — /admin/super/tenants/create
 * 4. TenantHierarchy — /admin/super/tenants/hierarchy
 * 5. SuperUserList — /admin/super/users
 * 6. SuperUserForm (create) — /admin/super/users/create
 * 7. BulkOperations — /admin/super/bulk
 * 8. SuperAuditLog — /admin/super/audit
 * 9. FederationControls — /admin/super/federation
 *
 * Uses HeroUI-compatible selectors throughout.
 */
export class SuperAdminPage extends BasePage {
  // ─── Common Locators ───────────────────────────────────────────────────────

  /** Page heading rendered by PageHeader component (h1) */
  readonly pageHeading: Locator;

  /** PageHeader description (p below h1) */
  readonly pageDescription: Locator;

  /** HeroUI StatCard components (Card with icon + label + value) */
  readonly statCards: Locator;

  /** HeroUI Table component rendered by DataTable */
  readonly dataTable: Locator;

  /** DataTable search input (inside topContent) */
  readonly tableSearchInput: Locator;

  /** HeroUI Cards (generic) */
  readonly cards: Locator;

  /** Loading spinner */
  readonly spinner: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1');
    this.pageDescription = page.locator('h1 + p, h1 ~ p').first();
    this.statCards = page.locator('[class*="CardBody"] >> ..');
    this.dataTable = page.locator('table[aria-label="Admin data table"]');
    this.tableSearchInput = page.locator('input[placeholder*="Search"]');
    this.cards = page.locator('[data-slot="base"]').or(page.locator('[class*="card"]'));
    this.spinner = page.locator('[class*="spinner"], [role="progressbar"]');
  }

  // ─── Navigation ────────────────────────────────────────────────────────────

  /**
   * Navigate to a Super Admin page by path
   */
  async navigateTo(path: string): Promise<void> {
    await this.page.goto(`/admin/${path.replace(/^\//, '')}`);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /** Navigate to Super Admin Dashboard */
  async gotoDashboard(): Promise<void> {
    await this.navigateTo('super');
  }

  /** Navigate to Tenant List */
  async gotoTenantList(): Promise<void> {
    await this.navigateTo('super/tenants');
  }

  /** Navigate to Tenant Create Form */
  async gotoTenantCreate(): Promise<void> {
    await this.navigateTo('super/tenants/create');
  }

  /** Navigate to Tenant Hierarchy */
  async gotoTenantHierarchy(): Promise<void> {
    await this.navigateTo('super/tenants/hierarchy');
  }

  /** Navigate to Super User List */
  async gotoUserList(): Promise<void> {
    await this.navigateTo('super/users');
  }

  /** Navigate to Super User Create Form */
  async gotoUserCreate(): Promise<void> {
    await this.navigateTo('super/users/create');
  }

  /** Navigate to Bulk Operations */
  async gotoBulkOperations(): Promise<void> {
    await this.navigateTo('super/bulk');
  }

  /** Navigate to Audit Log */
  async gotoAuditLog(): Promise<void> {
    await this.navigateTo('super/audit');
  }

  /** Navigate to Federation Controls */
  async gotoFederationControls(): Promise<void> {
    await this.navigateTo('super/federation');
  }

  // ─── Wait Helpers ──────────────────────────────────────────────────────────

  /**
   * Wait for the page to finish loading (spinner gone + heading visible)
   */
  async waitForPageLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    // Wait for the h1 heading rendered by PageHeader
    await this.pageHeading.waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
  }

  /**
   * Wait for loading spinners to disappear
   */
  async waitForLoadingComplete(): Promise<void> {
    // Wait for HeroUI Spinner or skeleton to disappear
    await this.page.waitForFunction(
      () => {
        const spinners = document.querySelectorAll('[role="progressbar"], [class*="spinner"]');
        return spinners.length === 0;
      },
      { timeout: 15000 }
    ).catch(() => {});
  }

  // ─── Common Queries ────────────────────────────────────────────────────────

  /**
   * Get all StatCard elements on the page.
   * StatCards are Cards with a label (text-default-500) and bold value.
   */
  getStatCards(): Locator {
    // StatCard renders: Card > CardBody > [icon div, content div with label p + value p]
    // Each stat card has a text-2xl bold value
    return this.page.locator('.text-2xl.font-bold').locator('..');
  }

  /**
   * Get the count of stat card elements
   */
  async getStatCardCount(): Promise<number> {
    // StatCards use the pattern: p.text-sm.text-default-500 (label) + p.text-2xl.font-bold (value)
    // They sit inside a grid with class grid-cols-1 ... lg:grid-cols-4
    const statGrid = this.page.locator('.grid.grid-cols-1').first();
    const cards = statGrid.locator('[data-slot="base"]').or(statGrid.locator(':scope > div'));
    return await cards.count();
  }

  /**
   * Get the HeroUI DataTable (table element)
   */
  getDataTable(): Locator {
    return this.dataTable;
  }

  /**
   * Get table header columns
   */
  getTableHeaders(): Locator {
    return this.dataTable.locator('th');
  }

  /**
   * Get table body rows
   */
  getTableRows(): Locator {
    return this.dataTable.locator('tbody tr');
  }

  /**
   * Get buttons matching specific text
   */
  getButton(text: string): Locator {
    return this.page.getByRole('button', { name: text });
  }

  /**
   * Get all switch/toggle elements on the page
   */
  getSwitches(): Locator {
    return this.page.locator('[role="switch"]');
  }

  /**
   * Get all input fields (HeroUI Input renders <input> inside wrapper)
   */
  getInputs(): Locator {
    return this.page.locator('input:not([type="hidden"])');
  }

  /**
   * Get a form input by its label text
   */
  getInputByLabel(label: string): Locator {
    return this.page.getByLabel(label);
  }

  /**
   * Get HeroUI Select components (rendered with data-slot="trigger")
   */
  getSelects(): Locator {
    return this.page.locator('[data-slot="trigger"]').or(this.page.locator('select'));
  }

  /**
   * Get HeroUI Tabs component
   */
  getTabs(): Locator {
    return this.page.locator('[role="tablist"]');
  }

  /**
   * Get a specific tab by its text content
   */
  getTab(text: string): Locator {
    return this.page.getByRole('tab', { name: text });
  }

  /**
   * Get HeroUI Chip elements
   */
  getChips(): Locator {
    return this.page.locator('[data-slot="base"][class*="chip"]').or(
      this.page.locator('.capitalize[class*="chip"]')
    );
  }

  /**
   * Get card elements with specific header text
   */
  getCardByHeader(headerText: string): Locator {
    return this.page.locator(`[data-slot="base"]:has-text("${headerText}")`).first();
  }

  /**
   * Check for Quick Actions section on dashboard
   */
  getQuickActions(): Locator {
    return this.page.locator('text=Quick Actions').locator('..');
  }

  // ─── Dashboard-specific ────────────────────────────────────────────────────

  /** Dashboard: Tenant overview cards grid */
  getDashboardTenantCards(): Locator {
    // The tenant cards are rendered in a grid below the "Tenants" heading
    return this.page.locator('h3:has-text("Tenants") ~ div [data-slot="base"]');
  }

  /** Dashboard: Refresh button */
  getRefreshButton(): Locator {
    return this.getButton('Refresh');
  }

  // ─── Tenant List-specific ──────────────────────────────────────────────────

  /** Tenant List: Create Tenant button */
  getCreateTenantButton(): Locator {
    return this.getButton('Create Tenant');
  }

  /** Tenant List: Filter tabs (All / Active / Inactive / Hub) */
  getTenantFilterTabs(): Locator {
    return this.getTabs();
  }

  // ─── Tenant Form-specific ──────────────────────────────────────────────────

  /** Tenant Form: Name input */
  getTenantNameInput(): Locator {
    return this.page.getByLabel('Tenant Name');
  }

  /** Tenant Form: Slug input */
  getTenantSlugInput(): Locator {
    return this.page.getByLabel('Slug');
  }

  /** Tenant Form: Domain input */
  getTenantDomainInput(): Locator {
    return this.page.getByLabel('Domain');
  }

  /** Tenant Form: Save/Create button */
  getTenantSaveButton(): Locator {
    return this.getButton('Create Tenant').or(this.getButton('Save Changes'));
  }

  /** Tenant Form: Back button */
  getBackButton(): Locator {
    return this.getButton('Back');
  }

  /** Tenant Form: Form tabs (Details, Contact, SEO, Location, Social, Features) */
  getTenantFormTabs(): Locator {
    return this.getTabs();
  }

  // ─── Tenant Hierarchy-specific ─────────────────────────────────────────────

  /** Hierarchy: Tree nodes (clickable items with Building2 icon) */
  getTreeNodes(): Locator {
    return this.page.locator('[role="button"]').filter({ hasText: /.+/ });
  }

  // ─── User List-specific ────────────────────────────────────────────────────

  /** User List: Create User button */
  getCreateUserButton(): Locator {
    return this.getButton('Create User');
  }

  /** User List: Tenant filter dropdown */
  getUserTenantFilter(): Locator {
    return this.page.getByLabel('Filter by Tenant');
  }

  // ─── User Form-specific ────────────────────────────────────────────────────

  /** User Form: Tenant selector (create mode only) */
  getUserTenantSelect(): Locator {
    return this.page.getByLabel('Tenant');
  }

  /** User Form: First Name input */
  getUserFirstNameInput(): Locator {
    return this.page.getByLabel('First Name');
  }

  /** User Form: Last Name input */
  getUserLastNameInput(): Locator {
    return this.page.getByLabel('Last Name');
  }

  /** User Form: Email input */
  getUserEmailInput(): Locator {
    return this.page.getByLabel('Email');
  }

  /** User Form: Password input (create mode only) */
  getUserPasswordInput(): Locator {
    return this.page.getByLabel('Password');
  }

  /** User Form: Role selector */
  getUserRoleSelect(): Locator {
    return this.page.getByLabel('Role');
  }

  /** User Form: Grant Tenant Super Admin switch */
  getUserSASwitch(): Locator {
    return this.page.locator('[role="switch"]').filter({ hasText: 'Grant Tenant Super Admin' });
  }

  /** User Form: Submit button */
  getUserSubmitButton(): Locator {
    return this.getButton('Create User').or(this.getButton('Update User'));
  }

  // ─── Bulk Operations-specific ──────────────────────────────────────────────

  /** Bulk Ops: Move Users card */
  getMoveUsersCard(): Locator {
    return this.page.locator('h3:has-text("Bulk Move Users")').locator('..').locator('..');
  }

  /** Bulk Ops: Update Tenants card */
  getUpdateTenantsCard(): Locator {
    return this.page.locator('h3:has-text("Bulk Update Tenants")').locator('..').locator('..');
  }

  /** Bulk Ops: Source Tenant selector */
  getSourceTenantSelect(): Locator {
    return this.page.getByLabel('Source Tenant');
  }

  /** Bulk Ops: Target Tenant selector */
  getTargetTenantSelect(): Locator {
    return this.page.getByLabel('Target Tenant');
  }

  /** Bulk Ops: Action selector (for tenants) */
  getBulkActionSelect(): Locator {
    return this.page.getByLabel('Action');
  }

  // ─── Audit Log-specific ────────────────────────────────────────────────────

  /** Audit Log: Action Type filter */
  getActionTypeFilter(): Locator {
    return this.page.getByLabel('Action Type');
  }

  /** Audit Log: Target Type filter */
  getTargetTypeFilter(): Locator {
    return this.page.getByLabel('Target Type');
  }

  /** Audit Log: Search input */
  getAuditSearchInput(): Locator {
    return this.page.getByLabel('Search');
  }

  // ─── Federation Controls-specific ──────────────────────────────────────────

  /** Federation: System Status card */
  getSystemStatusCard(): Locator {
    return this.page.locator('h3:has-text("System Status")').locator('..').locator('..');
  }

  /** Federation: Feature Controls card */
  getFeatureControlsCard(): Locator {
    return this.page.locator('h3:has-text("Feature Controls")').locator('..').locator('..');
  }

  /** Federation: Whitelist card */
  getWhitelistCard(): Locator {
    return this.page.locator('h3:has-text("Whitelist")').locator('..').locator('..');
  }

  /** Federation: Partnerships card */
  getPartnershipsCard(): Locator {
    return this.page.locator('h3:has-text("Partnerships")').locator('..').locator('..');
  }

  /** Federation: Federation toggle switch */
  getFederationToggle(): Locator {
    return this.page.locator('text=Federation').locator('..').locator('[role="switch"]').first();
  }

  /** Federation: Whitelist Mode toggle */
  getWhitelistModeToggle(): Locator {
    return this.page.locator('text=Whitelist Mode').locator('..').locator('[role="switch"]');
  }

  /** Federation: Emergency Lockdown / Lift Lockdown button */
  getLockdownButton(): Locator {
    return this.getButton('Emergency Lockdown').or(this.getButton('Lift Lockdown'));
  }

  /** Federation: Lockdown status chip */
  getLockdownStatus(): Locator {
    return this.page.locator('text=Lockdown Status').locator('..').locator('[class*="chip"]');
  }

  /** Federation: Add to whitelist input */
  getWhitelistTenantIdInput(): Locator {
    return this.page.getByLabel('Tenant ID');
  }
}

export default SuperAdminPage;
