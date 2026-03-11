// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Super Admin modules (super-admin/ directory):
 * - SuperAuditLog, FederationAuditLog, FederationControls,
 *   FederationSystemControls, FederationTenantFeatures, FederationWhitelist,
 *   Partnerships, TenantForm, TenantHierarchy, TenantListAdmin, TenantShow
 *
 * Smoke tests only — verify each component renders without crashing.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Common mocks ────────────────────────────────────────────────────────────

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn(), getAccessToken: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Admin', last_name: 'User', name: 'Admin User', role: 'admin', is_super_admin: true, tenant_id: 2 },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  AuthProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() })),
  useNotifications: vi.fn(() => ({ counts: { messages: 0, notifications: 0 } })),
}));

vi.mock('@/contexts/AuthContext', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Admin', last_name: 'User', name: 'Admin User', role: 'admin', is_super_admin: true, tenant_id: 2 },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  AuthProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Super-admin modules import from specific hook/context paths
vi.mock('@/hooks/useApi', () => {
  const mockData: unknown[] = [];
  const mockMeta = { current_page: 1, last_page: 1, per_page: 25, total: 0 };
  return {
    useApi: vi.fn(() => ({
      data: mockData,
      meta: mockMeta,
      isLoading: false,
      error: null,
      execute: vi.fn(),
    }))
  };
});

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() })),
}));

vi.mock('@/contexts/TenantContext', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string) => url || '/default.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
  resolveAssetUrl: vi.fn((url: string) => url || ''),
}));

vi.mock('@/lib/tenant-routing', () => ({
  tenantPath: vi.fn((p: string) => `/test${p}`),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

// Mock admin components imported by super-admin modules (they use direct path imports)
vi.mock('../../../components/PageHeader', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  PageHeader: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock('../../../components/ConfirmModal', () => ({
  default: () => null,
  ConfirmModal: () => null,
}));

vi.mock('@/admin/components/PageHeader', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  PageHeader: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock('@/admin/components/DataTable', () => ({
  DataTable: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  StatusBadge: () => <span>status</span>,
  Column: {},
}));

vi.mock('@/admin/components/ConfirmModal', () => ({
  default: () => null,
  ConfirmModal: () => null,
}));

// Complete adminSuper mock covering all methods used by super-admin components
const mockAdminSuper = vi.hoisted(() => ({
  // Dashboard & Audit
  getDashboard: vi.fn().mockResolvedValue({ success: true, data: {} }),
  getAudit: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: { page: 1, total_pages: 1, per_page: 25, total: 0 } } }),
  getAuditLog: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: { page: 1, total_pages: 1, per_page: 25, total: 0 } } }),
  exportAuditLog: vi.fn().mockResolvedValue({ success: true }),
  // Tenant management
  listTenants: vi.fn().mockResolvedValue({ success: true, data: [] }),
  getTenants: vi.fn().mockResolvedValue({ success: true, data: [] }),
  getTenant: vi.fn().mockResolvedValue({
    success: true,
    data: {
      id: 1, name: 'Test Tenant', slug: 'test', domain: 'test.example.com',
      status: 'active', is_hub: false, parent_id: null,
      configuration: {}, features: {}, modules: {},
      created_at: '2026-01-01', user_count: 10,
    },
  }),
  createTenant: vi.fn().mockResolvedValue({ success: true }),
  updateTenant: vi.fn().mockResolvedValue({ success: true }),
  deleteTenant: vi.fn().mockResolvedValue({ success: true }),
  reactivateTenant: vi.fn().mockResolvedValue({ success: true }),
  toggleHub: vi.fn().mockResolvedValue({ success: true }),
  moveTenant: vi.fn().mockResolvedValue({ success: true }),
  getHierarchy: vi.fn().mockResolvedValue({ success: true, data: [] }),
  updateHierarchy: vi.fn().mockResolvedValue({ success: true }),
  // Federation system controls
  getFederationStatus: vi.fn().mockResolvedValue({
    success: true,
    data: {
      system_controls: {
        federation_enabled: true, whitelist_mode_enabled: false, is_locked_down: false,
        lockdown_reason: null, cross_tenant_profiles_enabled: true, cross_tenant_messaging_enabled: true,
        cross_tenant_transactions_enabled: false, cross_tenant_listings_enabled: true,
        cross_tenant_events_enabled: false, cross_tenant_groups_enabled: false,
      },
      whitelisted_count: 0,
      partnership_stats: { active: 0, pending: 0, suspended: 0, terminated: 0 },
      recent_audit: [],
    },
  }),
  getSystemControls: vi.fn().mockResolvedValue({
    success: true,
    data: {
      federation_enabled: true, whitelist_mode_enabled: false, is_locked_down: false,
      lockdown_reason: null, cross_tenant_profiles_enabled: true, cross_tenant_messaging_enabled: true,
      cross_tenant_transactions_enabled: false, cross_tenant_listings_enabled: true,
      cross_tenant_events_enabled: false, cross_tenant_groups_enabled: false,
    },
  }),
  updateSystemControls: vi.fn().mockResolvedValue({ success: true }),
  emergencyLockdown: vi.fn().mockResolvedValue({ success: true }),
  liftLockdown: vi.fn().mockResolvedValue({ success: true }),
  // Federation whitelist
  getWhitelist: vi.fn().mockResolvedValue({ success: true, data: [] }),
  addToWhitelist: vi.fn().mockResolvedValue({ success: true }),
  removeFromWhitelist: vi.fn().mockResolvedValue({ success: true }),
  // Federation partnerships
  getFederationPartnerships: vi.fn().mockResolvedValue({ success: true, data: [] }),
  suspendPartnership: vi.fn().mockResolvedValue({ success: true }),
  terminatePartnership: vi.fn().mockResolvedValue({ success: true }),
  // Federation tenant features
  getTenantFederationFeatures: vi.fn().mockResolvedValue({ success: true, data: [] }),
  updateTenantFederationFeature: vi.fn().mockResolvedValue({ success: true }),
}));

