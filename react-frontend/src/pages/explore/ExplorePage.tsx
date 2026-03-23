// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Card,
  CardBody,
  Button,
  Avatar,
  Chip,
  Input,
  Skeleton,
} from '@heroui/react';
import {
  Search,
  Users,
  ArrowRightLeft,
  Clock,
  ListTodo,
  Eye,
  Bookmark,
  MapPin,
  Calendar,
  Crown,
  Trophy,
  Hash,
  UserPlus,
  Lightbulb,
  Heart,
  MessageSquare,
  TrendingUp,
  AlertCircle,
  RefreshCw,
} from 'lucide-react';
import { motion } from 'framer-motion';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useTenant, useAuth } from '@/contexts';
import { resolveAvatarUrl } from '@/lib/helpers';
import { ExploreSection, ExploreStatCard, HorizontalScroll } from '@/components/explore';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface TrendingPost {
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

interface PopularListing {
  id: number;
  title: string;
  type: string;
  image_url: string | null;
  location: string | null;
  estimated_hours: number | null;
  created_at: string;
  view_count: number;
  save_count: number;
  category_name: string;
  category_slug: string;
  category_color: string | null;
  author_name: string;
  author_avatar: string | null;
}

interface ActiveGroup {
  id: number;
  name: string;
  description: string | null;
  image_url: string | null;
  privacy: string;
  member_count: number;
  created_at: string;
}

interface UpcomingEvent {
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

interface TopContributor {
  id: number;
  name: string;
  avatar: string | null;
  xp: number;
  level: number;
  tagline: string | null;
}

interface TrendingHashtag {
  id: number;
  tag: string;
  post_count: number;
  last_used_at: string | null;
}

interface NewMember {
  id: number;
  name: string;
  avatar: string | null;
  tagline: string | null;
  created_at: string;
}

interface FeaturedChallenge {
  id: number;
  title: string;
  description: string | null;
  status: string;
  start_date: string | null;
  end_date: string | null;
  idea_count: number;
}

interface RecommendedListing {
  id: number;
  title: string;
  type: string;
  image_url: string | null;
  location: string | null;
  category_name: string;
  category_slug: string;
  author_name: string;
  author_avatar: string | null;
  match_reason: string | null;
}

interface CommunityStats {
  total_members: number;
  exchanges_this_month: number;
  hours_exchanged: number;
  active_listings: number;
}

interface ExploreData {
  trending_posts: TrendingPost[];
  popular_listings: PopularListing[];
  active_groups: ActiveGroup[];
  upcoming_events: UpcomingEvent[];
  top_contributors: TopContributor[];
  trending_hashtags: TrendingHashtag[];
  new_members: NewMember[];
  featured_challenges: FeaturedChallenge[];
  community_stats: CommunityStats;
  recommended_listings: RecommendedListing[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function ExplorePage() {
  const { t } = useTranslation('explore');
  usePageTitle(t('page_title'));

  const navigate = useNavigate();
  const { tenantPath, hasFeature } = useTenant();
  const { isAuthenticated } = useAuth();
  const [searchQuery, setSearchQuery] = useState('');

  const { data, isLoading, error, execute: retry } = useApi<ExploreData>('/v2/explore');

  // Fetch categories for quick-filter chips
  const { data: categories } = useApi<Array<{ id: number; name: string; slug: string; color?: string }>>(
    '/v2/categories?type=listing'
  );

  const handleSearch = () => {
    if (searchQuery.trim()) {
      navigate(tenantPath(`/search?q=${encodeURIComponent(searchQuery.trim())}`));
    }
  };

  const stats = data?.community_stats;

  // Format a date to a short readable format
  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
  };

  const formatDateTime = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  };

  // Memoize time-ago for trending posts
  const timeAgo = useCallback((dateStr: string) => {
    const now = new Date();
    const date = new Date(dateStr);
    const diffMs = now.getTime() - date.getTime();
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
    if (diffHours < 1) return t('time_ago.just_now');
    if (diffHours < 24) return t('time_ago.hours_ago', { count: diffHours });
    const diffDays = Math.floor(diffHours / 24);
    if (diffDays < 7) return t('time_ago.days_ago', { count: diffDays });
    return formatDate(dateStr);
  }, [t]);

  return (
    <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

      {/* ─── Hero Search Bar ──────────────────────────────────────────────── */}
      <motion.div
        initial={{ opacity: 0, y: -10 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4 }}
        className="text-center mb-8"
      >
        <h1 className="text-3xl sm:text-4xl font-bold text-[var(--text-primary)] mb-2">
          {t('heading')}
        </h1>
        <p className="text-[var(--text-muted)] mb-6 max-w-xl mx-auto">
          {t('subtitle')}
        </p>

        <form
          onSubmit={(e) => { e.preventDefault(); handleSearch(); }}
          className="max-w-2xl mx-auto mb-4 flex gap-2"
        >
          <Input
            size="lg"
            variant="bordered"
            placeholder={t('search_placeholder')}
            value={searchQuery}
            onValueChange={setSearchQuery}
            startContent={<Search className="w-5 h-5 text-[var(--text-muted)]" aria-hidden="true" />}
            classNames={{
              inputWrapper: 'bg-[var(--glass-bg)] border-[var(--glass-border)] backdrop-blur-md',
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter') handleSearch();
            }}
          />
          <Button
            type="submit"
            color="primary"
            size="lg"
            className="shrink-0"
            aria-label={t('search_placeholder')}
          >
            <Search className="w-5 h-5" aria-hidden="true" />
          </Button>
        </form>

        {/* Category quick-filter chips */}
        {categories && categories.length > 0 && (
          <div className="flex flex-wrap justify-center gap-2">
            {categories.slice(0, 8).map((cat) => (
              <Chip
                key={cat.id}
                variant="flat"
                size="sm"
                className="cursor-pointer hover:bg-[var(--surface-hover)] transition-colors"
                style={cat.color ? { borderColor: cat.color } : undefined}
                onClick={() => navigate(tenantPath(`/listings?category=${cat.slug}`))}
              >
                {cat.name}
              </Chip>
            ))}
          </div>
        )}
      </motion.div>

      {/* ─── API Error Banner ─────────────────────────────────────────────── */}
      {error && !isLoading && (
        <div className="mb-6 flex items-center gap-3 px-4 py-3 rounded-xl bg-danger-50 border border-danger-200 text-danger-700 dark:bg-danger-900/20 dark:border-danger-800 dark:text-danger-400">
          <AlertCircle className="w-5 h-5 shrink-0" aria-hidden="true" />
          <span className="text-sm flex-1">{t('error_loading')}</span>
          <Button size="sm" variant="flat" color="danger" onPress={() => retry()} startContent={<RefreshCw className="w-4 h-4" />}>
            {t('retry')}
          </Button>
        </div>
      )}

      {/* ─── Community Stats Banner ───────────────────────────────────────── */}
      <motion.div
        initial={{ opacity: 0, scale: 0.98 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ duration: 0.5, delay: 0.1 }}
        className="mb-10"
      >
        <Card
          className="border border-[var(--glass-border)] bg-[var(--glass-bg)] backdrop-blur-[var(--glass-blur)]"
          style={{ backdropFilter: `blur(var(--glass-blur)) saturate(var(--glass-saturate))` }}
        >
          <CardBody className="p-0">
            <div className="grid grid-cols-2 sm:grid-cols-4 divide-x divide-[var(--border-default)]">
              {isLoading ? (
                Array.from({ length: 4 }).map((_, i) => (
                  <div key={i} className="flex flex-col items-center gap-2 p-5">
                    <Skeleton className="w-10 h-10 rounded-xl" />
                    <Skeleton className="w-16 h-7 rounded" />
                    <Skeleton className="w-20 h-4 rounded" />
                  </div>
                ))
              ) : (
                <>
                  <ExploreStatCard icon={Users} label={t('stats.members')} value={stats?.total_members ?? 0} />
                  <ExploreStatCard icon={ArrowRightLeft} label={t('stats.exchanges_this_month')} value={stats?.exchanges_this_month ?? 0} />
                  <ExploreStatCard icon={Clock} label={t('stats.hours_exchanged')} value={stats?.hours_exchanged ?? 0} suffix="h" />
                  <ExploreStatCard icon={ListTodo} label={t('stats.active_listings')} value={stats?.active_listings ?? 0} />
                </>
              )}
            </div>
          </CardBody>
        </Card>
      </motion.div>

      {/* ─── Trending Posts ────────────────────────────────────────────────── */}
      {(isLoading || (data?.trending_posts && data.trending_posts.length > 0)) && (
        <ExploreSection
          title={t('trending_posts.title')}
          subtitle={t('trending_posts.subtitle')}
          seeAllLink={tenantPath('/feed')}
        >
          {isLoading ? (
            <div className="flex gap-4">
              {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i} className="min-w-[280px] snap-start">
                  <CardBody className="p-4 gap-3">
                    <div className="flex items-center gap-2">
                      <Skeleton className="w-8 h-8 rounded-full" />
                      <Skeleton className="w-24 h-4 rounded" />
                    </div>
                    <Skeleton className="w-full h-12 rounded" />
                    <Skeleton className="w-20 h-4 rounded" />
                  </CardBody>
                </Card>
              ))}
            </div>
          ) : (
            <HorizontalScroll>
              {data!.trending_posts.map((post) => (
                <Link
                  key={post.id}
                  to={tenantPath(`/feed?post=${post.id}`)}
                  className="min-w-[280px] max-w-[320px] snap-start shrink-0"
                >
                  <Card className="h-full border border-[var(--card-border)] bg-[var(--card-bg)] hover:bg-[var(--card-hover-bg)] transition-colors">
                    <CardBody className="p-4 gap-3">
                      <div className="flex items-center gap-2">
                        <Avatar
                          src={resolveAvatarUrl(post.author_avatar)}
                          size="sm"
                          name={post.author_name}
                          className="shrink-0"
                        />
                        <div className="min-w-0">
                          <p className="text-sm font-medium text-[var(--text-primary)] truncate">
                            {post.author_name}
                          </p>
                          <p className="text-xs text-[var(--text-muted)]">
                            {timeAgo(post.created_at)}
                          </p>
                        </div>
                      </div>
                      <p className="text-sm text-[var(--text-secondary)] line-clamp-3">
                        {post.excerpt}
                      </p>
                      <div className="flex items-center gap-3 text-xs text-[var(--text-muted)]">
                        <span className="flex items-center gap-1">
                          <Heart className="w-3.5 h-3.5" />
                          {post.likes_count}
                        </span>
                        <span className="flex items-center gap-1">
                          <MessageSquare className="w-3.5 h-3.5" />
                          {post.comments_count}
                        </span>
                      </div>
                    </CardBody>
                  </Card>
                </Link>
              ))}
            </HorizontalScroll>
          )}
        </ExploreSection>
      )}

      {/* ─── Popular Listings Grid ────────────────────────────────────────── */}
      {(isLoading || (data?.popular_listings && data.popular_listings.length > 0)) && (
        <ExploreSection
          title={t('popular_listings.title')}
          subtitle={t('popular_listings.subtitle')}
          seeAllLink={tenantPath('/listings?sort=popular')}
        >
          {isLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i}>
                  <CardBody className="p-4 gap-3">
                    <Skeleton className="w-full h-32 rounded-lg" />
                    <Skeleton className="w-3/4 h-5 rounded" />
                    <Skeleton className="w-1/2 h-4 rounded" />
                  </CardBody>
                </Card>
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              {data!.popular_listings.map((listing) => (
                <Link key={listing.id} to={tenantPath(`/listings/${listing.id}`)}>
                  <Card className="h-full border border-[var(--card-border)] bg-[var(--card-bg)] hover:bg-[var(--card-hover-bg)] transition-colors">
                    <CardBody className="p-4 gap-3">
                      {listing.image_url ? (
                        <img
                          src={listing.image_url}
                          alt={listing.title}
                          className="w-full h-32 object-cover rounded-lg"
                          loading="lazy"
                        />
                      ) : (
                        <div className="w-full h-32 rounded-lg bg-[var(--surface-elevated)] flex items-center justify-center">
                          <ListTodo className="w-8 h-8 text-[var(--text-subtle)]" />
                        </div>
                      )}
                      <div>
                        <div className="flex items-center gap-2 mb-1">
                          {listing.category_name && (
                            <Chip
                              size="sm"
                              variant="flat"
                              className="text-xs"
                              style={listing.category_color ? { backgroundColor: `${listing.category_color}20`, color: listing.category_color } : undefined}
                            >
                              {listing.category_name}
                            </Chip>
                          )}
                          <Chip size="sm" variant="flat" className="text-xs capitalize">
                            {listing.type}
                          </Chip>
                        </div>
                        <h3 className="text-sm font-semibold text-[var(--text-primary)] line-clamp-2">
                          {listing.title}
                        </h3>
                      </div>
                      <div className="flex items-center justify-between text-xs text-[var(--text-muted)]">
                        <span className="flex items-center gap-1">
                          <Eye className="w-3.5 h-3.5" />
                          {listing.view_count}
                        </span>
                        <span className="flex items-center gap-1">
                          <Bookmark className="w-3.5 h-3.5" />
                          {listing.save_count}
                        </span>
                        {listing.location && (
                          <span className="flex items-center gap-1 truncate max-w-[80px]">
                            <MapPin className="w-3.5 h-3.5 shrink-0" />
                            <span className="truncate">{listing.location}</span>
                          </span>
                        )}
                      </div>
                    </CardBody>
                  </Card>
                </Link>
              ))}
            </div>
          )}
        </ExploreSection>
      )}

      {/* ─── Upcoming Events ──────────────────────────────────────────────── */}
      {hasFeature('events') && (isLoading || (data?.upcoming_events && data.upcoming_events.length > 0)) && (
        <ExploreSection
          title={t('upcoming_events.title')}
          subtitle={t('upcoming_events.subtitle')}
          seeAllLink={tenantPath('/events')}
        >
          {isLoading ? (
            <div className="flex gap-4">
              {Array.from({ length: 4 }).map((_, i) => (
                <Card key={i} className="min-w-[260px]">
                  <CardBody className="p-4 gap-3">
                    <Skeleton className="w-full h-28 rounded-lg" />
                    <Skeleton className="w-3/4 h-5 rounded" />
                    <Skeleton className="w-1/2 h-4 rounded" />
                  </CardBody>
                </Card>
              ))}
            </div>
          ) : (
            <HorizontalScroll>
              {data!.upcoming_events.map((event) => (
                <Link
                  key={event.id}
                  to={tenantPath(`/events/${event.id}`)}
                  className="min-w-[260px] max-w-[300px] snap-start shrink-0"
                >
                  <Card className="h-full border border-[var(--card-border)] bg-[var(--card-bg)] hover:bg-[var(--card-hover-bg)] transition-colors">
                    <CardBody className="p-4 gap-3">
                      {event.image_url ? (
                        <img
                          src={event.image_url}
                          alt={event.title}
                          className="w-full h-28 object-cover rounded-lg"
                          loading="lazy"
                        />
                      ) : (
                        <div className="w-full h-28 rounded-lg bg-gradient-to-br from-[var(--color-primary)]/10 to-[var(--color-secondary)]/10 flex items-center justify-center">
                          <Calendar className="w-8 h-8 text-[var(--color-primary)]" />
                        </div>
                      )}
                      <h3 className="text-sm font-semibold text-[var(--text-primary)] line-clamp-2">
                        {event.title}
                      </h3>
                      <div className="flex flex-col gap-1 text-xs text-[var(--text-muted)]">
                        <span className="flex items-center gap-1">
                          <Calendar className="w-3.5 h-3.5 text-[var(--color-primary)]" />
                          {formatDateTime(event.start_at)}
                        </span>
                        {event.location && (
                          <span className="flex items-center gap-1 truncate">
                            <MapPin className="w-3.5 h-3.5 shrink-0" />
                            <span className="truncate">{event.is_online ? t('upcoming_events.online') : event.location}</span>
                          </span>
                        )}
                        <span className="flex items-center gap-1">
                          <Users className="w-3.5 h-3.5" />
                          {t('upcoming_events.attending', { count: event.rsvp_count })}
                          {event.max_attendees && ` / ${event.max_attendees}`}
                        </span>
                      </div>
                    </CardBody>
                  </Card>
                </Link>
              ))}
            </HorizontalScroll>
          )}
        </ExploreSection>
      )}

      {/* ─── Active Groups ────────────────────────────────────────────────── */}
      {hasFeature('groups') && (isLoading || (data?.active_groups && data.active_groups.length > 0)) && (
        <ExploreSection
          title={t('active_groups.title')}
          subtitle={t('active_groups.subtitle')}
          seeAllLink={tenantPath('/groups')}
        >
          {isLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {Array.from({ length: 3 }).map((_, i) => (
                <Card key={i}>
                  <CardBody className="p-4 gap-3">
                    <Skeleton className="w-12 h-12 rounded-full" />
                    <Skeleton className="w-3/4 h-5 rounded" />
                    <Skeleton className="w-full h-8 rounded" />
                  </CardBody>
                </Card>
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {data!.active_groups.map((group) => (
                <Link key={group.id} to={tenantPath(`/groups/${group.id}`)}>
                  <Card className="h-full border border-[var(--card-border)] bg-[var(--card-bg)] hover:bg-[var(--card-hover-bg)] transition-colors">
                    <CardBody className="p-4 gap-3">
                      <div className="flex items-center gap-3">
                        <Avatar
                          src={group.image_url ? resolveAvatarUrl(group.image_url) : undefined}
                          name={group.name}
                          size="md"
                          className="shrink-0"
                        />
                        <div className="min-w-0 flex-1">
                          <h3 className="text-sm font-semibold text-[var(--text-primary)] truncate">
                            {group.name}
                          </h3>
                          <p className="text-xs text-[var(--text-muted)] flex items-center gap-1">
                            <Users className="w-3 h-3" />
                            {t('active_groups.members', { count: group.member_count })}
                          </p>
                        </div>
                      </div>
                      {group.description && (
                        <p className="text-xs text-[var(--text-secondary)] line-clamp-2">
                          {group.description}
                        </p>
                      )}
                      <Button
                        size="sm"
                        variant="flat"
                        color="primary"
                        className="w-full"
                      >
                        {t('active_groups.view_group')}
                      </Button>
                    </CardBody>
                  </Card>
                </Link>
              ))}
            </div>
          )}
        </ExploreSection>
      )}

      {/* ─── Top Contributors ─────────────────────────────────────────────── */}
      {hasFeature('gamification') && (isLoading || (data?.top_contributors && data.top_contributors.length > 0)) && (
        <ExploreSection
          title={t('top_contributors.title')}
          subtitle={t('top_contributors.subtitle')}
          seeAllLink={tenantPath('/leaderboard')}
        >
          {isLoading ? (
            <div className="flex gap-4">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex flex-col items-center gap-2 min-w-[100px]">
                  <Skeleton className="w-16 h-16 rounded-full" />
                  <Skeleton className="w-16 h-4 rounded" />
                </div>
              ))}
            </div>
          ) : (
            <HorizontalScroll>
              {data!.top_contributors.map((user, index) => (
                <Link
                  key={user.id}
                  to={tenantPath(`/profile/${user.id}`)}
                  className="flex flex-col items-center gap-2 min-w-[110px] snap-start shrink-0 p-2 rounded-xl hover:bg-[var(--surface-hover)] transition-colors"
                >
                  <div className="relative">
                    <Avatar
                      src={resolveAvatarUrl(user.avatar)}
                      name={user.name}
                      size="lg"
                      className="w-16 h-16"
                    />
                    {index === 0 && (
                      <Crown className="w-5 h-5 text-amber-400 absolute -top-1.5 -right-1.5 drop-shadow" />
                    )}
                    <div className="absolute -bottom-1 left-1/2 -translate-x-1/2">
                      <Chip size="sm" variant="solid" color="primary" className="text-[10px] px-1.5 h-5 min-h-5">
                        {t('top_contributors.level', { level: user.level })}
                      </Chip>
                    </div>
                  </div>
                  <span className="text-xs font-medium text-[var(--text-primary)] text-center truncate max-w-[100px]">
                    {user.name}
                  </span>
                  <span className="text-[10px] text-[var(--text-muted)] flex items-center gap-0.5">
                    <Trophy className="w-3 h-3" />
                    {user.xp.toLocaleString()} XP
                  </span>
                </Link>
              ))}
            </HorizontalScroll>
          )}
        </ExploreSection>
      )}

      {/* ─── Trending Hashtags ────────────────────────────────────────────── */}
      {(isLoading || (data?.trending_hashtags && data.trending_hashtags.length > 0)) && (
        <ExploreSection
          title={t('trending_hashtags.title')}
          subtitle={t('trending_hashtags.subtitle')}
          seeAllLink={tenantPath('/feed/hashtags')}
        >
          {isLoading ? (
            <div className="flex flex-wrap gap-2">
              {Array.from({ length: 8 }).map((_, i) => (
                <Skeleton key={i} className="w-24 h-8 rounded-full" />
              ))}
            </div>
          ) : (
            <div className="flex flex-wrap gap-2">
              {data!.trending_hashtags.map((hashtag) => (
                <Link key={hashtag.id} to={tenantPath(`/feed/hashtag/${hashtag.tag}`)}>
                  <Chip
                    variant="flat"
                    size="md"
                    startContent={<Hash className="w-3.5 h-3.5" />}
                    className="cursor-pointer hover:bg-[var(--surface-hover)] transition-colors"
                  >
                    {hashtag.tag}
                    <span className="ml-1 text-[var(--text-muted)] text-xs">
                      ({hashtag.post_count})
                    </span>
                  </Chip>
                </Link>
              ))}
            </div>
          )}
        </ExploreSection>
      )}

      {/* ─── Recommended For You ──────────────────────────────────────────── */}
      {isAuthenticated && data?.recommended_listings && data.recommended_listings.length > 0 && (
        <ExploreSection
          title={t('recommended.title')}
          subtitle={t('recommended.subtitle')}
          seeAllLink={tenantPath('/matches')}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {data.recommended_listings.slice(0, 6).map((listing) => (
              <Link key={listing.id} to={tenantPath(`/listings/${listing.id}`)}>
                <Card className="h-full border border-[var(--card-border)] bg-[var(--card-bg)] hover:bg-[var(--card-hover-bg)] transition-colors">
                  <CardBody className="p-4 gap-3">
                    {listing.match_reason && (
                      <Chip size="sm" variant="flat" color="secondary" className="text-xs">
                        <TrendingUp className="w-3 h-3 mr-1 inline" />
                        {listing.match_reason}
                      </Chip>
                    )}
                    <div className="flex items-start gap-3">
                      <Avatar
                        src={resolveAvatarUrl(listing.author_avatar)}
                        name={listing.author_name}
                        size="sm"
                        className="shrink-0 mt-0.5"
                      />
                      <div className="min-w-0">
                        <h3 className="text-sm font-semibold text-[var(--text-primary)] line-clamp-2">
                          {listing.title}
                        </h3>
                        <p className="text-xs text-[var(--text-muted)]">
                          {t('recommended.by_author', { name: listing.author_name })}
                        </p>
                        {listing.category_name && (
                          <Chip size="sm" variant="flat" className="text-[10px] mt-1">
                            {listing.category_name}
                          </Chip>
                        )}
                      </div>
                    </div>
                  </CardBody>
                </Card>
              </Link>
            ))}
          </div>
        </ExploreSection>
      )}

      {/* ─── New Members ──────────────────────────────────────────────────── */}
      {(isLoading || (data?.new_members && data.new_members.length > 0)) && (
        <ExploreSection
          title={t('new_members.title')}
          subtitle={t('new_members.subtitle')}
          seeAllLink={tenantPath('/members')}
        >
          {isLoading ? (
            <div className="flex gap-4">
              {Array.from({ length: 6 }).map((_, i) => (
                <div key={i} className="flex flex-col items-center gap-2 min-w-[100px]">
                  <Skeleton className="w-14 h-14 rounded-full" />
                  <Skeleton className="w-16 h-4 rounded" />
                </div>
              ))}
            </div>
          ) : (
            <HorizontalScroll>
              {data!.new_members.map((member) => (
                <div
                  key={member.id}
                  className="flex flex-col items-center gap-2 min-w-[120px] snap-start shrink-0 p-3"
                >
                  <Link to={tenantPath(`/profile/${member.id}`)}>
                    <Avatar
                      src={resolveAvatarUrl(member.avatar)}
                      name={member.name}
                      size="lg"
                      className="w-14 h-14"
                    />
                  </Link>
                  <Link
                    to={tenantPath(`/profile/${member.id}`)}
                    className="text-xs font-medium text-[var(--text-primary)] text-center truncate max-w-[110px] hover:underline"
                  >
                    {member.name}
                  </Link>
                  {member.tagline && (
                    <p className="text-[10px] text-[var(--text-muted)] text-center truncate max-w-[110px]">
                      {member.tagline}
                    </p>
                  )}
                  {isAuthenticated && (
                    <Button
                      size="sm"
                      variant="flat"
                      color="primary"
                      className="text-xs h-7 px-3"
                      startContent={<UserPlus className="w-3 h-3" />}
                      onPress={() => navigate(tenantPath(`/messages/new/${member.id}`))}
                    >
                      {t('new_members.connect')}
                    </Button>
                  )}
                </div>
              ))}
            </HorizontalScroll>
          )}
        </ExploreSection>
      )}

      {/* ─── Featured Challenges ──────────────────────────────────────────── */}
      {hasFeature('ideation_challenges') && data?.featured_challenges && data.featured_challenges.length > 0 && (
        <ExploreSection
          title={t('featured_challenges.title')}
          subtitle={t('featured_challenges.subtitle')}
          seeAllLink={tenantPath('/ideation')}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {data.featured_challenges.map((challenge) => (
              <Link key={challenge.id} to={tenantPath(`/ideation/${challenge.id}`)}>
                <Card className="h-full border border-[var(--card-border)] bg-[var(--card-bg)] hover:bg-[var(--card-hover-bg)] transition-colors">
                  <CardBody className="p-4 gap-3">
                    <div className="flex items-start gap-3">
                      <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-[var(--color-secondary)]/10 shrink-0">
                        <Lightbulb className="w-5 h-5 text-[var(--color-secondary)]" />
                      </div>
                      <div className="min-w-0 flex-1">
                        <h3 className="text-sm font-semibold text-[var(--text-primary)] line-clamp-1">
                          {challenge.title}
                        </h3>
                        {challenge.description && (
                          <p className="text-xs text-[var(--text-secondary)] line-clamp-2 mt-1">
                            {challenge.description}
                          </p>
                        )}
                        <div className="flex items-center gap-3 mt-2 text-xs text-[var(--text-muted)]">
                          <span className="flex items-center gap-1">
                            <Lightbulb className="w-3 h-3" />
                            {t('featured_challenges.ideas_count', { count: challenge.idea_count })}
                          </span>
                          {challenge.end_date && (
                            <span>
                              {t('featured_challenges.ends', { date: formatDate(challenge.end_date) })}
                            </span>
                          )}
                        </div>
                        {/* Simple progress indicator */}
                        {challenge.end_date && challenge.start_date && (
                          <div className="mt-2">
                            <div className="w-full h-1.5 rounded-full bg-[var(--surface-elevated)]">
                              <div
                                className="h-1.5 rounded-full bg-[var(--color-secondary)]"
                                style={{
                                  width: `${Math.min(100, Math.max(5, ((Date.now() - new Date(challenge.start_date).getTime()) / (new Date(challenge.end_date).getTime() - new Date(challenge.start_date).getTime())) * 100))}%`,
                                }}
                              />
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  </CardBody>
                </Card>
              </Link>
            ))}
          </div>
        </ExploreSection>
      )}
    </div>
  );
}
