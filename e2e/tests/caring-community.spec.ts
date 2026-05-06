// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test } from '@playwright/test';

const tenantSlug = 'hour-timebank';

function captureBrowserDiagnostics(page: import('@playwright/test').Page) {
  const consoleErrors: string[] = [];
  const failedRequests: string[] = [];

  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });

  page.on('pageerror', (error) => {
    consoleErrors.push(error.message);
  });

  page.on('requestfailed', (request) => {
    failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText ?? ''}`.trim());
  });

  return { consoleErrors, failedRequests };
}

async function mockAuthenticatedCaringSession(
  page: import('@playwright/test').Page,
  role: 'member' | 'admin' = 'member'
): Promise<void> {
  await page.addInitScript(() => {
    localStorage.setItem('nexus_access_token', 'e2e-caring-token');
    localStorage.setItem('nexus_tenant_id', '2');
    localStorage.setItem('nexus_tenant_slug', 'hour-timebank');
    localStorage.setItem('dev_notice_dismissed', '2.1');
    localStorage.setItem('nexus_cookie_consent', JSON.stringify({ essential: true, analytics: false, marketing: false }));
  });

  await page.route('**/*tenant/bootstrap*', async (route) => {
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
          settings: { onboarding_enabled: false, onboarding_mandatory: false },
        },
      }),
    });
  });

  await page.route('**/*csrf-token*', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { csrf_token: 'e2e-csrf-token' } }),
    });
  });

  await page.route('**/*users/me*', async (route) => {
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
          role,
          status: 'active',
          onboarding_completed: true,
          email_verified_at: '2026-05-06T00:00:00.000Z',
        },
      }),
    });
  });

  await page.route('**/*caring-community/emergency-alerts*', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: [] }),
    });
  });

  await page.route('**/*cookie-consent*', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { essential: true, analytics: false, marketing: false } }),
    });
  });

  await page.route('**/*presence/heartbeat*', async (route) => {
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { ok: true } }) });
  });

  await page.route('**/*presence/online-count*', async (route) => {
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { count: 1 } }) });
  });

  await page.route('**/*realtime/config*', async (route) => {
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { enabled: false } }) });
  });

  await page.route('**/*notifications/counts*', async (route) => {
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { unread: 0 } }) });
  });

  await page.route('**/*messages/unread-count*', async (route) => {
    await route.fulfill({ contentType: 'application/json', body: JSON.stringify({ success: true, data: { count: 0 } }) });
  });

  await page.route('**/*legal/acceptance/status*', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { has_pending: false, pending_docs: [] } }),
    });
  });

  await page.route('**/*identity/status*', async (route) => {
    await route.fulfill({
      contentType: 'application/json',
      body: JSON.stringify({ success: true, data: { verified: true, required: false } }),
    });
  });
}

test.describe('Caring Community flows', () => {
  test('request-help does not show success when the API returns success false', async ({ page }) => {
    const diagnostics = captureBrowserDiagnostics(page);
    await mockAuthenticatedCaringSession(page);

    await page.route('**/v2/caring-community/request-help', async (route) => {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ success: false, error: 'Validation failed' }),
      });
    });

    await page.goto(`/${tenantSlug}/caring-community/request-help`);
    await page.getByRole('button', { name: /accept all|essential only/i }).first().click().catch(() => undefined);

    await page.locator('textarea').nth(0).fill('I need help with a grocery pickup.');
    await page.locator('input:not([type="hidden"])').first().fill('Tomorrow morning');
    await page.locator('button[type="submit"], button:has-text("Submit"), button:has-text("Request")').last().click();

    await expect(page.getByText('Validation failed')).toBeVisible();
    await expect(page.getByText(/Your request has been posted|success/i)).toHaveCount(0);
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.failedRequests).toEqual([]);
  });

  test('provider directory refetches when search text changes', async ({ page }) => {
    const diagnostics = captureBrowserDiagnostics(page);
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
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.failedRequests).toEqual([]);
  });

  test('old admin Caring Community URLs redirect to the dedicated Caring panel', async ({ page }) => {
    const diagnostics = captureBrowserDiagnostics(page);
    await mockAuthenticatedCaringSession(page, 'admin');

    await page.goto(`/${tenantSlug}/admin/caring-community/providers`);

    await expect(page).toHaveURL(new RegExp(`/${tenantSlug}/caring/providers$`));
    expect(diagnostics.consoleErrors).toEqual([]);
    expect(diagnostics.failedRequests).toEqual([]);
  });
});
