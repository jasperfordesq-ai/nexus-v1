// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupDiscussionTab
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Alice', name: 'Alice Test' },
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
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));

import { GroupDiscussionTab } from '../GroupDiscussionTab';
import type { Discussion } from '../GroupDiscussionTab';

const mockDiscussions: Discussion[] = [
  {
    id: 1,
    title: 'Welcome to the group!',
    content: 'Glad to have you here.',
    author: { id: 1, name: 'Alice Test', avatar_url: null },
    reply_count: 3,
    is_pinned: false,
    created_at: '2026-01-01T10:00:00Z',
    last_reply_at: null,
  },
];

const defaultProps = {
  isMember: true,
  isJoining: false,
  discussions: mockDiscussions,
  discussionsLoading: false,
  discussionsHasMore: false,
  expandedDiscussionId: null,
  expandedDiscussion: null,
  expandedLoading: false,
  replyContent: '',
  sendingReply: false,
  onJoinLeave: vi.fn(),
  onShowNewDiscussion: vi.fn(),
  onExpandDiscussion: vi.fn(),
  onLoadMoreDiscussions: vi.fn(),
  onReplyContentChange: vi.fn(),
  onSendReply: vi.fn(),
};

describe('GroupDiscussionTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders discussion titles', () => {
    render(<GroupDiscussionTab {...defaultProps} />);
    expect(screen.getByText('Welcome to the group!')).toBeInTheDocument();
  });

  it('renders new discussion button for members', () => {
    render(<GroupDiscussionTab {...defaultProps} />);
    expect(screen.getByText('detail.new_discussion')).toBeInTheDocument();
  });

  it('calls onShowNewDiscussion when new discussion button clicked', () => {
    const onShowNewDiscussion = vi.fn();
    render(<GroupDiscussionTab {...defaultProps} onShowNewDiscussion={onShowNewDiscussion} />);
    fireEvent.click(screen.getByText('detail.new_discussion'));
    expect(onShowNewDiscussion).toHaveBeenCalledTimes(1);
  });

  it('shows empty state when no discussions', () => {
    render(<GroupDiscussionTab {...defaultProps} discussions={[]} />);
    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('shows join prompt for non-members', () => {
    render(<GroupDiscussionTab {...defaultProps} isMember={false} />);
    expect(screen.getByText('detail.join_to_discuss')).toBeInTheDocument();
  });

  it('renders discussion reply count', () => {
    render(<GroupDiscussionTab {...defaultProps} />);
    // Reply count chip should be present
    expect(screen.getByText('3')).toBeInTheDocument();
  });

  it('calls onExpandDiscussion when discussion row is clicked', () => {
    const onExpandDiscussion = vi.fn();
    render(<GroupDiscussionTab {...defaultProps} onExpandDiscussion={onExpandDiscussion} />);
    fireEvent.click(screen.getByText('Welcome to the group!'));
    expect(onExpandDiscussion).toHaveBeenCalledWith(1);
  });
});
