// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import {
  hasAdminPanelAccess,
  hasBrokerPanelAccess,
  hasBrokerRole,
  hasPartnerPanelAccess,
  isPlatformSuperAdminUser,
  isSuperAdminUser,
} from './access';

describe('hasBrokerRole', () => {
  it('returns true only for the broker role string', () => {
    expect(hasBrokerRole({ role: 'broker' })).toBe(true);
  });

  it('returns false for other roles', () => {
    expect(hasBrokerRole({ role: 'admin' })).toBe(false);
    expect(hasBrokerRole({ role: 'coordinator' })).toBe(false);
  });

  it('is null/undefined safe', () => {
    expect(hasBrokerRole(null)).toBe(false);
    expect(hasBrokerRole(undefined)).toBe(false);
    expect(hasBrokerRole({})).toBe(false);
  });

  it('ignores a non-string role value', () => {
    expect(hasBrokerRole({ role: 123 })).toBe(false);
  });
});

describe('hasAdminPanelAccess', () => {
  it('grants access for admin role strings', () => {
    expect(hasAdminPanelAccess({ role: 'admin' })).toBe(true);
    expect(hasAdminPanelAccess({ role: 'tenant_admin' })).toBe(true);
    expect(hasAdminPanelAccess({ role: 'super_admin' })).toBe(true);
  });

  it('grants access for each boolean admin flag', () => {
    expect(hasAdminPanelAccess({ is_admin: true })).toBe(true);
    expect(hasAdminPanelAccess({ is_super_admin: true })).toBe(true);
    expect(hasAdminPanelAccess({ is_tenant_super_admin: true })).toBe(true);
    expect(hasAdminPanelAccess({ is_god: true })).toBe(true);
  });

  it('denies brokers even if a boolean admin flag is set', () => {
    expect(hasAdminPanelAccess({ role: 'broker', is_admin: true })).toBe(false);
    expect(hasAdminPanelAccess({ role: 'broker', is_god: true })).toBe(false);
  });

  it('requires the boolean flag to be strictly true', () => {
    // truthy-but-not-true must not unlock the panel
    expect(hasAdminPanelAccess({ is_admin: 1 })).toBe(false);
    expect(hasAdminPanelAccess({ is_admin: 'yes' })).toBe(false);
  });

  it('denies ordinary members and null users', () => {
    expect(hasAdminPanelAccess({ role: 'member' })).toBe(false);
    expect(hasAdminPanelAccess(null)).toBe(false);
    expect(hasAdminPanelAccess(undefined)).toBe(false);
  });
});

describe('isSuperAdminUser', () => {
  it('grants for super_admin and god role strings', () => {
    expect(isSuperAdminUser({ role: 'super_admin' })).toBe(true);
    expect(isSuperAdminUser({ role: 'god' })).toBe(true);
  });

  it('grants for each super-admin boolean flag', () => {
    expect(isSuperAdminUser({ is_super_admin: true })).toBe(true);
    expect(isSuperAdminUser({ is_tenant_super_admin: true })).toBe(true);
    expect(isSuperAdminUser({ is_god: true })).toBe(true);
  });

  it('denies regular admins, tenant admins, brokers, members and null users', () => {
    expect(isSuperAdminUser({ role: 'admin' })).toBe(false);
    expect(isSuperAdminUser({ role: 'tenant_admin' })).toBe(false);
    expect(isSuperAdminUser({ role: 'admin', is_admin: true })).toBe(false);
    expect(isSuperAdminUser({ role: 'broker' })).toBe(false);
    expect(isSuperAdminUser({ role: 'member' })).toBe(false);
    expect(isSuperAdminUser(null)).toBe(false);
    expect(isSuperAdminUser(undefined)).toBe(false);
  });

  it('requires boolean flags to be strictly true', () => {
    expect(isSuperAdminUser({ is_super_admin: 1 })).toBe(false);
    expect(isSuperAdminUser({ is_tenant_super_admin: 'yes' })).toBe(false);
  });
});

describe('isPlatformSuperAdminUser', () => {
  it('grants platform access for platform super-admin and god roles or flags', () => {
    expect(isPlatformSuperAdminUser({ role: 'super_admin' })).toBe(true);
    expect(isPlatformSuperAdminUser({ role: 'god' })).toBe(true);
    expect(isPlatformSuperAdminUser({ is_super_admin: true })).toBe(true);
    expect(isPlatformSuperAdminUser({ is_god: true })).toBe(true);
  });

  it('rejects tenant-scoped super admins and ordinary admin tiers', () => {
    expect(isPlatformSuperAdminUser({ is_tenant_super_admin: true })).toBe(false);
    expect(isPlatformSuperAdminUser({ role: 'tenant_admin' })).toBe(false);
    expect(isPlatformSuperAdminUser({ role: 'admin', is_admin: true })).toBe(false);
    expect(isPlatformSuperAdminUser({ role: 'member' })).toBe(false);
    expect(isPlatformSuperAdminUser(null)).toBe(false);
  });

  it('requires platform boolean flags to be strictly true', () => {
    expect(isPlatformSuperAdminUser({ is_super_admin: 1 })).toBe(false);
    expect(isPlatformSuperAdminUser({ is_god: 'yes' })).toBe(false);
  });
});

describe('hasPartnerPanelAccess', () => {
  it('admits every admin tier (panel is read-mostly; plumbing gates on isSuperAdminUser)', () => {
    expect(hasPartnerPanelAccess({ role: 'admin' })).toBe(true);
    expect(hasPartnerPanelAccess({ role: 'tenant_admin' })).toBe(true);
    expect(hasPartnerPanelAccess({ role: 'super_admin' })).toBe(true);
    expect(hasPartnerPanelAccess({ is_tenant_super_admin: true })).toBe(true);
  });

  it('denies brokers, members and null users', () => {
    expect(hasPartnerPanelAccess({ role: 'broker' })).toBe(false);
    expect(hasPartnerPanelAccess({ role: 'member' })).toBe(false);
    expect(hasPartnerPanelAccess(null)).toBe(false);
    expect(hasPartnerPanelAccess(undefined)).toBe(false);
  });
});

describe('hasBrokerPanelAccess', () => {
  it('grants access for broker/coordinator/god roles', () => {
    expect(hasBrokerPanelAccess({ role: 'broker' })).toBe(true);
    expect(hasBrokerPanelAccess({ role: 'coordinator' })).toBe(true);
    expect(hasBrokerPanelAccess({ role: 'god' })).toBe(true);
  });

  it('also grants access to anyone with admin-panel access', () => {
    expect(hasBrokerPanelAccess({ role: 'admin' })).toBe(true);
    expect(hasBrokerPanelAccess({ is_super_admin: true })).toBe(true);
  });

  it('denies ordinary members and null users', () => {
    expect(hasBrokerPanelAccess({ role: 'member' })).toBe(false);
    expect(hasBrokerPanelAccess(null)).toBe(false);
    expect(hasBrokerPanelAccess(undefined)).toBe(false);
  });
});
