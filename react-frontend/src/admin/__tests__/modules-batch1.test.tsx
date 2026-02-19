// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for admin modules batch 1:
 * - AdminDashboard
 * - AdminPlaceholder
 * - AdminNotFound
 * - UserList, UserCreate, UserEdit
 * - ListingsAdmin
 * - BlogAdmin, BlogPostForm
 * - CategoriesAdmin
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
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
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    showToast: vi.fn(),
  })),
  useNotifications: vi.fn(() => ({ counts: { messages: 0, notifications: 0 } })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
  resolveAssetUrl: vi.fn((url) => url || ''),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_, tag) => {
      return ({ children, ...props }: any) => {
        const { variants, initial, animate, exit, layout, whileHover, whileTap, transition, ...rest } = props;
        const Tag = typeof tag === 'string' ? tag : 'div';
        return <Tag {...rest}>{children}</Tag>;
      };
    },
  }),
  AnimatePresence: ({ children }: any) => <>{children}</>,
}));

vi.mock('recharts', () => ({
  ResponsiveContainer: ({ children }: any) => <div>{children}</div>,
  BarChart: ({ children }: any) => <div>{children}</div>,
  Bar: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  Legend: () => null,
  LineChart: ({ children }: any) => <div>{children}</div>,
  Line: () => null,
  PieChart: ({ children }: any) => <div>{children}</div>,
  Pie: () => null,
  Cell: () => null,
  AreaChart: ({ children }: any) => <div>{children}</div>,
  Area: () => null,
}));

// Mock admin API modules
vi.mock('../../api/adminApi', () => ({
  adminDashboard: {
    getStats: vi.fn().mockResolvedValue({ success: true, data: { total_users: 100, active_users: 50, pending_users: 5, total_listings: 200, active_listings: 150, total_transactions: 300, total_hours_exchanged: 500, new_users_this_month: 10, new_listings_this_month: 20 } }),
    getTrends: vi.fn().mockResolvedValue({ success: true, data: [] }),
    getActivity: vi.fn().mockResolvedValue({ success: true, data: [] }),
  },
  adminUsers: {
    list: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } } }),
    get: vi.fn().mockResolvedValue({ success: true, data: { id: 1, first_name: 'John', last_name: 'Doe', email: 'john@test.com', role: 'member', status: 'active', balance: 10, has_2fa_enabled: false, is_super_admin: false, created_at: '2026-01-01', badges: [] } }),
    create: vi.fn().mockResolvedValue({ success: true }),
    update: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
    approve: vi.fn().mockResolvedValue({ success: true }),
    recheckAllBadges: vi.fn().mockResolvedValue({ success: true }),
    recheckUserBadges: vi.fn().mockResolvedValue({ success: true }),
    getConsents: vi.fn().mockResolvedValue({ success: true, data: [] }),
    importUsers: vi.fn().mockResolvedValue({ success: true }),
  },
  adminListings: {
    list: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } } }),
    approve: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  adminBlog: {
    list: vi.fn().mockResolvedValue({ success: true, data: { data: [], meta: { page: 1, total_pages: 1, per_page: 20, total: 0, has_more: false } } }),
    get: vi.fn().mockResolvedValue({ success: true, data: { id: 1, title: 'Test', slug: 'test', status: 'draft', author_id: 1, created_at: '2026-01-01' } }),
    create: vi.fn().mockResolvedValue({ success: true }),
    update: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
    toggleStatus: vi.fn().mockResolvedValue({ success: true }),
  },
  adminCategories: {
    list: vi.fn().mockResolvedValue({ success: true, data: [] }),
    create: vi.fn().mockResolvedValue({ success: true }),
    update: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  adminAttributes: {
    list: vi.fn().mockResolvedValue({ success: true, data: [] }),
    create: vi.fn().mockResolvedValue({ success: true }),
    update: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
}));

