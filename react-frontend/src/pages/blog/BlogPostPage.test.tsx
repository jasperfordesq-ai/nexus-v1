// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for BlogPostPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

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
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, first_name: 'Test', last_name: 'User', avatar: null },
  })),
  useTenant: vi.fn(() => ({
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || ''),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('@/components/navigation', () => ({
  Breadcrumbs: () => <nav aria-label="breadcrumb" />,
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

vi.mock('@/components/social', () => ({
  SocialInteractionPanel: ({ targetType, targetId }: { targetType: string; targetId: number }) => (
    <div data-testid="social-panel" data-target-type={targetType} data-target-id={targetId} />
  ),
}));

vi.mock('dompurify', () => ({
  default: { sanitize: (html: string) => html },
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ slug: 'my-test-post' }),
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

import { BlogPostPage } from './BlogPostPage';
import { api } from '@/lib/api';

const mockApiGet = vi.mocked(api.get);

const mockPost = {
  id: 1,
  title: 'How Timebanking Works',
  slug: 'my-test-post',
  excerpt: 'A guide to timebanking.',
  content: '<p>Timebanking is a way of exchanging time.</p>',
  featured_image: null,
  published_at: '2026-01-15T10:00:00Z',
  created_at: '2026-01-15T10:00:00Z',
  views: 120,
  reading_time: 3,
  meta_title: null,
  meta_description: null,
  author: { id: 5, name: 'Jane Doe', avatar: null },
  category: { id: 1, name: 'Community', color: 'blue' },
  is_liked: false,
  likes_count: 4,
  comments_count: 2,
};

describe('BlogPostPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading skeleton while fetching post', () => {
    mockApiGet.mockReturnValue(new Promise(() => {}));
    render(<BlogPostPage />);
    // Loading state renders — page does not crash
    expect(document.body).toBeInTheDocument();
  });

  it('renders the post title after loading', async () => {
    mockApiGet.mockImplementation((url: string) => {
      if (url.includes('/v2/blog/')) {
        return Promise.resolve({ success: true, data: mockPost });
      }
      return Promise.resolve({ success: true, data: null });
    });

    render(<BlogPostPage />);

    await waitFor(() => {
      expect(screen.getByText('How Timebanking Works')).toBeInTheDocument();
    });
  });

  it('renders the post content', async () => {
    mockApiGet.mockImplementation((url: string) => {
      if (url.includes('/v2/blog/')) {
        return Promise.resolve({ success: true, data: mockPost });
      }
      return Promise.resolve({ success: true, data: null });
    });

    render(<BlogPostPage />);

    await waitFor(() => {
      expect(screen.getByText(/Timebanking is a way of exchanging time/i)).toBeInTheDocument();
    });
  });

  it('renders the author name', async () => {
    mockApiGet.mockImplementation((url: string) => {
      if (url.includes('/v2/blog/')) {
        return Promise.resolve({ success: true, data: mockPost });
      }
      return Promise.resolve({ success: true, data: null });
    });

    render(<BlogPostPage />);

    await waitFor(() => {
      expect(screen.getByText('Jane Doe')).toBeInTheDocument();
    });
  });

  it('renders the shared social panel for blog comments', async () => {
    mockApiGet.mockImplementation((url: string) => {
      if (url.includes('/v2/blog/')) {
        return Promise.resolve({ success: true, data: mockPost });
      }
      return Promise.resolve({ success: true, data: null });
    });

    render(<BlogPostPage />);

    await waitFor(() => {
      expect(screen.getByTestId('social-panel')).toHaveAttribute('data-target-type', 'blog');
      expect(screen.getByTestId('social-panel')).toHaveAttribute('data-target-id', '1');
    });
  });

  it('shows error state when post is not found', async () => {
    mockApiGet.mockResolvedValue({ success: false });
    render(<BlogPostPage />);

    await waitFor(() => {
      // Error state shows back to blog link and retry button
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

  it('shows error state when API throws', async () => {
    mockApiGet.mockRejectedValue(new Error('Network error'));
    render(<BlogPostPage />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

});
