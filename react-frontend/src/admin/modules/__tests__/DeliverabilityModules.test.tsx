// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Deliverability admin modules:
 * - CreateDeliverable, DeliverabilityAnalytics,
 *   DeliverabilityDashboard, DeliverablesList
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

// Mock admin API modules used by deliverability components
vi.mock('../../api/adminApi', () => ({
  adminDeliverability: {
    getDashboard: vi.fn().mockResolvedValue({
      success: true,
      data: { total: 0, by_status: {}, overdue: 0, completion_rate: 0, recent_activity: [] },
    }),
    list: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } },
    }),
    create: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
    getAnalytics: vi.fn().mockResolvedValue({
      success: true,
      data: { completion_trends: [], priority_distribution: {}, avg_days_to_complete: null, risk_distribution: {} },
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

// ─── CreateDeliverable ──────────────────────────────────────────────────────

import { CreateDeliverable } from '../deliverability/CreateDeliverable';

describe('CreateDeliverable', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><CreateDeliverable /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── DeliverabilityAnalytics ────────────────────────────────────────────────

import { DeliverabilityAnalytics } from '../deliverability/DeliverabilityAnalytics';

describe('DeliverabilityAnalytics', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><DeliverabilityAnalytics /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── DeliverabilityDashboard ────────────────────────────────────────────────

import { DeliverabilityDashboard } from '../deliverability/DeliverabilityDashboard';

describe('DeliverabilityDashboard', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><DeliverabilityDashboard /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── DeliverablesList ───────────────────────────────────────────────────────

import { DeliverablesList } from '../deliverability/DeliverablesList';

describe('DeliverablesList', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><DeliverablesList /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
