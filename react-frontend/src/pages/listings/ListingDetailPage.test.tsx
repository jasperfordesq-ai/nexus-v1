// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ListingDetailPage
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));
import { api } from '@/lib/api';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 99, first_name: 'Alice', name: 'Alice Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,

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

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
  useSocialInteractions: vi.fn(() => ({
    isLiked: false,
    likesCount: 0,
    commentsCount: 0,
    isLiking: false,
    comments: [],
    commentsLoading: false,
    commentsLoaded: false,
    toggleLike: vi.fn(),
    loadComments: vi.fn(),
    submitComment: vi.fn(),
    editComment: vi.fn(),
    deleteComment: vi.fn(),
    toggleReaction: vi.fn(),
    searchMentions: vi.fn(),
    shareToFeed: vi.fn(),
    loadLikers: vi.fn(),
  })),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || null),
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: '1' }),
    useNavigate: () => vi.fn(),
  };
});

vi.mock('framer-motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
  ),
  ImagePlaceholder: ({ size }: { size?: string }) => <div data-testid="image-placeholder" data-size={size} />,

  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
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

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav>{items.map((i) => <span key={i.label}>{i.label}</span>)}</nav>
  ),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message: string }) => <div data-testid="loading-screen">{message}</div>,
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <h2>{title}</h2>
      {description && <p>{description}</p>}
    </div>
  ),
}));

vi.mock('@/components/location', () => ({
  LocationMapCard: () => <div data-testid="location-map" />,
}));

vi.mock('@/components/social', () => ({
  CommentsSection: () => <div data-testid="comments-section" />,
  LikersModal: () => <div data-testid="likers-modal" />,
  ShareButton: () => <button>Share</button>,
}));

vi.mock('@/components/listings/ListingAnalyticsPanel', () => ({
  ListingAnalyticsPanel: () => <div data-testid="analytics-panel" />,
}));

vi.mock('@/components/listings/FeaturedBadge', () => ({
  FeaturedBadge: () => <span data-testid="featured-badge">Featured</span>,
}));

import { ListingDetailPage } from './ListingDetailPage';

const mockListing = {
  id: 1,
  title: 'Web Design Help',
  description: 'I can help you design your website',
  type: 'offer',
  status: 'active',
  user_id: 5,
  category_id: 1,
  category_name: 'Technology',
  hours_estimate: 2,
  location: 'Dublin, Ireland',
  latitude: 53.3498,
  longitude: -6.2603,
  created_at: '2026-01-01T10:00:00Z',
  is_favorited: false,
  image_url: null,
  user: { id: 5, name: 'Bob Smith', avatar: null, tagline: 'Web developer' },
};

describe('ListingDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url: string) => {
      if (url.includes('/config')) return Promise.resolve({ success: true, data: { exchange_workflow_enabled: true } });
      if (url.includes('/check')) return Promise.resolve({ success: true, data: null });
      return Promise.resolve({ success: true, data: mockListing });
    });
  });

  it('shows loading screen initially', () => {
    api.get.mockImplementation(() => new Promise(() => {}));
    render(<ListingDetailPage />);
    expect(screen.getByTestId('loading-screen')).toBeInTheDocument();
  });

  it('renders listing title after load', async () => {
    render(<ListingDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Web Design Help').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders listing description', async () => {
    render(<ListingDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('I can help you design your website')).toBeInTheDocument();
    });
  });

  it('renders owner info card when user data is present', async () => {
    render(<ListingDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Bob Smith')).toBeInTheDocument();
    });
  });

  it('shows empty state on API error', async () => {
    api.get.mockRejectedValue(new Error('Network error'));
    render(<ListingDetailPage />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows image placeholder when no image_url', async () => {
    render(<ListingDetailPage />);
    await waitFor(() => {
      expect(screen.getByTestId('image-placeholder')).toBeInTheDocument();
    });
  });

  it('renders location text when provided', async () => {
    render(<ListingDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Dublin, Ireland')).toBeInTheDocument();
    });
  });

  it('renders offer badge for offer type listing', async () => {
    render(<ListingDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Offering')).toBeInTheDocument();
    });
  });
});