function Wrapper({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// AdminDashboard
// ─────────────────────────────────────────────────────────────────────────────

import { AdminDashboard } from '../modules/dashboard/AdminDashboard';

describe('AdminDashboard', () => {
  it('renders without crashing', () => {
    render(<Wrapper><AdminDashboard /></Wrapper>);
    expect(screen.getByText('Dashboard')).toBeInTheDocument();
  });

  it('shows stat card labels', () => {
    render(<Wrapper><AdminDashboard /></Wrapper>);
    expect(screen.getByText('Total Users')).toBeInTheDocument();
    expect(screen.getByText('Active Listings')).toBeInTheDocument();
    expect(screen.getByText('Transactions')).toBeInTheDocument();
    expect(screen.getByText('Hours Exchanged')).toBeInTheDocument();
  });

  it('shows Quick Actions section', () => {
    render(<Wrapper><AdminDashboard /></Wrapper>);
    expect(screen.getByText('Quick Actions')).toBeInTheDocument();
  });

  it('shows Monthly Trends section', () => {
    render(<Wrapper><AdminDashboard /></Wrapper>);
    expect(screen.getByText('Monthly Trends')).toBeInTheDocument();
  });

  it('shows Recent Activity section', () => {
    render(<Wrapper><AdminDashboard /></Wrapper>);
    expect(screen.getByText('Recent Activity')).toBeInTheDocument();
  });

  it('shows Refresh button', () => {
    render(<Wrapper><AdminDashboard /></Wrapper>);
    expect(screen.getByText('Refresh')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// AdminPlaceholder
// ─────────────────────────────────────────────────────────────────────────────

import { AdminPlaceholder } from '../modules/AdminPlaceholder';

describe('AdminPlaceholder', () => {
  it('renders title', () => {
    render(<Wrapper><AdminPlaceholder title="Events" /></Wrapper>);
    expect(screen.getByText('Events')).toBeInTheDocument();
  });

  it('shows migration message', () => {
    render(<Wrapper><AdminPlaceholder title="Events" /></Wrapper>);
    expect(screen.getByText('Migration In Progress')).toBeInTheDocument();
  });

  it('renders description', () => {
    render(<Wrapper><AdminPlaceholder title="Events" description="Manage events" /></Wrapper>);
    expect(screen.getByText('Manage events')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// AdminNotFound
// ─────────────────────────────────────────────────────────────────────────────

import { AdminNotFound } from '../modules/AdminNotFound';

describe('AdminNotFound', () => {
  it('renders 404 message', () => {
    render(<Wrapper><AdminNotFound /></Wrapper>);
    expect(screen.getByText('Admin Page Not Found')).toBeInTheDocument();
  });

  it('shows back to dashboard button', () => {
    render(<Wrapper><AdminNotFound /></Wrapper>);
    expect(screen.getByText('Back to Admin Dashboard')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// ListingsAdmin
// ─────────────────────────────────────────────────────────────────────────────

import { ListingsAdmin } from '../modules/listings/ListingsAdmin';

describe('ListingsAdmin', () => {
  it('renders without crashing', () => {
    render(<Wrapper><ListingsAdmin /></Wrapper>);
    expect(screen.getByText('Content Directory')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// BlogAdmin
// ─────────────────────────────────────────────────────────────────────────────

import { BlogAdmin } from '../modules/blog/BlogAdmin';

describe('BlogAdmin', () => {
  it('renders without crashing', () => {
    render(<Wrapper><BlogAdmin /></Wrapper>);
    expect(screen.getByText('Blog Posts')).toBeInTheDocument();
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// CategoriesAdmin
// ─────────────────────────────────────────────────────────────────────────────

import { CategoriesAdmin } from '../modules/categories/CategoriesAdmin';

describe('CategoriesAdmin', () => {
  it('renders without crashing', () => {
    render(<Wrapper><CategoriesAdmin /></Wrapper>);
    expect(screen.getByText('Categories')).toBeInTheDocument();
  });
});
