// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { TFunction } from 'i18next';
import { describe, expect, it } from 'vitest';
import { badgeDisplayDescription, badgeDisplayName } from './badgeDisplay';

const translations: Record<string, string> = {
  'gamification.badges.vol_1h.name': 'Localized first steps',
  'gamification.badges.vol_1h.description': 'Localized requirement',
};
const t = ((key: string) => translations[key] ?? key) as TFunction;

describe('badge display localization', () => {
  it('uses stable codes for built-in badge copy', () => {
    const badge = {
      name: 'Server name',
      description: 'Server description',
      name_code: 'badges.vol_1h.name',
      description_code: 'badges.vol_1h.description',
    };

    expect(badgeDisplayName(t, badge)).toBe('Localized first steps');
    expect(badgeDisplayDescription(t, badge)).toBe('Localized requirement');
  });

  it('preserves tenant-authored badge copy when no code is present', () => {
    const badge = { name: 'Neighbourhood Hero', description: 'Chosen by our members' };

    expect(badgeDisplayName(t, badge)).toBe('Neighbourhood Hero');
    expect(badgeDisplayDescription(t, badge)).toBe('Chosen by our members');
  });
});
