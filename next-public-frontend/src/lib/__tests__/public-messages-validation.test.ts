// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import routeOwnershipManifest from '../../../route-ownership.json';
import { createTranslator } from '../i18n';

describe('public message validation', () => {
  it('resolves every Next-owned public route label key', () => {
    const t = createTranslator('en');

    for (const route of routeOwnershipManifest.nextPublicRoutes) {
      expect(t(route.labelKey), route.routeKey).not.toBe(route.labelKey);
    }
  });

  it('resolves listings chrome through the Irish locale path', () => {
    const t = createTranslator('ga');

    expect(t('pages.listings.title')).toBe('Liostuithe');
    expect(t('listings.providerLabel')).toBe('Soláthraí');
    expect(t('listings.valueHours', { count: 2 })).toBe('2 uair');
  });
});
