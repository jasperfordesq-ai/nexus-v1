// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import test from 'node:test';

import {
  assertRenderContract,
  isApiPath,
  isCriticalApiPath,
  isNonPublicAddress,
  maintenanceAuthHeaders,
  normalizeManifestRoute,
  normalizedDocumentUrl,
  validateManifest,
  validateManifestEntry,
} from '../prerender-worker.mjs';

test('private, loopback, link-local, and reserved addresses are blocked', () => {
  for (const address of [
    '0.0.0.0',
    '10.0.0.1',
    '100.64.0.1',
    '127.0.0.1',
    '169.254.1.1',
    '172.16.0.1',
    '192.168.0.1',
    '198.51.100.1',
    '203.0.113.1',
    '224.0.0.1',
    '::',
    '::1',
    '::ffff:127.0.0.1',
    'fc00::1',
    'fe80::1',
  ]) {
    assert.equal(isNonPublicAddress(address), true, address);
  }
});

test('globally routable addresses are accepted', () => {
  for (const address of ['1.1.1.1', '8.8.8.8', '2606:4700:4700::1111']) {
    assert.equal(isNonPublicAddress(address), false, address);
  }
});

test('document URL normalization ignores only fragments, queries, and a trailing slash', () => {
  assert.equal(
    normalizedDocumentUrl('https://EXAMPLE.com/about/?utm_source=test#section'),
    'https://example.com/about',
  );
});

function validRender(overrides = {}) {
  return {
    readiness: 'signal',
    apiSettled: true,
    pendingApiPaths: [],
    navigationStatus: 200,
    status: 200,
    redirect: null,
    finalUrl: 'https://example.com/about',
    pageContract: {
      canonical: 'https://example.com/about',
      errorBoundary: false,
      robots: 'index, follow',
      title: 'About',
      bodyTextLength: 500,
    },
    ...overrides,
  };
}

const entry = {
  url: 'https://example.com/about?nexus_prerender_bypass=1',
  canonicalUrl: 'https://example.com/about',
};

test('a valid tenant document satisfies the publish contract', () => {
  assert.doesNotThrow(() => assertRenderContract(entry, validRender(), [], []));
});

test('tenant setting may intentionally omit a canonical URL', () => {
  const render = validRender({
    pageContract: { ...validRender().pageContract, canonical: null },
  });
  assert.doesNotThrow(() => assertRenderContract(entry, render, [], []));
});

test('declared server errors are never downgraded to publishable snapshots', () => {
  assert.throws(
    () => assertRenderContract(entry, validRender({ status: 500 }), [], []),
    /non-publishable status 500/,
  );
});

test('an explicit tenant maintenance page remains a real 503 snapshot', () => {
  assert.doesNotThrow(() => assertRenderContract(
    entry,
    validRender({ status: 503 }),
    [],
    [],
  ));
});

test('redirect documents are rejected because the cache cannot serve a Location header', () => {
  assert.throws(
    () => assertRenderContract(entry, validRender({
      status: 301,
      redirect: 'https://example.com/new-about',
    }), [], []),
    /non-publishable status 301/,
  );
});

test('wrong-tenant canonical URLs are rejected', () => {
  const render = validRender({
    pageContract: {
      ...validRender().pageContract,
      canonical: 'https://other.example/about',
    },
  });
  assert.throws(() => assertRenderContract(entry, render, [], []), /canonical URL mismatch/);
});

test('blocked private subrequests make the snapshot fail closed', () => {
  assert.throws(
    () => assertRenderContract(entry, validRender(), [], ['http://127.0.0.1/secrets']),
    /blocked non-public request/,
  );
});

test('tenant-critical API 4xx and page errors make the render fail closed', () => {
  assert.equal(isCriticalApiPath('/', '/api/v2/tenant/bootstrap'), true);
  assert.equal(isCriticalApiPath('/blog', '/api/v2/blog/categories'), true);
  assert.equal(isCriticalApiPath('/blog/post', '/api/v2/blog/post'), true);
  assert.equal(isCriticalApiPath('/page/about', '/api/v2/pages/about'), true);
  assert.throws(
    () => assertRenderContract(entry, validRender(), [], [], [], ['403 /api/v2/blog']),
    /critical API request failed/,
  );
  assert.throws(
    () => assertRenderContract(entry, validRender(), [], [], ['render exploded'], []),
    /page error during render/,
  );
});

test('all first-party API failures, pending requests, and noindex shells fail closed', () => {
  assert.equal(isApiPath('/api/v2/events'), true);
  assert.equal(isApiPath('/v2/courses'), true);
  assert.equal(isApiPath('/assets/app.js'), false);
  assert.throws(
    () => assertRenderContract(entry, validRender(), ['404 /api/v2/events'], []),
    /API error during render/,
  );
  assert.throws(
    () => assertRenderContract(entry, validRender({ apiSettled: false, pendingApiPaths: ['/api/v2/events'] }), [], []),
    /did not settle/,
  );
  assert.throws(
    () => assertRenderContract(entry, validRender({
      pageContract: { ...validRender().pageContract, robots: 'noindex, nofollow' },
    }), [], []),
    /noindex document/,
  );
});

function validManifestEntry(overrides = {}) {
  return {
    url: 'https://example.com/alpha/about?nexus_prerender_bypass=1',
    canonicalUrl: 'https://example.com/alpha/about',
    output: '/output/example.com/alpha/about/index.html',
    cachePath: 'example.com/alpha/about/index.html',
    tenantId: '2',
    tenantSlug: 'alpha',
    host: 'example.com',
    route: '/about',
    ...overrides,
  };
}

test('manifest output is bound to the canonical host path under /output', () => {
  assert.doesNotThrow(() => validateManifestEntry(validManifestEntry()));
  assert.throws(
    () => validateManifestEntry(validManifestEntry({
      output: '/output/example.com/../../../httpdocs/index.html',
    })),
    /escapes \/output|does not match cachePath/,
  );
  assert.throws(
    () => validateManifestEntry(validManifestEntry({
      cachePath: 'example.com/alpha/..%2f../index.html',
    })),
    /cachePath mismatch/,
  );
});

test('route normalization rejects dot, empty, encoded separator, and malformed escape aliases', () => {
  assert.equal(normalizeManifestRoute('/blog/'), '/blog');
  for (const route of ['/./blog', '/../x', '/a/../../x', '//blog', '/blog//post', '/%2e%2e/x', '/a%2fb', '/bad%ZZ']) {
    assert.equal(normalizeManifestRoute(route), null, route);
  }
});

test('manifest cache paths and outputs must be unique', () => {
  const first = validManifestEntry();
  assert.throws(
    () => validateManifest({ urls: [first, { ...first }] }),
    /duplicate manifest output/,
  );
});

test('maintenance authentication is sent only to the exact tenant origin', () => {
  const entry = validManifestEntry();
  const token = 'A'.repeat(64);
  const headers = maintenanceAuthHeaders(
    entry,
    'https://example.com/alpha/about?nexus_prerender_bypass=1',
    { accept: 'text/html' },
    token,
  );
  assert.equal(headers.accept, 'text/html');
  assert.equal(
    headers.authorization,
    `Basic ${Buffer.from(`prerender:${token}`, 'utf-8').toString('base64')}`,
  );
  assert.equal(
    maintenanceAuthHeaders(entry, 'https://api.example.com/api/v2/tenant/bootstrap', {}, token),
    null,
  );
  assert.equal(
    maintenanceAuthHeaders(entry, 'https://example.net/redirect', {}, token),
    null,
  );
  assert.equal(maintenanceAuthHeaders(entry, entry.url, {}, null), null);
});
