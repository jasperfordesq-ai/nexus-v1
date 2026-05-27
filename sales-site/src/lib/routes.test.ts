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

  it('shows Features as a primary navigation item between Platform and Hosting', () => {
    expect(salesNavItems.slice(0, 3).map((item) => item.label)).toEqual(['Platform', 'Features', 'Hosting']);
  });
});
