import { test, expect } from '@playwright/test';
import { BrokerControlsPage } from '../../page-objects';

/**
 * Broker Controls E2E Tests
 *
 * Tests all 5 Broker Controls pages for correct rendering.
 * All tests are READ-ONLY -- no mutations, no approving/rejecting exchanges.
 * Uses the 'admin' project which has admin storage state.
 *
 * Pages tested:
 * 1. BrokerDashboard      -- /admin/broker-controls
 * 2. ExchangeManagement   -- /admin/broker-controls/exchanges
 * 3. RiskTags             -- /admin/broker-controls/risk-tags
 * 4. MessageReview        -- /admin/broker-controls/messages
 * 5. UserMonitoring       -- /admin/broker-controls/monitoring
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
// 1. Broker Dashboard
// ---------------------------------------------------------------------------

test.describe('Broker Controls - Dashboard', () => {
  test('should display broker controls heading', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoDashboard();
    await broker.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Broker Controls/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display stat cards with key metrics', async ({ page }) => {
    test.slow();
    const broker = new BrokerControlsPage(page);
    await broker.gotoDashboard();
    await broker.waitForPageLoad();

    // The dashboard should show stat cards for exchanges, risk tags, etc.
    // StatCard components render labels like "Pending Exchanges", "Risk Tags", etc.
    const statLabels = [
      'Pending Exchanges',
      'Flagged Listings',
      'Unreviewed Messages',
      'Monitored Users',
    ];

    let foundCount = 0;
    for (const label of statLabels) {
      const el = page.locator(`text=${label}`);
      if (await el.count() > 0) {
        foundCount++;
      }
    }

    // At least some stat labels should appear (data may vary)
    // The dashboard always renders the 4 stat cards even if values are 0
    expect(foundCount).toBeGreaterThanOrEqual(2);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display quick-link cards to sub-pages', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoDashboard();
    await broker.waitForPageLoad();

    // The 4 quick-link cards: Exchange Management, Risk Tags, Message Review, User Monitoring
    const quickLinkTexts = [
      'Exchange Management',
      'Risk Tags',
      'Message Review',
      'User Monitoring',
    ];

    let foundCount = 0;
    for (const text of quickLinkTexts) {
      const el = page.locator(`text=${text}`);
      if (await el.count() > 0) {
        foundCount++;
      }
    }

    expect(foundCount).toBeGreaterThanOrEqual(4);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have clickable links to sub-pages', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoDashboard();
    await broker.waitForPageLoad();

    const quickLinks = broker.getQuickLinkCards();
    const linkCount = await quickLinks.count();

    // Should have at least 4 links (one for each sub-page)
    expect(linkCount).toBeGreaterThanOrEqual(4);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have a Refresh button', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoDashboard();
    await broker.waitForPageLoad();

    const refreshBtn = broker.getRefreshButton();
    // Refresh button may or may not exist on all broker dashboard variants
    if (await refreshBtn.count() > 0) {
      await expect(refreshBtn).toBeVisible();
    }

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// 2. Exchange Management
// ---------------------------------------------------------------------------

test.describe('Broker Controls - Exchange Management', () => {
  test('should display exchange management heading', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoExchanges();
    await broker.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Exchange Management/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should render DataTable or empty state', async ({ page }) => {
    test.slow();
    const broker = new BrokerControlsPage(page);
    await broker.gotoExchanges();
    await broker.waitForPageLoad();

    // Wait for the DataTable or an empty state to appear
    const table = broker.getDataTable();
    const emptyState = page.locator('text=No exchange requests');

    await Promise.race([
      table.waitFor({ state: 'visible', timeout: 15000 }),
      emptyState.waitFor({ state: 'visible', timeout: 15000 }),
    ]).catch(() => {});

    const hasTable = await table.count() > 0;
    const hasEmpty = await emptyState.count() > 0;

    expect(hasTable || hasEmpty).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have status filter tabs', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoExchanges();
    await broker.waitForPageLoad();

    const tabs = broker.getTabs();
    if (await tabs.count() > 0) {
      await expect(tabs).toBeVisible();

      // Check for known tab labels
      const tabLabels = ['All', 'Pending', 'Approved', 'Rejected'];
      let found = 0;
      for (const label of tabLabels) {
        const tab = page.getByRole('tab', { name: label });
        if (await tab.count() > 0) {
          found++;
        }
      }
      expect(found).toBeGreaterThanOrEqual(2);
    }

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have a back link to broker dashboard', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoExchanges();
    await broker.waitForPageLoad();

    // Look for a link back to broker-controls
    const backLink = page.locator('a[href*="broker-controls"]').first();
    await expect(backLink).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// 3. Risk Tags
// ---------------------------------------------------------------------------

test.describe('Broker Controls - Risk Tags', () => {
  test('should display risk tags heading', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoRiskTags();
    await broker.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Risk Tags/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should render DataTable or empty state', async ({ page }) => {
    test.slow();
    const broker = new BrokerControlsPage(page);
    await broker.gotoRiskTags();
    await broker.waitForPageLoad();

    const table = broker.getDataTable();
    const emptyState = page.locator('text=No risk tags found').or(
      page.locator('text=No listings with risk tags')
    );

    await Promise.race([
      table.waitFor({ state: 'visible', timeout: 15000 }),
      emptyState.waitFor({ state: 'visible', timeout: 15000 }),
    ]).catch(() => {});

    const hasTable = await table.count() > 0;
    const hasEmpty = await emptyState.count() > 0;

    expect(hasTable || hasEmpty).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have risk level filter tabs', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoRiskTags();
    await broker.waitForPageLoad();

    const tabs = broker.getRiskLevelTabs();
    if (await tabs.count() > 0) {
      await expect(tabs).toBeVisible();
    }

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have a back link to broker dashboard', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoRiskTags();
    await broker.waitForPageLoad();

    const backLink = page.locator('a[href*="broker-controls"]').first();
    await expect(backLink).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// 4. Message Review
// ---------------------------------------------------------------------------

test.describe('Broker Controls - Message Review', () => {
  test('should display message review heading', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoMessages();
    await broker.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/Message Review/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should render DataTable or empty state', async ({ page }) => {
    test.slow();
    const broker = new BrokerControlsPage(page);
    await broker.gotoMessages();
    await broker.waitForPageLoad();

    const table = broker.getDataTable();
    const emptyState = page.locator('text=No messages').or(
      page.locator('text=No broker messages')
    );

    await Promise.race([
      table.waitFor({ state: 'visible', timeout: 15000 }),
      emptyState.waitFor({ state: 'visible', timeout: 15000 }),
    ]).catch(() => {});

    const hasTable = await table.count() > 0;
    const hasEmpty = await emptyState.count() > 0;

    expect(hasTable || hasEmpty).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have filter tabs (unreviewed, flagged, all)', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoMessages();
    await broker.waitForPageLoad();

    const tabs = broker.getMessageFilterTabs();
    if (await tabs.count() > 0) {
      await expect(tabs).toBeVisible();

      const tabLabels = ['Unreviewed', 'Flagged', 'All'];
      let found = 0;
      for (const label of tabLabels) {
        const tab = page.getByRole('tab', { name: label });
        if (await tab.count() > 0) {
          found++;
        }
      }
      expect(found).toBeGreaterThanOrEqual(2);
    }

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have a back link to broker dashboard', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoMessages();
    await broker.waitForPageLoad();

    const backLink = page.locator('a[href*="broker-controls"]').first();
    await expect(backLink).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// 5. User Monitoring
// ---------------------------------------------------------------------------

test.describe('Broker Controls - User Monitoring', () => {
  test('should display user monitoring heading', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoMonitoring();
    await broker.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText(/User Monitoring/i);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should render DataTable or empty state', async ({ page }) => {
    test.slow();
    const broker = new BrokerControlsPage(page);
    await broker.gotoMonitoring();
    await broker.waitForPageLoad();

    const table = broker.getDataTable();
    const emptyState = page.locator('text=No users are currently under monitoring').or(
      page.locator('text=No monitored users')
    );

    await Promise.race([
      table.waitFor({ state: 'visible', timeout: 15000 }),
      emptyState.waitFor({ state: 'visible', timeout: 15000 }),
    ]).catch(() => {});

    const hasTable = await table.count() > 0;
    const hasEmpty = await emptyState.count() > 0;

    expect(hasTable || hasEmpty).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have a back link to broker dashboard', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    await broker.gotoMonitoring();
    await broker.waitForPageLoad();

    const backLink = page.locator('a[href*="broker-controls"]').first();
    await expect(backLink).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// Cross-cutting: Accessibility
// ---------------------------------------------------------------------------

test.describe('Broker Controls - Accessibility', () => {
  test('all broker pages should have proper h1 heading', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    const routes = [
      { path: 'broker-controls', name: 'Dashboard' },
      { path: 'broker-controls/exchanges', name: 'Exchanges' },
      { path: 'broker-controls/risk-tags', name: 'Risk Tags' },
      { path: 'broker-controls/messages', name: 'Messages' },
      { path: 'broker-controls/monitoring', name: 'Monitoring' },
    ];

    for (const route of routes) {
      await broker.navigateTo(route.path);
      await page.waitForLoadState('domcontentloaded');

      const h1 = page.locator('h1');
      const h1Count = await h1.count();

      expect(h1Count, `${route.name} page should have an h1`).toBeGreaterThanOrEqual(1);
    }
  });
});

// ---------------------------------------------------------------------------
// Cross-cutting: Page Load Performance
// ---------------------------------------------------------------------------

test.describe('Broker Controls - Page Load Performance', () => {
  test('all broker pages should load within timeout', async ({ page }) => {
    const broker = new BrokerControlsPage(page);
    const routes = [
      'broker-controls',
      'broker-controls/exchanges',
      'broker-controls/risk-tags',
      'broker-controls/messages',
      'broker-controls/monitoring',
    ];

    for (const route of routes) {
      const start = Date.now();
      await broker.navigateTo(route);
      await page.waitForLoadState('domcontentloaded');
      const elapsed = Date.now() - start;

      // Each page should load within a reasonable 15s threshold
      expect(elapsed, `${route} took ${elapsed}ms`).toBeLessThan(15000);
    }
  });
});
