// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LeaderboardPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// Mock API module
// Default mock: returns null data for seasons (SeasonCard) and empty array for leaderboard
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockImplementation((url: string) => {
      if (url.includes('/seasons')) {
        return Promise.resolve({ success: true, data: null, meta: {} });
      }
      return Promise.resolve({ success: true, data: [], meta: {} });
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

// Mock contexts - must include ToastProvider since test-utils.tsx uses it
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
  AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
}));

import { LeaderboardPage } from './LeaderboardPage';

describe('LeaderboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders page title and description', () => {
    render(<LeaderboardPage />);
    expect(screen.getByText('Leaderboard')).toBeInTheDocument();
    expect(screen.getByText("See who's leading the community")).toBeInTheDocument();
  });

  it('shows type selector with XP as default', () => {
    render(<LeaderboardPage />);
    expect(screen.getByLabelText('Leaderboard type')).toBeInTheDocument();
  });

  it('shows period selector', () => {
    render(<LeaderboardPage />);
    expect(screen.getByLabelText('Leaderboard period')).toBeInTheDocument();
  });

  it('shows loading skeleton initially', () => {
    render(<LeaderboardPage />);
    const skeletons = document.querySelectorAll('.animate-pulse');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('shows empty state when no entries are loaded', async () => {
    // Default mock already returns [] for leaderboard and null for seasons
    render(<LeaderboardPage />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
    expect(screen.getByText('No rankings yet')).toBeInTheDocument();
  });

  it('displays leaderboard entries when loaded', async () => {
    const { api } = await import('@/lib/api');
    const mockEntries = [
      {
        position: 1,
        user: { id: 10, name: 'Alice Champion', avatar_url: null },
        xp: 5000,
        score: 5000,
        level: 15,
        is_current_user: false,
      },
      {
        position: 2,
        user: { id: 1, name: 'Test User', avatar_url: null },
        xp: 3500,
        score: 3500,
        level: 12,
        is_current_user: true,
      },
      {
        position: 3,
        user: { id: 20, name: 'Bob Runner', avatar_url: null },
        xp: 2000,
        score: 2000,
        level: 8,
        is_current_user: false,
      },
    ];

    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/seasons')) {
        return Promise.resolve({ success: true, data: null, meta: {} });
      }
      return Promise.resolve({
        success: true,
        data: mockEntries,
        meta: {
          period: 'all',
          type: 'xp',
          your_position: 2,
          total_entries: 50,
        },
      });
    });

    render(<LeaderboardPage />);

    await waitFor(() => {
      expect(screen.getByText('Alice Champion')).toBeInTheDocument();
    });
    expect(screen.getByText('Test User')).toBeInTheDocument();
    expect(screen.getByText('Bob Runner')).toBeInTheDocument();
    // Check XP scores are displayed
    expect(screen.getByText('5,000')).toBeInTheDocument();
    expect(screen.getByText('3,500')).toBeInTheDocument();
    // Check levels
    expect(screen.getByText('Level 15')).toBeInTheDocument();
    expect(screen.getByText('Level 12')).toBeInTheDocument();
  });

  it('highlights current user entry', async () => {
    const { api } = await import('@/lib/api');
    const mockEntries = [
      {
        position: 1,
        user: { id: 1, name: 'Test User', avatar_url: null },
        xp: 5000,
        score: 5000,
        level: 15,
        is_current_user: true,
      },
    ];

    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/seasons')) {
        return Promise.resolve({ success: true, data: null, meta: {} });
      }
      return Promise.resolve({
        success: true,
        data: mockEntries,
        meta: {
          period: 'all',
          type: 'xp',
          your_position: 1,
          total_entries: 50,
        },
      });
    });

    render(<LeaderboardPage />);

    await waitFor(() => {
      expect(screen.getByText('(You)')).toBeInTheDocument();
    });
  });

  it('shows error state on API failure', async () => {
    const { api } = await import('@/lib/api');
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/seasons')) {
        return Promise.resolve({ success: true, data: null, meta: {} });
      }
      return Promise.reject(new Error('Network error'));
    });

    render(<LeaderboardPage />);

    await waitFor(() => {
      expect(screen.getByText('Unable to Load Leaderboard')).toBeInTheDocument();
    });
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });
});
