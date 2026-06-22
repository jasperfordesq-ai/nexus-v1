// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import type { LandingIconId } from '@/types';
import { getIcon, iconMap } from './iconMap';

const ALL_IDS: LandingIconId[] = [
  'clock', 'users', 'zap', 'user-plus', 'search', 'handshake', 'coins',
  'heart', 'shield', 'star', 'globe', 'book-open', 'message-circle',
  'award', 'target', 'thumbs-up',
];

describe('iconMap', () => {
  it('maps all 16 known icon ids to a component', () => {
    expect(Object.keys(iconMap)).toHaveLength(16);
    for (const id of ALL_IDS) {
      expect(iconMap[id]).toBeTruthy();
    }
  });
});

describe('getIcon', () => {
  it('returns the mapped icon for a known id', () => {
    expect(getIcon('heart')).toBe(iconMap.heart);
    expect(getIcon('thumbs-up')).toBe(iconMap['thumbs-up']);
  });

  it('returns the Clock default when id is undefined', () => {
    expect(getIcon(undefined)).toBe(iconMap.clock);
  });

  it('returns the default fallback for an unrecognised id', () => {
    expect(getIcon('does-not-exist' as LandingIconId)).toBe(iconMap.clock);
  });

  it('uses a custom fallback only when the id is missing/unknown', () => {
    const custom = iconMap.star;
    expect(getIcon(undefined, custom)).toBe(custom);
    expect(getIcon('nope' as LandingIconId, custom)).toBe(custom);
    // a valid id always wins over the fallback
    expect(getIcon('users', custom)).toBe(iconMap.users);
  });
});
