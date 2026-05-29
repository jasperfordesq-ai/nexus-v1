// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { normaliseSalesPath, salesNavItems } from './routes';

describe('sales routes', () => {
  it('keeps the new dedicated features route instead of falling back to the front page', () => {
    expect(normaliseSalesPath('/features')).toBe('/features');
    expect(normaliseSalesPath('/features#federation')).toBe('/features');
  });

  it('shows Pricing as the primary commercial navigation item', () => {
    expect(salesNavItems.slice(0, 3).map((item) => item.label)).toEqual(['Platform', 'Features', 'Pricing']);
  });

  it('keeps the primary header navigation free of duplicated repository links', () => {
    expect(salesNavItems.map((item) => item.label)).not.toContain('GitHub');
    expect(new Set(salesNavItems.map((item) => item.href)).size).toBe(salesNavItems.length);
  });

  it('routes legal policy pages directly instead of falling back to the front page', () => {
    expect(normaliseSalesPath('/legal/terms')).toBe('/legal/terms');
    expect(normaliseSalesPath('/legal/privacy?ref=footer')).toBe('/legal/privacy');
    expect(normaliseSalesPath('/legal/cookies#analytics')).toBe('/legal/cookies');
    expect(normaliseSalesPath('/legal/acceptable-use')).toBe('/legal/acceptable-use');
    expect(normaliseSalesPath('/legal/data-processing')).toBe('/legal/data-processing');
  });
});
