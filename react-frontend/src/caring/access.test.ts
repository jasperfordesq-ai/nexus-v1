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
} from './access';

describe('isCaringViewOnly', () => {
  it('is false for null/undefined users', () => {
    expect(isCaringViewOnly(null)).toBe(false);
    expect(isCaringViewOnly(undefined)).toBe(false);
  });

  it('detects view-only roles', () => {
    for (const role of ['view_only', 'read_only', 'viewer', 'admin_viewer']) {
      expect(isCaringViewOnly({ role })).toBe(true);
    }
  });

  it('detects each boolean view-only flag', () => {
    expect(isCaringViewOnly({ is_view_only: true })).toBe(true);
    expect(isCaringViewOnly({ is_read_only: true })).toBe(true);
    expect(isCaringViewOnly({ view_only: true })).toBe(true);
    expect(isCaringViewOnly({ read_only: true })).toBe(true);
  });

  it('detects a view-only token in a string permission field', () => {
    expect(isCaringViewOnly({ permissions: 'viewer' })).toBe(true);
    expect(isCaringViewOnly({ admin_permissions: 'read_only' })).toBe(true);
    expect(isCaringViewOnly({ capabilities: 'view_only' })).toBe(true);
  });

  it('detects a view-only token inside an array permission field', () => {
    expect(isCaringViewOnly({ permissions: ['edit', 'viewer'] })).toBe(true);
    expect(isCaringViewOnly({ capabilities: ['admin_viewer'] })).toBe(true);
  });

  it('is false for ordinary members and non-view-only tokens', () => {
    expect(isCaringViewOnly({ role: 'member' })).toBe(false);
    expect(isCaringViewOnly({ permissions: ['edit', 'delete'] })).toBe(false);
    expect(isCaringViewOnly({ is_view_only: 'true' })).toBe(false); // strictly true required
  });
});

describe('hasFullCaringAccess', () => {
  it('is false for null users', () => {
    expect(hasFullCaringAccess(null)).toBe(false);
  });

  it('grants access for full-access roles', () => {
    for (const role of ['admin', 'tenant_admin', 'super_admin', 'god']) {
      expect(hasFullCaringAccess({ role })).toBe(true);
    }
  });

  it('grants access for boolean admin flags', () => {
    expect(hasFullCaringAccess({ is_admin: true })).toBe(true);
    expect(hasFullCaringAccess({ is_super_admin: true })).toBe(true);
    expect(hasFullCaringAccess({ is_tenant_super_admin: true })).toBe(true);
    expect(hasFullCaringAccess({ is_god: true })).toBe(true);
  });

  it('denies coordinators, brokers and members', () => {
    expect(hasFullCaringAccess({ role: 'coordinator' })).toBe(false);
    expect(hasFullCaringAccess({ role: 'broker' })).toBe(false);
    expect(hasFullCaringAccess({ role: 'member' })).toBe(false);
  });
});

describe('hasSafeguardingAccess', () => {
  it('grants access to full-access users and safeguarding roles', () => {
    expect(hasSafeguardingAccess({ role: 'admin' })).toBe(true);
    expect(hasSafeguardingAccess({ role: 'coordinator' })).toBe(true);
    expect(hasSafeguardingAccess({ role: 'broker' })).toBe(true);
  });

  it('denies plain members and null users', () => {
    expect(hasSafeguardingAccess({ role: 'member' })).toBe(false);
    expect(hasSafeguardingAccess(null)).toBe(false);
  });
});

describe('canManageCaring', () => {
  it('is true only for full-access users who are not view-only', () => {
    expect(canManageCaring({ role: 'admin' })).toBe(true);
  });

  it('is false for a full-access user who is also flagged view-only', () => {
    expect(canManageCaring({ role: 'admin', is_view_only: true })).toBe(false);
  });

  it('is false for safeguarding-only and member roles', () => {
    expect(canManageCaring({ role: 'coordinator' })).toBe(false);
    expect(canManageCaring({ role: 'member' })).toBe(false);
    expect(canManageCaring(null)).toBe(false);
  });
});
