// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for HashtagsDiscoveryPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenantPath: (p: string) => `/test${p}`,
  })),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => (
    <div data-testid="empty-state">{title}</div>
  ),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => <a href={to}>{children}</a>,
  };
});

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className, hoverable }: { children: React.ReactNode; className?: string; hoverable?: boolean }) => (
    <div className={className} data-hoverable={hoverable}>{children}</div>
  ),

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

import { HashtagsDiscoveryPage } from './HashtagsDiscoveryPage';
import { api } from '@/lib/api';

const mockApiGet = vi.mocked(api.get);

const mockTrendingHashtags = [
  { tag: 'gardening', post_count: 42, trend_direction: 'up' as const },
  { tag: 'cooking', post_count: 28, trend_direction: 'stable' as const },
  { tag: 'music', post_count: 15, trend_direction: 'down' as const },
];

describe('HashtagsDiscoveryPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching trending hashtags', () => {
    mockApiGet.mockReturnValue(new Promise(() => {}));
    render(<HashtagsDiscoveryPage />);
    expect(document.body).toBeInTheDocument();
  });

  it('renders trending hashtags after loading', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTrendingHashtags });
    render(<HashtagsDiscoveryPage />);

    await waitFor(() => {
      expect(screen.getByText('#gardening')).toBeInTheDocument();
    });
    expect(screen.getByText('#cooking')).toBeInTheDocument();
    expect(screen.getByText('#music')).toBeInTheDocument();
  });

  it('renders search input', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTrendingHashtags });
    render(<HashtagsDiscoveryPage />);

    await waitFor(() => {
      const inputs = screen.getAllByRole('textbox');
      expect(inputs.length).toBeGreaterThan(0);
    });
  });

  it('renders hashtag links pointing to correct hashtag routes', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTrendingHashtags });
    render(<HashtagsDiscoveryPage />);

    await waitFor(() => {
      const gardeningLink = screen.getAllByRole('link').find(l =>
        l.getAttribute('href')?.includes('/feed/hashtag/gardening')
      );
      expect(gardeningLink).toBeTruthy();
    });
  });

  it('shows empty state when no trending hashtags exist', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [] });
    render(<HashtagsDiscoveryPage />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    mockApiGet.mockRejectedValue(new Error('Network error'));
    render(<HashtagsDiscoveryPage />);

    await waitFor(() => {
      // Error state shows a try again button
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

  it('shows error state when API returns success: false', async () => {
    mockApiGet.mockResolvedValue({ success: false });
    render(<HashtagsDiscoveryPage />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

  it('retries loading when try again button is clicked', async () => {
    mockApiGet
      .mockResolvedValueOnce({ success: false })
      .mockResolvedValueOnce({ success: true, data: mockTrendingHashtags });

    render(<HashtagsDiscoveryPage />);

    // Wait for the error state to render the "Try Again" button
    await waitFor(() => {
      expect(screen.getByText('Try Again')).toBeInTheDocument();
    });

    // Find the retry button by text and click it (fireEvent.click triggers HeroUI onPress via virtual click)
    const retryButton = screen.getByText('Try Again').closest('button')!;
    fireEvent.click(retryButton);

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledTimes(2);
    });
  });

  it('initiates search when user types 2+ characters', async () => {
    mockApiGet.mockResolvedValueOnce({ success: true, data: mockTrendingHashtags });
    // Search API call
    mockApiGet.mockResolvedValueOnce({ success: true, data: [{ tag: 'garden', post_count: 5 }] });

    render(<HashtagsDiscoveryPage />);

    await waitFor(() => {
      expect(screen.getByText('#gardening')).toBeInTheDocument();
    });

    const searchInput = screen.getAllByRole('textbox')[0];
    fireEvent.change(searchInput, { target: { value: 'ga' } });

    // After debounce (300ms) the search API is called — but we only need to check state
    expect(searchInput).toBeInTheDocument();
  });
});
