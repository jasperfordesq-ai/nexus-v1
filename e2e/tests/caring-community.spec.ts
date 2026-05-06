// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test } from '@playwright/test';

const tenantSlug = 'hour-timebank';

async function mockAuthenticatedCaringSession(page: import('@playwright/test').Page): Promise<void> {
  await page.addInitScript(() => {
    localStorage.setItem('nexus_access_token', 'e2e-caring-token');
    localStorage.setItem('nexus_tenant_id', '2');
    localStorage.setItem('nexus_tenant_slug', 'hour-timebank');
  });

  await page.route('**/tenant/bootstrap**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          id: 2,
          name: 'hOUR Timebank',
          slug: tenantSlug,
          features: { caring_community: true },
          modules: {},
          branding: { name: 'hOUR Timebank' },
          supported_languages: ['en'],
          default_language: 'en',
        },
      }),
    });
  });

  await page.route('**/users/me**', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({
        success: true,
        data: {
          id: 101,
          tenant_id: 2,
          name: 'Caring Test Member',
          first_name: 'Caring',
          last_name: 'Member',
          email: 'caring.member@example.test',
          role: 'member',
          status: 'active',
        },
      }),
    });
  });
}

test.describe.skip('Caring Community flows', () => {
  test('request-help does not show success when the API returns success false', async ({ page }) => {
    await mockAuthenticatedCaringSession(page);

    await page.route('**/v2/caring-community/request-help', async (route) => {
      await route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ success: false, error: 'Validation failed' }),
      });
    });

    await page.goto(`/${tenantSlug}/caring-community/request-help`);
    await page.getByRole('button', { name: /accept all|essential only/i }).first().click().catch(() => undefined);

    await page.locator('textarea').nth(0).fill('I need help with a grocery pickup.');
    await page.locator('textarea').nth(1).fill('Tomorrow morning');
    await page.locator('button[type="submit"], button:has-text("Submit"), button:has-text("Request")').last().click();

    await expect(page.getByText('Validation failed')).toBeVisible();
    await expect(page.getByText(/Your request has been posted|success/i)).toHaveCount(0);
  });

  test('provider directory refetches when search text changes', async ({ page }) => {
    await mockAuthenticatedCaringSession(page);

    const providerUrls: string[] = [];
    await page.route('**/v2/caring-community/providers**', async (route) => {
      providerUrls.push(route.request().url());
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: { data: [], meta: { available_types: ['transport', 'healthcare'] } },
        }),
      });
    });

    await page.route('**/v2/caring-community/sub-regions**', async (route) => {
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({ success: true, data: [] }),
      });
    });

    await page.goto(`/${tenantSlug}/caring-community/providers`);
    await page.getByRole('button', { name: /accept all|essential only/i }).first().click().catch(() => undefined);

    const search = page.locator('input[aria-label]').first();
    await search.fill('clinic');
    await expect
      .poll(() => providerUrls.some((url) => url.includes('search=clinic')), { timeout: 5000 })
      .toBe(true);
  });
});
