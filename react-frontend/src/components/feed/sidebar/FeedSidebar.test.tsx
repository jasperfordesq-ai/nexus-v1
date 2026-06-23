// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Mock @/contexts ─────────────────────────────────────────────────────────
// Note: useFeature is imported from @/contexts directly by FeedSidebar
const mockUseFeature = vi.fn(() => true);

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useFeature: mockUseFeature,
  })
);

// ─── Stub all child sidebar widgets ──────────────────────────────────────────
vi.mock('./WidgetSkeleton', () => ({
  WidgetSkeleton: ({ lines }: { lines?: number }) => (
    <div data-testid="widget-skeleton" data-lines={lines} />
  ),
}));

vi.mock('./ProfileCardWidget', () => ({
  ProfileCardWidget: () => <div data-testid="profile-card-widget" />,
}));

vi.mock('./QuickActionsWidget', () => ({
  QuickActionsWidget: () => <div data-testid="quick-actions-widget" />,
}));

vi.mock('./FriendsWidget', () => ({
  FriendsWidget: ({ friends }: { friends: unknown[] }) => (
    <div data-testid="friends-widget" data-count={friends.length} />
  ),
}));

vi.mock('./CommunityPulseWidget', () => ({
  CommunityPulseWidget: () => <div data-testid="community-pulse-widget" />,
}));

vi.mock('./SuggestedListingsWidget', () => ({
  SuggestedListingsWidget: () => <div data-testid="suggested-listings-widget" />,
}));

vi.mock('./TopCategoriesWidget', () => ({
  TopCategoriesWidget: () => <div data-testid="top-categories-widget" />,
}));

vi.mock('./UpcomingEventsWidget', () => ({
  UpcomingEventsWidget: () => <div data-testid="upcoming-events-widget" />,
}));

vi.mock('./PopularGroupsWidget', () => ({
  PopularGroupsWidget: () => <div data-testid="popular-groups-widget" />,
}));

vi.mock('@/components/hashtags/TrendingHashtags', () => ({
  TrendingHashtags: ({ limit }: { limit?: number }) => (
    <div data-testid="trending-hashtags" data-limit={limit} />
  ),
  default: ({ limit }: { limit?: number }) => (
    <div data-testid="trending-hashtags" data-limit={limit} />
  ),
}));

vi.mock('@/components/endorsements/TopEndorsedWidget', () => ({
  TopEndorsedWidget: ({ limit }: { limit?: number }) => (
    <div data-testid="top-endorsed-widget" data-limit={limit} />
  ),
}));

vi.mock('@/components/feed/ConnectionSuggestionsWidget', () => ({
  ConnectionSuggestionsWidget: ({ layout }: { layout?: string }) => (
    <div data-testid="connection-suggestions-widget" data-layout={layout} />
  ),
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeSidebarResponse = (overrides = {}) => ({
  success: true,
  data: {
    friends: [],
    community_stats: null,
    suggested_listings: [],
    top_categories: [],
    upcoming_events: [],
    popular_groups: [],
    profile_stats: null,
    ...overrides,
  },
});

// ─── Test suite ─────────────────────────────────────────────────────────────
describe('FeedSidebar', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockUseFeature.mockReturnValue(true);
    mockApi.get.mockResolvedValue(makeSidebarResponse());
  });

  it('shows skeleton widgets while loading', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    const skeletons = screen.getAllByTestId('widget-skeleton');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('calls the correct API endpoint on mount', async () => {
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/feed/sidebar');
    });
  });

  it('always renders TrendingHashtags after data loads', async () => {
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('trending-hashtags')).toBeInTheDocument();
    });
  });

  it('always renders TopEndorsedWidget after data loads', async () => {
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('top-endorsed-widget')).toBeInTheDocument();
    });
  });

  it('renders ProfileCardWidget and QuickActionsWidget when authenticated', async () => {
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('profile-card-widget')).toBeInTheDocument();
      expect(screen.getByTestId('quick-actions-widget')).toBeInTheDocument();
    });
  });

  it('renders FriendsWidget when friends array is non-empty', async () => {
    mockApi.get.mockResolvedValue(
      makeSidebarResponse({
        friends: [{ id: 1, name: 'Alice', avatar_url: null }],
      })
    );
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      const widget = screen.getByTestId('friends-widget');
      expect(widget).toBeInTheDocument();
      expect(widget).toHaveAttribute('data-count', '1');
    });
  });

  it('does not render FriendsWidget when friends array is empty', async () => {
    mockApi.get.mockResolvedValue(makeSidebarResponse({ friends: [] }));
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => screen.getByTestId('trending-hashtags'));
    expect(screen.queryByTestId('friends-widget')).not.toBeInTheDocument();
  });

  it('renders CommunityPulseWidget when connections feature is on and community_stats provided', async () => {
    mockUseFeature.mockReturnValue(true);
    mockApi.get.mockResolvedValue(
      makeSidebarResponse({
        community_stats: { total_members: 50, active_this_week: 12 },
      })
    );
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('community-pulse-widget')).toBeInTheDocument();
    });
  });

  it('does not render CommunityPulseWidget when connections feature is disabled', async () => {
    mockUseFeature.mockReturnValue(false);
    mockApi.get.mockResolvedValue(
      makeSidebarResponse({
        community_stats: { total_members: 50, active_this_week: 12 },
      })
    );
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => screen.getByTestId('trending-hashtags'));
    expect(screen.queryByTestId('community-pulse-widget')).not.toBeInTheDocument();
  });

  it('renders UpcomingEventsWidget when upcoming_events is non-empty', async () => {
    mockApi.get.mockResolvedValue(
      makeSidebarResponse({
        upcoming_events: [{ id: 1, title: 'Community meetup', date: '2026-07-01' }],
      })
    );
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('upcoming-events-widget')).toBeInTheDocument();
    });
  });

  it('renders PopularGroupsWidget when popular_groups is non-empty', async () => {
    mockApi.get.mockResolvedValue(
      makeSidebarResponse({
        popular_groups: [{ id: 1, name: 'Gardening Club', member_count: 30 }],
      })
    );
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('popular-groups-widget')).toBeInTheDocument();
    });
  });

  it('renders SuggestedListingsWidget when suggested_listings is non-empty', async () => {
    mockApi.get.mockResolvedValue(
      makeSidebarResponse({
        suggested_listings: [{ id: 1, title: 'Piano lessons', time_cost: 2 }],
      })
    );
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('suggested-listings-widget')).toBeInTheDocument();
    });
  });

  it('renders ConnectionSuggestionsWidget when authenticated and connections feature is on', async () => {
    mockUseFeature.mockReturnValue(true);
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      const widget = screen.getByTestId('connection-suggestions-widget');
      expect(widget).toBeInTheDocument();
      expect(widget).toHaveAttribute('data-layout', 'sidebar');
    });
  });

  it('passes limit=8 to TrendingHashtags', async () => {
    const { FeedSidebar } = await import('./FeedSidebar');
    render(<FeedSidebar />);

    await waitFor(() => {
      expect(screen.getByTestId('trending-hashtags')).toHaveAttribute('data-limit', '8');
    });
  });
});
