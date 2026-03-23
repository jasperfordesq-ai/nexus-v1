// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ProfileFeed component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, name: 'Test User', role: 'user' },
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test', logo_url: null },
    tenantSlug: 'test',
    tenantPath: (p: string) => '/test' + p,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
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
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | null) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url: string) => url),
  formatRelativeTime: vi.fn(() => '5 min ago'),
}));

// Mock FeedCard to avoid deep rendering complexity
vi.mock('@/components/feed/FeedCard', () => ({
  FeedCard: ({ item }: { item: { id: number; body?: string } }) => (
    <div data-testid={`feed-card-${item.id}`}>{item.body || 'Feed item'}</div>
  ),
}));

import { ProfileFeed } from '../ProfileFeed';

const mockFeedItems = [
  {
    id: 1,
    type: 'post',
    body: 'My first post',
    created_at: '2026-01-01T12:00:00Z',
    is_liked: false,
    likes_count: 3,
    comments_count: 1,
    user: { id: 5, name: 'John', avatar_url: null },
  },
  {
    id: 2,
    type: 'post',
    body: 'Second post',
    created_at: '2026-01-02T12:00:00Z',
    is_liked: true,
    likes_count: 7,
    comments_count: 0,
    user: { id: 5, name: 'John', avatar_url: null },
  },
];

describe('ProfileFeed', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders skeleton loading state initially', () => {
    vi.mocked(api.get).mockImplementation(() => new Promise(() => {}));

    const { container } = render(<ProfileFeed userId={5} />);
    // Skeleton elements are present (GlassCard with .p-5 class)
    expect(container.querySelector('.p-5')).toBeInTheDocument();
  });

  it('renders feed items after loading', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockFeedItems,
      meta: { has_more: false },
    });

    render(<ProfileFeed userId={5} />);

    await waitFor(() => {
      expect(screen.getByTestId('feed-card-1')).toBeInTheDocument();
      expect(screen.getByTestId('feed-card-2')).toBeInTheDocument();
    });
  });

  it('renders empty state when no feed items', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
      meta: { has_more: false },
    });

    render(<ProfileFeed userId={5} />);

    await waitFor(() => {
      // t('no_activity_title', 'No activity yet') uses default
      expect(screen.getByText('No activity yet')).toBeInTheDocument();
    });
  });

  it('shows own-profile empty text for own profile', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
      meta: { has_more: false },
    });

    render(<ProfileFeed userId={5} isOwnProfile={true} />);

    await waitFor(() => {
      expect(screen.getByText('Share your first post or create a listing to get started!')).toBeInTheDocument();
    });
  });

  it('shows other-profile empty text for other profile', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
      meta: { has_more: false },
    });

    render(<ProfileFeed userId={5} isOwnProfile={false} />);

    await waitFor(() => {
      expect(screen.getByText("This member hasn't posted anything yet.")).toBeInTheDocument();
    });
  });

  it('shows error state on API failure', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    render(<ProfileFeed userId={5} />);

    await waitFor(() => {
      expect(screen.getByText('Retry')).toBeInTheDocument();
    });
  });

  it('shows load more button when has_more is true', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: mockFeedItems,
      meta: { has_more: true, cursor: 'abc' },
    });

    render(<ProfileFeed userId={5} />);

    await waitFor(() => {
      expect(screen.getByText('Load more')).toBeInTheDocument();
    });
  });

  it('fetches feed with correct userId parameter', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [],
      meta: { has_more: false },
    });

    render(<ProfileFeed userId={42} />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('user_id=42')
      );
    });
  });
});
