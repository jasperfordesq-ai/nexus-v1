// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { buildCanonicalUrl, buildPageTitle } from '../metadata';

describe('metadata helpers', () => {
  it('builds slug-prefixed canonicals for shared-host tenants', () => {
    expect(
      buildCanonicalUrl({
        origin: 'https://app.project-nexus.ie',
        routeSegments: ['about'],
        tenantMode: 'path',
        tenantSlug: 'hour-timebank',
      }),
    ).toBe('https://app.project-nexus.ie/hour-timebank/about');
  });

  it('builds clean canonicals for custom-domain tenants', () => {
    expect(
      buildCanonicalUrl({
        origin: 'https://community.example',
        routeSegments: ['blog', 'community-news'],
        tenantMode: 'host',
        tenantSlug: undefined,
      }),
    ).toBe('https://community.example/blog/community-news');
  });

  it('combines the route label with tenant branding', () => {
    expect(
      buildPageTitle({
        pageLabel: 'About',
        platformName: 'Project NEXUS',
        tenantName: 'Hour Timebank',
      }),
    ).toBe('About | Hour Timebank | Project NEXUS');
  });
});
