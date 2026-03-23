// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TrendingHashtags component
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

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
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

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { TrendingHashtags } from '../TrendingHashtags';

const mockHashtags = [
  { tag: 'timebanking', post_count: 42, trend_direction: 'up' as const },
  { tag: 'community', post_count: 37, trend_direction: 'stable' as const },
  { tag: 'gardening', post_count: 15, trend_direction: 'down' as const },
];

describe('TrendingHashtags', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', () => {
    vi.mocked(api.get).mockReturnValueOnce(new Promise(() => {}));
    render(<TrendingHashtags />);
    // During loading the heading should already appear from the loading state
    expect(screen.getByRole('heading', { level: 3 }) || document.querySelector('h3')).toBeTruthy();
  });

  it('renders nothing when API returns empty array', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    const { container } = render(<TrendingHashtags />);
    await waitFor(() => {
      // HeroUI provider wrapper is always present, so container.firstChild is never null.
      // Instead, verify that no hashtag-related content is rendered.
      expect(container.textContent?.trim()).toBe('');
    });
  });

  it('displays hashtag names after loading', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockHashtags });

    render(<TrendingHashtags />);
    await waitFor(() => {
      expect(screen.getByText('#timebanking')).toBeInTheDocument();
      expect(screen.getByText('#community')).toBeInTheDocument();
      expect(screen.getByText('#gardening')).toBeInTheDocument();
    });
  });

  it('displays post counts for each hashtag', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockHashtags });

    render(<TrendingHashtags />);
    await waitFor(() => {
      // i18n renders real English text e.g. "42 posts"
      expect(screen.getByText('42 posts')).toBeInTheDocument();
    });
  });

  it('shows rank numbers for each hashtag', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockHashtags });

    render(<TrendingHashtags />);
    await waitFor(() => {
      expect(screen.getByText('1')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();
      expect(screen.getByText('3')).toBeInTheDocument();
    });
  });

  it('renders hashtag links pointing to feed/hashtag pages', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockHashtags });

    render(<TrendingHashtags />);
    await waitFor(() => {
      const timebankingLink = screen.getByText('#timebanking').closest('a');
      expect(timebankingLink).toHaveAttribute('href', '/test/feed/hashtag/timebanking');
    });
  });

  it('renders a View All link to /feed/hashtags', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: mockHashtags });

    render(<TrendingHashtags />);
    await waitFor(() => {
      const viewAllLink = screen.getByRole('link', { name: /view/i }) ||
        [...screen.getAllByRole('link')].find((l) => l.getAttribute('href') === '/test/feed/hashtags');
      expect(viewAllLink).toBeTruthy();
    });
  });

  it('calls API with the limit parameter', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });

    render(<TrendingHashtags limit={5} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/feed/hashtags/trending?limit=5');
    });
  });

  it('handles API error gracefully without crashing', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    const { container } = render(<TrendingHashtags />);
    await waitFor(() => {
      // HeroUI provider wrapper is always present, so container.firstChild is never null.
      // Instead, verify that no hashtag-related content is rendered.
      expect(container.textContent?.trim()).toBe('');
    });
  });
});
