// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import {
  formatMarketplaceCurrency,
  normalizeSupportedMarketplaceCurrency,
} from './marketplaceNumbers';

describe('marketplace currency helpers', () => {
  it('preserves major-unit amounts for decimal currencies', () => {
    expect(formatMarketplaceCurrency(25.5, 'GBP')).toMatch(/25\.50/);
  });

  it('does not invent decimals for zero-decimal currencies', () => {
    const formatted = formatMarketplaceCurrency(2500, 'JPY');

    expect(formatted).toMatch(/2[,.]500/);
    expect(formatted).not.toMatch(/[,.]00(?:\D|$)/);
  });

  it('normalizes supported tenant currencies without assuming EUR', () => {
    expect(normalizeSupportedMarketplaceCurrency(' jpy ')).toBe('JPY');
    expect(normalizeSupportedMarketplaceCurrency(null)).toBe('');
  });
});
