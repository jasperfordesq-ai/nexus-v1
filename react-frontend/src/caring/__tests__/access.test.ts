// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import {
  canManageCaring,
  hasFullCaringAccess,
  hasSafeguardingAccess,
  isCaringViewOnly,
} from '../access';

describe('caring access helpers', () => {
  it('allows full Caring access for tenant admins', () => {
    expect(hasFullCaringAccess({ role: 'tenant_admin' })).toBe(true);
    expect(hasSafeguardingAccess({ role: 'tenant_admin' })).toBe(true);
  });

  it('allows safeguarding-only access for brokers and coordinators', () => {
    expect(hasFullCaringAccess({ role: 'broker' })).toBe(false);
    expect(hasSafeguardingAccess({ role: 'broker' })).toBe(true);
    expect(hasSafeguardingAccess({ role: 'coordinator' })).toBe(true);
  });

  it('treats view-only markers as read-only for admin actions', () => {
    const user = { role: 'tenant_admin', permissions: ['view_only'] };
    expect(isCaringViewOnly(user)).toBe(true);
    expect(canManageCaring(user)).toBe(false);
  });

  it('allows admin actions for non-view-only full admins', () => {
    expect(canManageCaring({ role: 'admin' })).toBe(true);
  });
});
