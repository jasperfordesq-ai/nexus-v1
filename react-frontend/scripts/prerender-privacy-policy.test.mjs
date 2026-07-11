// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const source = readFileSync(new URL('./prerender.mjs', import.meta.url), 'utf8');
const protectedRoots = [
  'explore', 'listings', 'events', 'groups', 'jobs', 'volunteering',
  'organisations', 'ideation', 'resources', 'kb', 'marketplace', 'courses',
  'podcasts',
];

test('build-time public prerender plan excludes authenticated member routes', () => {
  const publicBlock = source.match(/const PUBLIC_ROUTES = \[([\s\S]*?)\];/);
  assert.ok(publicBlock, 'PUBLIC_ROUTES declaration must remain statically auditable');

  const publicRoutes = [...publicBlock[1].matchAll(/['"]([^'"]+)['"]/g)]
    .map((match) => match[1]);

  assert.equal(publicRoutes.includes('/blog'), true, '/blog must remain publicly prerendered');

  for (const root of protectedRoots) {
    assert.equal(
      publicRoutes.some((route) => route === `/${root}` || route.startsWith(`/${root}/`)),
      false,
      `/${root} must not be build-time prerendered as a public route`,
    );
  }
});

test('sitemap fallback can add only sanitized public blog articles', () => {
  const dynamicBlock = source.match(/const DYNAMIC_ROUTE_PATTERNS = \[([\s\S]*?)\];/);
  assert.ok(dynamicBlock, 'DYNAMIC_ROUTE_PATTERNS declaration must remain statically auditable');
  assert.match(dynamicBlock[1], /blog/);
  for (const root of protectedRoots) {
    assert.equal(
      dynamicBlock[1].includes(root),
      false,
      `dynamic prerender matching must not include /${root}`,
    );
  }
});
