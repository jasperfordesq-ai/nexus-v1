// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ExchangesPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// Mock API module
// Default mock: returns exchange_workflow_enabled config and empty exchanges
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockImplementation((url: string) => {
      if (url.includes('/config')) {
        return Promise.resolve({
          success: true,
          data: { exchange_workflow_enabled: true },
          meta: {},
        });
      }
      return Promise.resolve({ success: true, data: [], meta: {} });
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

// Mock contexts
vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/lib/exchange-status', () => ({
  EXCHANGE_STATUS_CONFIG: {
    pending_provider: { label: 'Pending Provider', color: 'warning', icon: () => null },
    pending_requester: { label: 'Pending Requester', color: 'warning', icon: () => null },
    accepted: { label: 'Accepted', color: 'success', icon: () => null },
    active: { label: 'Active', color: 'primary', icon: () => null },
    pending_confirmation: { label: 'Pending Confirmation', color: 'warning', icon: () => null },
    completed: { label: 'Completed', color: 'success', icon: () => null },
    cancelled: { label: 'Cancelled', color: 'danger', icon: () => null },
    declined: { label: 'Declined', color: 'danger', icon: () => null },
    expired: { label: 'Expired', color: 'default', icon: () => null },
    disputed: { label: 'Disputed', color: 'danger', icon: () => null },
  },
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, ...props }: Record<string, unknown>) => <div {...props}>{children}</div>,
  ExchangeCardSkeleton: () => <div data-testid="exchange-skeleton">Loading...</div>,
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <h2>{title}</h2>
      {description && <p>{description}</p>}
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, layout, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { ExchangesPage } from './ExchangesPage';

describe('ExchangesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders page title and description', () => {
    render(<ExchangesPage />);
    expect(screen.getByText('My Exchanges')).toBeInTheDocument();
    expect(screen.getByText('Track your service exchange requests and confirmations')).toBeInTheDocument();
  });

  it('shows Browse Listings button', () => {
    render(<ExchangesPage />);
    expect(screen.getByText('Browse Listings')).toBeInTheDocument();
  });

  it('renders status filter tabs', () => {
    render(<ExchangesPage />);
    expect(screen.getByText('Active')).toBeInTheDocument();
    expect(screen.getByText('Needs Confirmation')).toBeInTheDocument();
    expect(screen.getByText('Completed')).toBeInTheDocument();
    expect(screen.getByText('All')).toBeInTheDocument();
  });

  it('shows loading skeletons initially', () => {
    render(<ExchangesPage />);
    const skeletons = screen.getAllByTestId('exchange-skeleton');
    expect(skeletons.length).toBe(4);
  });

  it('shows empty state when no exchanges are loaded', async () => {
    // Default mock already returns config with exchange_workflow_enabled: true
    // and empty exchanges array, so just render
    render(<ExchangesPage />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
    expect(screen.getByText('No Exchanges Found')).toBeInTheDocument();
  });

  it('displays exchanges when loaded', async () => {
    const { api } = await import('@/lib/api');
    const mockExchanges = [
      {
        id: 1,
        requester_id: 1,
        provider_id: 2,
        status: 'active',
        proposed_hours: 2,
        created_at: '2026-01-15T10:00:00Z',
        listing: { title: 'Gardening Help' },
        requester: { name: 'Test User', avatar: null },
        provider: { name: 'Provider User', avatar: null },
        requester_confirmed_at: null,
        provider_confirmed_at: null,
      },
    ];

    // First call for config, second for exchanges
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: { exchange_workflow_enabled: true }, meta: {} })
      .mockResolvedValueOnce({ success: true, data: mockExchanges, meta: {} });

    render(<ExchangesPage />);

    await waitFor(() => {
      expect(screen.getByText('Gardening Help')).toBeInTheDocument();
    });
    expect(screen.getByText(/Provider User/)).toBeInTheDocument();
  });

  it('shows role indicator for requester', async () => {
    const { api } = await import('@/lib/api');
    const mockExchanges = [
      {
        id: 1,
        requester_id: 1,
        provider_id: 2,
        status: 'active',
        proposed_hours: 1,
        created_at: '2026-01-15T10:00:00Z',
        listing: { title: 'Test Service' },
        requester: { name: 'Test User', avatar: null },
        provider: { name: 'Other User', avatar: null },
        requester_confirmed_at: null,
        provider_confirmed_at: null,
      },
    ];

    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: { exchange_workflow_enabled: true }, meta: {} })
      .mockResolvedValueOnce({ success: true, data: mockExchanges, meta: {} });

    render(<ExchangesPage />);

    await waitFor(() => {
      expect(screen.getByText('You requested')).toBeInTheDocument();
    });
  });

  it('shows hour count on exchange cards', async () => {
    const { api } = await import('@/lib/api');
    const mockExchanges = [
      {
        id: 1,
        requester_id: 1,
        provider_id: 2,
        status: 'active',
        proposed_hours: 3,
        created_at: '2026-01-15T10:00:00Z',
        listing: { title: 'Cooking Lessons' },
        requester: { name: 'Test User', avatar: null },
        provider: { name: 'Chef Bob', avatar: null },
        requester_confirmed_at: null,
        provider_confirmed_at: null,
      },
    ];

    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: { exchange_workflow_enabled: true }, meta: {} })
      .mockResolvedValueOnce({ success: true, data: mockExchanges, meta: {} });

    render(<ExchangesPage />);

    await waitFor(() => {
      expect(screen.getByText('3 hours')).toBeInTheDocument();
    });
  });
});
