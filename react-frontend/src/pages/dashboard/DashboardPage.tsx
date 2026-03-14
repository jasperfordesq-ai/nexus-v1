// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Dashboard Page - Main user dashboard
 * Theme-aware styling for light and dark modes
 *
 * Layout: 2-column on desktop (2/3 main + 1/3 sidebar)
 * Sections:
 *   Main: Welcome, Stats, Recent Listings, Recent Activity Feed
 *   Sidebar: Quick Actions, Suggested Matches, My Groups, Upcoming Events, Gamification
 */

import { useState, useEffect, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Chip, Progress, Skeleton } from '@heroui/react';
import {
  Clock,
  ListTodo,
  MessageSquare,
  Wallet,
  TrendingUp,
  Users,
  Calendar,
  Bell,
  ArrowRight,
  Plus,
  AlertTriangle,
  RefreshCw,
  Activity,
  Sparkles,
  MapPin,
  Heart,
  MessageCircle,
  UserPlus,
  Award,
  Star,
  ThumbsUp,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useAuth, useTenant, useFeature, useModule, useNotifications } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';
import type { WalletBalance, Listing, Event, Group } from '@/types/api';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GamificationProfile {
  level: number;
  xp: number;
  level_progress: number; // percentage 0-100
  badges_count: number;
  level_thresholds?: Record<number, number>;
}

interface FeedActivityItem {
  id: number;
  content: string;
  author_name: string;
  author_avatar: string;
  author_id: number;
  created_at: string;
  type: 'post' | 'listing' | 'event' | 'poll' | 'goal';
  likes_count: number;
  comments_count: number;
  is_liked: boolean;
}

interface EndorsementEntry {
  skill: string;
  count: number;
}

