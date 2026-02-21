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
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Chip, Progress } from '@heroui/react';
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
  level_name: string;
  xp: number;
  xp_for_next_level: number;
  xp_progress_percent: number;
  total_badges: number;
  streak_days: number;
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
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: format feed action text
// ─────────────────────────────────────────────────────────────────────────────

function formatActivityAction(item: FeedActivityItem): string {
  switch (item.type) {
    case 'listing':
      return 'shared a listing';
    case 'event':
      return 'posted about an event';
    case 'poll':
      return 'created a poll';
    case 'goal':
      return 'set a new goal';
    default:
      return 'shared a post';
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function DashboardPage() {
  usePageTitle('Dashboard');
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
        api.get<Listing[]>('/v2/listings?limit=5&sort=-created_at').catch(() => null),
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
          promise: api.get<FeedActivityItem[]>('/v2/feed?limit=5').catch(() => null),
        });
      }

      if (hasListingsModule) {
        optionalRequests.push({
          key: 'suggested',
          promise: api.get<Listing[]>('/v2/listings?limit=4&sort=-created_at').catch(() => null),
        });
      }

      if (hasGroups) {
        optionalRequests.push({
          key: 'groups',
          promise: api.get<Group[]>('/v2/groups?my_groups=1&limit=3').catch(() => null),
        });
      }

      if (hasEvents) {
        optionalRequests.push({
          key: 'events',
          promise: api.get<Event[]>('/v2/events?filter=upcoming&limit=3').catch(() => null),
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
        const gRes = optionalResults.gamification as { success?: boolean; data?: unknown };
        if (gRes?.success && gRes.data) {
          const gData = gRes.data as { data?: GamificationProfile };
          gamificationData = gData.data ?? (gRes.data as unknown as GamificationProfile);
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
      });
    } catch (err) {
      logError('Failed to load dashboard data', err);
      setError('Failed to load dashboard data. Please try again.');
    } finally {
      setIsLoading(false);
      setActivityLoading(false);
      setSuggestedLoading(false);
      setGroupsLoading(false);
      setEventsLoading(false);
    }
  }, [hasGamification, hasFeedModule, hasListingsModule, hasGroups, hasEvents]);

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
          title="Dashboard"
          description="Your personal dashboard. View your balance, listings, and activity."
          noIndex
        />
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Dashboard</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadDashboardData()}
          >
            Try Again
          </Button>
        </GlassCard>
      </>
    );
  }

  return (
    <>
      <PageMeta
        title="Dashboard"
        description="Your personal dashboard. View your balance, listings, and activity."
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
                    <p className="font-semibold text-theme-primary">Complete your profile setup</p>
                    <p className="text-sm text-theme-muted">Tell us about your interests and skills to get personalized matches</p>
                  </div>
                </div>
                <Link to={tenantPath('/onboarding')}>
                  <Button
                    size="sm"
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={<ArrowRight className="w-4 h-4" aria-hidden="true" />}
                  >
                    Get Started
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
                  Welcome back, {user?.first_name || user?.name?.split(' ')[0] || 'there'}!
                </h1>
                <p className="text-theme-muted mt-1">
                  Here's what's happening in your {branding.name} community
                </p>
              </div>
              <div className="flex gap-3">
                <Link to={tenantPath('/listings/create')}>
                  <Button
                    className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                    startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
                  >
                    New Listing
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
            label="Balance"
            value={stats.walletBalance ? `${stats.walletBalance.balance}h` : '\u2014'}
            color="indigo"
            href="/wallet"
            isLoading={isLoading}
          />
          <StatCard
            icon={<ListTodo className="w-5 h-5" aria-hidden="true" />}
            label="Active Listings"
            value={stats.activeListingsCount.toString()}
            color="emerald"
            href="/listings"
            isLoading={isLoading}
          />
          <StatCard
            icon={<MessageSquare className="w-5 h-5" aria-hidden="true" />}
            label="Messages"
            value={notificationCounts.messages.toString()}
            color="amber"
            href="/messages"
            isLoading={isLoading}
          />
          <StatCard
            icon={<Clock className="w-5 h-5" aria-hidden="true" />}
            label="Pending"
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
                    <span className="truncate">Recent Listings</span>
                  </h2>
                  <Link to={tenantPath('/listings')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                    View all <ArrowRight className="w-4 h-4" aria-hidden="true" />
                  </Link>
                </div>

                {isLoading ? (
                  <div className="space-y-3">
                    {[1, 2, 3].map((i) => (
                      <div key={i} className="animate-pulse">
                        <div className="h-16 bg-theme-elevated rounded-lg" />
                      </div>
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
                              {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                            </Chip>
                          </div>
                        </Link>
                      </article>
                    ))}
                  </div>
                ) : (
                  <div className="text-center py-8 text-theme-subtle">
                    <ListTodo className="w-12 h-12 mx-auto mb-3 opacity-50" aria-hidden="true" />
                    <p>No recent listings</p>
                    <Link to={tenantPath('/listings/create')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-2 inline-block">
                      Create your first listing
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
                      <span className="truncate">Recent Activity</span>
                    </h2>
                    <Link to={tenantPath('/feed')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                      View All <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    </Link>
                  </div>

                  {activityLoading ? (
                    <div className="space-y-3">
                      {[1, 2, 3].map((i) => (
                        <div key={i} className="animate-pulse flex items-center gap-3 p-3">
                          <div className="w-10 h-10 rounded-full bg-theme-elevated shrink-0" />
                          <div className="flex-1 space-y-2">
                            <div className="h-4 bg-theme-elevated rounded w-3/4" />
                            <div className="h-3 bg-theme-elevated rounded w-1/2" />
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
                              <span className="text-theme-muted">{formatActivityAction(item)}</span>
                            </p>
                            <p className="text-xs text-theme-subtle line-clamp-1">{item.content}</p>
                          </div>
                          <div className="hidden sm:flex items-center gap-3 shrink-0 text-theme-subtle text-xs">
                            {item.likes_count > 0 && (
                              <span className="flex items-center gap-1">
                                <Heart className="w-3 h-3" aria-hidden="true" />
                                {item.likes_count}
                              </span>
                            )}
                            {item.comments_count > 0 && (
                              <span className="flex items-center gap-1">
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
                      <p>No recent activity</p>
                      <Link to={tenantPath('/feed')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-2 inline-block">
                        Check out the feed
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
                <h2 className="text-lg font-semibold text-theme-primary mb-4">Quick Actions</h2>
                <div className="space-y-2">
                  <QuickActionLink to={tenantPath('/listings/create')} icon={<Plus aria-hidden="true" />} label="Create Listing" />
                  <QuickActionLink to={tenantPath('/messages')} icon={<MessageSquare aria-hidden="true" />} label="Messages" />
                  <QuickActionLink to={tenantPath('/wallet')} icon={<Wallet aria-hidden="true" />} label="View Wallet" />
                  <QuickActionLink to={tenantPath('/members')} icon={<Users aria-hidden="true" />} label="Find Members" />
                  {hasEvents && (
                    <QuickActionLink to={tenantPath('/events')} icon={<Calendar aria-hidden="true" />} label="Browse Events" />
                  )}
                  <QuickActionLink to={tenantPath('/notifications')} icon={<Bell aria-hidden="true" />} label="Notifications" />
                </div>
              </GlassCard>
            </motion.div>

            {/* Pending Reviews */}
            <motion.div variants={itemVariants}>
              <PendingReviewsCard />
            </motion.div>

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
                        <span className="truncate">Suggested for You</span>
                      </h2>
                      <Link to={tenantPath('/listings')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                        Browse All <ArrowRight className="w-4 h-4" aria-hidden="true" />
                      </Link>
                    </div>

                    {suggestedLoading ? (
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                        {[1, 2, 3, 4].map((i) => (
                          <div key={i} className="animate-pulse">
                            <div className="h-24 bg-theme-elevated rounded-lg" />
                          </div>
                        ))}
                      </div>
                    ) : stats.suggestedListings.length > 0 ? (
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                        {stats.suggestedListings.map((listing) => (
                          <Link
                            key={listing.id}
                            to={tenantPath(`/listings/${listing.id}`)}
                            className="block p-3 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors group"
                            aria-label={`${listing.title} - ${listing.type === 'offer' ? 'Offer' : 'Request'}`}
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
                                {listing.type === 'offer' ? 'Offer' : 'Request'}
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
                        <p className="text-sm">No suggestions yet</p>
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
                      <span className="truncate">My Groups</span>
                    </h2>
                    <Link to={tenantPath('/groups')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                      View All <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    </Link>
                  </div>

                  {groupsLoading ? (
                    <div className="space-y-3">
                      {[1, 2, 3].map((i) => (
                        <div key={i} className="animate-pulse flex items-center gap-3">
                          <div className="w-10 h-10 rounded-lg bg-theme-elevated shrink-0" />
                          <div className="flex-1 space-y-2">
                            <div className="h-4 bg-theme-elevated rounded w-2/3" />
                            <div className="h-3 bg-theme-elevated rounded w-1/3" />
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
                              {group.member_count ?? group.members_count ?? 0} members
                            </p>
                          </div>
                        </Link>
                      ))}
                    </div>
                  ) : (
                    <div className="text-center py-6 text-theme-subtle">
                      <Users className="w-10 h-10 mx-auto mb-2 opacity-50" aria-hidden="true" />
                      <p className="text-sm">You haven't joined any groups yet</p>
                      <Link to={tenantPath('/groups')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-1 inline-block">
                        Discover groups
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
                      <span className="truncate">Upcoming Events</span>
                    </h2>
                    <Link to={tenantPath('/events')} className="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm flex items-center gap-1 shrink-0 whitespace-nowrap">
                      View All <ArrowRight className="w-4 h-4" aria-hidden="true" />
                    </Link>
                  </div>

                  {eventsLoading ? (
                    <div className="space-y-3">
                      {[1, 2, 3].map((i) => (
                        <div key={i} className="animate-pulse flex items-center gap-3">
                          <div className="w-12 h-14 rounded-lg bg-theme-elevated shrink-0" />
                          <div className="flex-1 space-y-2">
                            <div className="h-4 bg-theme-elevated rounded w-3/4" />
                            <div className="h-3 bg-theme-elevated rounded w-1/2" />
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
                                <p className="text-xs text-theme-subtle mt-0.5">Online event</p>
                              )}
                            </div>
                          </Link>
                        );
                      })}
                    </div>
                  ) : (
                    <div className="text-center py-6 text-theme-subtle">
                      <Calendar className="w-10 h-10 mx-auto mb-2 opacity-50" aria-hidden="true" />
                      <p className="text-sm">No upcoming events</p>
                      <Link to={tenantPath('/events')} className="text-indigo-600 dark:text-indigo-400 hover:underline text-sm mt-1 inline-block">
                        Browse events
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
                    Your Progress
                  </h2>
                  {isLoading ? (
                    <div className="space-y-4 animate-pulse">
                      <div className="h-4 bg-theme-elevated rounded w-1/2" />
                      <div className="h-2 bg-theme-elevated rounded-full" />
                      <div className="h-4 bg-theme-elevated rounded w-1/3" />
                    </div>
                  ) : stats.gamification ? (
                    <div className="space-y-4">
                      {/* Level info */}
                      <div className="flex items-center justify-between">
                        <span className="text-sm font-medium text-theme-primary flex items-center gap-1.5">
                          <Award className="w-4 h-4 text-amber-500" aria-hidden="true" />
                          Level {stats.gamification.level}
                        </span>
                        <Chip size="sm" variant="flat" color="warning" className="text-xs">
                          {stats.gamification.level_name}
                        </Chip>
                      </div>

                      {/* XP Progress */}
                      <div>
                        <div className="flex justify-between text-xs sm:text-sm mb-1.5">
                          <span className="text-theme-muted">
                            {stats.gamification.xp} / {stats.gamification.xp_for_next_level} XP
                          </span>
                          <span className="text-theme-primary font-medium">
                            {Math.round(stats.gamification.xp_progress_percent)}%
                          </span>
                        </div>
                        <Progress
                          value={Math.min(stats.gamification.xp_progress_percent, 100)}
                          color="warning"
                          size="sm"
                          aria-label="XP Progress"
                          className="h-2"
                        />
                      </div>

                      {/* Quick stats */}
                      <div className="grid grid-cols-2 gap-3 pt-2">
                        <div className="text-center p-2 rounded-lg bg-theme-elevated">
                          <div className="text-lg font-bold text-theme-primary">{stats.gamification.total_badges}</div>
                          <div className="text-xs text-theme-subtle">Badges</div>
                        </div>
                        <div className="text-center p-2 rounded-lg bg-theme-elevated">
                          <div className="text-lg font-bold text-theme-primary">{stats.gamification.streak_days} day</div>
                          <div className="text-xs text-theme-subtle">Streak</div>
                        </div>
                      </div>

                      <Link
                        to={tenantPath('/achievements')}
                        className="block text-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm"
                      >
                        View Achievements <ArrowRight className="w-3.5 h-3.5 inline-block ml-1" aria-hidden="true" />
                      </Link>
                    </div>
                  ) : (
                    <div className="space-y-4">
                      <p className="text-sm text-theme-subtle text-center">Start earning XP by participating in the community!</p>
                      <Link
                        to={tenantPath('/achievements')}
                        className="block text-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm"
                      >
                        View Achievements <ArrowRight className="w-3.5 h-3.5 inline-block ml-1" aria-hidden="true" />
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
      <GlassCard className="p-4 hover:scale-[1.02] transition-transform">
        <div className={`inline-flex p-2 rounded-lg bg-gradient-to-br ${colorClasses[color]} mb-3`}>
          {icon}
        </div>
        <div className="text-theme-muted text-sm">{label}</div>
        {isLoading ? (
          <div className="h-8 w-16 bg-theme-elevated rounded animate-pulse mt-1" />
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
  const [pending, setPending] = useState<PendingReview[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchPending = async () => {
      try {
        const response = await api.get<{ local: PendingReview[]; federated: any[]; total_pending: number }>('/v2/reviews/pending');
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
          Pending Reviews
        </h2>
        <div className="animate-pulse space-y-3">
          {[1, 2].map((i) => (
            <div key={i} className="h-16 bg-theme-elevated rounded-lg" />
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
        Pending Reviews
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
                {review.direction === 'sent' ? 'Sent' : 'Received'} {review.amount}h
              </Chip>
              <span className="text-xs text-amber-600 dark:text-amber-400 ml-auto flex items-center gap-1">
                <Star className="w-3 h-3" aria-hidden="true" />
                Review
              </span>
            </div>
          </Link>
        ))}
      </div>

      <Link
        to={tenantPath('/wallet')}
        className="block mt-4 text-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 dark:hover:text-indigo-300 text-sm"
      >
        View All Transactions <ArrowRight className="w-3.5 h-3.5 inline-block ml-1" aria-hidden="true" />
      </Link>
    </GlassCard>
  );
}

export default DashboardPage;
