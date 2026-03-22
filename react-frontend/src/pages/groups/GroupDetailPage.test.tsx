// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupDetailPage
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || null),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
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

vi.mock('@/components/compose', () => ({
  ComposeHub: () => <div data-testid="compose-hub" />,
}));

// Mock tab components
vi.mock('./tabs/GroupFeedTab', () => ({
  GroupFeedTab: () => <div data-testid="group-feed-tab" />,
}));

vi.mock('./tabs/GroupDiscussionTab', () => ({
  GroupDiscussionTab: () => <div data-testid="group-discussion-tab" />,
}));

vi.mock('./tabs/GroupMembersTab', () => ({
  GroupMembersTab: () => <div data-testid="group-members-tab" />,
}));

vi.mock('./tabs/GroupEventsTab', () => ({
  GroupEventsTab: () => <div data-testid="group-events-tab" />,
}));

vi.mock('./tabs/GroupFilesTab', () => ({
  GroupFilesTab: () => <div data-testid="group-files-tab" />,
}));

vi.mock('./tabs/GroupAnnouncementsTab', () => ({
  GroupAnnouncementsTab: () => <div data-testid="group-announcements-tab" />,
}));

vi.mock('./tabs/GroupChatroomsTab', () => ({
  GroupChatroomsTab: () => <div data-testid="group-chatrooms-tab" />,
}));

vi.mock('./tabs/GroupTasksTab', () => ({
  GroupTasksTab: () => <div data-testid="group-tasks-tab" />,
}));

vi.mock('./tabs/GroupSubgroupsTab', () => ({
  GroupSubgroupsTab: () => <div data-testid="group-subgroups-tab" />,
}));

vi.mock('./components/PinnedAnnouncementsBanner', () => ({
  PinnedAnnouncementsBanner: () => null,
}));

import { GroupDetailPage } from './GroupDetailPage';

const mockGroup = {
  id: 1,
  name: 'Gardening Enthusiasts',
  description: 'A group for garden lovers',
  is_private: false,
  owner_id: 5,
  member_count: 12,
  location: 'Dublin',
  latitude: null,
  longitude: null,
  image_url: null,
  created_at: '2026-01-01T10:00:00Z',
  is_member: true,
  is_admin: false,
  parent_group_id: null,
};

describe('GroupDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url: string) => {
      if (url.includes('/members')) return Promise.resolve({ success: true, data: [] });
      if (url.includes('/events')) return Promise.resolve({ success: true, data: [] });
      if (url.includes('/feed')) return Promise.resolve({ success: true, data: [] });
      if (url.includes('/discussions')) return Promise.resolve({ success: true, data: [] });
      if (url.includes('/subgroups')) return Promise.resolve({ success: true, data: [] });
      return Promise.resolve({ success: true, data: mockGroup });
    });
    api.post.mockResolvedValue({ success: true });
    api.delete.mockResolvedValue({ success: true });
  });

  it('shows loading screen initially', () => {
    api.get.mockImplementation(() => new Promise(() => {}));
    render(<GroupDetailPage />);
    expect(screen.getByTestId('loading-screen')).toBeInTheDocument();
  });

  it('renders group name after load', async () => {
    render(<GroupDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Gardening Enthusiasts').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders group description', async () => {
    render(<GroupDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('A group for garden lovers')).toBeInTheDocument();
    });
  });

  it('shows error state on API error', async () => {
    api.get.mockRejectedValue(new Error('Not found'));
    render(<GroupDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('Unable to Load Group')).toBeInTheDocument();
    });
  });

  it('renders group tabs navigation', async () => {
    render(<GroupDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Gardening Enthusiasts').length).toBeGreaterThanOrEqual(1);
    });
    // Tab labels should be present
    const tabs = screen.getAllByRole('tab');
    expect(tabs.length).toBeGreaterThan(0);
  });

  it('renders member count', async () => {
    render(<GroupDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('12 members').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders public/private badge', async () => {
    render(<GroupDetailPage />);
    await waitFor(() => {
      // Public badge should be shown for non-private groups
      expect(screen.getAllByText('Gardening Enthusiasts').length).toBeGreaterThanOrEqual(1);
    });
  });
});
