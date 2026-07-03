// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it } from 'vitest';
import {
  clearResumePosition,
  readResumePosition,
  readSpeedPreference,
  saveResumePosition,
  saveSpeedPreference,
} from './resumeStore';

const TENANT = 2;

describe('resumeStore', () => {
  beforeEach(() => {
    window.localStorage.clear();
  });

  it('round-trips a resume position per tenant and episode', () => {
    saveResumePosition(TENANT, 42, 123.9);
    expect(readResumePosition(TENANT, 42)).toBe(123);
    // Different tenant/episode keys stay isolated.
    expect(readResumePosition(TENANT, 43)).toBeNull();
    expect(readResumePosition(9, 42)).toBeNull();
  });

  it('clears a single episode position', () => {
    saveResumePosition(TENANT, 1, 60);
    saveResumePosition(TENANT, 2, 90);
    clearResumePosition(TENANT, 1);
    expect(readResumePosition(TENANT, 1)).toBeNull();
    expect(readResumePosition(TENANT, 2)).toBe(90);
  });

  it('ignores invalid positions', () => {
    saveResumePosition(TENANT, 1, 0);
    saveResumePosition(TENANT, 2, Number.NaN);
    expect(readResumePosition(TENANT, 1)).toBeNull();
    expect(readResumePosition(TENANT, 2)).toBeNull();
  });

  it('prunes the oldest entries beyond the cap', () => {
    for (let i = 1; i <= 55; i++) {
      saveResumePosition(TENANT, i, 30 + i);
    }
    // Oldest entries are evicted; the most recent survive.
    expect(readResumePosition(TENANT, 1)).toBeNull();
    expect(readResumePosition(TENANT, 55)).toBe(85);
    const map = JSON.parse(window.localStorage.getItem(`nexus:podcasts:resume:${TENANT}`) ?? '{}');
    expect(Object.keys(map).length).toBeLessThanOrEqual(50);
  });

  it('persists and validates the speed preference', () => {
    expect(readSpeedPreference(TENANT)).toBe(1);
    saveSpeedPreference(TENANT, 1.5);
    expect(readSpeedPreference(TENANT)).toBe(1.5);
    // Out-of-range values are rejected on write and read.
    saveSpeedPreference(TENANT, 9);
    expect(readSpeedPreference(TENANT)).toBe(1.5);
  });
});
