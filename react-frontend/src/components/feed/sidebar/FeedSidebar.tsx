// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedSidebar - Orchestrator component that fetches sidebar data
 * and renders all sidebar widgets.
 *
 * API: GET /api/v2/feed/sidebar
 */

import { useState, useEffect } from 'react';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { WidgetSkeleton } from './WidgetSkeleton';
import { ProfileCardWidget } from './ProfileCardWidget';
import { QuickActionsWidget } from './QuickActionsWidget';
import { FriendsWidget } from './FriendsWidget';
import type { Friend } from './FriendsWidget';
import { CommunityPulseWidget } from './CommunityPulseWidget';
import type { CommunityStats } from './CommunityPulseWidget';
import { SuggestedListingsWidget } from './SuggestedListingsWidget';
import type { SuggestedListing } from './SuggestedListingsWidget';
import { TopCategoriesWidget } from './TopCategoriesWidget';
import type { Category } from './TopCategoriesWidget';
import { PeopleYouMayKnowWidget } from './PeopleYouMayKnowWidget';
import type { SuggestedMember } from './PeopleYouMayKnowWidget';
import { UpcomingEventsWidget } from './UpcomingEventsWidget';
import type { UpcomingEvent } from './UpcomingEventsWidget';
import { PopularGroupsWidget } from './PopularGroupsWidget';
import type { PopularGroup } from './PopularGroupsWidget';
import { TrendingHashtags } from '@/components/hashtags/TrendingHashtags';
import { TopEndorsedWidget } from '@/components/endorsements/TopEndorsedWidget';

interface SidebarApiResponse {
  friends?: Friend[];
  community_stats?: CommunityStats;
  suggested_listings?: SuggestedListing[];
  top_categories?: Category[];
  suggested_members?: SuggestedMember[];
  upcoming_events?: UpcomingEvent[];
  popular_groups?: PopularGroup[];
  profile_stats?: Record<string, unknown> | null;
}

export function FeedSidebar() {
  const { isAuthenticated } = useAuth();
  const [data, setData] = useState<SidebarApiResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loadSidebar = async () => {
      try {
        setIsLoading(true);
        const response = await api.get<SidebarApiResponse>('/v2/feed/sidebar');
        if (response.success && response.data) {
          setData(response.data);
        }
      } catch (err) {
        logError('Failed to load feed sidebar data', err);
      } finally {
        setIsLoading(false);
      }
    };
    loadSidebar();
  }, []);

  if (isLoading) {
    return (
      <div className="space-y-4">
        <WidgetSkeleton lines={2} />
        <WidgetSkeleton lines={4} />
        <WidgetSkeleton lines={3} />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      {isAuthenticated && <ProfileCardWidget />}
      {isAuthenticated && <QuickActionsWidget />}
      {data?.friends && data.friends.length > 0 && (
        <FriendsWidget friends={data.friends} />
      )}
      {data?.community_stats && (
        <CommunityPulseWidget stats={data.community_stats} />
      )}
      {data?.suggested_listings && data.suggested_listings.length > 0 && (
        <SuggestedListingsWidget listings={data.suggested_listings} />
      )}
      {data?.top_categories && data.top_categories.length > 0 && (
        <TopCategoriesWidget categories={data.top_categories} />
      )}
      {data?.suggested_members && data.suggested_members.length > 0 && (
        <PeopleYouMayKnowWidget members={data.suggested_members} />
      )}
      {data?.upcoming_events && data.upcoming_events.length > 0 && (
        <UpcomingEventsWidget events={data.upcoming_events} />
      )}
      {data?.popular_groups && data.popular_groups.length > 0 && (
        <PopularGroupsWidget groups={data.popular_groups} />
      )}
      <TrendingHashtags limit={8} />
      <TopEndorsedWidget limit={5} />
    </div>
  );
}

export default FeedSidebar;
