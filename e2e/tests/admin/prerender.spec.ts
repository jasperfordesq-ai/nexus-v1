// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test, type Page } from '@playwright/test';

const TENANT = process.env.E2E_TENANT || 'hour-timebank';

async function stubPrerenderApi(page: Page): Promise<void> {
  await page.route('**/api/v2/admin/prerender/health', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { status: 'green', checks: [] } }),
    });
  });

  await page.route('**/api/v2/admin/prerender/summary', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          total_files: 2,
          total_size_bytes: 1200,
          oldest_age_s: 10,
          newest_age_s: 2,
          by_status: { fresh: 1, warn: 1, stale: 0 },
          queue: { queued: 0, running: 0, failed: 0 },
          last_event_at: null,
          build_commit: 'test',
          cache_path: '/tmp/prerender',
          realtime_channel: 'prerender',
        },
      }),
    });
  });

  await page.route('**/api/v2/admin/prerender/tenant-safety**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          tenant: { id: 2, slug: TENANT, host: 'app.project-nexus.ie', prefix: `/${TENANT}` },
          counts: {
            expected: 3,
            snapshots: 2,
            missing: 1,
            stale: 1,
            asset_invalid: 1,
            unexpected: 1,
            static: 1,
            sitemap: 2,
          },
          expected_routes: ['/', '/page/shared'],
          missing_routes: ['/page/missing'],
          unexpected_routes: ['/page/tenant-ten-only'],
          stale_routes: ['/page/shared'],
          asset_invalid_routes: ['/page/shared'],
          snapshots: [
            {
              route: '/page/tenant-ten-only',
              cache_path: 'app.project-nexus.ie/hour-timebank/page/tenant-ten-only/index.html',
              expected: false,
              source: 'unexpected',
              staleness: 'fresh',
              content_stale: false,
              asset_issues: [],
              reason: { key: 'cms_page_not_published_for_tenant', value: 'tenant-ten-only' },
            },
            {
              route: '/page/shared',
              cache_path: 'app.project-nexus.ie/hour-timebank/page/shared/index.html',
              expected: true,
              source: 'sitemap',
              staleness: 'warn',
              content_stale: true,
              asset_issues: ['missing.js'],
              reason: { key: 'sitemap_route', value: '/page/shared' },
            },
          ],
        },
      }),
    });
  });

  await page.route('**/api/v2/admin/prerender/purge', async (route) => {
    const request = route.request();
    const payload = request.postDataJSON() as Record<string, unknown>;
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          dry_run: Boolean(payload.dry_run),
          deleted_count: 1,
          deleted: ['app.project-nexus.ie/hour-timebank/page/shared/index.html'],
        },
      }),
    });
  });

  await page.route('**/api/v2/admin/prerender/jobs', async (route) => {
    const request = route.request();
    if (request.method() !== 'POST') {
      await route.continue();
      return;
    }

    const payload = request.postDataJSON() as Record<string, unknown>;
    const routes = String(payload.routes ?? '');
    const tenantSlug = String(payload.tenant_slug ?? '');
    const isTenantOwnedRoute = routes.split(',').map((routeName) => routeName.trim()).some((routeName) => /^\/(?:page|blog|listings)\//.test(routeName));

    if (isTenantOwnedRoute && !tenantSlug) {
      await route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          success: false,
          error: 'Tenant-owned routes require a tenant slug.',
        }),
      });
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: { job_id: 42 },
      }),
    });
  });
}

test.describe('Admin - Prerender Engine', () => {
  test.beforeEach(({}, testInfo) => {
    testInfo.setTimeout(90_000);
  });

  test('shows tenant safety guidance and protects live all-tenant purge', async ({ page }) => {
    test.skip(
      !process.env.E2E_ADMIN_EMAIL || !process.env.E2E_ADMIN_PASSWORD,
      'No E2E admin credentials configured',
    );

    await stubPrerenderApi(page);

    await page.goto(`/${TENANT}/admin/seo/prerender?tab=tenant-safety`, { waitUntil: 'domcontentloaded' });

    await expect(page.getByRole('heading', { name: /Tenant safety/i })).toBeVisible({ timeout: 30_000 });
    await page.getByLabel(/Tenant slug/i).fill(TENANT);
    await page.getByRole('button', { name: /Inspect/i }).click();

    await expect(page.getByText('/page/tenant-ten-only')).toBeVisible();
    await expect(page.getByText(/not published/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /Open inventory/i })).toBeVisible();

    await page.getByRole('tab', { name: /Overview/i }).click();
    await page.getByLabel(/^Tenant slug$/i).fill(TENANT);
    await page.getByLabel(/^Routes$/i).fill('/page/shared');
    const scopedJob = page.waitForResponse((response) => (
      response.url().includes('/api/v2/admin/prerender/jobs') && response.request().method() === 'POST'
    ));
    await page.getByRole('button', { name: /Queue job/i }).click();
    await expect((await scopedJob).status()).toBe(200);

    await page.getByLabel(/^Routes$/i).fill('/page/tenant-ten-only');
    const rejectedJob = page.waitForResponse((response) => (
      response.url().includes('/api/v2/admin/prerender/jobs') && response.request().method() === 'POST'
    ));
    await page.getByRole('button', { name: /Queue job/i }).click();
    await expect((await rejectedJob).status()).toBe(422);

    await page.getByLabel(/Pattern/i).fill('**/*');
    await page.getByLabel(/Preview only/i).uncheck();

    const blockedDelete = page.getByRole('button', { name: /Delete snapshots/i });
    await expect(blockedDelete).toBeDisabled();

    await page.getByLabel(/Preview only/i).check();
    await page.getByRole('button', { name: /Preview delete/i }).click();
    await expect(page.getByText(/Would delete/i)).toBeVisible();

    await page.getByLabel(/Preview only/i).uncheck();
    await expect(blockedDelete).toBeDisabled();
    await page.getByLabel(/I understand this delete applies to every tenant/i).check();
    await page.getByLabel(/Type ALL TENANTS/i).fill('ALL TENANTS');
    await expect(blockedDelete).toBeEnabled();
  });
});
