// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupExchangesPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// Mock API module
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: {} }),
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

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, ...props }: Record<string, unknown>) => <div {...props}>{children}</div>,
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

import { GroupExchangesPage } from './GroupExchangesPage';

describe('GroupExchangesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders page title and description', () => {
    render(<GroupExchangesPage />);
    expect(screen.getByText('Group Exchanges')).toBeInTheDocument();
    expect(screen.getByText('Multi-participant exchanges with split types and confirmations')).toBeInTheDocument();
  });

  it('shows New Exchange button', () => {
    render(<GroupExchangesPage />);
    expect(screen.getByText('New Exchange')).toBeInTheDocument();
  });

  it('renders status filter tabs', () => {
    render(<GroupExchangesPage />);
    expect(screen.getByText('All')).toBeInTheDocument();
    expect(screen.getByText('Active')).toBeInTheDocument();
    expect(screen.getByText('Needs Confirmation')).toBeInTheDocument();
    expect(screen.getByText('Completed')).toBeInTheDocument();
    expect(screen.getByText('Cancelled')).toBeInTheDocument();
  });

  it('shows loading skeleton initially', () => {
    render(<GroupExchangesPage />);
    // The loading skeletons are GlassCard divs with animate-pulse class
    const skeletons = document.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('shows empty state when no exchanges are loaded', async () => {
    const { api } = await import('@/lib/api');
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [], meta: {} });

    render(<GroupExchangesPage />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
    expect(screen.getByText('No Group Exchanges Found')).toBeInTheDocument();
  });

  it('displays group exchanges when loaded', async () => {
    const { api } = await import('@/lib/api');
    const mockExchanges = [
      {
        id: 1,
        title: 'Community Garden Project',
        description: 'A group gardening exchange',
        organizer_id: 1,
        organizer_name: 'Test User',
        organizer_avatar: null,
        status: 'active',
        split_type: 'equal',
        total_hours: 10,
        participant_count: 4,
        created_at: '2026-01-20T10:00:00Z',
        updated_at: '2026-01-20T10:00:00Z',
        completed_at: null,
      },
    ];

    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: mockExchanges,
      meta: { has_more: false },
    });

    render(<GroupExchangesPage />);

    await waitFor(() => {
      expect(screen.getByText('Community Garden Project')).toBeInTheDocument();
    });
    // "Active" appears as both a tab and a status chip, so check multiple matches
    expect(screen.getAllByText('Active').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText(/4 participants/)).toBeInTheDocument();
    expect(screen.getByText(/10 hours/)).toBeInTheDocument();
  });

  it('shows organizer role indicator for current user', async () => {
    const { api } = await import('@/lib/api');
    const mockExchanges = [
      {
        id: 1,
        title: 'My Group Exchange',
        description: null,
        organizer_id: 1,
        organizer_name: 'Test User',
        organizer_avatar: null,
        status: 'active',
        split_type: 'equal',
        total_hours: 5,
        participant_count: 3,
        created_at: '2026-01-20T10:00:00Z',
        updated_at: '2026-01-20T10:00:00Z',
        completed_at: null,
      },
    ];

    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: mockExchanges,
      meta: { has_more: false },
    });

    render(<GroupExchangesPage />);

    await waitFor(() => {
      expect(screen.getByText('You organized')).toBeInTheDocument();
    });
  });

  it('shows split type badge on exchange cards', async () => {
    const { api } = await import('@/lib/api');
    const mockExchanges = [
      {
        id: 1,
        title: 'Weighted Exchange',
        description: null,
        organizer_id: 2,
        organizer_name: 'Other User',
        organizer_avatar: null,
        status: 'completed',
        split_type: 'weighted',
        total_hours: 8,
        participant_count: 2,
        created_at: '2026-01-20T10:00:00Z',
        updated_at: '2026-01-25T10:00:00Z',
        completed_at: '2026-01-25T10:00:00Z',
      },
    ];

    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: mockExchanges,
      meta: { has_more: false },
    });

    render(<GroupExchangesPage />);

    await waitFor(() => {
      expect(screen.getByText('weighted split')).toBeInTheDocument();
    });
  });
});
