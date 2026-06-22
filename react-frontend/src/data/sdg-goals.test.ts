// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { SDG_GOALS } from './sdg-goals';

describe('SDG_GOALS', () => {
  it('contains exactly the 17 UN goals', () => {
    expect(SDG_GOALS).toHaveLength(17);
  });

  it('has sequential, unique ids from 1 to 17', () => {
    const ids = SDG_GOALS.map((g) => g.id);
    expect(ids).toEqual(Array.from({ length: 17 }, (_, i) => i + 1));
    expect(new Set(ids).size).toBe(17);
  });

  it('gives every goal a non-empty label and emoji icon', () => {
    for (const goal of SDG_GOALS) {
      expect(goal.label.length).toBeGreaterThan(0);
      expect(goal.icon.length).toBeGreaterThan(0);
    }
  });

  it('uses valid 6-digit hex colours', () => {
    for (const goal of SDG_GOALS) {
      expect(goal.color).toMatch(/^#[0-9A-Fa-f]{6}$/);
    }
  });
});
