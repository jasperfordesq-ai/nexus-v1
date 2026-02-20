// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Super admin modules:
 * - BulkOperations, FederationAuditLog, FederationControls,
 *   FederationTenantFeatures, SuperDashboard, SuperUserForm,
 *   SuperUserList, TenantForm, TenantHierarchy, TenantList,
 *   TenantShow, UserShow
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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string) => url || '/default.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
  resolveAssetUrl: vi.fn((url: string) => url || ''),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_, tag) => ({ children, ...props }: any) => {
      const { variants, initial, animate, exit, layout, whileHover, whileTap, transition, ...rest } = props;
      const Tag = typeof tag === 'string' ? tag : 'div';
      return <Tag {...rest}>{children}</Tag>;
    },
  }),
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: any) => <div>{children}</div>,
  BarChart: ({ children }: any) => <div>{children}</div>,
  Bar: () => null, XAxis: () => null, YAxis: () => null,
  CartesianGrid: () => null, Tooltip: () => null, Legend: () => null,
  LineChart: ({ children }: any) => <div>{children}</div>,
  Line: () => null,
  PieChart: ({ children }: any) => <div>{children}</div>,
  Pie: () => null, Cell: () => null,
  AreaChart: ({ children }: any) => <div>{children}</div>,
  Area: () => null,
}));

// Mock admin API modules used by super components
vi.mock('../../api/adminApi', () => ({
  adminSuper: {
    getDashboard: vi.fn().mockResolvedValue({
      success: true,
      data: {
        tenants: 5, users: 100, active_users: 50, total_transactions: 200,
        total_hours: 500, recent_tenants: [], system_health: {},
      },
    }),
    listTenants: vi.fn().mockResolvedValue({ success: true, data: [] }),
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
    getHierarchy: vi.fn().mockResolvedValue({ success: true, data: [] }),
    updateHierarchy: vi.fn().mockResolvedValue({ success: true }),
    listUsers: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getUser: vi.fn().mockResolvedValue({
      success: true,
      data: {
        id: 1, first_name: 'Test', last_name: 'User', email: 'test@example.com',
        role: 'member', status: 'active', tenant_id: 2, tenant_name: 'Test',
        created_at: '2026-01-01',
      },
    }),
    createUser: vi.fn().mockResolvedValue({ success: true }),
    updateUser: vi.fn().mockResolvedValue({ success: true }),
    deleteUser: vi.fn().mockResolvedValue({ success: true }),
    bulkMoveUsers: vi.fn().mockResolvedValue({ success: true }),
    bulkUpdateTenants: vi.fn().mockResolvedValue({ success: true }),
    grantSuperAdmin: vi.fn().mockResolvedValue({ success: true }),
    revokeSuperAdmin: vi.fn().mockResolvedValue({ success: true }),
    grantGlobalSuperAdmin: vi.fn().mockResolvedValue({ success: true }),
    revokeGlobalSuperAdmin: vi.fn().mockResolvedValue({ success: true }),
    moveUserTenant: vi.fn().mockResolvedValue({ success: true }),
    moveAndPromote: vi.fn().mockResolvedValue({ success: true }),
    getAudit: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0 } } }),
    getSystemControls: vi.fn().mockResolvedValue({ success: true, data: {} }),
    updateSystemControls: vi.fn().mockResolvedValue({ success: true }),
    getWhitelist: vi.fn().mockResolvedValue({ success: true, data: [] }),
    addToWhitelist: vi.fn().mockResolvedValue({ success: true }),
    removeFromWhitelist: vi.fn().mockResolvedValue({ success: true }),
    getFederationPartnerships: vi.fn().mockResolvedValue({ success: true, data: [] }),
    suspendPartnership: vi.fn().mockResolvedValue({ success: true }),
    terminatePartnership: vi.fn().mockResolvedValue({ success: true }),
    emergencyLockdown: vi.fn().mockResolvedValue({ success: true }),
    liftLockdown: vi.fn().mockResolvedValue({ success: true }),
    getTenantFederationFeatures: vi.fn().mockResolvedValue({ success: true, data: {} }),
    updateTenantFederationFeature: vi.fn().mockResolvedValue({ success: true }),
    getAuditLog: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: { page: 1, total_pages: 1, per_page: 25, total: 0 } } }),
    exportAuditLog: vi.fn().mockResolvedValue({ success: true }),
  },
}));

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

// ─── BulkOperations ─────────────────────────────────────────────────────────

import { BulkOperations } from '../super/BulkOperations';

describe('BulkOperations', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><BulkOperations /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationAuditLog ─────────────────────────────────────────────────────

import { FederationAuditLog } from '../super/FederationAuditLog';

describe('FederationAuditLog (super)', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><FederationAuditLog /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationControls ─────────────────────────────────────────────────────

import { FederationControls } from '../super/FederationControls';

describe('FederationControls (super)', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><FederationControls /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationTenantFeatures ───────────────────────────────────────────────

import { FederationTenantFeatures } from '../super/FederationTenantFeatures';

describe('FederationTenantFeatures (super)', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super/federation/tenants/:tenantId" entry="/admin/super/federation/tenants/1">
        <FederationTenantFeatures />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── SuperDashboard ─────────────────────────────────────────────────────────

import { SuperDashboard } from '../super/SuperDashboard';

describe('SuperDashboard', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><SuperDashboard /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── SuperUserForm ──────────────────────────────────────────────────────────

import { SuperUserForm } from '../super/SuperUserForm';

describe('SuperUserForm', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super/users/:id" entry="/admin/super/users/1">
        <SuperUserForm />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── SuperUserList ──────────────────────────────────────────────────────────

import { SuperUserList } from '../super/SuperUserList';

describe('SuperUserList', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><SuperUserList /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantForm ─────────────────────────────────────────────────────────────

import { TenantForm } from '../super/TenantForm';

describe('TenantForm (super)', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super/tenants/:id" entry="/admin/super/tenants/1">
        <TenantForm />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantHierarchy ────────────────────────────────────────────────────────

import { TenantHierarchy } from '../super/TenantHierarchy';

describe('TenantHierarchy (super)', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><TenantHierarchy /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantList ─────────────────────────────────────────────────────────────

import { TenantList } from '../super/TenantList';

describe('TenantList (super)', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><TenantList /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── TenantShow ─────────────────────────────────────────────────────────────

import { TenantShow } from '../super/TenantShow';

describe('TenantShow (super)', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super/tenants/:id" entry="/admin/super/tenants/1">
        <TenantShow />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── UserShow ───────────────────────────────────────────────────────────────

import { UserShow } from '../super/UserShow';

describe('UserShow (super)', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/super/users/:id" entry="/admin/super/users/1">
        <UserShow />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});
