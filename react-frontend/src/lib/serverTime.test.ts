// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import {
  daysBetween,
  formatServerDate,
  formatServerDateTime,
  parseServerTimestamp,
} from './serverTime';

describe('parseServerTimestamp', () => {
  it('returns null for empty/nullish input', () => {
    expect(parseServerTimestamp(null)).toBeNull();
    expect(parseServerTimestamp(undefined)).toBeNull();
    expect(parseServerTimestamp('')).toBeNull();
  });

  it('passes through an ISO string with a Z marker', () => {
    const d = parseServerTimestamp('2026-04-26T18:00:00Z');
    expect(d?.toISOString()).toBe('2026-04-26T18:00:00.000Z');
  });

  it('passes through an ISO string with a numeric offset', () => {
    const d = parseServerTimestamp('2026-04-26T18:00:00+02:00');
    // +02:00 means the UTC instant is two hours earlier
    expect(d?.toISOString()).toBe('2026-04-26T16:00:00.000Z');
  });

  it('anchors a bare date at UTC noon for stable day-of-month', () => {
    const d = parseServerTimestamp('2026-04-26');
    expect(d?.toISOString()).toBe('2026-04-26T12:00:00.000Z');
  });

  it('promotes a bare MySQL DATETIME to UTC', () => {
    const d = parseServerTimestamp('2026-04-26 18:00:00');
    expect(d?.toISOString()).toBe('2026-04-26T18:00:00.000Z');
  });

  it('returns null for an unparseable value', () => {
    expect(parseServerTimestamp('not-a-date')).toBeNull();
  });

  it('returns null for an invalid zoned-looking value', () => {
    expect(parseServerTimestamp('2026-13-99T99:99:99Z')).toBeNull();
  });
});

describe('formatServerDate', () => {
  it('returns a dash for nullish input', () => {
    expect(formatServerDate(null)).toBe('—');
    expect(formatServerDate(undefined)).toBe('—');
    expect(formatServerDate('garbage')).toBe('—');
  });

  it('renders a non-dash localised date for a valid value', () => {
    const out = formatServerDate('2026-04-26');
    expect(out).not.toBe('—');
    expect(out).not.toBe('Invalid Date');
    expect(out.length).toBeGreaterThan(0);
  });
});

describe('formatServerDateTime', () => {
  it('returns a dash for nullish/invalid input', () => {
    expect(formatServerDateTime(null)).toBe('—');
    expect(formatServerDateTime('garbage')).toBe('—');
  });

  it('renders a non-dash localised datetime for a valid value', () => {
    const out = formatServerDateTime('2026-04-26 18:00:00');
    expect(out).not.toBe('—');
    expect(out).not.toBe('Invalid Date');
  });
});

describe('daysBetween', () => {
  it('returns the whole-day difference', () => {
    expect(daysBetween('2026-04-01', '2026-04-11')).toBe(10);
  });

  it('rounds partial days up', () => {
    // 1 day and 1 hour apart -> rounds up to 2
    expect(daysBetween('2026-04-01 00:00:00', '2026-04-02 01:00:00')).toBe(2);
  });

  it('can be zero for the same instant', () => {
    expect(daysBetween('2026-04-01 12:00:00', '2026-04-01 12:00:00')).toBe(0);
  });

  it('can be negative when the range is reversed', () => {
    expect(daysBetween('2026-04-11', '2026-04-01')).toBe(-10);
  });

  it('returns null when either side fails to parse', () => {
    expect(daysBetween(null, '2026-04-01')).toBeNull();
    expect(daysBetween('2026-04-01', null)).toBeNull();
    expect(daysBetween('bad', 'also-bad')).toBeNull();
  });
});
