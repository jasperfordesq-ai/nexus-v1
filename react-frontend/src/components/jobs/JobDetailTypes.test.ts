// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import {
  parseArrayResponse,
  TYPE_CHIP_COLORS,
  STATUS_COLORS,
  STATUS_CHIP_COLORS,
} from './JobDetailTypes';

describe('parseArrayResponse', () => {
  it('returns the array unchanged when given an array', () => {
    const arr = [1, 2, 3];
    expect(parseArrayResponse<number>(arr)).toBe(arr);
  });

  it('returns an empty array unchanged', () => {
    expect(parseArrayResponse<number>([])).toEqual([]);
  });

  it('unwraps a { data: [...] } envelope', () => {
    expect(parseArrayResponse<string>({ data: ['a', 'b'] })).toEqual(['a', 'b']);
  });

  it('returns [] when the envelope data is null', () => {
    expect(parseArrayResponse({ data: null })).toEqual([]);
  });

  it('returns [] for null input', () => {
    expect(parseArrayResponse(null)).toEqual([]);
  });

  it('returns [] for undefined input', () => {
    expect(parseArrayResponse(undefined)).toEqual([]);
  });

  it('returns [] for a primitive (string/number)', () => {
    expect(parseArrayResponse('nope')).toEqual([]);
    expect(parseArrayResponse(42)).toEqual([]);
  });

  it('returns [] for an object without a data key', () => {
    expect(parseArrayResponse({ items: [1] })).toEqual([]);
  });

  it('preserves the elements of an unwrapped envelope', () => {
    const result = parseArrayResponse<{ id: number }>({ data: [{ id: 1 }, { id: 2 }] });
    expect(result).toHaveLength(2);
    expect(result[0].id).toBe(1);
  });
});

describe('job colour maps', () => {
  it('TYPE_CHIP_COLORS maps the known job types', () => {
    expect(TYPE_CHIP_COLORS.paid).toBe('success');
    expect(TYPE_CHIP_COLORS.volunteer).toBe('default');
    expect(TYPE_CHIP_COLORS.timebank).toBe('accent');
  });

  it('STATUS_COLORS covers the application lifecycle statuses', () => {
    expect(STATUS_COLORS.applied).toBe('warning');
    expect(STATUS_COLORS.accepted).toBe('success');
    expect(STATUS_COLORS.rejected).toBe('danger');
    expect(STATUS_COLORS.withdrawn).toBe('default');
  });

  it('STATUS_CHIP_COLORS maps applied and rejected', () => {
    expect(STATUS_CHIP_COLORS.applied).toBe('warning');
    expect(STATUS_CHIP_COLORS.rejected).toBe('danger');
  });

  it('returns undefined for an unknown status key (no default baked in)', () => {
    expect(STATUS_COLORS.nonexistent).toBeUndefined();
  });
});
