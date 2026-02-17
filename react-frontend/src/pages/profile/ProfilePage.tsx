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
import { Button, Avatar, Tabs, Tab, Chip } from '@heroui/react';
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
} from 'lucide-react';
import DOMPurify from 'dompurify';
import { GlassCard } from '@/components/ui';
import { LoadingScreen, EmptyState } from '@/components/feedback';
import { useAuth, useFeature, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
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

export function ProfilePage() {
  usePageTitle('Profile');
  const { id } = useParams<{ id: string }>();
  const { user: currentUser, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const hasGamification = useFeature('gamification');
  const hasReviews = useFeature('reviews');
  const toast = useToast();

  const [profile, setProfile] = useState<UserType | null>(null);
  const [listings, setListings] = useState<Listing[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState('about');
  const [connectionStatus, setConnectionStatus] = useState<ConnectionStatus>('none');
  const [connectionId, setConnectionId] = useState<number | null>(null);
  const [isConnecting, setIsConnecting] = useState(false);
  const [gamification, setGamification] = useState<GamificationSummary | null>(null);

  // Reviews state
  const [reviews, setReviews] = useState<Review[]>([]);
  const [isLoadingReviews, setIsLoadingReviews] = useState(false);
  const [reviewsLoaded, setReviewsLoaded] = useState(false);
  const [reviewsAvailable, setReviewsAvailable] = useState(true);

  const isOwnProfile = !id || (currentUser && id === currentUser.id.toString());
  const profileId = id || currentUser?.id?.toString();

  const loadProfile = useCallback(async () => {
    if (!profileId) return;

    try {
      setIsLoading(true);
      setError(null);

      const requests: Promise<unknown>[] = [
        api.get<UserType>(`/v2/users/${profileId}`),
        api.get<Listing[]>(`/v2/users/${profileId}/listings?limit=6`),
      ];

      // Check connection status if viewing another user's profile
      if (isAuthenticated && currentUser && profileId !== currentUser.id.toString()) {
        requests.push(
          api.get<{ status: ConnectionStatus; connection_id?: number }>(`/v2/connections/status/${profileId}`)
        );
      }

      // Fetch gamification data if enabled
      if (hasGamification && isAuthenticated) {
        requests.push(
          api.get<GamificationSummary>('/v2/gamification/badges').catch(() => null)
        );
      }

      const results = await Promise.all(requests);
      const connectionIdx = (isAuthenticated && currentUser && profileId !== currentUser.id.toString()) ? 2 : -1;
      const gamificationIdx = hasGamification && isAuthenticated ? (connectionIdx >= 0 ? 3 : 2) : -1;

      const [profileRes, listingsRes] = results as [
        { success: boolean; data?: UserType },
        { success: boolean; data?: Listing[] },
      ];
      const connectionRes = connectionIdx >= 0 ? results[connectionIdx] as { success: boolean; data?: { status: ConnectionStatus; connection_id?: number } } | undefined : undefined;
      const gamificationRes = gamificationIdx >= 0 ? results[gamificationIdx] as { success?: boolean; data?: GamificationSummary } | null : null;

      if (profileRes.success && profileRes.data) {
        setProfile(profileRes.data);
      } else {
        setError('Profile not found');
        return;
      }
      if (listingsRes.success && listingsRes.data) {
        setListings(listingsRes.data);
      }
      if (connectionRes?.success && connectionRes.data) {
        setConnectionStatus(connectionRes.data.status);
        setConnectionId(connectionRes.data.connection_id ?? null);
      }
      if (gamificationRes?.success && gamificationRes.data) {
        // The badges endpoint returns an array of badges
        const badges = Array.isArray(gamificationRes.data) ? gamificationRes.data as ProfileBadge[] : [];
        if (Array.isArray(badges)) {
          const earned = badges.filter((b: ProfileBadge) => b.earned);
          setGamification({
            level: profileRes.data?.level ?? 1,
            level_name: '',
            xp: 0,
            total_badges: earned.length,
            badges: badges.slice(0, 12), // Show first 12
          });
        }
      }
    } catch (err) {
      logError('Failed to load profile', err);
      setError('Failed to load profile. Please try again.');
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
      const response = await api.get<Review[]>(`/v2/users/${profileId}/reviews`);

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
          toast.success('Request sent', 'Connection request sent successfully');
        } else {
          toast.error('Failed', response.error || 'Failed to send request');
        }
      } else if (connectionStatus === 'pending_received' && connectionId) {
        // Accept connection request
        const response = await api.post(`/v2/connections/${connectionId}/accept`);
        if (response.success) {
          setConnectionStatus('connected');
          toast.success('Connected', 'You are now connected');
        } else {
          toast.error('Failed', response.error || 'Failed to accept request');
        }
      } else if ((connectionStatus === 'pending_sent' || connectionStatus === 'connected') && connectionId) {
        // Cancel/Remove connection
        const response = await api.delete(`/v2/connections/${connectionId}`);
        if (response.success) {
          setConnectionStatus('none');
          setConnectionId(null);
          toast.info('Removed', 'Connection removed');
        } else {
          toast.error('Failed', response.error || 'Failed to remove connection');
        }
      }
    } catch (error) {
      logError('Connection action failed', error);
      toast.error('Error', 'Something went wrong');
    } finally {
      setIsConnecting(false);
    }
  }, [profile?.id, connectionStatus, connectionId, toast]);

  if (isLoading) {
    return <LoadingScreen message="Loading profile..." />;
  }

  // Error state with retry
  if (error && !profile) {
    return (
      <div className="max-w-4xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Profile</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath('/members')}>
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
              >
                Browse Members
              </Button>
            </Link>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadProfile()}
            >
              Try Again
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
        title="Profile not found"
        description="This user profile does not exist or has been removed"
        action={
          <Link to={tenantPath('/members')}>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
            >
              Browse Members
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
                  Lvl {profile.level}
                </div>
              )}
            </div>

            {/* Info */}
            <div className="flex-1 text-center sm:text-left">
              <div className="flex flex-col sm:flex-row items-center sm:items-start gap-2">
                <h1 className="text-2xl sm:text-3xl font-bold text-theme-primary">{profile.name}</h1>
                {/* Connected chip for other users */}
                {!isOwnProfile && connectionStatus === 'connected' && (
                  <Chip
                    color="success"
                    variant="flat"
                    size="sm"
                    startContent={<UserCheck className="w-3 h-3" />}
                    className="mt-1"
                  >
                    Connected
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
                    Joined{' '}
                    <time dateTime={profile.created_at}>
                      {new Date(profile.created_at).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                    </time>
                  </span>
                )}
                {profile.rating && (
                  <span className="flex items-center gap-1" aria-label={`Rating: ${profile.rating.toFixed(1)} out of 5`}>
                    <Star className="w-4 h-4 text-amber-400" aria-hidden="true" />
                    <span aria-hidden="true">{profile.rating.toFixed(1)}</span>
                  </span>
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
                        Edit Profile
                      </Button>
                    </Link>
                    <Link to={tenantPath('/settings')}>
                      <Button
                        variant="flat"
                        className="bg-theme-elevated text-theme-primary"
                        startContent={<Settings className="w-4 h-4" aria-hidden="true" />}
                      >
                        Settings
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
                        Send Message
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
                          ? 'Pending'
                          : connectionStatus === 'pending_received'
                          ? 'Accept'
                          : 'Connect'}
                      </Button>
                    )}
                    {/* Send credits */}
                    {isAuthenticated && (
                      <Link to={tenantPath(`/wallet?to=${profile.id}`)}>
                        <Button
                          variant="flat"
                          className="bg-emerald-500/20 text-emerald-600 dark:text-emerald-400"
                          startContent={<ArrowUpRight className="w-4 h-4" aria-hidden="true" />}
                        >
                          Send Credits
                        </Button>
                      </Link>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        </GlassCard>
      </motion.div>

      {/* Stats Cards Row */}
      <motion.div variants={itemVariants} className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <ProfileStatCard
          icon={<ArrowUpRight className="w-5 h-5" aria-hidden="true" />}
          label="Hours Given"
          value={profile.total_hours_given ?? 0}
          color="emerald"
        />
        <ProfileStatCard
          icon={<ArrowDownLeft className="w-5 h-5" aria-hidden="true" />}
          label="Hours Received"
          value={profile.total_hours_received ?? 0}
          color="indigo"
        />
        <ProfileStatCard
          icon={<ListTodo className="w-5 h-5" aria-hidden="true" />}
          label="Active Listings"
          value={listings.length}
          color="purple"
        />
        <ProfileStatCard
          icon={<Users className="w-5 h-5" aria-hidden="true" />}
          label="Groups"
          value={((profile as unknown as Record<string, unknown>).groups_count as number) ?? 0}
          color="amber"
        />
        <ProfileStatCard
          icon={<CalendarCheck className="w-5 h-5" aria-hidden="true" />}
          label="Events"
          value={((profile as unknown as Record<string, unknown>).events_attended as number) ?? 0}
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
                About
              </span>
            }
          />
          <Tab
            key="listings"
            aria-label="User listings"
            title={
              <span className="flex items-center gap-2">
                <ListTodo className="w-4 h-4" aria-hidden="true" />
                Listings
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
                  Reviews
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
                  Achievements
                </span>
              }
            />
          )}
        </Tabs>

        <div className="mt-6">
          {activeTab === 'about' && (
            <GlassCard className="p-6">
              <h2 className="text-lg font-semibold text-theme-primary mb-4">About</h2>
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
                <p className="text-theme-subtle italic">No bio added yet.</p>
              )}

              {profile.skills && profile.skills.length > 0 && (
                <div className="mt-6">
                  <h3 className="text-sm font-medium text-theme-muted mb-3">Skills</h3>
                  <div className="flex flex-wrap gap-2">
                    {profile.skills.map((skill, index) => (
                      <Chip
                        key={index}
                        variant="flat"
                        size="sm"
                        className="bg-indigo-500/20 text-indigo-600 dark:text-indigo-300"
                      >
                        {skill}
                      </Chip>
                    ))}
                  </div>
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
                      <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full">
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
                            {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                          </Chip>
                        </div>
                        <h3 className="font-medium text-theme-primary mb-1">{listing.title}</h3>
                        <p className="text-sm text-theme-subtle line-clamp-2">{listing.description}</p>
                        <div className="flex items-center gap-2 mt-3 text-xs text-theme-subtle">
                          <Clock className="w-3 h-3" aria-hidden="true" />
                          {listing.hours_estimate ?? listing.estimated_hours ?? '\u2014'}h
                        </div>
                      </GlassCard>
                    </article>
                  </Link>
                ))
              ) : (
                <div className="col-span-2">
                  <EmptyState
                    icon={<ListTodo className="w-12 h-12" aria-hidden="true" />}
                    title="No listings"
                    description={isOwnProfile ? "You haven't created any listings yet" : "This user hasn't created any listings"}
                    action={
                      isOwnProfile && (
                        <Link to={tenantPath('/listings/create')}>
                          <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                            Create Listing
                          </Button>
                        </Link>
                      )
                    }
                  />
                </div>
              )}
            </div>
          )}

          {/* Reviews Tab */}
          {activeTab === 'reviews' && (
            <div className="space-y-4">
              {isLoadingReviews ? (
                // Loading skeleton for reviews
                <div className="space-y-3">
                  {[1, 2, 3].map((i) => (
                    <GlassCard key={i} className="p-5 animate-pulse">
                      <div className="flex items-start gap-4">
                        <div className="w-10 h-10 rounded-full bg-theme-hover flex-shrink-0" />
                        <div className="flex-1">
                          <div className="h-4 bg-theme-hover rounded w-1/4 mb-2" />
                          <div className="h-3 bg-theme-hover rounded w-1/5 mb-3" />
                          <div className="h-3 bg-theme-hover rounded w-3/4" />
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
                            {reviews.length} review{reviews.length !== 1 ? 's' : ''}
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
                    title="No reviews yet"
                    description={
                      isOwnProfile
                        ? 'Complete exchanges to receive reviews from other members'
                        : 'This member has not received any reviews yet'
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
                            {gamification.total_badges} Badge{gamification.total_badges !== 1 ? 's' : ''} Earned
                          </p>
                          {profile.level && (
                            <p className="text-xs text-theme-subtle">Level {profile.level}</p>
                          )}
                        </div>
                      </div>
                      {isOwnProfile && (
                        <Link to={tenantPath('/achievements')}>
                          <Button size="sm" variant="flat" className="bg-theme-elevated text-theme-muted">
                            View All
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
                          <span className="inline-block mt-2 text-xs text-emerald-500 font-medium">Earned</span>
                        ) : (
                          <span className="inline-flex items-center gap-1 mt-2 text-xs text-theme-subtle">
                            <Lock className="w-3 h-3" aria-hidden="true" /> Locked
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
                    title="No achievements yet"
                    description={isOwnProfile ? "Start participating to earn badges and achievements!" : "This user hasn't earned any achievements yet"}
                    action={isOwnProfile && (
                      <Link to={tenantPath('/achievements')}>
                        <Button className="bg-gradient-to-r from-amber-500 to-orange-600 text-white">
                          View Available Badges
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
  const reviewerName = review.reviewer
    ? `${review.reviewer.first_name} ${review.reviewer.last_name}`.trim()
    : 'Anonymous';

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
                  For: {review.listing_title}
                </p>
              )}
            </div>
            <time
              dateTime={review.created_at}
              className="text-xs text-theme-subtle flex-shrink-0"
            >
              {new Date(review.created_at).toLocaleDateString('en-US', {
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
