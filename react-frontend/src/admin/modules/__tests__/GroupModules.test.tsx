// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Group admin modules:
 * - GroupList, GroupDetail, GroupAnalytics, GroupApprovals,
 *   GroupModeration, GroupPolicies, GroupRanking,
 *   GroupRecommendations, GroupTypes
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

// Also mock individual context imports (GroupDetail etc. import directly)
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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));

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

// Mock admin API modules used by group components
vi.mock('../../api/adminApi', () => ({
  adminGroups: {
    list: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } },
    }),
    getGroup: vi.fn().mockResolvedValue({
      success: true,
      data: { id: 1, name: 'Test Group', description: 'A test group', status: 'active', member_count: 5, visibility: 'public', created_at: '2026-01-01' },
    }),
    updateGroup: vi.fn().mockResolvedValue({ success: true }),
    getAnalytics: vi.fn().mockResolvedValue({
      success: true,
      data: { total_groups: 10, active_groups: 8, total_members: 100, avg_members: 10, growth: [] },
    }),
    getApprovals: vi.fn().mockResolvedValue({ success: true, data: [] }),
    approveMember: vi.fn().mockResolvedValue({ success: true }),
    rejectMember: vi.fn().mockResolvedValue({ success: true }),
    getModeration: vi.fn().mockResolvedValue({ success: true, data: [] }),
    updateStatus: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
    getGroupTypes: vi.fn().mockResolvedValue({ success: true, data: [] }),
    createGroupType: vi.fn().mockResolvedValue({ success: true }),
    updateGroupType: vi.fn().mockResolvedValue({ success: true }),
    deleteGroupType: vi.fn().mockResolvedValue({ success: true }),
    getPolicies: vi.fn().mockResolvedValue({ success: true, data: [] }),
    setPolicy: vi.fn().mockResolvedValue({ success: true }),
    getMembers: vi.fn().mockResolvedValue({ success: true, data: [] }),
    promoteMember: vi.fn().mockResolvedValue({ success: true }),
    demoteMember: vi.fn().mockResolvedValue({ success: true }),
    kickMember: vi.fn().mockResolvedValue({ success: true }),
    geocodeGroup: vi.fn().mockResolvedValue({ success: true }),
    batchGeocode: vi.fn().mockResolvedValue({ success: true }),
    getRecommendationData: vi.fn().mockResolvedValue({
      success: true,
      data: { recommendations: [], stats: { total: 0, avg_score: 0, join_rate: 0 } },
    }),
    getFeaturedGroups: vi.fn().mockResolvedValue({ success: true, data: [] }),
    updateFeaturedGroups: vi.fn().mockResolvedValue({ success: true }),
    toggleFeatured: vi.fn().mockResolvedValue({ success: true }),
  },
}));

// Also mock @/admin/api/adminApi (GroupDetail etc. use absolute imports)
vi.mock('@/admin/api/adminApi', () => ({
  adminGroups: {
    list: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: {} } }),
    getGroup: vi.fn().mockResolvedValue({ success: true, data: { id: 1, name: 'Test Group', description: 'Test', status: 'active', member_count: 5 } }),
    updateGroup: vi.fn().mockResolvedValue({ success: true }),
    getMembers: vi.fn().mockResolvedValue({ success: true, data: [] }),
    promoteMember: vi.fn().mockResolvedValue({ success: true }),
    demoteMember: vi.fn().mockResolvedValue({ success: true }),
    kickMember: vi.fn().mockResolvedValue({ success: true }),
    geocodeGroup: vi.fn().mockResolvedValue({ success: true }),
    getGroupTypes: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getPolicies: vi.fn().mockResolvedValue({ success: true, data: [] }),
    setPolicy: vi.fn().mockResolvedValue({ success: true }),
    getFeaturedGroups: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getRecommendationData: vi.fn().mockResolvedValue({ success: true, data: { recommendations: [], stats: { total: 0, avg_score: 0, join_rate: 0 } } }),
    toggleFeatured: vi.fn().mockResolvedValue({ success: true }),
    updateFeaturedGroups: vi.fn().mockResolvedValue({ success: true }),
    getAnalytics: vi.fn().mockResolvedValue({ success: true, data: {} }),
    getApprovals: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getModeration: vi.fn().mockResolvedValue({ success: true, data: [] }),
  },
}));

// ─── Wrapper helpers ─────────────────────────────────────────────────────────

function W({ children, path = '/test/admin' }: { children: React.ReactNode; path?: string }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={[path]}>
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

// ─── GroupList ────────────────────────────────────────────────────────────────

import { GroupList } from '../groups/GroupList';

describe('GroupList', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GroupList /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GroupDetail ─────────────────────────────────────────────────────────────

import GroupDetail from '../groups/GroupDetail';

describe('GroupDetail', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/groups/:id" entry="/admin/groups/1">
        <GroupDetail />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GroupAnalytics ──────────────────────────────────────────────────────────

import { GroupAnalytics } from '../groups/GroupAnalytics';

describe('GroupAnalytics', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GroupAnalytics /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GroupApprovals ──────────────────────────────────────────────────────────

import { GroupApprovals } from '../groups/GroupApprovals';

describe('GroupApprovals', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GroupApprovals /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GroupModeration ─────────────────────────────────────────────────────────

import { GroupModeration } from '../groups/GroupModeration';

describe('GroupModeration', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GroupModeration /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GroupPolicies ───────────────────────────────────────────────────────────
// Note: This is a modal component requiring props (isOpen, onClose, typeId, typeName).

import GroupPolicies from '../groups/GroupPolicies';

describe('GroupPolicies', () => {
  it('renders without crashing when closed', () => {
    const { container } = render(
      <W>
        <GroupPolicies isOpen={false} onClose={vi.fn()} typeId={1} typeName="Test Type" />
      </W>
    );
    expect(container).toBeTruthy();
  });

  it('renders without crashing when open', () => {
    const { container } = render(
      <W>
        <GroupPolicies isOpen={true} onClose={vi.fn()} typeId={1} typeName="Test Type" />
      </W>
    );
    expect(container).toBeTruthy();
  });
});

// ─── GroupRanking ────────────────────────────────────────────────────────────

import GroupRanking from '../groups/GroupRanking';

describe('GroupRanking', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GroupRanking /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GroupRecommendations ────────────────────────────────────────────────────

import GroupRecommendations from '../groups/GroupRecommendations';

describe('GroupRecommendations', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GroupRecommendations /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GroupTypes ──────────────────────────────────────────────────────────────

import GroupTypes from '../groups/GroupTypes';

describe('GroupTypes', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GroupTypes /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
