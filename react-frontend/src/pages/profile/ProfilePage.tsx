// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Profile Page - User profile view
 *
 * Features:
 * - User profile header with avatar, meta, connection actions
 * - Detailed stats cards (hours given/received, listings, groups, events)
 * - Tabs: About, Listings, Reviews, Achievements
 * - Reviews tab with star ratings loaded from API
 * - Enhanced connection display with "Connected" chip and "Send Message" button
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Avatar, Tabs, Tab, Chip, Skeleton } from '@heroui/react';
import {
  User,
  MapPin,
  Calendar,
  UserPlus,
  UserCheck,
  MessageSquare,
  Edit,
  Star,
  Clock,
  ListTodo,
  Award,
  Settings,
  ArrowLeft,
  RefreshCw,
  AlertTriangle,
  Trophy,
  Lock,
  Users,
  CalendarCheck,
  ArrowUpRight,
  ArrowDownLeft,
  Rss,
} from 'lucide-react';
import DOMPurify from 'dompurify';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { LocationMapCard } from '@/components/location';
import { ReviewModal } from '@/components/reviews';
import { TransferModal } from '@/components/wallet';
import { ProfileFeed } from '@/components/profile/ProfileFeed';
import { VerificationBadgeRow, VerificationBadgeSummary } from '@/components/verification/VerificationBadge';
import { EndorseButton } from '@/components/endorsements/EndorseButton';
import { AvailabilityGrid } from '@/components/availability/AvailabilityGrid';
import { useTranslation } from 'react-i18next';
import { useAuth, useFeature, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { User as UserType, Listing, Review } from '@/types/api';

type ConnectionStatus = 'none' | 'pending_sent' | 'pending_received' | 'connected';

interface ProfileBadge {
  key: string;
  name: string;
  description: string;
  icon: string;
  tier: string;
  earned: boolean;
  earned_at: string | null;
}

interface GamificationSummary {
  level: number;
  level_name: string;
  xp: number;
  total_badges: number;
  badges: ProfileBadge[];
}

interface ProfileStats {
  listings_count?: number;
}

interface ProfileApiBadge {
  name: string;
  badge_key: string;
  icon: string;
  description: string;
  earned_at: string;
}

interface NexusScoreSummary {
  total_score: number;
  tier: string;
  percentile: number;
}

interface ProfileApiUser extends UserType {
  badges?: ProfileApiBadge[];
  stats?: ProfileStats;
  nexus_score?: NexusScoreSummary | null;
}

interface GamificationProfileResponse {
  xp?: number;
  level?: number;
  badges_count?: number;
}

interface GamificationBadgeResponse {
  badge_key?: string;
  key?: string;
  name?: string;
  description?: string;
  icon?: string;
  earned_at?: string | null;
}

export function ProfilePage() {
  const { t } = useTranslation('profile');
  const { id } = useParams<{ id: string }>();
  const { user: currentUser, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const hasGamification = useFeature('gamification');
  const hasReviews = useFeature('reviews');
  const toast = useToast();

  const [profile, setProfile] = useState<ProfileApiUser | null>(null);
  usePageTitle(profile?.name ? `${profile.name}` : t('page_title'));
  const [listings, setListings] = useState<Listing[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState('about');
  const [connectionStatus, setConnectionStatus] = useState<ConnectionStatus>('none');
  const [connectionId, setConnectionId] = useState<number | null>(null);
  const [isConnecting, setIsConnecting] = useState(false);
  const [gamification, setGamification] = useState<GamificationSummary | null>(null);

  // Endorsement data keyed by skill name
  const [endorsements, setEndorsements] = useState<Record<string, { count: number; isEndorsed: boolean }>>({});

  // Reviews state
  const [reviews, setReviews] = useState<Review[]>([]);
  const [isLoadingReviews, setIsLoadingReviews] = useState(false);
  const [reviewsLoaded, setReviewsLoaded] = useState(false);
  const [reviewsAvailable, setReviewsAvailable] = useState(true);
  const [isReviewModalOpen, setIsReviewModalOpen] = useState(false);
  const [isTransferModalOpen, setIsTransferModalOpen] = useState(false);
  const [currentBalance, setCurrentBalance] = useState(0);

  // Resolve "me" alias (from gamification notification links) to own profile
  const resolvedId = id === 'me' ? undefined : id;
  const isOwnProfile = !resolvedId || (currentUser && resolvedId === currentUser.id.toString());
  const profileId = resolvedId || currentUser?.id?.toString();

  const loadProfile = useCallback(async () => {
    if (!profileId) return;

    try {
      setIsLoading(true);
      setError(null);

      const requests: Promise<unknown>[] = [
        api.get<UserType>(`/v2/users/${profileId}`),
        api.get<Listing[]>(`/v2/users/${profileId}/listings?limit=6`),
      ];

      if (hasGamification) {
        requests.push(
          api.get<GamificationProfileResponse>(`/v2/gamification/profile?user_id=${profileId}`),
          api.get<GamificationBadgeResponse[]>(`/v2/gamification/badges?user_id=${profileId}`)
        );
      }

      // Check connection status if viewing another user's profile
      if (isAuthenticated && currentUser && profileId !== currentUser.id.toString()) {
        requests.push(
          api.get<{ status: ConnectionStatus; connection_id?: number }>(`/v2/connections/status/${profileId}`)
        );
      }

      const results = await Promise.all(requests);
      const connectionIdx = (isAuthenticated && currentUser && profileId !== currentUser.id.toString())
        ? (hasGamification ? 4 : 2)
        : -1;

      const [profileRes, listingsRes] = results as [
        { success: boolean; data?: ProfileApiUser },
        { success: boolean; data?: Listing[] },
      ];
      const gamificationProfileRes = hasGamification
        ? (results[2] as { success: boolean; data?: GamificationProfileResponse } | undefined)
        : undefined;
      const gamificationBadgesRes = hasGamification
        ? (results[3] as { success: boolean; data?: GamificationBadgeResponse[] } | undefined)
        : undefined;
      const connectionRes = connectionIdx >= 0 ? results[connectionIdx] as { success: boolean; data?: { status: ConnectionStatus; connection_id?: number } } | undefined : undefined;

      if (profileRes.success && profileRes.data) {
        setProfile(profileRes.data);

        if (hasGamification) {
          const profileBadges: ProfileBadge[] = Array.isArray(profileRes.data.badges)
            ? profileRes.data.badges.map((b) => ({
                key: b.badge_key,
                name: b.name,
                description: b.description || '',
                icon: b.icon || '',
                tier: '',
                earned: true,
                earned_at: b.earned_at,
              }))
            : [];

          const gamificationBadges: ProfileBadge[] =
            gamificationBadgesRes?.success && Array.isArray(gamificationBadgesRes.data)
              ? gamificationBadgesRes.data
                  .map((b) => {
                    const key = b.badge_key || b.key;
                    if (!key || !b.name) return null;
                    return {
                      key,
                      name: b.name,
                      description: b.description || '',
                      icon: b.icon || '',
                      tier: '',
                      earned: true,
                      earned_at: b.earned_at ?? null,
                    };
                  })
                  .filter((b): b is ProfileBadge => b !== null)
              : [];

          const mergedBadges = gamificationBadges.length > 0 ? gamificationBadges : profileBadges;

          setGamification({
            level: gamificationProfileRes?.data?.level ?? profileRes.data.level ?? 1,
            level_name: '',
            xp: gamificationProfileRes?.data?.xp ?? profileRes.data.xp ?? 0,
            total_badges: gamificationProfileRes?.data?.badges_count ?? mergedBadges.length,
            badges: mergedBadges.slice(0, 12),
          });
        }
      } else {
        setError(t('not_found'));
        return;
      }
      if (listingsRes.success && listingsRes.data) {
        setListings(listingsRes.data);
      }
      if (connectionRes?.success && connectionRes.data) {
        setConnectionStatus(connectionRes.data.status);
        setConnectionId(connectionRes.data.connection_id ?? null);
      }

      // Fetch endorsement data for skills
      if (profileRes.success && profileRes.data?.skills?.length) {
        try {
          const endorseRes = await api.get<{
            endorsements?: Record<string, {
              skill_name: string;
              count: number;
              endorsed_by_ids?: string;
            }>;
          }>(`/v2/members/${profileId}/endorsements`);
          if (endorseRes.success && endorseRes.data?.endorsements) {
            const currentUserId = currentUser?.id?.toString() || '';
            const map: Record<string, { count: number; isEndorsed: boolean }> = {};
            for (const [skill, info] of Object.entries(endorseRes.data.endorsements)) {
              const endorserIds = info.endorsed_by_ids?.split(',') || [];
              map[skill] = {
                count: info.count || 0,
                isEndorsed: currentUserId ? endorserIds.includes(currentUserId) : false,
              };
            }
            setEndorsements(map);
          }
        } catch {
          // Endorsement data is supplementary — don't fail the whole page
        }
      }
    } catch (err) {
      logError('Failed to load profile', err);
      setError(t('load_error'));
    } finally {
      setIsLoading(false);
    }
  }, [profileId, isAuthenticated, currentUser, hasGamification]);

  useEffect(() => {
    loadProfile();
  }, [loadProfile]);

  // Load reviews when Reviews tab is selected (lazy load)
  const loadReviews = useCallback(async () => {
    if (!profileId || reviewsLoaded || isLoadingReviews) return;

    try {
      setIsLoadingReviews(true);
      const response = await api.get<Review[]>(`/v2/reviews/user/${profileId}?per_page=50`);

      if (response.success && response.data) {
        setReviews(response.data);
        setReviewsLoaded(true);
      } else {
        // Endpoint may not exist, gracefully hide the tab
        setReviewsAvailable(false);
      }
    } catch {
      // If the endpoint fails (404 etc.), hide the reviews tab
      setReviewsAvailable(false);
    } finally {
      setIsLoadingReviews(false);
    }
  }, [profileId, reviewsLoaded, isLoadingReviews]);

  useEffect(() => {
    if (activeTab === 'reviews' && !reviewsLoaded) {
      loadReviews();
    }
  }, [activeTab, reviewsLoaded, loadReviews]);

  const handleConnect = useCallback(async () => {
    if (!profile?.id) return;

    try {
      setIsConnecting(true);

      if (connectionStatus === 'none') {
        // Send connection request
        const response = await api.post<{ connection_id: number }>('/v2/connections/request', {
          user_id: profile.id,
        });
        if (response.success) {
          setConnectionStatus('pending_sent');
          setConnectionId(response.data?.connection_id ?? null);
          toast.success(t('toast.request_sent_title'), t('toast.request_sent'));
        } else {
          toast.error(t('toast.failed'), response.error || t('toast.failed'));
        }
      } else if (connectionStatus === 'pending_received' && connectionId) {
        // Accept connection request
        const response = await api.post(`/v2/connections/${connectionId}/accept`);
        if (response.success) {
          setConnectionStatus('connected');
          toast.success(t('toast.connected_title'), t('toast.connected'));
        } else {
          toast.error(t('toast.failed'), response.error || t('toast.failed'));
        }
      } else if ((connectionStatus === 'pending_sent' || connectionStatus === 'connected') && connectionId) {
        // Cancel/Remove connection
        const response = await api.delete(`/v2/connections/${connectionId}`);
        if (response.success) {
          setConnectionStatus('none');
          setConnectionId(null);
          toast.info(t('toast.removed_title'), t('toast.removed'));
        } else {
          toast.error(t('toast.failed'), response.error || t('toast.failed'));
        }
      }
    } catch (error) {
      logError('Connection action failed', error);
      toast.error(t('toast.failed'), t('toast.error'));
    } finally {
      setIsConnecting(false);
    }
  }, [profile?.id, connectionStatus, connectionId, toast]);

  if (isLoading) {
    return <LoadingScreen message={t('loading')} />;
  }

  // Error state with retry
  if (error && !profile) {
    return (
      <div className="max-w-4xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath('/members')}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                {t('browse_members')}
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadProfile()}
            >
              {t('try_again')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  // Profile not found
  if (!profile) {
    return (
      <EmptyState
        icon={<User className="w-12 h-12" aria-hidden="true" />}
        title={t('not_found')}
        description={t('not_found_desc')}
        action={
          <Link to={tenantPath('/members')}>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
            >
              {t('browse_members')}
            </Button>
          </Link>
        }
      />
    );
  }

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

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="max-w-4xl mx-auto space-y-6"
    >
      {/* Profile Header */}
      <motion.div variants={itemVariants}>
        <GlassCard className="p-6 sm:p-8">
          <div className="flex flex-col sm:flex-row items-center sm:items-start gap-6">
            {/* Avatar */}
            <div className="relative">
              <Avatar
                src={resolveAvatarUrl(profile.avatar_url || profile.avatar)}
                name={profile.name}
                className="w-24 h-24 sm:w-32 sm:h-32 ring-4 ring-theme-default"
              />
              {hasGamification && profile.level && (
                <div className="absolute -bottom-2 -right-2 px-2 py-1 rounded-full bg-gradient-to-r from-amber-500 to-orange-500 text-white text-xs font-bold">
                  {t('level', { level: profile.level })}
                </div>
              )}
            </div>

            {/* Info */}
            <div className="flex-1 text-center sm:text-left">
              <div className="flex flex-col sm:flex-row items-center sm:items-start gap-2">
                <h1 className="text-lg sm:text-2xl lg:text-3xl font-bold text-theme-primary">{profile.name}</h1>
                {/* Verification badges */}
                <VerificationBadgeRow userId={profile.id} size="sm" />
                {/* Connected chip for other users */}
                {!isOwnProfile && connectionStatus === 'connected' && (
                  <Chip
                    color="success"
                    variant="flat"
                    size="sm"
                    startContent={<UserCheck className="w-3 h-3" />}
                    className="mt-1"
                  >
                    {t('connected')}
                  </Chip>
                )}
              </div>
              {profile.tagline && (
                <p className="text-theme-muted mt-1">{profile.tagline}</p>
              )}

              {/* Meta */}
              <div className="flex flex-wrap justify-center sm:justify-start gap-4 mt-4 text-sm text-theme-subtle">
                {profile.location && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    {profile.location}
                  </span>
                )}
                {profile.created_at && (
                  <span className="flex items-center gap-1">
                    <Calendar className="w-4 h-4" aria-hidden="true" />
                    <time dateTime={profile.created_at}>
                      {t('joined', { date: new Date(profile.created_at).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }) })}
                    </time>
                  </span>
                )}
                {profile.rating && (
                  <span className="flex items-center gap-1" aria-label={`Rating: ${profile.rating.toFixed(1)} out of 5`}>
                    <Star className="w-4 h-4 text-amber-400" aria-hidden="true" />
                    <span aria-hidden="true">{profile.rating.toFixed(1)}</span>
                  </span>
                )}
                {profile.nexus_score && (
                  isOwnProfile ? (
                    <Link
                      to={tenantPath('/nexus-score')}
                      className="flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 text-xs font-semibold hover:bg-indigo-500/25 transition-colors"
                      aria-label={`NexusScore: ${profile.nexus_score.total_score} (${profile.nexus_score.tier}) — view breakdown`}
                    >
                      <Trophy className="w-3.5 h-3.5" aria-hidden="true" />
                      {profile.nexus_score.total_score}
                      <span className="text-[10px] opacity-70 capitalize">{profile.nexus_score.tier}</span>
                    </Link>
                  ) : (
                    <span
                      className="flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 text-xs font-semibold"
                      aria-label={`NexusScore: ${profile.nexus_score.total_score} (${profile.nexus_score.tier})`}
                    >
                      <Trophy className="w-3.5 h-3.5" aria-hidden="true" />
                      {profile.nexus_score.total_score}
                      <span className="text-[10px] opacity-70 capitalize">{profile.nexus_score.tier}</span>
                    </span>
                  )
                )}
              </div>

              {/* Actions */}
              <div className="flex flex-wrap justify-center sm:justify-start gap-3 mt-6">
                {isOwnProfile ? (
                  <>
                    <Link to={tenantPath('/settings')}>
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<Edit className="w-4 h-4" aria-hidden="true" />}
                      >
                        {t('edit_profile')}
                      </Button>
                    </Link>
                    <Link to={tenantPath('/settings')}>
                      <Button
                        variant="flat"
                        className="bg-theme-elevated text-theme-primary"
                        startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                      >
                        {t('settings')}
                      </Button>
                    </Link>
                  </>
                ) : (
                  <>
                    <Link to={tenantPath(`/messages/new/${profile.id}`)}>
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                      >
                        {t('send_message')}
                      </Button>
                    </Link>
                    {isAuthenticated && connectionStatus !== 'connected' && (
                      <Button
                        variant="flat"
                        className={
                          connectionStatus === 'pending_sent'
                            ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                            : connectionStatus === 'pending_received'
                            ? 'bg-indigo-500/20 text-indigo-600 dark:text-indigo-400'
                            : 'bg-theme-elevated text-theme-primary'
                        }
                        startContent={
                          connectionStatus === 'pending_sent' ? (
                            <Clock className="w-4 h-4" aria-hidden="true" />
                          ) : connectionStatus === 'pending_received' ? (
                            <UserPlus className="w-4 h-4" aria-hidden="true" />
                          ) : (
                            <UserPlus className="w-4 h-4" aria-hidden="true" />
                          )
                        }
                        onPress={handleConnect}
                        isLoading={isConnecting}
                      >
                        {connectionStatus === 'pending_sent'
                          ? t('pending')
                          : connectionStatus === 'pending_received'
                          ? t('accept')
                          : t('connect')}
                      </Button>
                    )}
                    {/* Write Review */}
                    {isAuthenticated && hasReviews && (
                      <Button
                        variant="flat"
                        className="bg-amber-500/20 text-amber-600 dark:text-amber-400"
                        startContent={<Star className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => setIsReviewModalOpen(true)}
                      >
                        {t('write_review')}
                      </Button>
                    )}
                    {/* Send credits */}
                    {isAuthenticated && (
                      <Button
                        variant="flat"
                        className="bg-emerald-500/20 text-emerald-600 dark:text-emerald-400"
                        startContent={<ArrowUpRight className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => {
                          api.get<{ balance: number }>('/v2/wallet/balance').then((res) => {
                            if (res.success && res.data) setCurrentBalance(res.data.balance);
                          }).catch((err) => {
                            logError('Failed to load wallet balance', err);
                          });
                          setIsTransferModalOpen(true);
                        }}
                      >
                        {t('send_credits')}
                      </Button>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        </GlassCard>

        {/* Location Map */}
        {profile.location && profile.latitude && profile.longitude && (
          <LocationMapCard
            title={t('location_title')}
            locationText={profile.location}
            markers={[{
              id: profile.id,
              lat: Number(profile.latitude),
              lng: Number(profile.longitude),
              title: `${profile.first_name} ${profile.last_name}`,
            }]}
            center={{ lat: Number(profile.latitude), lng: Number(profile.longitude) }}
            mapHeight="200px"
            zoom={13}
            className="mt-6"
          />
        )}
      </motion.div>

      {/* Stats Cards Row */}
      <motion.div variants={itemVariants} className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <ProfileStatCard
          icon={<ArrowUpRight className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.hours_given')}
          value={profile.total_hours_given ?? 0}
          color="emerald"
        />
        <ProfileStatCard
          icon={<ArrowDownLeft className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.hours_received')}
          value={profile.total_hours_received ?? 0}
          color="indigo"
        />
        <ProfileStatCard
          icon={<ListTodo className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.active_listings')}
          value={profile.stats?.listings_count ?? listings.length}
          color="purple"
        />
        <ProfileStatCard
          icon={<Users className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.groups')}
          value={profile.groups_count ?? 0}
          color="amber"
        />
        <ProfileStatCard
          icon={<CalendarCheck className="w-5 h-5" aria-hidden="true" />}
          label={t('stats.events')}
          value={profile.events_attended ?? 0}
          color="rose"
        />
      </motion.div>

      {/* Tabs Content */}
      <motion.div variants={itemVariants}>
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as string)}
          aria-label="Profile sections"
          classNames={{
            tabList: 'bg-theme-elevated p-1 rounded-lg',
            cursor: 'bg-theme-hover',
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
          }}
        >
          <Tab
            key="about"
            aria-label="About this user"
            title={
              <span className="flex items-center gap-2">
                <User className="w-4 h-4" aria-hidden="true" />
                {t('tabs.about')}
              </span>
            }
          />
          <Tab
            key="listings"
            aria-label="User listings"
            title={
              <span className="flex items-center gap-2">
                <ListTodo className="w-4 h-4" aria-hidden="true" />
                {t('tabs.listings')}
              </span>
            }
          />
          <Tab
            key="activity"
            aria-label="User activity feed"
            title={
              <span className="flex items-center gap-2">
                <Rss className="w-4 h-4" aria-hidden="true" />
                {t('tabs.activity', 'Activity')}
              </span>
            }
          />
          <Tab
            key="availability"
            aria-label="User availability"
            title={
              <span className="flex items-center gap-2">
                <Calendar className="w-4 h-4" aria-hidden="true" />
                {t('tabs.availability')}
              </span>
            }
          />
          {hasReviews && reviewsAvailable && (
            <Tab
              key="reviews"
              aria-label="User reviews"
              title={
                <span className="flex items-center gap-2">
                  <Star className="w-4 h-4" aria-hidden="true" />
                  {t('tabs.reviews')}
                </span>
              }
            />
          )}
          {hasGamification && (
            <Tab
              key="achievements"
              aria-label="User achievements"
              title={
                <span className="flex items-center gap-2">
                  <Award className="w-4 h-4" aria-hidden="true" />
                  {t('tabs.achievements')}
                </span>
              }
            />
          )}
        </Tabs>

        <div className="mt-6">
          {activeTab === 'about' && (
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4">{t('about.heading')}</h2>
              {profile.bio ? (
                <div
                  className="text-theme-muted whitespace-pre-wrap prose prose-sm max-w-none dark:prose-invert"
                  dangerouslySetInnerHTML={{
                    __html: DOMPurify.sanitize(profile.bio, {
                      ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li'],
                    }),
                  }}
                />
              ) : (
                <p className="text-theme-subtle italic">{t('about.no_bio')}</p>
              )}

              {profile.skills && profile.skills.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-sm font-medium text-theme-muted mb-3">{t('about.skills')}</h3>
                  <div className="flex flex-wrap gap-2">
                    {profile.skills.map((skill) => (
                      <div key={skill} className="inline-flex items-center gap-1.5">
                        <Chip
                          variant="flat"
                          size="sm"
                          className="bg-indigo-500/20 text-indigo-600 dark:text-indigo-300"
                        >
                          {skill}
                        </Chip>
                        {/* Endorsement button for other users' skills */}
                        {!isOwnProfile && isAuthenticated && profile.id && (
                          <EndorseButton
                            memberId={profile.id}
                            skillName={skill}
                            endorsementCount={endorsements[skill]?.count ?? 0}
                            isEndorsed={endorsements[skill]?.isEndorsed ?? false}
                            compact
                          />
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Verification Badges Summary */}
              {profile.id && (
                <div className="mt-6">
                  <VerificationBadgeSummary userId={profile.id} />
                </div>
              )}
            </GlassCard>
          )}

          {activeTab === 'listings' && (
            <div className="grid sm:grid-cols-2 gap-4" role="list" aria-label="User listings">
              {listings.length > 0 ? (
                listings.map((listing) => (
                  <Link
                    key={listing.id}
                    to={tenantPath(`/listings/${listing.id}`)}
                    aria-label={`${listing.type === 'offer' ? 'Offering' : 'Requesting'}: ${listing.title}`}
                  >
                    <article role="listitem">
                      <GlassCard className="hover:scale-[1.02] transition-transform h-full flex flex-col overflow-hidden">
                        {listing.image_url && (
                          <img
                            src={resolveAssetUrl(listing.image_url)}
                            alt={listing.title || 'Listing image'}
                            className="w-full h-32 object-cover"
                            loading="lazy"
                          />
                        )}
                        <div className="p-5 flex flex-col flex-1">
                          <div className="flex items-center gap-2 mb-2">
                            <Chip
                              size="sm"
                              variant="flat"
                              className={
                                listing.type === 'offer'
                                  ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                                  : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                              }
                            >
                              {listing.type === 'offer' ? t('listing_type.offer') : t('listing_type.request')}
                            </Chip>
                          </div>
                          <h3 className="font-medium text-theme-primary mb-1">{listing.title}</h3>
                          <p className="text-sm text-theme-subtle line-clamp-2">{listing.description}</p>
                          <div className="flex items-center gap-2 mt-3 text-xs text-theme-subtle">
                            <Clock className="w-3 h-3" aria-hidden="true" />
                            {listing.hours_estimate ?? listing.estimated_hours ?? '\u2014'}h
                          </div>
                        </div>
                      </GlassCard>
                    </article>
                  </Link>
                ))
              ) : (
                <div className="col-span-2">
                  <EmptyState
                    icon={<ListTodo className="w-12 h-12" aria-hidden="true" />}
                    title={t('no_listings')}
                    description={isOwnProfile ? t('no_listings_own') : t('no_listings_other')}
                    action={
                      isOwnProfile && (
                        <Link to={tenantPath('/listings/create')}>
                          <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                            {t('create_listing')}
                          </Button>
                        </Link>
                      )
                    }
                  />
                </div>
              )}
            </div>
          )}

          {activeTab === 'activity' && profile && (
            <ProfileFeed userId={profile.id} isOwnProfile={!!isOwnProfile} />
          )}

          {activeTab === 'availability' && profile && (
            <GlassCard className="p-6">
              <AvailabilityGrid userId={profile.id} editable={false} compact />
            </GlassCard>
          )}

          {/* Reviews Tab */}
          {activeTab === 'reviews' && (
            <div className="space-y-4">
              {isLoadingReviews ? (
                // Loading skeleton for reviews
                <div aria-label="Loading reviews" aria-busy="true" className="space-y-3">
                  {Array.from({ length: 3 }).map((_, i) => (
                    <GlassCard key={i} className="p-5">
                      <div className="flex items-start gap-4">
                        <Skeleton className="rounded-full flex-shrink-0">
                          <div className="w-10 h-10 rounded-full bg-default-300" />
                        </Skeleton>
                        <div className="flex-1 space-y-2">
                          <Skeleton className="rounded-lg">
                            <div className="h-4 rounded-lg bg-default-300 w-1/4" />
                          </Skeleton>
                          <Skeleton className="rounded-lg">
                            <div className="h-3 rounded-lg bg-default-200 w-1/5" />
                          </Skeleton>
                          <Skeleton className="rounded-lg">
                            <div className="h-3 rounded-lg bg-default-200 w-3/4" />
                          </Skeleton>
                        </div>
                      </div>
                    </GlassCard>
                  ))}
                </div>
              ) : reviews.length > 0 ? (
                <>
                  {/* Reviews summary */}
                  {profile.rating && (
                    <GlassCard className="p-4">
                      <div className="flex items-center gap-4">
                        <div className="text-center">
                          <div className="text-3xl font-bold text-theme-primary">{profile.rating.toFixed(1)}</div>
                          <div className="flex items-center gap-0.5 mt-1">
                            {[1, 2, 3, 4, 5].map((star) => (
                              <Star
                                key={star}
                                className={`w-4 h-4 ${
                                  star <= Math.round(profile.rating ?? 0)
                                    ? 'text-amber-400 fill-amber-400'
                                    : 'text-theme-subtle'
                                }`}
                                aria-hidden="true"
                              />
                            ))}
                          </div>
                          <p className="text-xs text-theme-subtle mt-1">
                            {t('reviews_count', { count: reviews.length })}
                          </p>
                        </div>
                      </div>
                    </GlassCard>
                  )}

                  {/* Individual reviews */}
                  {reviews.map((review) => (
                    <ReviewCard key={review.id} review={review} />
                  ))}
                </>
              ) : (
                <GlassCard className="p-6">
                  <EmptyState
                    icon={<Star className="w-12 h-12" aria-hidden="true" />}
                    title={t('no_reviews')}
                    description={
                      isOwnProfile
                        ? t('no_reviews_own')
                        : t('no_reviews_other')
                    }
                  />
                </GlassCard>
              )}
            </div>
          )}

          {activeTab === 'achievements' && (
            <div className="space-y-4">
              {gamification && gamification.badges.length > 0 ? (
                <>
                  {/* Summary */}
                  <GlassCard className="p-4">
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-gradient-to-br from-amber-500/20 to-orange-500/20">
                          <Trophy className="w-5 h-5 text-amber-500" aria-hidden="true" />
                        </div>
                        <div>
                          <p className="text-sm font-medium text-theme-primary">
                            {t('achievements.badges', { count: gamification.total_badges })}
                          </p>
                          {profile.level && (
                            <p className="text-xs text-theme-subtle">{t('achievements.level', { level: profile.level })}</p>
                          )}
                        </div>
                      </div>
                      {isOwnProfile && (
                        <Link to={tenantPath('/achievements')}>
                          <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-muted">
                            {t('achievements.view_all')}
                          </Button>
                        </Link>
                      )}
                    </div>
                  </GlassCard>

                  {/* Badge Grid */}
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                    {gamification.badges.map((badge) => (
                      <GlassCard
                        key={badge.key}
                        className={`p-4 text-center ${badge.earned ? '' : 'opacity-50'}`}
                      >
                        <div className="text-3xl mb-2">{badge.icon || '🏆'}</div>
                        <p className="text-sm font-medium text-theme-primary truncate">{badge.name}</p>
                        <p className="text-xs text-theme-subtle mt-1 line-clamp-2">{badge.description}</p>
                        {badge.earned ? (
                          <span className="inline-block mt-2 text-xs text-emerald-500 font-medium">{t('achievements.badge_earned')}</span>
                        ) : (
                          <span className="inline-flex items-center gap-1 mt-2 text-xs text-theme-subtle">
                            <Lock className="w-3 h-3" aria-hidden="true" /> {t('achievements.badge_locked')}
                          </span>
                        )}
                      </GlassCard>
                    ))}
                  </div>
                </>
              ) : (
                <GlassCard className="p-6">
                  <EmptyState
                    icon={<Award className="w-12 h-12" aria-hidden="true" />}
                    title={t('achievements.no_achievements')}
                    description={isOwnProfile ? t('achievements.no_achievements_own') : t('achievements.no_achievements_other')}
                    action={isOwnProfile && (
                      <Link to={tenantPath('/achievements')}>
                        <Button className="bg-gradient-to-r from-amber-500 to-orange-600 text-white">
                          {t('achievements.view_badges')}
                        </Button>
                      </Link>
                    )}
                  />
                </GlassCard>
              )}
            </div>
          )}
        </div>
      </motion.div>

      {/* Review Modal */}
      {profile && (
        <ReviewModal
          isOpen={isReviewModalOpen}
          onClose={() => setIsReviewModalOpen(false)}
          onSuccess={() => {
            setReviewsLoaded(false);
            loadReviews();
          }}
          receiverId={profile.id}
          receiverName={profile.name || ''}
          receiverAvatar={profile.avatar_url || ''}
        />
      )}

      {/* Transfer Credits Modal */}
      {profile && (
        <TransferModal
          isOpen={isTransferModalOpen}
          onClose={() => setIsTransferModalOpen(false)}
          currentBalance={currentBalance}
          onTransferComplete={() => {
            setIsTransferModalOpen(false);
            toast.success(t('credits_sent_success', { name: profile.name }));
          }}
          initialRecipientId={profile.id}
        />
      )}
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Profile Stat Card
// ─────────────────────────────────────────────────────────────────────────────

interface ProfileStatCardProps {
  icon: React.ReactNode;
  label: string;
  value: number;
  color: 'emerald' | 'indigo' | 'purple' | 'amber' | 'rose';
}

function ProfileStatCard({ icon, label, value, color }: ProfileStatCardProps) {
  const colorClasses: Record<string, string> = {
    emerald: 'from-emerald-500/20 to-teal-500/20 text-emerald-500',
    indigo: 'from-indigo-500/20 to-blue-500/20 text-indigo-500',
    purple: 'from-purple-500/20 to-fuchsia-500/20 text-purple-500',
    amber: 'from-amber-500/20 to-orange-500/20 text-amber-500',
    rose: 'from-rose-500/20 to-pink-500/20 text-rose-500',
  };

  return (
    <GlassCard className="p-4 text-center">
      <div className={`inline-flex p-2 rounded-lg bg-gradient-to-br ${colorClasses[color]} mb-2`}>
        {icon}
      </div>
      <div className="text-xl font-bold text-theme-primary">{value}</div>
      <div className="text-xs text-theme-subtle">{label}</div>
    </GlassCard>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Review Card
// ─────────────────────────────────────────────────────────────────────────────

interface ReviewCardProps {
  review: Review;
}

function ReviewCard({ review }: ReviewCardProps) {
  const { t } = useTranslation('profile');
  const reviewerName = review.reviewer
    ? `${review.reviewer.first_name} ${review.reviewer.last_name}`.trim()
    : t('anonymous');

  return (
    <GlassCard className="p-5">
      <div className="flex items-start gap-4">
        <Avatar
          src={resolveAvatarUrl(review.reviewer?.avatar)}
          name={reviewerName}
          size="sm"
          className="flex-shrink-0 ring-2 ring-theme-muted/20"
        />
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2">
            <div>
              <p className="font-medium text-theme-primary text-sm">{reviewerName}</p>
              {review.listing_title && (
                <p className="text-xs text-theme-subtle truncate">
                  {t('for_listing', { title: review.listing_title })}
                </p>
              )}
            </div>
            <time
              dateTime={review.created_at}
              className="text-xs text-theme-subtle flex-shrink-0"
            >
              {new Date(review.created_at).toLocaleDateString(undefined, {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
              })}
            </time>
          </div>

          {/* Star Rating */}
          <div className="flex items-center gap-0.5 mt-2" aria-label={`Rating: ${review.rating} out of 5`}>
            {[1, 2, 3, 4, 5].map((star) => (
              <Star
                key={star}
                className={`w-4 h-4 ${
                  star <= review.rating
                    ? 'text-amber-400 fill-amber-400'
                    : 'text-theme-subtle'
                }`}
                aria-hidden="true"
              />
            ))}
          </div>

          {/* Comment */}
          {review.comment && (
            <p className="text-sm text-theme-muted mt-2 whitespace-pre-wrap">{review.comment}</p>
          )}
        </div>
      </div>
    </GlassCard>
  );
}

export default ProfilePage;
