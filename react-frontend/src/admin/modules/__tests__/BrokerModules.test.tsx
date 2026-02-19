// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Broker admin modules:
 * - BrokerDashboard, ExchangeManagement, RiskTags, MessageReview,
 *   UserMonitoring, VettingRecords, BrokerConfiguration, ExchangeDetail
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

// Mock admin API modules used by broker components
vi.mock('../../api/adminApi', () => ({
  adminBroker: {
    getDashboard: vi.fn().mockResolvedValue({
      success: true,
      data: {
        pending_exchanges: 0, unreviewed_messages: 0, high_risk_listings: 0,
        monitored_users: 0, vetting_pending: 0, vetting_expiring: 0,
        safeguarding_alerts: 0, recent_activity: [],
      },
    }),
    getExchanges: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } },
    }),
    showExchange: vi.fn().mockResolvedValue({
      success: true,
      data: {
        exchange: { id: 1, requester_id: 1, requester_name: 'A', provider_id: 2, provider_name: 'B', status: 'pending', created_at: '2026-01-01' },
        history: [], risk_tag: null,
      },
    }),
    getRiskTags: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getMessages: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } },
    }),
    getMonitoring: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getConfiguration: vi.fn().mockResolvedValue({ success: true, data: {} }),
    saveConfiguration: vi.fn().mockResolvedValue({ success: true }),
    approveExchange: vi.fn().mockResolvedValue({ success: true }),
    rejectExchange: vi.fn().mockResolvedValue({ success: true }),
  },
  adminVetting: {
    list: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } },
    }),
    stats: vi.fn().mockResolvedValue({
      success: true,
      data: { total: 0, pending: 0, verified: 0, expired: 0, expiring_soon: 0 },
    }),
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

// ─── BrokerDashboard ─────────────────────────────────────────────────────────

import { BrokerDashboard } from '../broker/BrokerDashboard';

describe('BrokerDashboard', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><BrokerDashboard /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── ExchangeManagement ──────────────────────────────────────────────────────

import { ExchangeManagement } from '../broker/ExchangeManagement';

describe('ExchangeManagement', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><ExchangeManagement /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── RiskTags ────────────────────────────────────────────────────────────────

import { RiskTagsPage } from '../broker/RiskTags';

describe('RiskTagsPage', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><RiskTagsPage /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── MessageReview ───────────────────────────────────────────────────────────

import { MessageReview } from '../broker/MessageReview';

describe('MessageReview', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><MessageReview /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── UserMonitoring ──────────────────────────────────────────────────────────

import { UserMonitoring } from '../broker/UserMonitoring';

describe('UserMonitoring', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><UserMonitoring /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── VettingRecords ──────────────────────────────────────────────────────────

import { VettingRecords } from '../broker/VettingRecords';

describe('VettingRecords', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><VettingRecords /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── BrokerConfiguration ─────────────────────────────────────────────────────

import BrokerConfiguration from '../broker/BrokerConfiguration';

describe('BrokerConfiguration', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><BrokerConfiguration /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── ExchangeDetail ──────────────────────────────────────────────────────────

import ExchangeDetail from '../broker/ExchangeDetail';

describe('ExchangeDetail', () => {
  it('renders without crashing with route param', () => {
    const { container } = render(
      <WRoute path="/admin/broker-controls/exchanges/:id" entry="/admin/broker-controls/exchanges/1">
        <ExchangeDetail />
      </WRoute>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });
});
