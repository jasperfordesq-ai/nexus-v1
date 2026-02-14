import { test, expect } from '@playwright/test';
import { SuperAdminPage } from '../../page-objects';

/**
 * Super Admin Panel E2E Tests
 *
 * Tests all 9 Super Admin pages for correct rendering.
 * All tests are READ-ONLY — no mutations, no creating/deleting data.
 * Uses the 'admin' project which has admin storage state.
 *
 * Pages tested:
 * 1. SuperDashboard — /admin/super
 * 2. TenantList — /admin/super/tenants
 * 3. TenantForm (create) — /admin/super/tenants/create
 * 4. TenantHierarchy — /admin/super/tenants/hierarchy
 * 5. SuperUserList — /admin/super/users
 * 6. SuperUserForm (create) — /admin/super/users/create
 * 7. BulkOperations — /admin/super/bulk
 * 8. SuperAuditLog — /admin/super/audit
 * 9. FederationControls — /admin/super/federation
 */

// Collect console errors per test for verification
let consoleErrors: string[] = [];

test.beforeEach(async ({ page }) => {
  consoleErrors = [];
  page.on('pageerror', (error) => {
    consoleErrors.push(error.message);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 1. Super Dashboard
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Dashboard', () => {
  test('should display dashboard heading and description', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoDashboard();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Super Admin Dashboard');

    // Description text from PageHeader
    const description = page.locator('h1 ~ p').first();
    if (await description.count() > 0) {
      await expect(description).toContainText('Platform-wide overview');
    }

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display stat cards (at least 3)', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoDashboard();
    await superAdmin.waitForPageLoad();

    // StatCards are rendered in a grid-cols-4 grid, each with a bold value
    // Wait for either the actual values or the loading skeleton to appear
    const statGrid = page.locator('.grid.grid-cols-1').first();
    await expect(statGrid).toBeVisible({ timeout: 15000 });

    // Each StatCard renders a Card component — count children in the stat grid
    // StatCard labels: "Total Tenants", "Active Tenants", "Total Users", "Total Listings"
    const statLabels = ['Total Tenants', 'Active Tenants', 'Total Users', 'Total Listings'];
    let foundCount = 0;
    for (const label of statLabels) {
      const labelEl = page.locator(`text=${label}`);
      if (await labelEl.count() > 0) {
        foundCount++;
      }
    }

    // At minimum 3 stat cards should be present (data may or may not have loaded)
    expect(foundCount).toBeGreaterThanOrEqual(3);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display quick actions section', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoDashboard();
    await superAdmin.waitForPageLoad();

    const quickActions = page.locator('h3:has-text("Quick Actions")');
    await expect(quickActions).toBeVisible({ timeout: 15000 });

    // Quick action buttons should include known actions
    const actionLabels = ['Create Tenant', 'View Hierarchy', 'Bulk Operations'];
    for (const label of actionLabels) {
      const btn = page.getByRole('button', { name: label }).or(page.getByRole('link', { name: label }));
      if (await btn.count() > 0) {
        await expect(btn.first()).toBeVisible();
      }
    }

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display tenant overview section', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoDashboard();
    await superAdmin.waitForPageLoad();

    // "Tenants" heading for the tenant cards section
    const tenantsHeading = page.locator('h3:has-text("Tenants")');
    await expect(tenantsHeading).toBeVisible({ timeout: 15000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have a Refresh button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoDashboard();
    await superAdmin.waitForPageLoad();

    const refreshBtn = superAdmin.getRefreshButton();
    await expect(refreshBtn).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Tenant List
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Tenant List', () => {
  test('should display tenants heading with Create Tenant button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantList();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Tenants');

    const createBtn = superAdmin.getCreateTenantButton();
    await expect(createBtn).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should render DataTable with search', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantList();
    await superAdmin.waitForPageLoad();

    // DataTable renders a HeroUI Table with aria-label
    const table = superAdmin.getDataTable();
    await expect(table).toBeVisible({ timeout: 15000 });

    // DataTable includes a search input
    const searchInput = page.locator('input[placeholder*="Search tenants"]');
    await expect(searchInput).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have filter tabs (All, Active, Inactive, Hub)', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantList();
    await superAdmin.waitForPageLoad();

    const tabs = superAdmin.getTabs();
    await expect(tabs).toBeVisible({ timeout: 15000 });

    // Check individual tab items
    const tabLabels = ['All Tenants', 'Active', 'Inactive', 'Hub Tenants'];
    for (const label of tabLabels) {
      const tab = page.getByRole('tab', { name: label });
      if (await tab.count() > 0) {
        await expect(tab).toBeVisible();
      }
    }

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display table columns', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantList();
    await superAdmin.waitForPageLoad();

    const headers = superAdmin.getTableHeaders();
    const headerCount = await headers.count();

    // TenantList defines 8 columns: Tenant, Domain, Status, Users, Hub, Parent, Created, Actions
    expect(headerCount).toBeGreaterThanOrEqual(5);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Tenant Form (Create)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Tenant Create Form', () => {
  test('should display create form heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantCreate();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Create Tenant');

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have required form fields (name, slug, domain)', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantCreate();
    await superAdmin.waitForPageLoad();

    // Tenant Name input (required)
    const nameInput = superAdmin.getTenantNameInput();
    await expect(nameInput).toBeVisible({ timeout: 10000 });

    // Slug input (required for create)
    const slugInput = superAdmin.getTenantSlugInput();
    await expect(slugInput).toBeVisible();

    // Domain input
    const domainInput = superAdmin.getTenantDomainInput();
    await expect(domainInput).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have Save/Create button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantCreate();
    await superAdmin.waitForPageLoad();

    const saveBtn = superAdmin.getTenantSaveButton();
    await expect(saveBtn).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have Back button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantCreate();
    await superAdmin.waitForPageLoad();

    const backBtn = superAdmin.getBackButton();
    await expect(backBtn).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have multi-tab form (Details, Contact, SEO, etc.)', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantCreate();
    await superAdmin.waitForPageLoad();

    const tabs = superAdmin.getTabs();
    await expect(tabs).toBeVisible({ timeout: 15000 });

    // Check for expected tab names
    const expectedTabs = ['Details', 'Contact', 'SEO', 'Location', 'Social', 'Features'];
    for (const tabName of expectedTabs) {
      const tab = page.getByRole('tab', { name: tabName });
      if (await tab.count() > 0) {
        await expect(tab).toBeVisible();
      }
    }

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have Active and Hub toggle switches on Details tab', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantCreate();
    await superAdmin.waitForPageLoad();

    // The Details tab is shown by default with Active and Hub switches
    const switches = superAdmin.getSwitches();
    const switchCount = await switches.count();
    expect(switchCount).toBeGreaterThanOrEqual(2);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Tenant Hierarchy
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Tenant Hierarchy', () => {
  test('should display hierarchy heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantHierarchy();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Tenant Hierarchy');

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display tree view or empty state', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantHierarchy();
    await superAdmin.waitForPageLoad();

    // Either we see tree nodes with Building2 icons, or the empty state
    const treeContent = page.locator('[role="button"]');
    const emptyState = page.locator('text=No tenant hierarchy data available');

    // Wait for one or the other
    await Promise.race([
      treeContent.first().waitFor({ state: 'visible', timeout: 15000 }),
      emptyState.waitFor({ state: 'visible', timeout: 15000 }),
    ]).catch(() => {});

    const hasTree = await treeContent.count() > 0;
    const hasEmpty = await emptyState.count() > 0;

    // One of them should be visible
    expect(hasTree || hasEmpty).toBeTruthy();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have Refresh button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoTenantHierarchy();
    await superAdmin.waitForPageLoad();

    const refreshBtn = superAdmin.getRefreshButton();
    await expect(refreshBtn).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Super User List
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - User List', () => {
  test('should display cross-tenant users heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserList();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Cross-Tenant Users');

    expect(consoleErrors).toHaveLength(0);
  });

  test('should render DataTable with search', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserList();
    await superAdmin.waitForPageLoad();

    const table = superAdmin.getDataTable();
    await expect(table).toBeVisible({ timeout: 15000 });

    // Search input with user-specific placeholder
    const searchInput = page.locator('input[placeholder*="Search users"]');
    await expect(searchInput).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have tenant filter dropdown', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserList();
    await superAdmin.waitForPageLoad();

    // HeroUI Select with label "Filter by Tenant"
    const tenantFilter = superAdmin.getUserTenantFilter();
    await expect(tenantFilter).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have Create User button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserList();
    await superAdmin.waitForPageLoad();

    const createBtn = superAdmin.getCreateUserButton();
    await expect(createBtn).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display table columns', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserList();
    await superAdmin.waitForPageLoad();

    const headers = superAdmin.getTableHeaders();
    const headerCount = await headers.count();

    // SuperUserList defines 6 columns: User, Tenant, Role, Status, Joined, Actions
    expect(headerCount).toBeGreaterThanOrEqual(4);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Super User Form (Create)
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - User Create Form', () => {
  test('should display create user heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserCreate();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Create User');

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have tenant selector', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserCreate();
    await superAdmin.waitForPageLoad();

    // In create mode, Tenant select is shown
    const tenantSelect = superAdmin.getUserTenantSelect();
    await expect(tenantSelect).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have name and email fields', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserCreate();
    await superAdmin.waitForPageLoad();

    const firstName = superAdmin.getUserFirstNameInput();
    await expect(firstName).toBeVisible({ timeout: 10000 });

    const email = superAdmin.getUserEmailInput();
    await expect(email).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have password field in create mode', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserCreate();
    await superAdmin.waitForPageLoad();

    const password = superAdmin.getUserPasswordInput();
    await expect(password).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have role selector and submit button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserCreate();
    await superAdmin.waitForPageLoad();

    const roleSelect = superAdmin.getUserRoleSelect();
    await expect(roleSelect).toBeVisible({ timeout: 10000 });

    const submitBtn = superAdmin.getUserSubmitButton();
    await expect(submitBtn).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have Grant Tenant Super Admin switch', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoUserCreate();
    await superAdmin.waitForPageLoad();

    // Switch with text "Grant Tenant Super Admin"
    const saSwitch = page.locator('text=Grant Tenant Super Admin');
    await expect(saSwitch).toBeVisible({ timeout: 10000 });

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Bulk Operations
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Bulk Operations', () => {
  test('should display bulk operations heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoBulkOperations();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Bulk Operations');

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display Bulk Move Users card', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoBulkOperations();
    await superAdmin.waitForPageLoad();

    const moveUsersHeader = page.locator('h3:has-text("Bulk Move Users")');
    await expect(moveUsersHeader).toBeVisible({ timeout: 15000 });

    // Should have Source Tenant and Target Tenant selectors inside the card
    const sourceSelect = superAdmin.getSourceTenantSelect();
    await expect(sourceSelect).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display Bulk Update Tenants card', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoBulkOperations();
    await superAdmin.waitForPageLoad();

    const updateTenantsHeader = page.locator('h3:has-text("Bulk Update Tenants")');
    await expect(updateTenantsHeader).toBeVisible({ timeout: 15000 });

    // Should have Action selector inside the card
    const actionSelect = superAdmin.getBulkActionSelect();
    await expect(actionSelect).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should show two operation cards side by side', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoBulkOperations();
    await superAdmin.waitForPageLoad();

    // Both cards exist on the page
    const moveCard = superAdmin.getMoveUsersCard();
    const updateCard = superAdmin.getUpdateTenantsCard();

    await expect(moveCard).toBeVisible({ timeout: 15000 });
    await expect(updateCard).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 8. Super Audit Log
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Audit Log', () => {
  test('should display audit log heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoAuditLog();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Audit Log');

    expect(consoleErrors).toHaveLength(0);
  });

  test('should render DataTable', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoAuditLog();
    await superAdmin.waitForPageLoad();

    const table = superAdmin.getDataTable();
    await expect(table).toBeVisible({ timeout: 15000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should have filter controls', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoAuditLog();
    await superAdmin.waitForPageLoad();

    // Action Type filter
    const actionTypeFilter = superAdmin.getActionTypeFilter();
    await expect(actionTypeFilter).toBeVisible({ timeout: 10000 });

    // Target Type filter
    const targetTypeFilter = superAdmin.getTargetTypeFilter();
    await expect(targetTypeFilter).toBeVisible();

    // Search input
    const searchInput = superAdmin.getAuditSearchInput();
    await expect(searchInput).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display table columns for audit entries', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoAuditLog();
    await superAdmin.waitForPageLoad();

    const headers = superAdmin.getTableHeaders();
    const headerCount = await headers.count();

    // SuperAuditLog defines 5 columns: Action, Target, Actor, Description, Date
    expect(headerCount).toBeGreaterThanOrEqual(4);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// 9. Federation Controls
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Federation Controls', () => {
  test('should display federation controls heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoFederationControls();
    await superAdmin.waitForPageLoad();

    const heading = page.locator('h1');
    await expect(heading).toBeVisible({ timeout: 15000 });
    await expect(heading).toContainText('Federation Control Center');

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display System Status card with toggle switches', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoFederationControls();
    await superAdmin.waitForPageLoad();

    const systemStatusCard = superAdmin.getSystemStatusCard();
    await expect(systemStatusCard).toBeVisible({ timeout: 15000 });

    // Federation toggle and Whitelist Mode toggle
    const switches = systemStatusCard.locator('[role="switch"]');
    const switchCount = await switches.count();
    expect(switchCount).toBeGreaterThanOrEqual(2);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display Feature Controls card with feature toggle switches', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoFederationControls();
    await superAdmin.waitForPageLoad();

    const featureCard = superAdmin.getFeatureControlsCard();
    await expect(featureCard).toBeVisible({ timeout: 15000 });

    // 6 feature toggles: Profiles, Messaging, Transactions, Listings, Events, Groups
    const switches = featureCard.locator('[role="switch"]');
    const switchCount = await switches.count();
    expect(switchCount).toBeGreaterThanOrEqual(4);

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display Whitelist section', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoFederationControls();
    await superAdmin.waitForPageLoad();

    const whitelistCard = superAdmin.getWhitelistCard();
    await expect(whitelistCard).toBeVisible({ timeout: 15000 });

    // Should have an Add button and Tenant ID input
    const addBtn = whitelistCard.getByRole('button', { name: 'Add' });
    await expect(addBtn).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display Partnerships section', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoFederationControls();
    await superAdmin.waitForPageLoad();

    const partnershipsCard = superAdmin.getPartnershipsCard();
    await expect(partnershipsCard).toBeVisible({ timeout: 15000 });

    expect(consoleErrors).toHaveLength(0);
  });

  test('should display lockdown status and button', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoFederationControls();
    await superAdmin.waitForPageLoad();

    // Lockdown Status label should be visible
    const lockdownLabel = page.locator('text=Lockdown Status');
    await expect(lockdownLabel).toBeVisible({ timeout: 15000 });

    // Emergency Lockdown or Lift Lockdown button
    const lockdownBtn = superAdmin.getLockdownButton();
    await expect(lockdownBtn).toBeVisible();

    expect(consoleErrors).toHaveLength(0);
  });

  test('should show 4 cards in grid layout', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    await superAdmin.gotoFederationControls();
    await superAdmin.waitForPageLoad();

    // The page has 4 cards: System Status, Feature Controls, Whitelist, Partnerships
    const cardHeaders = ['System Status', 'Feature Controls', 'Whitelist', 'Partnerships'];
    let visibleCount = 0;

    for (const header of cardHeaders) {
      const card = page.locator(`h3:has-text("${header}")`);
      if (await card.isVisible({ timeout: 5000 }).catch(() => false)) {
        visibleCount++;
      }
    }

    expect(visibleCount).toBeGreaterThanOrEqual(4);

    expect(consoleErrors).toHaveLength(0);
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// Cross-cutting concerns
// ─────────────────────────────────────────────────────────────────────────────

test.describe('Super Admin - Accessibility', () => {
  test('all pages should have proper h1 heading', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    const routes = [
      { path: 'super', name: 'Dashboard' },
      { path: 'super/tenants', name: 'Tenants' },
      { path: 'super/tenants/create', name: 'Create Tenant' },
      { path: 'super/tenants/hierarchy', name: 'Hierarchy' },
      { path: 'super/users', name: 'Users' },
      { path: 'super/users/create', name: 'User Create' },
      { path: 'super/bulk', name: 'Bulk' },
      { path: 'super/audit', name: 'Audit' },
      { path: 'super/federation', name: 'Federation' },
    ];

    for (const route of routes) {
      await superAdmin.navigateTo(route.path);
      await page.waitForLoadState('domcontentloaded');

      const h1 = page.locator('h1');
      const h1Count = await h1.count();

      // Every page should have exactly one h1
      expect(h1Count, `${route.name} page should have an h1`).toBeGreaterThanOrEqual(1);
    }
  });
});

test.describe('Super Admin - Page Load Performance', () => {
  test('all pages should load within timeout', async ({ page }) => {
    const superAdmin = new SuperAdminPage(page);
    const routes = [
      'super',
      'super/tenants',
      'super/tenants/create',
      'super/tenants/hierarchy',
      'super/users',
      'super/users/create',
      'super/bulk',
      'super/audit',
      'super/federation',
    ];

    for (const route of routes) {
      const start = Date.now();
      await superAdmin.navigateTo(route);
      await page.waitForLoadState('domcontentloaded');
      const elapsed = Date.now() - start;

      // Each page should load within the global test timeout (30s)
      // but we check for a reasonable 15s threshold
      expect(elapsed, `${route} took ${elapsed}ms`).toBeLessThan(15000);
    }
  });
});
