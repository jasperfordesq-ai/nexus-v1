import { Page, Locator } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Broker Controls Page Object (React Admin with HeroUI)
 *
 * Provides navigation and element access for the 5+ Broker Controls pages:
 * 1. BrokerDashboard      -- /admin/broker-controls
 * 2. ExchangeManagement   -- /admin/broker-controls/exchanges
 * 3. RiskTags             -- /admin/broker-controls/risk-tags
 * 4. MessageReview        -- /admin/broker-controls/messages
 * 5. UserMonitoring       -- /admin/broker-controls/monitoring
 * 6. VettingRecords       -- /admin/broker-controls/vetting (if enabled)
 *
 * Uses HeroUI-compatible selectors and tenant-aware routing.
 */
export class BrokerControlsPage extends BasePage {
  /** Page heading rendered by PageHeader component (h1) */
  readonly pageHeading: Locator;

  /** Loading spinner */
  readonly spinner: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.pageHeading = page.locator('h1');
    this.spinner = page.locator('[class*="spinner"], [role="progressbar"]');
  }

  // -- Navigation ----------------------------------------------------------

  /** Navigate to a broker controls page by path */
  async navigateTo(path: string): Promise<void> {
    await this.goto(`admin/${path.replace(/^\//, '')}`);
  }

  /** Navigate to Broker Dashboard */
  async gotoDashboard(): Promise<void> {
    await this.navigateTo('broker-controls');
  }

  /** Navigate to Exchange Management */
  async gotoExchanges(): Promise<void> {
    await this.navigateTo('broker-controls/exchanges');
  }

  /** Navigate to Risk Tags */
  async gotoRiskTags(): Promise<void> {
    await this.navigateTo('broker-controls/risk-tags');
  }

  /** Navigate to Message Review */
  async gotoMessages(): Promise<void> {
    await this.navigateTo('broker-controls/messages');
  }

  /** Navigate to User Monitoring */
  async gotoMonitoring(): Promise<void> {
    await this.navigateTo('broker-controls/monitoring');
  }

  // -- Wait Helpers ---------------------------------------------------------

  /** Wait for the page heading to appear */
  async waitForPageLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.pageHeading.waitFor({ state: 'visible', timeout: 15000 }).catch(() => {});
  }

  // -- Dashboard-specific ---------------------------------------------------

  /** Dashboard: Get all StatCard elements (Cards with bold numeric values) */
  getStatCards(): Locator {
    // StatCard renders a Card with a label + bold value inside
    return this.page.locator('[data-slot="base"]').filter({ has: this.page.locator('.text-2xl, .text-3xl') });
  }

  /** Dashboard: Get quick-link cards (the 5 navigation cards) */
  getQuickLinkCards(): Locator {
    // Quick link cards contain ChevronRight icon and link to sub-pages
    return this.page.locator('a[href*="broker-controls/"]');
  }

  /** Dashboard: Refresh button */
  getRefreshButton(): Locator {
    return this.page.getByRole('button', { name: 'Refresh' });
  }

  // -- Exchange Management-specific -----------------------------------------

  /** Exchanges: DataTable */
  getDataTable(): Locator {
    return this.page.locator('table[aria-label="Admin data table"]');
  }

  /** Exchanges: Table header columns */
  getTableHeaders(): Locator {
    return this.getDataTable().locator('th');
  }

  /** Exchanges: Status filter tabs */
  getTabs(): Locator {
    return this.page.locator('[role="tablist"]');
  }

  /** Exchanges: Back button (arrow link to broker dashboard) */
  getBackLink(): Locator {
    return this.page.locator('a[href*="broker-controls"]').filter({ hasText: /back/i }).first()
      .or(this.page.locator('a[href="/admin/broker-controls"]').first());
  }

  // -- Risk Tags-specific ---------------------------------------------------

  /** Risk Tags: Risk level filter tabs */
  getRiskLevelTabs(): Locator {
    return this.getTabs();
  }

  // -- Message Review-specific ----------------------------------------------

  /** Messages: Filter tabs (unreviewed, flagged, all) */
  getMessageFilterTabs(): Locator {
    return this.getTabs();
  }

  // -- User Monitoring-specific ---------------------------------------------

  /** Monitoring: Get the monitoring data table or empty state */
  getMonitoringContent(): Locator {
    return this.getDataTable()
      .or(this.page.locator('text=No users are currently under monitoring'));
  }

  // -- Shared ---------------------------------------------------------------

  /** Get a button by text */
  getButton(text: string): Locator {
    return this.page.getByRole('button', { name: text });
  }
}

export default BrokerControlsPage;