interface DashboardStats {
  walletBalance: WalletBalance | null;
  recentListings: Listing[];
  activeListingsCount: number;
  pendingTransactions: number;
  gamification: GamificationProfile | null;
  recentActivity: FeedActivityItem[];
  suggestedListings: Listing[];
  myGroups: Group[];
  upcomingEvents: Event[];
  myEndorsements: EndorsementEntry[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: format feed action text
// ─────────────────────────────────────────────────────────────────────────────

function formatActivityAction(item: FeedActivityItem, t: (key: string) => string): string {
  switch (item.type) {
    case 'listing':
      return t('activity.action_listing');
    case 'event':
      return t('activity.action_event');
    case 'poll':
      return t('activity.action_poll');
    case 'goal':
      return t('activity.action_goal');
    default:
      return t('activity.action_post');
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function DashboardPage() {
  const { t } = useTranslation('dashboard');
  usePageTitle(t('meta.title'));
  const { user } = useAuth();
  const { branding, tenantPath } = useTenant();
  const { counts: notificationCounts } = useNotifications();
  const hasGamification = useFeature('gamification');
  const hasEvents = useFeature('events');
  const hasGroups = useFeature('groups');
  const hasFeedModule = useModule('feed');
  const hasListingsModule = useModule('listings');

  const [stats, setStats] = useState<DashboardStats>({
    walletBalance: null,
    recentListings: [],
    activeListingsCount: 0,
    pendingTransactions: 0,
    gamification: null,
    recentActivity: [],
    suggestedListings: [],
    myGroups: [],
    upcomingEvents: [],
    myEndorsements: [],
  });
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Individual loading states for widgets added after initial load
  const [activityLoading, setActivityLoading] = useState(true);
  const [suggestedLoading, setSuggestedLoading] = useState(true);
  const [groupsLoading, setGroupsLoading] = useState(true);
  const [eventsLoading, setEventsLoading] = useState(true);

  const loadDashboardData = useCallback(async () => {
    try {
      setError(null);
      setIsLoading(true);
      setActivityLoading(true);
      setSuggestedLoading(true);
      setGroupsLoading(true);
      setEventsLoading(true);

      // Core requests - always fetched
      const coreRequests = [
        api.get<WalletBalance>('/v2/wallet/balance').catch(() => null),
        api.get<Listing[]>('/v2/listings?per_page=5').catch(() => null),
        api.get<{ count: number }>('/v2/wallet/pending-count').catch(() => null),
      ];

      // Optional requests based on feature flags
      const optionalRequests: Array<{
        key: string;
        promise: Promise<unknown>;
      }> = [];

      if (hasGamification) {
        optionalRequests.push({
          key: 'gamification',
          promise: api.get<GamificationProfile>('/v2/gamification/profile').catch(() => null),
        });
      }

      if (hasFeedModule) {
        optionalRequests.push({
          key: 'activity',
          promise: api.get<FeedActivityItem[]>('/v2/feed?per_page=5').catch(() => null),
        });
      }

      if (hasListingsModule) {
        optionalRequests.push({
          key: 'suggested',
          promise: api.get<Listing[]>('/v2/listings?per_page=4').catch(() => null),
        });
      }

      if (hasGroups) {
        optionalRequests.push({
          key: 'groups',
          promise: api.get<Group[]>(`/v2/groups?user_id=${user?.id}&per_page=3`).catch(() => null),
        });
      }

      // Always fetch endorsements for the current user (non-blocking)
      if (user?.id) {
        optionalRequests.push({
          key: 'endorsements',
          promise: api.get(`/v2/members/${user.id}/endorsements`).catch(() => null),
        });
      }

      if (hasEvents) {
        optionalRequests.push({
          key: 'events',
          promise: api.get<Event[]>('/v2/events?when=upcoming&per_page=3').catch(() => null),
        });
      }

      // Run all requests in parallel using Promise.allSettled
      const allPromises = [
        ...coreRequests,
        ...optionalRequests.map((r) => r.promise),
      ];
      const results = await Promise.allSettled(allPromises);

      // Extract core results
      const walletRes = results[0].status === 'fulfilled' ? results[0].value : null;
      const listingsRes = results[1].status === 'fulfilled' ? results[1].value : null;
      const pendingRes = results[2].status === 'fulfilled' ? results[2].value : null;

      // Type cast core results
      const walletData = walletRes as { success?: boolean; data?: WalletBalance } | null;
      const listingsData = listingsRes as { success?: boolean; data?: Listing[]; meta?: { total_items?: number } } | null;
      const pendingData = pendingRes as { success?: boolean; data?: { count: number } } | null;

      // Extract optional results by key
      const optionalResults: Record<string, unknown> = {};
      optionalRequests.forEach((req, index) => {
        const result = results[coreRequests.length + index];
        optionalResults[req.key] = result.status === 'fulfilled' ? result.value : null;
      });

      // Parse gamification
      let gamificationData: GamificationProfile | null = null;
      if (optionalResults.gamification) {
        const gRes = optionalResults.gamification as { success?: boolean; data?: GamificationProfile };
        if (gRes?.success && gRes.data) {
          gamificationData = gRes.data;
        }
      }

      // Parse activity feed
      let activityData: FeedActivityItem[] = [];
      if (optionalResults.activity) {
        const aRes = optionalResults.activity as { success?: boolean; data?: FeedActivityItem[] };
        if (aRes?.success && Array.isArray(aRes.data)) {
          activityData = aRes.data;
        }
      }

      // Parse suggested listings
      let suggestedData: Listing[] = [];
      if (optionalResults.suggested) {
        const sRes = optionalResults.suggested as { success?: boolean; data?: Listing[] };
        if (sRes?.success && Array.isArray(sRes.data)) {
          suggestedData = sRes.data;
        }
      }

      // Parse groups
      let groupsData: Group[] = [];
      if (optionalResults.groups) {
        const gRes = optionalResults.groups as { success?: boolean; data?: Group[] };
        if (gRes?.success && Array.isArray(gRes.data)) {
          groupsData = gRes.data;
        }
      }

      // Parse events
      let eventsData: Event[] = [];
      if (optionalResults.events) {
        const eRes = optionalResults.events as { success?: boolean; data?: Event[] };
        if (eRes?.success && Array.isArray(eRes.data)) {
          eventsData = eRes.data;
        }
      }

      // Parse endorsements — API returns { endorsements: [{skill_name, count, ...}], stats }
      let endorsementsData: EndorsementEntry[] = [];
      if (optionalResults.endorsements) {
        const eRes = optionalResults.endorsements as {
          success?: boolean;
          data?: { endorsements?: Array<{ skill_name: string; count: number }> };
        };
        if (eRes?.success && Array.isArray(eRes.data?.endorsements)) {
          endorsementsData = eRes.data.endorsements
            .map((e) => ({ skill: e.skill_name, count: Number(e.count) || 0 }))
            .filter((e) => e.count > 0)
            .sort((a, b) => b.count - a.count)
            .slice(0, 6);
        }
      }

      // Get total count from meta if available
      const listingsCount = listingsData?.meta?.total_items
        ?? listingsData?.data?.length
        ?? 0;

      setStats({
        walletBalance: walletData?.success ? walletData.data ?? null : null,
        recentListings: listingsData?.success ? listingsData.data ?? [] : [],
        activeListingsCount: listingsCount,
        pendingTransactions: pendingData?.success ? pendingData.data?.count ?? 0 : 0,
        gamification: gamificationData,
        recentActivity: activityData,
        suggestedListings: suggestedData,
        myGroups: groupsData,
        upcomingEvents: eventsData,
        myEndorsements: endorsementsData,
      });
    } catch (err) {
      logError('Failed to load dashboard data', err);
      setError(t('unable_to_load'));
    } finally {
      setIsLoading(false);
      setActivityLoading(false);
      setSuggestedLoading(false);
      setGroupsLoading(false);
      setEventsLoading(false);
    }
  }, [hasGamification, hasFeedModule, hasListingsModule, hasGroups, hasEvents, user?.id, t]);

  useEffect(() => {
    loadDashboardData();
  }, [loadDashboardData]);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  if (error) {
    return (
      <>
        <PageMeta
          title={t('meta.title')}
          description={t('meta.description')}
          noIndex
        />
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadDashboardData()}
          >
            {t('try_again')}
          </Button>
        </GlassCard>
      </>
    );
  }

  return (
    <>
      <PageMeta
        title={t('meta.title')}
        description={t('meta.description')}
        noIndex
      />
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="space-y-6 min-w-0 max-w-full"
      >
        {/* Onboarding Banner */}
        {user && user.onboarding_completed === false && (
          <motion.div variants={itemVariants}>
            <GlassCard className="p-5 border-l-4 border-l-indigo-500">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div className="flex items-center gap-3">
                  <Sparkles className="w-6 h-6 text-indigo-500 flex-shrink-0" aria-hidden="true" />
                  <div>
                    <p className="font-semibold text-theme-primary">{t('onboarding.banner_title')}</p>
                    <p className="text-sm text-theme-muted">{t('onboarding.banner_subtitle')}</p>
                  </div>
                </div>
                <Link to={tenantPath('/onboarding')}>
                  <Button
                    size="sm"
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('onboarding.get_started')}
                  </Button>
                </Link>
              </div>
            </GlassCard>
          </motion.div>
        )}

        {/* Welcome Header */}
        <motion.div variants={itemVariants}>
          <GlassCard className="p-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
              <div>
                <h1 className="text-2xl font-bold text-theme-primary">
                  {t('welcome_back', { name: user?.first_name || user?.name?.split(' ')[0] || 'there' })}
                </h1>
                <p className="text-theme-muted mt-1">
                  {t('community_activity', { community: branding.name })}
                </p>
              </div>
              <div className="flex gap-3">
                <Link to={tenantPath('/listings/create')}>
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  >
                    {t('new_listing')}
                  </Button>
                </Link>
              </div>
            </div>
          </GlassCard>
        </motion.div>

        {/* Stats Grid */}
        <motion.div
          variants={itemVariants}
          className="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3 md:gap-4"
        >
          <StatCard
            icon={<Wallet className="w-5 h-5" aria-hidden="true" />}
            label={t('stats.balance')}
            value={stats.walletBalance ? `${stats.walletBalance.balance}h` : '\u2014'}
            color="indigo"
            href="/wallet"
            isLoading={isLoading}
          />
          <StatCard
            icon={<ListTodo className="w-5 h-5" aria-hidden="true" />}
            label={t('stats.active_listings')}
            value={stats.activeListingsCount.toString()}
            color="emerald"
            href="/listings"
            isLoading={isLoading}
          />
          <StatCard
            icon={<MessageSquare className="w-5 h-5" aria-hidden="true" />}
            label={t('stats.messages')}
            value={notificationCounts.messages.toString()}
            color="amber"
            href="/messages"
            isLoading={isLoading}
          />
          <StatCard
            icon={<Clock className="w-5 h-5" aria-hidden="true" />}
            label={t('stats.pending')}
            value={stats.pendingTransactions.toString()}
            color="rose"
            href="/wallet"
            isLoading={isLoading}
          />
        </motion.div>

        {/* Main Content: 2-column layout */}
        <div className="grid lg:grid-cols-3 gap-4 sm:gap-5 lg:gap-6 min-w-0">
          {/* ─── Left Column (2/3 width) ─── */}
          <div className="lg:col-span-2 space-y-6 min-w-0">
            {/* Recent Listings */}
            <motion.div variants={itemVariants}>
              <GlassCard className="p-6">
                <div className="flex items-center justify-between gap-2 mb-4">
                  <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2 min-w-0">
                    <ListTodo className="w-5 h-5 text-indigo-500 dark:text-indigo-400 shrink-0" aria-hidden="true" />
                    <span className="truncate">{t('sections.recent_listings')}</span>
                  </h2>
                  <Link to={tenantPath('/listings')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                    {t('view_all')} <ArrowRight className="w-4 h-4" aria-hidden="true" />
                  </Link>
                </div>

                {isLoading ? (
                  <div aria-label="Loading listings" aria-busy="true" className="space-y-3">
                    {Array.from({ length: 3 }).map((_, i) => (
                      <Skeleton key={i} className="rounded-lg">
                        <div className="h-16 rounded-lg bg-default-300" />
                      </Skeleton>
                    ))}
                  </div>
                ) : stats.recentListings.length > 0 ? (
                  <div className="space-y-3">
                    {stats.recentListings.map((listing) => (
                      <article key={listing.id}>
                        <Link
                          to={tenantPath(`/listings/${listing.id}`)}
                          className="block p-4 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors"
                          aria-label={`${listing.title} - ${listing.type === 'offer' ? 'Offering' : 'Requesting'}`}
                        >
                          <div className="flex items-start justify-between gap-4">
                            <div className="flex items-start gap-3 min-w-0">
                              <Avatar
                                src={resolveAvatarUrl(listing.author_avatar ?? listing.user?.avatar)}
                                name={listing.author_name ?? listing.user?.name ?? 'User'}
                                size="sm"
                                className="shrink-0"
                              />
                              <div className="min-w-0">
                                <h3 className="font-medium text-theme-primary truncate">{listing.title}</h3>
                                <p className="text-sm text-theme-muted line-clamp-1">{listing.description}</p>
                              </div>
                            </div>
                            <Chip
                              size="sm"
                              variant="flat"
                              color={listing.type === 'offer' ? 'success' : 'warning'}
                              className="shrink-0"
                            >
                              {listing.type === 'offer' ? t('listings.offering') : t('listings.requesting')}
                            </Chip>
                          </div>
                        </Link>
                      </article>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-theme-subtle">
                    <ListTodo className="w-12 h-12 mx-auto mb-3 opacity-50" aria-hidden="true" />
                    <p>{t('listings.empty')}</p>
                    <Link to={tenantPath('/listings/create')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-2 inline-block">
                      {t('listings.create_first')}
                    </Link>
                  </div>
                )}
              </GlassCard>
            </motion.div>

            {/* Recent Activity Feed */}
            {hasFeedModule && (
              <motion.div variants={itemVariants}>
                <GlassCard className="p-6">
                  <div className="flex items-center justify-between gap-2 mb-4">
                    <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2 min-w-0">
                      <Activity className="w-5 h-5 text-purple-500 dark:text-purple-400 shrink-0" aria-hidden="true" />
                      <span className="truncate">{t('sections.recent_activity')}</span>
                    </h2>
                    <Link to={tenantPath('/feed')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                      {t('view_all_caps')} <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    </Link>
                  </div>

                  {activityLoading ? (
                    <div aria-label="Loading activity" aria-busy="true" className="space-y-3">
                      {Array.from({ length: 3 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-3 p-3">
                          <Skeleton className="rounded-full shrink-0"><div className="w-10 h-10 rounded-full bg-default-300" /></Skeleton>
                          <div className="flex-1 space-y-2">
                            <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-3/4" /></Skeleton>
                            <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/2" /></Skeleton>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : stats.recentActivity.length > 0 ? (
                    <div className="space-y-1">
                      {stats.recentActivity.map((item) => (
                        <Link
                          key={item.id}
                          to={tenantPath('/feed')}
                          className="flex items-center gap-3 p-3 rounded-lg hover:bg-theme-hover transition-colors group"
                        >
                          <Avatar
                            src={resolveAvatarUrl(item.author_avatar)}
                            name={item.author_name}
                            size="sm"
                            className="shrink-0"
                          />
                          <div className="flex-1 min-w-0">
                            <p className="text-sm text-theme-primary">
                              <span className="font-medium">{item.author_name}</span>
                              {' '}
                              <span className="text-theme-muted">{formatActivityAction(item, t)}</span>
                            </p>
                            <p className="text-xs text-theme-subtle line-clamp-1">{item.content}</p>
                          </div>
                          <div className="flex items-center gap-3 shrink-0 text-theme-subtle text-xs">
                            {item.likes_count > 0 && (
                              <span className="hidden sm:flex items-center gap-1">
                                <Heart className="w-3 h-3" aria-hidden="true" />
                                {item.likes_count}
                              </span>
                            )}
                            {item.comments_count > 0 && (
                              <span className="hidden sm:flex items-center gap-1">
                                <MessageCircle className="w-3 h-3" aria-hidden="true" />
                                {item.comments_count}
                              </span>
                            )}
                            <span className="whitespace-nowrap">{formatRelativeTime(item.created_at)}</span>
                          </div>
                        </Link>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-8 text-theme-subtle">
                      <Activity className="w-12 h-12 mx-auto mb-3 opacity-50" aria-hidden="true" />
                      <p>{t('activity.empty')}</p>
                      <Link to={tenantPath('/feed')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-2 inline-block">
                        {t('activity.check_feed')}
                      </Link>
                    </div>
                  )}
                </GlassCard>
              </motion.div>
            )}
          </div>

          {/* ─── Right Column (1/3 width — sidebar) ─── */}
          <div className="space-y-6 min-w-0">
            {/* Quick Actions */}
            <motion.div variants={itemVariants}>
              <GlassCard className="p-6">
                <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('sections.quick_actions')}</h2>
                <div className="space-y-2">
                  <QuickActionLink to={tenantPath('/listings/create')} icon={<Plus aria-hidden="true" />} label={t('quick_actions.create_listing')} />
                  <QuickActionLink to={tenantPath('/messages')} icon={<MessageSquare aria-hidden="true" />} label={t('quick_actions.messages')} />
                  <QuickActionLink to={tenantPath('/wallet')} icon={<Wallet aria-hidden="true" />} label={t('quick_actions.view_wallet')} />
                  <QuickActionLink to={tenantPath('/members')} icon={<Users aria-hidden="true" />} label={t('quick_actions.find_members')} />
                  {hasEvents && (
                    <QuickActionLink to={tenantPath('/events')} icon={<Calendar aria-hidden="true" />} label={t('quick_actions.browse_events')} />
                  )}
                  <QuickActionLink to={tenantPath('/notifications')} icon={<Bell aria-hidden="true" />} label={t('quick_actions.notifications')} />
                </div>
              </GlassCard>
            </motion.div>

            {/* Pending Reviews */}
            <motion.div variants={itemVariants}>
              <PendingReviewsCard />
            </motion.div>

            {/* Endorsements Received */}
            {(isLoading || stats.myEndorsements.length > 0) && (
              <motion.div variants={itemVariants}>
                <GlassCard className="p-6">
                  <div className="flex items-center justify-between gap-2 mb-4">
                    <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2 min-w-0">
                      <ThumbsUp className="w-5 h-5 text-indigo-500 dark:text-indigo-400 shrink-0" aria-hidden="true" />
                      <span className="truncate">{t('sections.endorsements', 'Endorsements')}</span>
                    </h2>
                    <Link
                      to={tenantPath(`/profile/${user?.id}`)}
                      className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap"
                    >
                      {t('view_all')} <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    </Link>
                  </div>
                  {isLoading ? (
                    <div aria-label="Loading endorsements" aria-busy="true" className="space-y-2">
                      {Array.from({ length: 3 }).map((_, i) => (
                        <div key={i} className="flex items-center justify-between p-2">
                          <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-2/3" /></Skeleton>
                          <Skeleton className="rounded-full"><div className="h-5 w-8 rounded-full bg-default-300" /></Skeleton>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <div className="space-y-1.5">
                      {stats.myEndorsements.map(({ skill, count }) => (
                        <div
                          key={skill}
                          className="flex items-center justify-between px-3 py-2 rounded-lg bg-theme-elevated"
                        >
                          <span className="text-sm text-theme-primary truncate">{skill}</span>
                          <span className="flex items-center gap-1 text-xs font-semibold text-indigo-600 dark:text-indigo-400 ml-2 shrink-0">
                            <ThumbsUp className="w-3 h-3" aria-hidden="true" />
                            {count}
                          </span>
                        </div>
                      ))}
                    </div>
                  )}
                </GlassCard>
              </motion.div>
            )}

            {/* Suggested Matches */}
            {hasListingsModule && (
              <motion.div variants={itemVariants}>
                <GlassCard className="p-6 relative overflow-hidden">
                  {/* Subtle gradient border effect */}
                  <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-indigo-500/10 via-transparent to-purple-500/10 pointer-events-none" />
                  <div className="relative">
                    <div className="flex items-center justify-between gap-2 mb-4">
                      <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2 min-w-0">
                        <Sparkles className="w-5 h-5 text-amber-500 dark:text-amber-400 shrink-0" aria-hidden="true" />
                        <span className="truncate">{t('sections.suggested_for_you')}</span>
                      </h2>
                      <Link to={tenantPath('/listings')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                        {t('browse_all')} <ArrowRight className="w-4 h-4" aria-hidden="true" />
                      </Link>
                    </div>

                    {suggestedLoading ? (
                      <div aria-label="Loading suggestions" aria-busy="true" className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                        {Array.from({ length: 4 }).map((_, i) => (
                          <Skeleton key={i} className="rounded-lg">
                            <div className="h-24 rounded-lg bg-default-300" />
                          </Skeleton>
                        ))}
                      </div>
                    ) : stats.suggestedListings.length > 0 ? (
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                        {stats.suggestedListings.map((listing) => (
                          <Link
                            key={listing.id}
                            to={tenantPath(`/listings/${listing.id}`)}
                            className="block p-3 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors group"
                            aria-label={`${listing.title} - ${listing.type === 'offer' ? t('listings.offer') : t('listings.request')}`}
                          >
                            <div className="flex items-center gap-2 mb-2">
                              <Avatar
                                src={resolveAvatarUrl(listing.author_avatar ?? listing.user?.avatar)}
                                name={listing.author_name ?? listing.user?.name ?? 'User'}
                                size="sm"
                                className="w-6 h-6"
                              />
                              <Chip
                                size="sm"
                                variant="flat"
                                color={listing.type === 'offer' ? 'success' : 'warning'}
                                className="text-[10px] h-5"
                              >
                                {listing.type === 'offer' ? t('listings.offer') : t('listings.request')}
                              </Chip>
                            </div>
                            <h3 className="text-sm font-medium text-theme-primary line-clamp-2 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                              {listing.title}
                            </h3>
                          </Link>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-6 text-theme-subtle">
                        <Sparkles className="w-10 h-10 mx-auto mb-2 opacity-50" aria-hidden="true" />
                        <p className="text-sm">{t('suggestions.empty')}</p>
                      </div>
                    )}
                  </div>
                </GlassCard>
              </motion.div>
            )}

            {/* My Groups */}
            {hasGroups && (
              <motion.div variants={itemVariants}>
                <GlassCard className="p-6">
                  <div className="flex items-center justify-between gap-2 mb-4">
                    <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2 min-w-0">
                      <Users className="w-5 h-5 text-teal-500 dark:text-teal-400 shrink-0" aria-hidden="true" />
                      <span className="truncate">{t('sections.my_groups')}</span>
                    </h2>
                    <Link to={tenantPath('/groups')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                      {t('view_all_caps')} <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    </Link>
                  </div>

                  {groupsLoading ? (
                    <div aria-label="Loading groups" aria-busy="true" className="space-y-3">
                      {Array.from({ length: 3 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-3">
                          <Skeleton className="rounded-lg shrink-0"><div className="w-10 h-10 rounded-lg bg-default-300" /></Skeleton>
                          <div className="flex-1 space-y-2">
                            <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-2/3" /></Skeleton>
                            <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/3" /></Skeleton>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : stats.myGroups.length > 0 ? (
                    <div className="space-y-2">
                      {stats.myGroups.map((group) => (
                        <Link
                          key={group.id}
                          to={tenantPath(`/groups/${group.id}`)}
                          className="flex items-center gap-3 p-3 rounded-lg hover:bg-theme-hover transition-colors"
                        >
                          {group.image_url ? (
                            <Avatar
                              src={resolveAvatarUrl(group.image_url)}
                              name={group.name}
                              size="sm"
                              radius="sm"
                              className="shrink-0"
                            />
                          ) : (
                            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-teal-500/20 to-emerald-500/20 flex items-center justify-center shrink-0">
                              <span className="text-sm font-bold text-teal-600 dark:text-teal-400">
                                {group.name.charAt(0).toUpperCase()}
                              </span>
                            </div>
                          )}
                          <div className="min-w-0 flex-1">
                            <h3 className="text-sm font-medium text-theme-primary truncate">{group.name}</h3>
                            <p className="text-xs text-theme-subtle flex items-center gap-1">
                              <UserPlus className="w-3 h-3" aria-hidden="true" />
                              {t('groups.members_count', { count: group.member_count ?? group.members_count ?? 0 })}
                            </p>
                          </div>
                        </Link>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-6 text-theme-subtle">
                      <Users className="w-10 h-10 mx-auto mb-2 opacity-50" aria-hidden="true" />
                      <p className="text-sm">{t('groups.empty')}</p>
                      <Link to={tenantPath('/groups')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-1 inline-block">
                        {t('groups.discover')}
                      </Link>
                    </div>
                  )}
                </GlassCard>
              </motion.div>
            )}

            {/* Upcoming Events */}
            {hasEvents && (
              <motion.div variants={itemVariants}>
                <GlassCard className="p-6">
                  <div className="flex items-center justify-between gap-2 mb-4">
                    <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2 min-w-0">
                      <Calendar className="w-5 h-5 text-rose-500 dark:text-rose-400 shrink-0" aria-hidden="true" />
                      <span className="truncate">{t('sections.upcoming_events')}</span>
                    </h2>
                    <Link to={tenantPath('/events')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                      {t('view_all_caps')} <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    </Link>
                  </div>

                  {eventsLoading ? (
                    <div aria-label="Loading events" aria-busy="true" className="space-y-3">
                      {Array.from({ length: 3 }).map((_, i) => (
                        <div key={i} className="flex items-center gap-3">
                          <Skeleton className="rounded-lg shrink-0"><div className="w-12 h-14 rounded-lg bg-default-300" /></Skeleton>
                          <div className="flex-1 space-y-2">
                            <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-3/4" /></Skeleton>
                            <Skeleton className="rounded-lg"><div className="h-3 rounded-lg bg-default-200 w-1/2" /></Skeleton>
                          </div>
                        </div>
                      ))}
                    </div>
                  ) : stats.upcomingEvents.length > 0 ? (
                    <div className="space-y-2">
                      {stats.upcomingEvents.map((event) => {
                        const eventDate = new Date(event.start_date);
                        const day = eventDate.getDate();
                        const month = eventDate.toLocaleString('default', { month: 'short' }).toUpperCase();

                        return (
                          <Link
                            key={event.id}
                            to={tenantPath(`/events/${event.id}`)}
                            className="flex items-center gap-3 p-3 rounded-lg hover:bg-theme-hover transition-colors"
                          >
                            {/* Date badge */}
                            <div className="w-12 h-14 rounded-lg bg-gradient-to-br from-rose-500/20 to-pink-500/20 flex flex-col items-center justify-center shrink-0">
                              <span className="text-xs font-semibold text-rose-600 dark:text-rose-400 leading-none">
                                {month}
                              </span>
                              <span className="text-lg font-bold text-theme-primary leading-none mt-0.5">
                                {day}
                              </span>
                            </div>
                            <div className="min-w-0 flex-1">
                              <h3 className="text-sm font-medium text-theme-primary truncate">{event.title}</h3>
                              {event.location && (
                                <p className="text-xs text-theme-subtle flex items-center gap-1 mt-0.5">
                                  <MapPin className="w-3 h-3 shrink-0" aria-hidden="true" />
                                  <span className="truncate">{event.location}</span>
                                </p>
                              )}
                              {event.is_online && !event.location && (
                                <p className="text-xs text-theme-subtle mt-0.5">{t('events.online_event')}</p>
                              )}
                            </div>
                          </Link>
                        );
                      })}
                    </div>
                  ) : (
                    <div className="text-center py-6 text-theme-subtle">
                      <Calendar className="w-10 h-10 mx-auto mb-2 opacity-50" aria-hidden="true" />
                      <p className="text-sm">{t('events.empty')}</p>
                      <Link to={tenantPath('/events')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-1 inline-block">
                        {t('events.browse')}
                      </Link>
                    </div>
                  )}
                </GlassCard>
              </motion.div>
            )}

            {/* Gamification Preview */}
            {hasGamification && (
              <motion.div variants={itemVariants}>
                <GlassCard className="p-6">
                  <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <TrendingUp className="w-5 h-5 text-amber-500 dark:text-amber-400" aria-hidden="true" />
                    {t('sections.your_progress')}
                  </h2>
                  {isLoading ? (
                    <div aria-label="Loading progress" aria-busy="true" className="space-y-4">
                      <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-300 w-1/2" /></Skeleton>
                      <Skeleton className="rounded-full"><div className="h-2 rounded-full bg-default-200" /></Skeleton>
                      <Skeleton className="rounded-lg"><div className="h-4 rounded-lg bg-default-200 w-1/3" /></Skeleton>
                    </div>
                  ) : stats.gamification ? (
                    <div className="space-y-4">
                      {/* Level info */}
                      <div className="flex items-center justify-between">
                        <span className="text-sm font-medium text-theme-primary flex items-center gap-1.5">
                          <Award className="w-4 h-4 text-amber-500" aria-hidden="true" />
                          {t('gamification.level', { level: stats.gamification.level })}
                        </span>
                        <Chip size="sm" variant="flat" color="warning" className="text-xs">
                          {stats.gamification.xp} XP
                        </Chip>
                      </div>

                      {/* XP Progress */}
                      <div>
                        <div className="flex justify-between text-xs sm:text-sm mb-1.5">
                          <span className="text-theme-muted">
                            {t('gamification.level', { level: stats.gamification.level })}
                          </span>
                          <span className="text-theme-primary font-medium">
                            {Math.round(stats.gamification.level_progress)}%
                          </span>
                        </div>
                        <Progress
                          value={Math.min(stats.gamification.level_progress, 100)}
                          color="warning"
                          size="sm"
                          aria-label={t('gamification.xp_progress')}
                          className="h-2"
                        />
                      </div>

                      {/* Quick stats */}
                      <div className="flex justify-center pt-2">
                        <div className="text-center p-2 rounded-lg bg-theme-elevated w-full">
                          <div className="text-lg font-bold text-theme-primary">{stats.gamification.badges_count}</div>
                          <div className="text-xs text-theme-subtle">{t('gamification.badges')}</div>
                        </div>
                      </div>

                      <Link
                        to={tenantPath('/achievements')}
                        className="block text-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm"
                      >
                        {t('view_achievements')} <ArrowRight className="w-3.5 h-3.5 inline-block ml-1" aria-hidden="true" />
                      </Link>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      <p className="text-sm text-theme-subtle text-center">{t('gamification.start_earning')}</p>
                      <Link
                        to={tenantPath('/achievements')}
                        className="block text-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm"
                      >
                        {t('view_achievements')} <ArrowRight className="w-3.5 h-3.5 inline-block ml-1" aria-hidden="true" />
                      </Link>
                    </div>
                  )}
                </GlassCard>
              </motion.div>
            )}
          </div>
        </div>
      </motion.div>
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Sub-components
// ─────────────────────────────────────────────────────────────────────────────

interface StatCardProps {
  icon: React.ReactNode;
  label: string;
  value: string;
  color: 'indigo' | 'emerald' | 'amber' | 'rose';
  href: string;
  isLoading?: boolean;
}

function StatCard({ icon, label, value, color, href, isLoading }: StatCardProps) {
  const { tenantPath } = useTenant();
  const colorClasses = {
    indigo: 'from-indigo-500/20 to-purple-500/20 text-indigo-600 dark:text-indigo-400',
    emerald: 'from-emerald-500/20 to-teal-500/20 text-emerald-600 dark:text-emerald-400',
    amber: 'from-amber-500/20 to-orange-500/20 text-amber-600 dark:text-amber-400',
    rose: 'from-rose-500/20 to-pink-500/20 text-rose-600 dark:text-rose-400',
  };

  return (
    <Link to={tenantPath(href)} aria-label={`${label}: ${isLoading ? 'Loading' : value}`}>
      <GlassCard className="p-4 hover:scale-[1.02] active:scale-[0.98] transition-transform">
        <div className={`inline-flex p-2 rounded-lg bg-gradient-to-br ${colorClasses[color]} mb-3`}>
          {icon}
        </div>
        <div className="text-theme-muted text-sm">{label}</div>
        {isLoading ? (
          <Skeleton className="rounded-lg mt-1"><div className="h-8 w-16 rounded-lg bg-default-300" /></Skeleton>
        ) : (
          <div className="text-2xl font-bold text-theme-primary">{value}</div>
        )}
      </GlassCard>
    </Link>
  );
}

interface QuickActionLinkProps {
  to: string;
  icon: React.ReactNode;
  label: string;
}

function QuickActionLink({ to, icon, label }: QuickActionLinkProps) {
  return (
    <Link
      to={to}
      className="flex items-center gap-3 p-3 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors text-theme-secondary hover:text-theme-primary"
    >
      <span className="text-indigo-600 dark:text-indigo-400">{icon}</span>
      <span>{label}</span>
    </Link>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Pending Reviews Card
// ─────────────────────────────────────────────────────────────────────────────

interface PendingReview {
  id: number;
  amount: number;
  description: string;
  created_at: string;
  direction: 'sent' | 'received';
  other_party_name: string;
  other_party_id: number;
}

function PendingReviewsCard() {
  const { tenantPath } = useTenant();
  const { t } = useTranslation('dashboard');
  const [pending, setPending] = useState<PendingReview[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPending = async () => {
      try {
        const response = await api.get<{ local: PendingReview[]; federated: PendingReview[]; total_pending: number }>('/v2/reviews/pending');
        if (response.data?.local) {
          setPending(response.data.local.slice(0, 3)); // Show max 3
        }
      } catch (error) {
        logError('Failed to fetch pending reviews', { error });
      } finally {
        setLoading(false);
      }
    };

    fetchPending();
  }, []);

  if (loading) {
    return (
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
          <Star className="w-5 h-5 text-amber-500" aria-hidden="true" />
          {t('sections.pending_reviews')}
        </h2>
        <div aria-label="Loading pending reviews" aria-busy="true" className="space-y-3">
          {Array.from({ length: 2 }).map((_, i) => (
            <Skeleton key={i} className="rounded-lg">
              <div className="h-16 rounded-lg bg-default-300" />
            </Skeleton>
          ))}
        </div>
      </GlassCard>
    );
  }

  if (pending.length === 0) {
    return null; // Don't show card if no pending reviews
  }

  return (
    <GlassCard className="p-6">
      <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
        <Star className="w-5 h-5 text-amber-500" aria-hidden="true" />
        {t('sections.pending_reviews')}
        <Chip size="sm" color="warning" variant="flat" className="ml-auto">
          {pending.length}
        </Chip>
      </h2>

      <div className="space-y-3">
        {pending.map((review) => (
          <Link
            key={review.id}
            to={tenantPath(`/profile/${review.other_party_id}`)}
            className="block p-3 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors group"
          >
            <div className="flex items-center justify-between gap-2 mb-1">
              <span className="text-sm font-medium text-theme-primary group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                {review.other_party_name}
              </span>
              <span className="text-xs text-theme-subtle">
                {formatRelativeTime(review.created_at)}
              </span>
            </div>
            <p className="text-xs text-theme-subtle line-clamp-1">
              {review.description}
            </p>
            <div className="flex items-center gap-1 mt-2">
              <Chip size="sm" variant="flat" color={review.direction === 'sent' ? 'primary' : 'success'} className="text-[10px]">
                {review.direction === 'sent' ? t('reviews.sent', { amount: review.amount }) : t('reviews.received', { amount: review.amount })}
              </Chip>
              <span className="text-xs text-amber-600 dark:text-amber-400 ml-auto flex items-center gap-1">
                <Star className="w-3 h-3" aria-hidden="true" />
                {t('reviews.review')}
              </span>
            </div>
          </Link>
        ))}
      </div>

      <Link
        to={tenantPath('/wallet')}
        className="block mt-4 text-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm"
      >
        {t('view_all_transactions')} <ArrowRight className="w-3.5 h-3.5 inline-block ml-1" aria-hidden="true" />
      </Link>
    </GlassCard>
  );
}

export default DashboardPage;
