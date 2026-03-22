// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupFeedTab
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Alice', name: 'Alice Test', avatar: null },
    isAuthenticated: true,
  })),
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
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/contexts/ToastContext', () => ({
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('framer-motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

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

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <h2>{title}</h2>
      {description && <p>{description}</p>}
    </div>
  ),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

vi.mock('@/components/feed/FeedCard', () => ({
  FeedCard: ({ item }: { item: { id: number; author?: { name: string } } }) => (
    <div data-testid={`feed-card-${item.id}`}>{item.author?.name}</div>
  ),
}));

vi.mock('@/components/feed/types', () => ({
  getAuthor: vi.fn((item: { author?: { name: string } }) => item.author || null),
}));

import { GroupFeedTab } from '../GroupFeedTab';
import type { FeedItem } from '@/components/feed/types';

const mockFeedItem = {
  id: 1,
  type: 'post' as const,
  author: { id: 1, name: 'Alice Test', avatar: null },
  content: 'Hello group!',
  likes_count: 0,
  comments_count: 0,
  is_liked: false,
  created_at: '2026-01-01T10:00:00Z',
  group_id: 1,
} as unknown as FeedItem;

const defaultProps = {
  isMember: true,
  isJoining: false,
  feedItems: [mockFeedItem],
  feedLoading: false,
  feedHasMore: false,
  feedLoadingMore: false,
  onJoinLeave: vi.fn(),
  onComposeOpen: vi.fn(),
  onLoadMore: vi.fn(),
  onToggleLike: vi.fn(),
  onHidePost: vi.fn(),
  onMuteUser: vi.fn(),
  onReportPost: vi.fn(),
  onDeletePost: vi.fn(),
  onVotePoll: vi.fn(),
};

describe('GroupFeedTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders feed items for members', () => {
    render(<GroupFeedTab {...defaultProps} />);
    expect(screen.getByTestId('feed-card-1')).toBeInTheDocument();
  });

  it('shows empty state when no feed items', () => {
    render(<GroupFeedTab {...defaultProps} feedItems={[]} />);
    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('shows compose prompt for members', () => {
    render(<GroupFeedTab {...defaultProps} feedItems={[]} />);
    // The compose prompt (write something area) should be shown for members
    expect(screen.getByText('detail.whats_happening')).toBeInTheDocument();
  });

  it('shows join prompt for non-members', () => {
    render(<GroupFeedTab {...defaultProps} isMember={false} feedItems={[]} />);
    expect(screen.getByText('detail.join_to_post')).toBeInTheDocument();
  });

  it('calls onComposeOpen when compose area is clicked', () => {
    const onComposeOpen = vi.fn();
    render(<GroupFeedTab {...defaultProps} feedItems={[]} onComposeOpen={onComposeOpen} />);
    fireEvent.click(screen.getByText('detail.whats_happening'));
    expect(onComposeOpen).toHaveBeenCalledTimes(1);
  });

  it('shows load more button when feedHasMore is true', () => {
    render(<GroupFeedTab {...defaultProps} feedHasMore={true} />);
    expect(screen.getByText('common:load_more')).toBeInTheDocument();
  });

  it('does not show load more button when no more items', () => {
    render(<GroupFeedTab {...defaultProps} feedHasMore={false} />);
    expect(screen.queryByText('common:load_more')).not.toBeInTheDocument();
  });
});
