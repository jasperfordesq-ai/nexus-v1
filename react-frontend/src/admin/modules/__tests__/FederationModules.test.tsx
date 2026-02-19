// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Federation admin modules:
 * - FederationSettings, ApiKeys, CreateApiKey, DataManagement,
 *   FederationAnalytics, MyProfile, PartnerDirectory, Partnerships
 *
 * Smoke tests only — verify each component renders without crashing.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
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

// Mock admin API modules used by federation components
vi.mock('../../api/adminApi', () => ({
  adminFederation: {
    getSettings: vi.fn().mockResolvedValue({
      success: true,
      data: {
        federation_enabled: false,
        tenant_id: 2,
        settings: {
          allow_inbound_partnerships: true,
          auto_approve_partners: false,
          shared_categories: [],
          max_partnerships: 10,
        },
      },
    }),
    updateSettings: vi.fn().mockResolvedValue({ success: true }),
    getPartnerships: vi.fn().mockResolvedValue({ success: true, data: [] }),
    approvePartnership: vi.fn().mockResolvedValue({ success: true }),
    rejectPartnership: vi.fn().mockResolvedValue({ success: true }),
    terminatePartnership: vi.fn().mockResolvedValue({ success: true }),
    getDirectory: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getProfile: vi.fn().mockResolvedValue({
      success: true,
      data: { name: 'Test Community', description: '', contact_email: '', is_visible: true },
    }),
    updateProfile: vi.fn().mockResolvedValue({ success: true }),
    getAnalytics: vi.fn().mockResolvedValue({
      success: true,
      data: { partnerships: 0, shared_listings: 0, cross_transactions: 0, activity: [] },
    }),
    getApiKeys: vi.fn().mockResolvedValue({ success: true, data: [] }),
    createApiKey: vi.fn().mockResolvedValue({ success: true, data: { key: 'test-key-123' } }),
    getDataManagement: vi.fn().mockResolvedValue({
      success: true,
      data: { shared_data: [], import_history: [], export_history: [] },
    }),
  },
}));

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── FederationSettings ──────────────────────────────────────────────────────

import { FederationSettings } from '../federation/FederationSettings';

describe('FederationSettings', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><FederationSettings /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── ApiKeys ─────────────────────────────────────────────────────────────────

import { ApiKeys } from '../federation/ApiKeys';

describe('ApiKeys', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><ApiKeys /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── CreateApiKey ────────────────────────────────────────────────────────────

import { CreateApiKey } from '../federation/CreateApiKey';

describe('CreateApiKey', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><CreateApiKey /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── DataManagement ──────────────────────────────────────────────────────────

import { DataManagement } from '../federation/DataManagement';

describe('DataManagement', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><DataManagement /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FederationAnalytics ─────────────────────────────────────────────────────

import { FederationAnalytics } from '../federation/FederationAnalytics';

describe('FederationAnalytics', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><FederationAnalytics /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── MyProfile ───────────────────────────────────────────────────────────────

import { MyProfile } from '../federation/MyProfile';

describe('MyProfile', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><MyProfile /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── PartnerDirectory ────────────────────────────────────────────────────────

import { PartnerDirectory } from '../federation/PartnerDirectory';

describe('PartnerDirectory', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><PartnerDirectory /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── Partnerships ────────────────────────────────────────────────────────────

import { Partnerships } from '../federation/Partnerships';

describe('Partnerships', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><Partnerships /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
