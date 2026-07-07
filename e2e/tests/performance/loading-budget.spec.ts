// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test, type Page, type Response } from '@playwright/test';

interface SeenResponse {
  url: string;
  resourceType: string;
  bytes: number | null;
}

const tenantSlug = process.env.E2E_TENANT_SLUG || 'hour-timebank';

function collectResponses(page: Page): SeenResponse[] {
  const seen: SeenResponse[] = [];
  page.on('response', (response: Response) => {
    const headers = response.headers();
    const length = Number(headers['content-length'] || '');
    seen.push({
      url: response.url(),
      resourceType: response.request().resourceType(),
      bytes: Number.isFinite(length) && length > 0 ? length : null,
    });
  });

  return seen;
}

function matching(seen: SeenResponse[], pattern: RegExp): SeenResponse[] {
  return seen.filter((entry) => pattern.test(entry.url));
}

async function openMeasured(page: Page, path: string): Promise<SeenResponse[]> {
  const seen = collectResponses(page);
  await page.goto(`/${tenantSlug}${path}`, { waitUntil: 'domcontentloaded' });
  await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => undefined);
  return seen;
}

test.describe('@performance route loading budgets', () => {
  test('auth pages do not eagerly load maps, payments, realtime, telemetry, or every locale namespace', async ({ page }) => {
    for (const route of ['/login', '/register']) {
      const seen = await openMeasured(page, route);

      expect.soft(matching(seen, /(?:maps\.googleapis|maps\.gstatic|\/maps\/api\/js)/i), `${route} loaded Google Maps before user interaction`).toHaveLength(0);
      expect.soft(matching(seen, /(?:js\.stripe\.com|stripe-js|stripe\.com\/v3)/i), `${route} loaded Stripe before checkout`).toHaveLength(0);
      expect.soft(matching(seen, /(?:pusher-js|sockjs|ws-)/i), `${route} loaded realtime transport before auth`).toHaveLength(0);
      expect.soft(matching(seen, /(?:@sentry|sentry|browsertracing|replay)/i), `${route} loaded telemetry SDK before consent`).toHaveLength(0);

      const localeJson = matching(seen, /\/locales\/[^/]+\/[^/?]+\.json/i);
      expect.soft(localeJson.length, `${route} loaded too many locale namespaces`).toBeLessThanOrEqual(12);
    }
  });

  test('browse routes avoid checkout and map bundles unless the workflow needs them', async ({ page }) => {
    for (const route of ['/marketplace', '/listings', '/events', '/groups']) {
      const seen = await openMeasured(page, route);

      expect.soft(matching(seen, /(?:js\.stripe\.com|stripe-js|stripe\.com\/v3)/i), `${route} loaded Stripe on initial browse`).toHaveLength(0);
      expect.soft(matching(seen, /(?:maps\.googleapis|maps\.gstatic|\/maps\/api\/js)/i), `${route} loaded Google Maps on initial browse`).toHaveLength(0);
      expect.soft(matching(seen, /(?:@sentry|sentry|browsertracing|replay)/i), `${route} loaded telemetry SDK on initial browse`).toHaveLength(0);

      const oversizedImages = seen.filter((entry) =>
        entry.resourceType === 'image'
        && entry.bytes !== null
        && entry.bytes > 1_000_000
        && /(?:\/uploads\/|\/storage\/|\/api\/v2\/media\/thumbnail)/.test(entry.url)
      );
      expect.soft(oversizedImages.map((entry) => entry.url), `${route} loaded oversized local images`).toEqual([]);
    }
  });
});
