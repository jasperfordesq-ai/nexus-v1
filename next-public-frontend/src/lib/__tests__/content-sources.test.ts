// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { getPublicEndpointForRoute, publicContentSources } from '../content-sources';

describe('public content sources', () => {
  it('resolves collection and detail endpoints from the shared manifest', () => {
    expect(publicContentSources.sourceOfTruth).toBe('laravel_public_api');
    expect(publicContentSources.databaseQueriesFromNext).toBe(false);
    expect(getPublicEndpointForRoute('listings')).toBe('/v2/listings');
    expect(getPublicEndpointForRoute('listingDetail', { id: '42' })).toBe('/v2/listings/42');
  });

  it('encodes route parameters before calling Laravel public APIs', () => {
    expect(getPublicEndpointForRoute('blog-detail', { slug: 'summer news' })).toBe('/v2/blog/summer%20news');
  });
});
