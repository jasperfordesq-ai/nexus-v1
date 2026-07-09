// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { getOpportunityCategoryName, type OpportunityCategory } from './volunteering';

describe('getOpportunityCategoryName', () => {
  it('returns the string as-is when category is a plain name', () => {
    expect(getOpportunityCategoryName('Environment')).toBe('Environment');
  });

  it('returns null for null, undefined, and empty/whitespace strings', () => {
    expect(getOpportunityCategoryName(null)).toBeNull();
    expect(getOpportunityCategoryName(undefined)).toBeNull();
    expect(getOpportunityCategoryName('')).toBeNull();
    expect(getOpportunityCategoryName('   ')).toBeNull();
  });

  it('unwraps the name from an API category object ({ id, name, color })', () => {
    const category: OpportunityCategory = { id: 3, name: 'Community Care', color: '#22c55e' };
    expect(getOpportunityCategoryName(category)).toBe('Community Care');
  });

  it('returns null for a category object without a usable name', () => {
    expect(getOpportunityCategoryName({ id: 3 })).toBeNull();
    expect(getOpportunityCategoryName({ id: 3, name: null })).toBeNull();
    expect(getOpportunityCategoryName({ id: 3, name: '' })).toBeNull();
    expect(getOpportunityCategoryName({ id: 3, name: '  ' })).toBeNull();
  });

  it('never returns a non-string value (regression: object rendered as React child)', () => {
    const values: Array<OpportunityCategory | undefined> = [
      'Gardening',
      { id: 1, name: 'Youth Work', color: null },
      { id: 2 },
      null,
      undefined,
      '',
    ];
    for (const value of values) {
      const result = getOpportunityCategoryName(value);
      expect(result === null || typeof result === 'string').toBe(true);
    }
  });
});
