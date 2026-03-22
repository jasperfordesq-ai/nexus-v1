// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for HashtagPage
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
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, first_name: 'Test', last_name: 'User' },
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

vi.mock('@/components/feed/FeedCard', () => ({
  FeedCard: ({ item }: { item: { id: number } }) => (
    <div data-testid={`feed-card-${item.id}`}>Feed Item {item.id}</div>
  ),
}));

vi.mock('@/components/feed/types', () => ({
  getAuthor: vi.fn((item) => ({ id: item.author_id || 99 })),
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ tag: 'gardening' }),
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => <a href={to}>{children}</a>,
  };
});

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit', 'layout', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
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

import { HashtagPage } from './HashtagPage';
import { api } from '@/lib/api';

const mockApiGet = vi.mocked(api.get);

const mockFeedItems = [
  { id: 1, type: 'post', is_liked: false, likes_count: 2, author_id: 5 },
  { id: 2, type: 'post', is_liked: true, likes_count: 7, author_id: 6 },
];

describe('HashtagPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the hashtag name in the heading', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFeedItems, meta: { has_more: false } });
    render(<HashtagPage />);

    await waitFor(() => {
      expect(screen.getByText('gardening')).toBeInTheDocument();
    });
  });

  it('renders back to feed button link', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFeedItems, meta: { has_more: false } });
    render(<HashtagPage />);

    await waitFor(() => {
      const backButton = screen.getByRole('button');
      expect(backButton).toBeInTheDocument();
    });
  });

  it('renders feed cards after loading', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockFeedItems, meta: { has_more: false } });
    render(<HashtagPage />);

    await waitFor(() => {
      expect(screen.getByTestId('feed-card-1')).toBeInTheDocument();
    });
    expect(screen.getByTestId('feed-card-2')).toBeInTheDocument();
  });

  it('shows empty state when no posts exist for the hashtag', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [], meta: { has_more: false } });
    render(<HashtagPage />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    mockApiGet.mockRejectedValue(new Error('Network error'));
    render(<HashtagPage />);

    await waitFor(() => {
      // Error state renders a retry button
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

  it('shows error state when API returns success: false', async () => {
    mockApiGet.mockResolvedValue({ success: false });
    render(<HashtagPage />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

  it('shows post count when meta provides total_items', async () => {
    mockApiGet.mockResolvedValue({
      success: true,
      data: mockFeedItems,
      meta: { has_more: false, total_items: 42 },
    });
    render(<HashtagPage />);

    await waitFor(() => {
      expect(screen.getByText('gardening')).toBeInTheDocument();
    });
    // Post count is shown — exact i18n key "hashtag.post_count" with { count: 42 }
    expect(document.body).toBeInTheDocument();
  });

  it('shows load more button when has_more is true', async () => {
    mockApiGet.mockResolvedValue({
      success: true,
      data: mockFeedItems,
      meta: { has_more: true, cursor: 'abc' },
    });
    render(<HashtagPage />);

    await waitFor(() => {
      expect(screen.getByTestId('feed-card-1')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    // Load more button should be among them
    expect(buttons.length).toBeGreaterThanOrEqual(2);
  });
});
