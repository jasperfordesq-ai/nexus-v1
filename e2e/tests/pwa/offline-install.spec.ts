// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect } from '@playwright/test';
import { tenantUrl } from '../../helpers/test-utils';

const EMPTY_STORAGE = { cookies: [], origins: [] };

async function primeNonPwaStorage(page: import('@playwright/test').Page): Promise<void> {
  await page.addInitScript(() => {
    localStorage.setItem('dev_notice_dismissed', '2.1');
    localStorage.setItem('nexus_cookie_consent', JSON.stringify({
      essential: true,
      analytics: false,
      preferences: true,
      timestamp: new Date().toISOString(),
    }));
  });
}

test.describe('production PWA lifecycle', () => {
  test.use({ storageState: EMPTY_STORAGE, serviceWorkers: 'allow' });

  test('tenant manifest preserves install identity, scope, and shortcuts', async ({ page }) => {
    await primeNonPwaStorage(page);
    await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });

    const href = await page.locator('link[rel="manifest"]').getAttribute('href');
    expect(href).toContain('/api/v2/pwa/manifest?path=');

    // The gate loads many pages that each fetch this manifest via
    // <link rel="manifest">, so the manifest route's per-IP throttle can be
    // briefly exhausted when this explicit fetch runs. Retry on 429, and
    // surface the real status if it still isn't ok (so a non-throttle failure
    // is diagnosable rather than a bare "expected true, received false").
    let response = await page.request.get(href!);
    for (let attempt = 0; attempt < 5 && response.status() === 429; attempt++) {
      await page.waitForTimeout(3000);
      response = await page.request.get(href!);
    }
    expect(response.ok(), `manifest ${href} returned HTTP ${response.status()}`).toBe(true);
    expect(response.headers()['content-type']).toContain('application/manifest+json');
    const manifest = await response.json();

    expect(manifest.id).toBe('/hour-timebank/');
    expect(manifest.start_url).toBe('/hour-timebank/');
    expect(manifest.scope).toBe('/hour-timebank/');
    expect(manifest.shortcuts.map((shortcut: { url: string }) => shortcut.url)).toEqual([
      '/hour-timebank/listings',
      '/hour-timebank/messages',
      '/hour-timebank/wallet',
    ]);
  });

  test('clean installation restarts the public shell with browser HTTP cache empty', async ({ page, context }) => {
    const pageErrors: string[] = [];
    page.on('pageerror', (error) => pageErrors.push(error.message));

    await primeNonPwaStorage(page);
    await page.goto(tenantUrl('login'), { waitUntil: 'domcontentloaded' });
    await expect(page.locator('input[type="email"]')).toBeVisible({ timeout: 20_000 });

    await page.waitForFunction(() => navigator.serviceWorker?.controller != null, undefined, {
      timeout: 30_000,
    });
    await page.waitForFunction(async () => {
      const cacheNames = await caches.keys();
      const precache = cacheNames.find((name) => name.includes('workbox-precache'));
      const immutable = cacheNames.find((name) => name === 'nexus-immutable-assets-v1');
      const tenantBootstrap = cacheNames.find((name) => name === 'nexus-tenant-bootstrap-v1');
      if (!precache || !immutable || !tenantBootstrap) return false;
      const [precacheKeys, immutableKeys, tenantBootstrapKeys] = await Promise.all([
        caches.open(precache).then((cache) => cache.keys()),
        caches.open(immutable).then((cache) => cache.keys()),
        caches.open(tenantBootstrap).then((cache) => cache.keys()),
      ]);
      const precachePaths = precacheKeys.map((request) => new URL(request.url).pathname);
      return precachePaths.includes('/index.html')
        && precachePaths.some((path) => /\/assets\/app-[^/]+\.js$/.test(path))
        && precachePaths.some((path) => /\/assets\/index-[^/]+\.css$/.test(path))
        && immutableKeys.length > 0
        && tenantBootstrapKeys.some((request) => (
          new URL(request.url).searchParams.get('slug') === 'hour-timebank'
        ));
    }, undefined, { timeout: 30_000 });

    // Tenant-prefixed and authenticated navigations are intentionally
    // network-only. Prime an explicitly identity-free public route before
    // taking the browser offline so this test exercises the public shell cache.
    await page.goto('/about', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('main, [role="main"]').first()).toBeVisible();

    // Remove the browser HTTP cache so only Workbox's revisioned shell,
    // startup precache, and controlled immutable-route cache can satisfy boot.
    const cdp = await context.newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.clearBrowserCache');

    await context.setOffline(true);
    try {
      await page.reload({ waitUntil: 'domcontentloaded', timeout: 30_000 });
      await expect(page.locator('main, [role="main"]').first()).toBeVisible();
      expect(pageErrors).toEqual([]);
    } finally {
      await context.setOffline(false);
      await cdp.detach();
    }
  });

  test('generated worker contains fresh-shell, offline-fallback, and immutable-asset policies', async ({ request }) => {
    const response = await request.get('/sw.js');
    expect(response.ok()).toBe(true);
    const worker = await response.text();

    expect(worker).toContain('nexus-public-html-shell-v3');
    expect(worker).toContain('nexus-immutable-assets-v1');
    expect(worker).toContain('nexus-tenant-bootstrap-v1');
    expect(worker).toContain('index.html');
    expect(worker).toContain('PrecacheFallbackPlugin');
  });
});
