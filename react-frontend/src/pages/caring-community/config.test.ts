// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { CARING_COMMUNITY_ADMIN_ROUTE, CARING_COMMUNITY_ROUTE } from './config';

describe('Caring Community route config', () => {
  it('keeps the member and admin routes behind the same feature switch', () => {
    expect(CARING_COMMUNITY_ROUTE.path).toBe('caring-community');
    expect(CARING_COMMUNITY_ROUTE.href).toBe('/caring-community');
    expect(CARING_COMMUNITY_ROUTE.feature).toBe('caring_community');
    expect(CARING_COMMUNITY_ADMIN_ROUTE.path).toBe('caring');
    expect(CARING_COMMUNITY_ADMIN_ROUTE.href).toBe('/caring');
    expect(CARING_COMMUNITY_ADMIN_ROUTE.feature).toBe(CARING_COMMUNITY_ROUTE.feature);
  });
});
