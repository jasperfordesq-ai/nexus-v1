// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Moderation admin modules:
 * - CommentsModeration, FeedModeration, ReportsManagement, ReviewsModeration
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

// Moderation modules import useApi and useToast directly from specific paths
vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks/useApi', () => ({
  useApi: vi.fn(() => ({
    data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
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

// Mock admin components imported by moderation modules (they use direct path imports)
vi.mock('@/admin/components/PageHeader', () => ({
  default: ({ children }: any) => <div>{children}</div>,
  PageHeader: ({ children }: any) => <div>{children}</div>,
}));

vi.mock('@/admin/components/ConfirmModal', () => ({
  default: () => null,
  ConfirmModal: () => null,
}));

// Mock admin API modules used by moderation components
vi.mock('@/admin/api/adminApi', () => ({
  adminModeration: {
    getComments: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    }),
    getFeedPosts: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    }),
    getReports: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    }),
    getReportStats: vi.fn().mockResolvedValue({
      success: true,
      data: { total: 0, pending: 0, resolved: 0, dismissed: 0 },
    }),
    getReviews: vi.fn().mockResolvedValue({
      success: true,
      data: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } },
    }),
    hideComment: vi.fn().mockResolvedValue({ success: true }),
    deleteComment: vi.fn().mockResolvedValue({ success: true }),
    hideFeedPost: vi.fn().mockResolvedValue({ success: true }),
    deleteFeedPost: vi.fn().mockResolvedValue({ success: true }),
    resolveReport: vi.fn().mockResolvedValue({ success: true }),
    dismissReport: vi.fn().mockResolvedValue({ success: true }),
    flagReview: vi.fn().mockResolvedValue({ success: true }),
    hideReview: vi.fn().mockResolvedValue({ success: true }),
    deleteReview: vi.fn().mockResolvedValue({ success: true }),
  },
}));

// Mock admin API types
vi.mock('@/admin/api/types', () => ({}));

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

// ─── CommentsModeration ─────────────────────────────────────────────────────

import CommentsModeration from '../moderation/CommentsModeration';

describe('CommentsModeration', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><CommentsModeration /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── FeedModeration ─────────────────────────────────────────────────────────

import FeedModeration from '../moderation/FeedModeration';

describe('FeedModeration', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><FeedModeration /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── ReportsManagement ──────────────────────────────────────────────────────

import ReportsManagement from '../moderation/ReportsManagement';

describe('ReportsManagement', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><ReportsManagement /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});

// ─── ReviewsModeration ──────────────────────────────────────────────────────

import ReviewsModeration from '../moderation/ReviewsModeration';

describe('ReviewsModeration', () => {
  it('renders without crashing', () => {
    const { container } = render(<W><ReviewsModeration /></W>);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