vi.mock('@/admin/api/adminApi', () => ({ adminSuper: mockAdminSuper }));

// Also mock the relative path import used by some super-admin components
vi.mock('../../../api/adminApi', () => ({ adminSuper: mockAdminSuper }));

// Mock admin API types
vi.mock('@/admin/api/types', () => ({}));
vi.mock('../../../api/types', () => ({}));

// ─── Wrapper helpers ─────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

function WRoute({ children, path, entry }: { children: React.ReactNode; path: string; entry: string }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={[entry]}>
        <Routes>
          <Route path={path} element={children} />
        </Routes>
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── SuperAuditLog ──────────────────────────────────────────────────────────

import SuperAuditLog from '../super-admin/SuperAuditLog';

describe('SuperAuditLog', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><SuperAuditLog /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationAuditLog (super-admin) ───────────────────────────────────────

import SAFederationAuditLog from '../super-admin/FederationAuditLog';

describe('FederationAuditLog (super-admin)', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><SAFederationAuditLog /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationControls (super-admin) ───────────────────────────────────────

import SAFederationControls from '../super-admin/FederationControls';

describe('FederationControls (super-admin)', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><SAFederationControls /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationSystemControls ───────────────────────────────────────────────

import FederationSystemControls from '../super-admin/FederationSystemControls';

describe('FederationSystemControls', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><FederationSystemControls /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationTenantFeatures (super-admin) ─────────────────────────────────

import SAFederationTenantFeatures from '../super-admin/FederationTenantFeatures';

describe('FederationTenantFeatures (super-admin)', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super-admin/federation/tenants/:tenantId" entry="/admin/super-admin/federation/tenants/1">
        <SAFederationTenantFeatures />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationWhitelist ────────────────────────────────────────────────────

import FederationWhitelist from '../super-admin/FederationWhitelist';

describe('FederationWhitelist', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><FederationWhitelist /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── Partnerships ───────────────────────────────────────────────────────────

import Partnerships from '../super-admin/Partnerships';

describe('Partnerships', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><Partnerships /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantForm (super-admin) ───────────────────────────────────────────────

import { TenantForm as SATenantForm } from '../super-admin/TenantForm';

describe('TenantForm (super-admin)', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super-admin/tenants/:id/edit" entry="/admin/super-admin/tenants/1/edit">
        <SATenantForm />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantHierarchy (super-admin) ──────────────────────────────────────────

import { TenantHierarchy as SATenantHierarchy } from '../super-admin/TenantHierarchy';

describe('TenantHierarchy (super-admin)', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><SATenantHierarchy /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantListAdmin ────────────────────────────────────────────────────────

import { TenantListAdmin } from '../super-admin/TenantListAdmin';

describe('TenantListAdmin', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><TenantListAdmin /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantShow (super-admin) ───────────────────────────────────────────────

import { TenantShow as SATenantShow } from '../super-admin/TenantShow';

describe('TenantShow (super-admin)', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super-admin/tenants/:id" entry="/admin/super-admin/tenants/1">
        <SATenantShow />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});
