// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for simple admin modules:
 * - EventsAdmin, GoalsAdmin, IdeationAdmin, JobsAdmin, PerformanceDashboard, PollsAdmin
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

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks/useApi', () => ({
  useApi: vi.fn(() => ({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
    isLoading: false,
    error: null,
    execute: vi.fn(),
  })),
}));

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

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('@/admin/components/PageHeader', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  PageHeader: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock('@/admin/components/ConfirmModal', () => ({
  default: () => null,
  ConfirmModal: () => null,
}));

vi.mock('@/admin/components/DataTable', () => ({
  DataTable: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  StatusBadge: () => null,
  Column: undefined,
}));

vi.mock('@/lib/tenant-routing', () => ({
  tenantPath: (p: string) => `/test${p}`,
}));

// Mock the barrel re-export from ../../components used by these modules
vi.mock('../../components', () => ({
  PageHeader: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  DataTable: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  ConfirmModal: () => null,
  EmptyState: () => null,
  StatusBadge: () => null,
  Column: undefined,
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

// ─── EventsAdmin ─────────────────────────────────────────────────────────────

import EventsAdmin from '../events/EventsAdmin';

describe('EventsAdmin', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><EventsAdmin /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── GoalsAdmin ──────────────────────────────────────────────────────────────

import GoalsAdmin from '../goals/GoalsAdmin';

describe('GoalsAdmin', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><GoalsAdmin /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── IdeationAdmin ───────────────────────────────────────────────────────────

import IdeationAdmin from '../ideation/IdeationAdmin';

describe('IdeationAdmin', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><IdeationAdmin /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── JobsAdmin ───────────────────────────────────────────────────────────────

import JobsAdmin from '../jobs/JobsAdmin';

describe('JobsAdmin', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><JobsAdmin /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── PerformanceDashboard ────────────────────────────────────────────────────

import PerformanceDashboard from '../performance/PerformanceDashboard';

describe('PerformanceDashboard', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><PerformanceDashboard /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── PollsAdmin ──────────────────────────────────────────────────────────────

import PollsAdmin from '../polls/PollsAdmin';

describe('PollsAdmin', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><PollsAdmin /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
