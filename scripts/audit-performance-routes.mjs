// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { chromium } from 'playwright';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = (process.env.PERF_AUDIT_BASE_URL || process.env.E2E_BASE_URL || 'http://localhost:5173').replace(/\/+$/, '');
const tenantSlug = process.env.PERF_AUDIT_TENANT_SLUG || process.env.E2E_TENANT_SLUG || 'hour-timebank';
const email = process.env.PERF_AUDIT_EMAIL || process.env.E2E_USER_EMAIL || '';
const password = process.env.PERF_AUDIT_PASSWORD || process.env.E2E_USER_PASSWORD || '';
const adminEmail = process.env.PERF_AUDIT_ADMIN_EMAIL || process.env.E2E_ADMIN_EMAIL || email;
const adminPassword = process.env.PERF_AUDIT_ADMIN_PASSWORD || process.env.E2E_ADMIN_PASSWORD || password;
const outputRoot = process.env.PERF_AUDIT_OUTPUT_DIR || path.join('.local-docs-archive', 'performance-traces', 'latest');
const navigationTimeoutMs = Number.parseInt(process.env.PERF_AUDIT_NAV_TIMEOUT_MS || '30000', 10);
const networkIdleTimeoutMs = Number.parseInt(process.env.PERF_AUDIT_NETWORK_IDLE_TIMEOUT_MS || '12000', 10);
const preflightTimeoutMs = Number.parseInt(process.env.PERF_AUDIT_PREFLIGHT_TIMEOUT_MS || '8000', 10);
const configuredMarketplaceDetailPath = process.env.PERF_AUDIT_MARKETPLACE_DETAIL_PATH || '';
const targetKind = process.env.PERF_AUDIT_TARGET_KIND || '';
const simulateCookieConsent = process.env.PERF_AUDIT_SIMULATE_COOKIE_CONSENT !== '0';
const isLocalDevTarget = /^https?:\/\/(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?(?:\/|$)/i.test(baseUrl);

const configWarnings = [];
if (isLocalDevTarget && targetKind !== 'production-preview') {
  configWarnings.push('Local Vite/dev-server target detected; request counts and transfer sizes reflect unbundled development modules and are not production performance proof.');
}

const routes = [
  { name: 'login', path: '/login', auth: 'none' },
  { name: 'register', path: '/register', auth: 'none' },
  { name: 'listings', path: '/listings', auth: 'none' },
  { name: 'marketplace-detail', path: configuredMarketplaceDetailPath || '/marketplace', auth: 'none', autoDiscover: !configuredMarketplaceDetailPath },
  { name: 'events', path: '/events', auth: 'none' },
  { name: 'groups', path: '/groups', auth: 'user' },
  { name: 'feed', path: '/feed', auth: 'user' },
  { name: 'profile', path: '/profile', auth: 'user' },
  { name: 'search', path: '/search?q=help', auth: 'user' },
  { name: 'messages', path: '/messages', auth: 'user' },
  { name: 'admin-dashboard', path: '/admin', auth: 'admin' },
];

const badEarlyLoadPatterns = [
  { label: 'Google Maps', pattern: /(?:maps\.googleapis|maps\.gstatic|\/maps\/api\/js|GoogleMapsProvider-|OpenStreetMapView-|LocationMap-)/i },
  { label: 'Stripe', pattern: /(?:js\.stripe\.com|stripe-js|stripe\.com\/v3)/i },
  { label: 'Sentry SDK', pattern: /(?:browsertracing|replay|@sentry|sentry).*\.js/i },
  { label: 'Realtime transport', pattern: /(?:pusher-|pusher-js|sockjs|\/app\/[^/]+\/events|presence|realtime)/i },
  { label: 'WebAuthn/passkey', pattern: /(?:webauthn|simplewebauthn|PublicKeyCredential)/i },
  { label: 'Cookie consent banner', pattern: /CookieConsentBanner-/i },
  { label: 'Podcast runtime', pattern: /PodcastPlayerContext-/i },
  { label: 'Social interaction runtime', pattern: /useSocialInteractions-/i },
];

const localeJsonPattern = /\/locales\/[^/]+\/[^/?]+\.json(?:[?#]|$)/i;
const adminLocalePattern = /\/locales\/[^/]+\/admin(?:[_-][^/?]+)?\.json(?:[?#]|$)/i;

function routeUrl(routePath) {
  if (/^https?:\/\//i.test(routePath)) {
    return routePath;
  }

  const normalizedPath = routePath.startsWith(`/${tenantSlug}/`)
    ? routePath.slice(tenantSlug.length + 1)
    : routePath;

  return `${baseUrl}/${tenantSlug}${normalizedPath.startsWith('/') ? normalizedPath : `/${normalizedPath}`}`;
}

function contentLength(response) {
  const raw = response.headers()['content-length'];
  const parsed = Number(raw || '');
  return Number.isFinite(parsed) && parsed > 0 ? parsed : null;
}

async function resolveMarketplaceDetailPath(context) {
  if (configuredMarketplaceDetailPath) {
    return configuredMarketplaceDetailPath;
  }

  const page = await context.newPage();
  try {
    await page.goto(routeUrl('/marketplace'), { waitUntil: 'domcontentloaded', timeout: navigationTimeoutMs });
    await page.waitForLoadState('networkidle', { timeout: networkIdleTimeoutMs }).catch(() => undefined);

    const discovered = await page.evaluate((slug) => {
      const prefix = `/${slug}/marketplace/`;
      const links = Array.from(document.querySelectorAll('a[href]'));
      for (const link of links) {
        const href = link.getAttribute('href') || '';
        const url = new URL(href, window.location.href);
        if (!url.pathname.startsWith(prefix)) {
          continue;
        }
        const relative = `${url.pathname}${url.search}${url.hash}`;
        const tenantRelative = relative.slice(`/${slug}`.length);
        if (tenantRelative !== '/marketplace' && tenantRelative !== '/marketplace/') {
          return tenantRelative;
        }
      }

      return null;
    }, tenantSlug);

    if (discovered) {
      console.log(`Discovered marketplace detail path: ${discovered}`);
      return discovered;
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    configWarnings.push(`Could not auto-discover marketplace detail path: ${message}`);
  } finally {
    await page.close();
  }

  configWarnings.push('PERF_AUDIT_MARKETPLACE_DETAIL_PATH is not set and auto-discovery found no marketplace detail link; marketplace-detail will measure /marketplace and should not be treated as detail-page proof.');
  return '/marketplace';
}

async function login(page, credentials, role) {
  if (!credentials.email || !credentials.password) {
    return false;
  }

  try {
    await page.goto(routeUrl('/login'), { waitUntil: 'domcontentloaded', timeout: navigationTimeoutMs });
    const emailInput = page.locator('input[type="email"], input[name="email"]').first();
    const passwordInput = page.locator('input[type="password"], input[name="password"]').first();
    await emailInput.fill(credentials.email, { timeout: 10_000 });
    await passwordInput.fill(credentials.password, { timeout: 10_000 });
    await page.locator('button[type="submit"], input[type="submit"]').first().click();
    await page.waitForLoadState('networkidle', { timeout: networkIdleTimeoutMs }).catch(() => undefined);
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.log(`Could not authenticate ${role}: ${message}`);
    return false;
  }

  const ok = !page.url().includes('/login');
  console.log(ok ? `Authenticated ${role}.` : `Could not authenticate ${role}; protected routes will be marked redirected/skipped.`);

  return ok;
}

async function measureRoute(context, route) {
  const page = await context.newPage();
  const responses = [];
  const failedRequests = [];

  page.on('response', (response) => {
    const request = response.request();
    responses.push({
      url: response.url(),
      status: response.status(),
      resourceType: request.resourceType(),
      method: request.method(),
      bytes: contentLength(response),
      cacheControl: response.headers()['cache-control'] || '',
      contentType: response.headers()['content-type'] || '',
    });
  });

  page.on('requestfailed', (request) => {
    failedRequests.push({
      url: request.url(),
      resourceType: request.resourceType(),
      failure: request.failure()?.errorText || 'request failed',
    });
  });

  const startedAt = Date.now();
  let finalUrl = routeUrl(route.path);
  let loadError = null;

  try {
    await page.goto(finalUrl, { waitUntil: 'domcontentloaded', timeout: navigationTimeoutMs });
    await page.waitForLoadState('networkidle', { timeout: networkIdleTimeoutMs }).catch(() => undefined);
    finalUrl = page.url();
  } catch (error) {
    loadError = error instanceof Error ? error.message : String(error);
  }

  const elapsedMs = Date.now() - startedAt;
  const timing = await page.evaluate(() => {
    const nav = performance.getEntriesByType('navigation')[0];
    if (!nav) return null;
    const entry = nav.toJSON();
    return {
      domContentLoadedMs: Math.round(entry.domContentLoadedEventEnd || 0),
      loadEventMs: Math.round(entry.loadEventEnd || 0),
      transferSize: Math.round(entry.transferSize || 0),
      encodedBodySize: Math.round(entry.encodedBodySize || 0),
      decodedBodySize: Math.round(entry.decodedBodySize || 0),
    };
  }).catch(() => null);

  await page.close();

  const totals = summariseResponses(responses);
  const warnings = warningsFor(route, responses, finalUrl, failedRequests);

  return {
    name: route.name,
    path: route.path,
    auth: route.auth,
    requestedUrl: routeUrl(route.path),
    finalUrl,
    redirectedToLogin: finalUrl.includes('/login') && route.auth !== 'none',
    elapsedMs,
    timing,
    totals,
    warnings,
    failedRequests,
    responses,
    loadError,
  };
}

function summariseResponses(responses) {
  const byType = {};
  let knownBytes = 0;
  let unknownBytes = 0;

  for (const response of responses) {
    byType[response.resourceType] ??= { count: 0, knownBytes: 0, unknownBytes: 0 };
    byType[response.resourceType].count++;
    if (response.bytes === null) {
      byType[response.resourceType].unknownBytes++;
      unknownBytes++;
    } else {
      byType[response.resourceType].knownBytes += response.bytes;
      knownBytes += response.bytes;
    }
  }

  return {
    requestCount: responses.length,
    knownBytes,
    unknownBytes,
    byType,
  };
}

function warningsFor(route, responses, finalUrl, failedRequests) {
  const warnings = [];
  const matching = (pattern) => responses.filter((response) => pattern.test(response.url));

  for (const check of badEarlyLoadPatterns) {
    const matches = matching(check.pattern);
    if (matches.length > 0 && !routeNeedsDependency(route, check.label)) {
      warnings.push(`${check.label} loaded unexpectedly: ${matches.slice(0, 3).map((r) => r.url).join(', ')}`);
    }
  }

  const oversizedLocalImages = responses.filter((response) =>
    response.resourceType === 'image'
    && response.bytes !== null
    && response.bytes > 1_000_000
    && /(?:\/uploads\/|\/storage\/|\/api\/v2\/media\/thumbnail)/.test(response.url)
  );
  if (oversizedLocalImages.length > 0) {
    warnings.push(`Oversized local images: ${oversizedLocalImages.slice(0, 5).map((r) => `${r.bytes} ${r.url}`).join(', ')}`);
  }

  const localeResponses = responses.filter((response) => localeJsonPattern.test(response.url));
  if (localeResponses.length > 12) {
    warnings.push(`Locale waterfall detected: ${localeResponses.length} locale JSON response(s) loaded.`);
  }

  const adminLocaleResponses = localeResponses.filter((response) => adminLocalePattern.test(response.url));
  if (route.auth !== 'admin' && adminLocaleResponses.length > 0) {
    warnings.push(`Non-admin route loaded admin locale payload(s): ${adminLocaleResponses.slice(0, 5).map((r) => r.url).join(', ')}`);
  }

  const largeLocaleResponses = localeResponses.filter((response) => response.bytes !== null && response.bytes > 250_000);
  if (largeLocaleResponses.length > 0) {
    warnings.push(`Large locale payload(s): ${largeLocaleResponses.slice(0, 5).map((r) => `${formatBytes(r.bytes)} ${r.url}`).join(', ')}`);
  }

  const thumbnailResponses = responses.filter((response) => /\/api\/v2\/media\/thumbnail/i.test(response.url));
  const weakThumbnailCache = thumbnailResponses.filter((response) => !/max-age=\d{5,}|immutable/i.test(response.cacheControl));
  if (weakThumbnailCache.length > 0) {
    warnings.push(`Thumbnail responses without long-lived cache headers: ${weakThumbnailCache.slice(0, 5).map((r) => r.url).join(', ')}`);
  }

  if (route.auth !== 'none' && finalUrl.includes('/login')) {
    warnings.push('Protected route redirected to login; provide PERF_AUDIT_EMAIL/PASSWORD or admin credentials for full workflow evidence.');
  }

  if (route.name === 'marketplace-detail' && route.path === '/marketplace') {
    warnings.push('Marketplace detail path was not supplied; set PERF_AUDIT_MARKETPLACE_DETAIL_PATH to a real detail URL path for complete evidence.');
  }

  if (failedRequests.length > 0) {
    warnings.push(`${failedRequests.length} request(s) failed.`);
  }

  return warnings;
}

function routeNeedsDependency(route, label) {
  if (label === 'Google Maps') return /map|nearby/i.test(route.name);
  if (label === 'Stripe') return /checkout|payment|billing/i.test(route.name);
  if (label === 'Realtime transport') return /messages/i.test(route.name);
  if (label === 'WebAuthn/passkey') return false;
  if (label === 'Cookie consent banner') return false;
  if (label === 'Podcast runtime') return /podcast/i.test(route.name);
  if (label === 'Social interaction runtime') return /feed|profile/i.test(route.name);
  return false;
}

function formatBytes(bytes) {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KiB`;
  return `${(bytes / 1024 / 1024).toFixed(2)} MiB`;
}

function markdownReport(results) {
  const lines = [
    '# Performance Route Audit',
    '',
    `- Base URL: ${baseUrl}`,
    `- Tenant: ${tenantSlug}`,
    `- Generated: ${new Date().toISOString()}`,
    `- Target kind: ${targetKind || (isLocalDevTarget ? 'local' : 'remote')}`,
    `- Simulated cookie consent: ${simulateCookieConsent ? 'yes' : 'no'}`,
    `- Navigation timeout: ${navigationTimeoutMs} ms`,
    '',
    '| Route | Auth | Requests | Known Transfer | DCL | Load | Warnings |',
    '|---|---:|---:|---:|---:|---:|---|',
  ];

  if (configWarnings.length > 0) {
    lines.splice(6, 0, '', '## Configuration Warnings', '', ...configWarnings.map((warning) => `- ${warning}`));
  }

  for (const result of results) {
    lines.push(markdownRow([
      result.name,
      result.auth,
      String(result.totals.requestCount),
      formatBytes(result.totals.knownBytes),
      result.timing ? `${result.timing.domContentLoadedMs} ms` : 'n/a',
      result.timing ? `${result.timing.loadEventMs} ms` : 'n/a',
      result.warnings.length > 0 ? result.warnings.join('<br>') : 'OK',
    ]));
  }

  lines.push('', '## Route Details', '');
  for (const result of results) {
    lines.push(`### ${result.name}`, '');
    lines.push(`- Requested: ${result.requestedUrl}`);
    lines.push(`- Final: ${result.finalUrl}`);
    lines.push(`- Elapsed wall time: ${result.elapsedMs} ms`);
    lines.push(`- Requests: ${result.totals.requestCount}`);
    lines.push(`- Known transfer: ${formatBytes(result.totals.knownBytes)}`);
    if (result.loadError) {
      lines.push(`- Load error: ${result.loadError}`);
    }
    if (result.warnings.length > 0) {
      lines.push(`- Warnings: ${result.warnings.join('; ')}`);
    }
    const largest = result.responses
      .filter((response) => response.bytes !== null)
      .sort((a, b) => b.bytes - a.bytes)
      .slice(0, 8);
    if (largest.length > 0) {
      lines.push('', '| Resource | Type | Status | Transfer | Cache-Control |');
      lines.push('|---|---:|---:|---:|---|');
      for (const response of largest) {
        lines.push(markdownRow([
          response.url,
          response.resourceType,
          String(response.status),
          formatBytes(response.bytes),
          response.cacheControl || '',
        ]));
      }
    }
    lines.push('');
  }

  return `${lines.join('\n')}\n`;
}

function markdownRow(cells) {
  return `| ${cells.map((cell) => String(cell).replace(/\\/g, '\\\\').replace(/\|/g, '\\|')).join(' | ')} |`;
}

async function main() {
  await mkdir(outputRoot, { recursive: true });

  if (process.env.PERF_AUDIT_SKIP_PREFLIGHT !== '1') {
    try {
      await fetch(routeUrl('/login'), { signal: AbortSignal.timeout(preflightTimeoutMs) });
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      throw new Error(`Performance audit target is not reachable at ${routeUrl('/login')} within ${preflightTimeoutMs} ms. Set PERF_AUDIT_BASE_URL or PERF_AUDIT_SKIP_PREFLIGHT=1. ${message}`);
    }
  }

  const browser = await chromium.launch({ headless: process.env.PERF_AUDIT_HEADED !== '1' });
  const contextOptions = { ignoreHTTPSErrors: true, serviceWorkers: 'block' };
  const unauthContext = await browser.newContext(contextOptions);
  const userContext = await browser.newContext(contextOptions);
  const adminContext = await browser.newContext(contextOptions);

  if (simulateCookieConsent) {
    const consentScript = () => {
      localStorage.setItem('nexus_cookie_consent', JSON.stringify({
        essential: true,
        analytics: true,
        preferences: true,
        timestamp: new Date().toISOString(),
      }));
    };
    await Promise.all([
      unauthContext.addInitScript(consentScript),
      userContext.addInitScript(consentScript),
      adminContext.addInitScript(consentScript),
    ]);
  }

  try {
    const userPage = await userContext.newPage();
    const adminPage = await adminContext.newPage();
    const userAuthenticated = await login(userPage, { email, password }, 'user');
    const adminAuthenticated = await login(adminPage, { email: adminEmail, password: adminPassword }, 'admin');
    await userPage.close();
    await adminPage.close();

    const marketplaceRoute = routes.find((route) => route.name === 'marketplace-detail');
    if (marketplaceRoute?.autoDiscover) {
      marketplaceRoute.path = await resolveMarketplaceDetailPath(unauthContext);
    }

    const results = [];
    for (const route of routes) {
      if (route.auth === 'user' && !userAuthenticated) {
        console.log(`Measuring ${route.name} without user auth evidence; route may redirect.`);
      }
      if (route.auth === 'admin' && !adminAuthenticated) {
        console.log(`Measuring ${route.name} without admin auth evidence; route may redirect.`);
      }

      const context = route.auth === 'admin' ? adminContext : route.auth === 'user' ? userContext : unauthContext;
      console.log(`Measuring ${route.name}...`);
      results.push(await measureRoute(context, route));
    }

    const json = JSON.stringify({
      baseUrl,
      tenantSlug,
      generatedAt: new Date().toISOString(),
      isLocalDevTarget,
      navigationTimeoutMs,
      networkIdleTimeoutMs,
      configWarnings,
      targetKind,
      simulateCookieConsent,
      results,
    }, null, 2);
    const markdown = markdownReport(results);
    await writeFile(path.join(outputRoot, 'route-audit.json'), json);
    await writeFile(path.join(outputRoot, 'route-audit.md'), markdown);

    const warningCount = results.reduce((count, result) => count + result.warnings.length, configWarnings.length);
    console.log(`Wrote ${path.join(outputRoot, 'route-audit.md')}`);
    console.log(`Warnings: ${warningCount}`);
    process.exitCode = warningCount > 0 && process.env.PERF_AUDIT_STRICT === '1' ? 1 : 0;
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
