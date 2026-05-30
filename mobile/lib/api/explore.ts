// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface ExploreStats {
  total_members: number;
  exchanges_this_month: number;
  hours_exchanged: number;
  active_listings: number;
}

export interface ExplorePost {
  id: number;
  user_id: number;
  excerpt: string;
  image_url: string | null;
  created_at: string;
  author_name: string;
  author_avatar: string | null;
  likes_count: number;
  comments_count: number;
  engagement: number;
}

export interface ExploreListing {
  id: number;
  title: string;
  type: string;
  image_url: string | null;
  location: string | null;
  estimated_hours: number | string | null;
  created_at: string;
  view_count?: number;
  save_count?: number;
  category_name?: string | null;
  category_slug?: string | null;
  category_color?: string | null;
  author_name: string;
  author_avatar: string | null;
  match_reason?: string | null;
  match_score?: number | null;
  distance_km?: number | null;
}

export interface ExploreGroup {
  id: number;
  name: string;
  description: string | null;
  image_url: string | null;
  privacy: string;
  member_count: number;
  created_at: string;
}

export interface ExploreEvent {
  id: number;
  title: string;
  description: string | null;
  image_url: string | null;
  start_at: string;
  end_at: string | null;
  location: string | null;
  is_online: boolean;
  max_attendees: number | null;
  rsvp_count: number;
}

export interface ExploreMember {
  id: number;
  name: string;
  avatar: string | null;
  tagline: string | null;
  created_at?: string | null;
  xp?: number;
  level?: number;
  reason?: string | null;
}

export interface ExploreBlogPost {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  image_url: string | null;
  published_at: string;
  reading_time: number;
  view_count: number;
  author_name: string;
  author_avatar: string | null;
}

export interface ExploreVolunteeringOpportunity {
  id: number;
  title: string;
  description: string | null;
  location: string | null;
  skills_needed: string | null;
  org_name: string;
  org_logo?: string | null;
  application_count: number;
  created_at: string;
}

export interface ExploreOrganisation {
  id: number;
  name: string;
  description: string | null;
  logo_url: string | null;
  website_url: string | null;
  opportunity_count: number;
}

export interface ExplorePoll {
  id: number;
  question: string;
  description: string | null;
  author_name: string;
  option_count: number;
  vote_count: number;
  closes_at: string | null;
  created_at: string;
}

export interface ExploreJob {
  id: number;
  title: string;
  description: string | null;
  location: string | null;
  org_name: string;
  application_count: number;
  deadline: string | null;
  created_at: string;
}

export interface ExploreResource {
  id: number;
  title: string;
  description: string | null;
  resource_type: string | null;
  url: string | null;
  view_count: number;
  category_name: string;
}

export interface ExploreCategory {
  id: number;
  name: string;
  slug: string;
  color?: string | null;
}

export interface ExploreData {
  trending_posts: ExplorePost[];
  popular_listings: ExploreListing[];
  recommended_listings: ExploreListing[];
  near_you_listings: ExploreListing[];
  active_groups: ExploreGroup[];
  upcoming_events: ExploreEvent[];
  top_contributors: ExploreMember[];
  new_members: ExploreMember[];
  suggested_connections: ExploreMember[];
  trending_blog_posts: ExploreBlogPost[];
  volunteering_opportunities: ExploreVolunteeringOpportunity[];
  active_organisations: ExploreOrganisation[];
  active_polls: ExplorePoll[];
  latest_jobs: ExploreJob[];
  featured_resources: ExploreResource[];
  categories: ExploreCategory[];
  community_stats: ExploreStats;
}

export interface ExploreResponse {
  data: ExploreData;
  meta?: {
    base_url?: string;
  };
}

export function getExplore(): Promise<ExploreResponse> {
  return api.get<ExploreResponse>(`${API_V2}/explore`);
}
