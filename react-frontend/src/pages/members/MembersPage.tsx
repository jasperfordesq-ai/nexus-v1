// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Members Page - Community member directory
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Input, Select, SelectItem, Avatar, Button, Chip, Tooltip } from '@heroui/react';
import Search from 'lucide-react/icons/search';
import Users from 'lucide-react/icons/users';
import MapIcon from 'lucide-react/icons/map';
import MapPin from 'lucide-react/icons/map-pin';
import Star from 'lucide-react/icons/star';
import Clock from 'lucide-react/icons/clock';
import Filter from 'lucide-react/icons/filter';
import Grid from 'lucide-react/icons/grid-3x3';
import List from 'lucide-react/icons/list';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Sparkles from 'lucide-react/icons/sparkles';
import TrendingUp from 'lucide-react/icons/trending-up';
import BadgeCheck from 'lucide-react/icons/badge-check';
import UserCircle from 'lucide-react/icons/circle-user';
import { useTranslation } from 'react-i18next';
import { GlassCard, MemberCardSkeleton, AlgorithmLabel, useAlgorithmInfo } from '@/components/ui';
import { PresenceIndicator } from '@/components/social';
import { EntityMapView } from '@/components/location';
import { EmptyState } from '@/components/feedback';
import { PageMeta } from '@/components/seo';
import { useAuth, useToast, useTenant, useFeature } from '@/contexts';
import { usePresenceOptional } from '@/contexts/PresenceContext';
import { api } from '@/lib/api';
import { MAPS_ENABLED } from '@/lib/map-config';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import type { User } from '@/types/api';

type SortOption = 'communityrank' | 'name' | 'joined' | 'rating' | 'hours_given';
type ViewMode = 'grid' | 'list' | 'map';
type QuickFilter = 'all' | 'new' | 'active';

const ITEMS_PER_PAGE = 24;
const SEARCH_DEBOUNCE_MS = 300;

const VALID_SORTS: SortOption[] = ['communityrank', 'name', 'joined', 'rating', 'hours_given'];
const VALID_VIEW_MODES: ViewMode[] = ['grid', 'list', 'map'];

const containerVariants = {
  hidden: { opacity: 0 },
  visible: {
    opacity: 1,
    transition: { staggerChildren: 0.05 },
  },
};

const itemVariants = {
  hidden: { opacity: 0, scale: 0.95 },
  visible: { opacity: 1, scale: 1 },
};

export function MembersPage() {
  const { t } = useTranslation('common');
  usePageTitle(t('members.title'));
  const [searchParams, setSearchParams] = useSearchParams();
  const toast = useToast();

  const [members, setMembers] = useState<User[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [totalCount, setTotalCount] = useState<number | null>(null);

  // Quick filter state
  const [quickFilter, setQuickFilter] = useState<QuickFilter>('all');

  // Search and filter state
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const rawSort = searchParams.get('sort');
  const initialSort: SortOption | null = rawSort && (VALID_SORTS as string[]).includes(rawSort) ? rawSort as SortOption : null;
  const [sortBy, setSortBy] = useState<SortOption | null>(initialSort);
  const storedViewMode = localStorage.getItem('members_view_mode');
  const [viewMode, setViewMode] = useState<ViewMode>(
    storedViewMode && (VALID_VIEW_MODES as string[]).includes(storedViewMode) ? storedViewMode as ViewMode : 'grid'
  );
  const [nearMeEnabled, setNearMeEnabled] = useState(false);
  const [radiusKm, setRadiusKm] = useState(25);
  const { user, isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const membersAlgorithm = useAlgorithmInfo('members');
  const defaultSort: SortOption | null = membersAlgorithm
    ? membersAlgorithm.key === 'communityrank'
      ? 'communityrank'
      : 'name'
    : membersAlgorithm === null
      ? 'name'
      : null;
  const activeSortBy = sortBy ?? defaultSort;
  const isNearbyMode = nearMeEnabled && user?.latitude != null && user?.longitude != null;

  // Refs
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const membersCountRef = useRef(0);
  const loadAbortRef = useRef<AbortController | null>(null);
  const requestIdRef = useRef(0);

  // Debounce search input
  useEffect(() => {
    if (searchTimeoutRef.current) {
      clearTimeout(searchTimeoutRef.current);
    }

    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) {
        clearTimeout(searchTimeoutRef.current);
      }
    };
  }, [searchQuery]);

  // Keep ref in sync for offset calculation without stale closures
  useEffect(() => { membersCountRef.current = members.length; }, [members.length]);

  // Persist view mode
  useEffect(() => {
    localStorage.setItem('members_view_mode', viewMode);
  }, [viewMode]);

  useEffect(() => {
    return () => {
      loadAbortRef.current?.abort();
    };
  }, []);

  // Handle quick filter selection
  const handleQuickFilter = useCallback((filter: QuickFilter) => {
    setQuickFilter(filter);
    if (filter === 'new') {
      setSortBy('joined');
    } else if (filter === 'active') {
      setSortBy('hours_given');
    } else {
      setSortBy(defaultSort ?? 'name');
    }
  }, [defaultSort]);

  // Sync quickFilter when sortBy changes from the Select dropdown
  useEffect(() => {
    if (activeSortBy === 'joined') {
      setQuickFilter('new');
    } else if (activeSortBy === 'hours_given') {
      setQuickFilter('active');
    } else {
      setQuickFilter('all');
    }
  }, [activeSortBy]);

  // Load members
  const loadMembers = useCallback(async (append = false) => {
    if (!activeSortBy) {
      return;
    }

    const requestId = ++requestIdRef.current;
    loadAbortRef.current?.abort();
    const controller = new AbortController();
    loadAbortRef.current = controller;

    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (!isNearbyMode) {
        params.set('sort', activeSortBy);
        // Quick filters imply descending order for joined and hours_given
        if (activeSortBy === 'joined' || activeSortBy === 'hours_given') {
          params.set('order', 'desc');
        }
      }
      params.set('limit', ITEMS_PER_PAGE.toString());

      if (append && membersCountRef.current > 0) {
        params.set('offset', membersCountRef.current.toString());
      }

      let endpoint = '/v2/users';
      if (isNearbyMode) {
        endpoint = '/v2/members/nearby';
        params.set('lat', String(user.latitude));
        params.set('lon', String(user.longitude));
        params.set('radius_km', String(radiusKm));
      }

      const response = await api.get<User[]>(`${endpoint}?${params}`, { signal: controller.signal });

      if (controller.signal.aborted || requestId !== requestIdRef.current) {
        return;
      }

      if (response.success && response.data) {
        if (append) {
          setMembers((prev) => [...prev, ...response.data!]);
        } else {
          setMembers(response.data);
        }
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);

        // Get total count from response meta if available
        if (response.meta?.total_items !== undefined) {
          setTotalCount(response.meta.total_items);
        }
      } else {
        if (!append) {
          setError(t('members.load_failed'));
        } else {
          toast.error(t('members.load_more_failed'));
        }
      }
    } catch (err) {
      if (controller.signal.aborted || requestId !== requestIdRef.current) {
        return;
      }
      logError('Failed to load members', err);
      if (!append) {
        setError(t('members.load_failed'));
      } else {
        toast.error(t('members.load_more_failed'));
      }
    } finally {
      if (requestId === requestIdRef.current) {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    }
  }, [activeSortBy, debouncedQuery, t, toast, isNearbyMode, user?.latitude, user?.longitude, radiusKm]);

  // Load more
  const loadMoreMembers = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadMembers(true);
  }, [isLoadingMore, hasMore, loadMembers]);

  // Presence context for fetching online status
  const presence = usePresenceOptional();

  // Load on mount and when filters change
  useEffect(() => {
    if (!activeSortBy) {
      return;
    }
    loadMembers();
    // Reset pagination state when filters change
    setHasMore(true);
    setTotalCount(null);
  }, [activeSortBy, debouncedQuery, nearMeEnabled, user?.id, user?.latitude, user?.longitude, radiusKm]); // eslint-disable-line react-hooks/exhaustive-deps -- reset pagination on filter change; user.id retries on login/token-refresh

  // Fetch presence for visible members
  useEffect(() => {
    if (members.length > 0 && presence) {
      const userIds = members.map((m) => m.id);
      presence.fetchPresence(userIds);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps -- fetch presence when member list updates; presence excluded (stable ref)
  }, [members]);

  // Update URL params
  useEffect(() => {
    if (!activeSortBy || !defaultSort) {
      return;
    }
    const params = new URLSearchParams();
    if (debouncedQuery) params.set('q', debouncedQuery);
    if (activeSortBy !== defaultSort) params.set('sort', activeSortBy);
    setSearchParams(params, { replace: true });
  }, [activeSortBy, debouncedQuery, defaultSort, setSearchParams]);

  function handleNearMeToggle() {
    if (nearMeEnabled) {
      setNearMeEnabled(false);
      return;
    }
    if (user?.latitude == null || user?.longitude == null) {
      toast.error(t('members.near_me_no_location'));
      return;
    }
    setSortBy(null);  // reset sort when entering nearby mode
    setNearMeEnabled(true);
  }

  return (
    <div className="space-y-5">
      <PageMeta title={t('page_meta.members.title')} description={t('page_meta.members.description')} />
      {/* Hero Banner */}
      <div className="relative overflow-hidden rounded-xl border border-theme-default bg-theme-surface p-5 shadow-sm sm:p-6">
        <div className="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <div className="rounded-lg bg-indigo-500/10 p-2 text-indigo-600 dark:text-indigo-400">
                <Users className="w-5 h-5" aria-hidden="true" />
              </div>
              <h1 className="text-2xl sm:text-3xl font-semibold text-theme-primary">{t('members.title')}</h1>
            </div>
            <div className="flex items-center gap-3 flex-wrap">
              <p className="text-theme-muted text-sm">{t('members.subtitle')}</p>
              {totalCount != null && !isLoading && (
                <span className="inline-flex items-center gap-1.5 rounded-full border border-theme-default bg-theme-elevated px-2.5 py-1 text-xs font-medium text-theme-secondary">
                  <span className="w-1.5 h-1.5 rounded-full bg-indigo-500" aria-hidden="true" />
                  {t('members.showing', { shown: members.length.toLocaleString(), total: totalCount.toLocaleString() })}
                </span>
              )}
              {activeSortBy === 'communityrank' && !isNearbyMode && (
                <AlgorithmLabel area="members" />
              )}
            </div>
          </div>
          {isAuthenticated && user && (
            <Link to={tenantPath(`/profile/${user.id}`)}>
              <Button
                color="primary"
                className="shrink-0 font-semibold shadow-sm"
                startContent={<UserCircle className="w-4 h-4" />}
              >
                {t('members.my_profile')}
              </Button>
            </Link>
          )}
        </div>
      </div>

      {/* Quick Filters */}
      <div className="flex flex-wrap items-center gap-2">
        <Button
          size="sm"
          variant={quickFilter === 'all' ? 'solid' : 'flat'}
          className={
            quickFilter === 'all'
              ? 'bg-linear-to-r from-indigo-500 to-purple-600 text-white'
              : 'bg-theme-elevated text-theme-secondary hover:text-indigo-500 hover:bg-indigo-500/5 transition-colors'
          }
          isDisabled={isNearbyMode}
          startContent={<Users className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={() => handleQuickFilter('all')}
          aria-pressed={quickFilter === 'all'}
        >
          {t('members.all')}
        </Button>
        <Button
          size="sm"
          variant={quickFilter === 'new' ? 'solid' : 'flat'}
          className={
            quickFilter === 'new'
              ? 'bg-linear-to-r from-indigo-500 to-purple-600 text-white'
              : 'bg-theme-elevated text-theme-secondary hover:text-emerald-500 hover:bg-emerald-500/5 transition-colors'
          }
          isDisabled={isNearbyMode}
          startContent={<Sparkles className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={() => handleQuickFilter('new')}
          aria-pressed={quickFilter === 'new'}
        >
          {t('members.new')}
        </Button>
        <Button
          size="sm"
          variant={quickFilter === 'active' ? 'solid' : 'flat'}
          className={
            quickFilter === 'active'
              ? 'bg-linear-to-r from-indigo-500 to-purple-600 text-white'
              : 'bg-theme-elevated text-theme-secondary hover:text-amber-500 hover:bg-amber-500/5 transition-colors'
          }
          isDisabled={isNearbyMode}
          startContent={<TrendingUp className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={() => handleQuickFilter('active')}
          aria-pressed={quickFilter === 'active'}
        >
          {t('members.active')}
        </Button>
      </div>

      {/* Search & Sort Filters */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder={t('members.search_placeholder')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              aria-label={t('members.search_placeholder')}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          <div className="flex gap-3">
            <Select
              placeholder={t('members.sort_by')}
              selectedKeys={activeSortBy ? [activeSortBy] : []}
              disallowEmptySelection
              isDisabled={isNearbyMode}
              onChange={(e) => setSortBy(e.target.value as SortOption)}
              className="w-36 sm:w-44"
              aria-label={t('members.sort_by')}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Filter className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            >
              {membersAlgorithm?.key === 'communityrank' ? (
                <SelectItem key="communityrank">{t('members.sort_communityrank')}</SelectItem>
              ) : null}
              <SelectItem key="name">{t('members.sort_name')}</SelectItem>
              <SelectItem key="joined">{t('members.sort_newest')}</SelectItem>
              <SelectItem key="rating">{t('members.sort_rated')}</SelectItem>
              <SelectItem key="hours_given">{t('members.sort_active')}</SelectItem>
            </Select>

            <Button
              size="sm"
              variant={nearMeEnabled ? 'solid' : 'flat'}
              className={nearMeEnabled
                ? 'bg-primary text-white'
                : 'bg-theme-elevated text-theme-primary'}
              startContent={<MapPin className="w-4 h-4" aria-hidden="true" />}
              onPress={handleNearMeToggle}
              aria-pressed={nearMeEnabled}
              aria-label={t('members.near_me')}
            >
              {t('members.near_me')}
            </Button>

            {nearMeEnabled && (
              <Select
                aria-label={t('members.radius_label')}
                selectedKeys={[String(radiusKm)]}
                disallowEmptySelection
                onSelectionChange={(keys) => {
                  const val = keys instanceof Set ? ([...keys][0] as string) : '25';
                  setRadiusKm(Number(val) || 25);
                }}
                className="w-32"
                classNames={{
                  trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  value: 'text-theme-primary',
                }}
              >
                <SelectItem key="5">{t('radius_5')}</SelectItem>
                <SelectItem key="10">{t('radius_10')}</SelectItem>
                <SelectItem key="25">{t('radius_25')}</SelectItem>
                <SelectItem key="50">{t('radius_50')}</SelectItem>
                <SelectItem key="100">{t('radius_100')}</SelectItem>
              </Select>
            )}

            <div className="flex rounded-xl overflow-hidden border border-theme-default" role="group" aria-label={t('aria.view_mode', 'View mode')}>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className={`rounded-none transition-colors ${viewMode === 'grid' ? 'bg-indigo-500/10 text-indigo-500 dark:text-indigo-400' : 'bg-theme-elevated text-theme-muted'}`}
                aria-label={t('aria.grid_view', 'Grid view')}
                aria-pressed={viewMode === 'grid'}
                onPress={() => setViewMode('grid')}
              >
                <Grid className="w-4 h-4" aria-hidden="true" />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className={`rounded-none transition-colors ${viewMode === 'list' ? 'bg-indigo-500/10 text-indigo-500 dark:text-indigo-400' : 'bg-theme-elevated text-theme-muted'}`}
                aria-label={t('aria.list_view', 'List view')}
                aria-pressed={viewMode === 'list'}
                onPress={() => setViewMode('list')}
              >
                <List className="w-4 h-4" aria-hidden="true" />
              </Button>
              {MAPS_ENABLED && (
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  className={`rounded-none rounded-r-xl transition-colors ${viewMode === 'map' ? 'bg-indigo-500/10 text-indigo-500 dark:text-indigo-400' : 'bg-theme-elevated text-theme-muted'}`}
                  aria-label={t('aria.map_view', 'Map view')}
                  aria-pressed={viewMode === 'map'}
                  onPress={() => setViewMode('map')}
                >
                  <MapIcon className="w-4 h-4" aria-hidden="true" />
                </Button>
              )}
            </div>
          </div>
        </div>
      </GlassCard>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">{t('members.unable_to_load')}</h3>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadMembers()}
          >
            {t('members.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Members Grid/List */}
      {!error && (
        <>
          {isLoading ? (
            <div role="status" className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4' : 'space-y-3'} aria-label={t('aria.loading_members', 'Loading members')} aria-busy="true">
              {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
                <MemberCardSkeleton key={i} />
              ))}
            </div>
          ) : members.length === 0 ? (
            <EmptyState
              icon={<Users className="w-12 h-12" />}
              title={t('members.no_members')}
              description={debouncedQuery ? t('members.no_members_search') : t('members.no_members_community')}
            />
          ) : (
            <>
              {/* Results count with search context */}
              {debouncedQuery && totalCount !== null && (
                <p className="text-sm text-theme-muted">
                  {t('members.results_matching', { shown: members.length.toLocaleString(), total: totalCount.toLocaleString(), query: debouncedQuery })}
                </p>
              )}

              {viewMode === 'map' ? (
                <EntityMapView
                  items={members}
                  getCoordinates={(m) =>
                    m.latitude != null && m.longitude != null
                      ? { lat: Number(m.latitude), lng: Number(m.longitude) }
                      : null
                  }
                  getMarkerConfig={(m) => ({
                    id: m.id,
                    title: m.name?.trim() || `${m.first_name || ''} ${m.last_name || ''}`.trim() || t('members.fallback_name'),
                  })}
                  renderInfoContent={(m) => (
                    <div className="p-2 max-w-[200px]">
                      <div className="flex items-center gap-2">
                        {(m.avatar || m.avatar_url) && (
                          <img src={resolveAvatarUrl(m.avatar ?? m.avatar_url) || undefined} alt={t('members.avatar_alt', { name: m.name || `${m.first_name || ''} ${m.last_name || ''}`.trim() || t('members.fallback_name') })} className="w-8 h-8 rounded-full" width={32} height={32} loading="lazy" />
                        )}
                        <div>
                          <h4 className="font-semibold text-sm text-theme-primary">
                            {m.name || `${m.first_name || ''} ${m.last_name || ''}`.trim()}
                          </h4>
                          {m.tagline && (
                            <p className="text-xs text-theme-muted">{m.tagline}</p>
                          )}
                        </div>
                      </div>
                    </div>
                  )}
                  isLoading={isLoading}
                  emptyMessage={t('members.no_location')}
                />
              ) : (
                <motion.div
                  key={`${debouncedQuery}-${activeSortBy}-${quickFilter}`}
                  variants={containerVariants}
                  initial="hidden"
                  animate="visible"
                  className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4' : 'space-y-3'}
                >
                  {members.map((member) => (
                    <motion.div key={member.id} variants={itemVariants}>
                      <MemberCard member={member} viewMode={viewMode} sortBy={sortBy ?? undefined} />
                    </motion.div>
                  ))}
                </motion.div>
              )}

              {/* Load More with progress */}
              {hasMore && (
                <div className="space-y-3 pt-4">
                  {totalCount != null && totalCount > 0 && (
                    <div className="space-y-1.5">
                      <div className="flex justify-between text-xs text-theme-muted px-1">
                        <span>{members.length.toLocaleString()} / {totalCount.toLocaleString()}</span>
                        <span className="font-medium text-theme-secondary">{Math.round((members.length / totalCount) * 100)}%</span>
                      </div>
                      <div className="h-1.5 rounded-full bg-theme-elevated overflow-hidden">
                        <motion.div
                          className="h-full rounded-full bg-linear-to-r from-indigo-500 to-purple-600"
                          initial={{ width: '0%' }}
                          animate={{ width: `${Math.round((members.length / totalCount) * 100)}%` }}
                          transition={{ duration: 0.6, ease: 'easeOut' }}
                        />
                      </div>
                    </div>
                  )}
                  <div className="text-center">
                    <Button
                      variant="flat"
                      className="bg-theme-elevated text-theme-muted hover:bg-theme-hover"
                      onPress={loadMoreMembers}
                      isLoading={isLoadingMore}
                    >
                      {totalCount != null && totalCount > members.length
                        ? t('members.load_more_count', { remaining: (totalCount - members.length).toLocaleString() })
                        : t('members.load_more')}
                    </Button>
                  </div>
                </div>
              )}
            </>
          )}
        </>
      )}
    </div>
  );
}

interface MemberCardProps {
  member: User;
  viewMode: ViewMode;
  sortBy?: SortOption;
}

const MemberCard = memo(function MemberCard({ member, viewMode, sortBy }: MemberCardProps) {
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const hasGamification = useFeature('gamification');

  // Handle empty names gracefully - fallback to "Member" or first_name/last_name
  const displayName = member.name?.trim()
    || `${member.first_name || ''} ${member.last_name || ''}`.trim()
    || t('members.fallback_name');

  // Resolve avatar from either field (index returns 'avatar', nearby may return 'avatar_url')
  const avatarSrc = resolveAvatarUrl(member.avatar ?? member.avatar_url);

  const level = member.level ?? 0;
  const showcasedBadges = member.showcased_badges ?? [];

  // Format join date for "New members" sort
  const joinedLabel = sortBy === 'joined' && member.created_at
    ? t('members.joined_date', {
        date: new Date(member.created_at).toLocaleDateString(undefined, { month: 'short', year: 'numeric' }),
      })
    : null;

  // Distance label for nearby mode
  const distanceLabel = member.distance != null
    ? `${Number(member.distance).toFixed(1)} km`
    : null;

  if (viewMode === 'list') {
    return (
      <Link to={tenantPath(`/profile/${member.id}`)} aria-label={`${displayName}'s profile`}>
        <article>
          <GlassCard className="p-4 hover:bg-theme-hover hover:shadow-md hover:shadow-indigo-500/5 border-l-4 border-l-indigo-500/20 hover:border-l-indigo-500/50 transition-all duration-200">
            <div className="flex items-center gap-4">
              <div className="relative inline-block">
                <Avatar
                  src={avatarSrc}
                  name={displayName}
                  size="lg"
                  className="ring-2 ring-theme-muted/20"
                />
                <PresenceIndicator userId={member.id} size="md" />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-1.5">
                  <h3 className="font-semibold text-theme-primary">{displayName}</h3>
                  {member.is_verified && (
                    <Tooltip content={t('members.verified_member', 'Verified member')}>
                      <BadgeCheck className="w-4 h-4 text-teal-500 shrink-0" aria-label={t('members.verified_member', 'Verified member')} />
                    </Tooltip>
                  )}
                  {hasGamification && level > 0 && (
                    <Chip size="sm" variant="flat" className="bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 text-xs h-5 min-w-0">
                      {t('level_short', { level })}
                    </Chip>
                  )}
                  {hasGamification && showcasedBadges.length > 0 && (
                    <span className="flex items-center gap-0.5 ml-1" aria-label={t('members.showcased_badges')}>
                      {showcasedBadges.map((badge) => (
                        <Tooltip key={badge.badge_key} content={badge.name}>
                          <span className="text-base leading-none cursor-default" aria-label={badge.name}>{badge.icon || '🏆'}</span>
                        </Tooltip>
                      ))}
                    </span>
                  )}
                </div>
                {member.tagline && (
                  <p className="text-sm text-theme-subtle truncate">{member.tagline}</p>
                )}
              </div>
              <div className="flex items-center gap-3 sm:gap-6 text-sm text-theme-subtle">
                {member.location && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    <span>{member.location}</span>
                  </span>
                )}
                {member.rating != null && (
                  <span className="flex items-center gap-1" aria-label={t('members.rating_aria', { rating: member.rating.toFixed(1) })}>
                    <Star className="w-4 h-4 text-amber-400" aria-hidden="true" />
                    <span>{member.rating.toFixed(1)}</span>
                  </span>
                )}
                <span className="flex items-center gap-1 shrink-0 whitespace-nowrap" aria-label={t('members.hours_exchanged_aria', { count: (member.total_hours_given ?? 0) + (member.total_hours_received ?? 0) })}>
                  <Clock className="w-4 h-4" aria-hidden="true" />
                  <span>{t('members.hours_short', { count: (member.total_hours_given ?? 0) + (member.total_hours_received ?? 0) })}</span>
                </span>
                {joinedLabel && (
                  <span className="flex items-center gap-1 shrink-0 whitespace-nowrap text-indigo-500 dark:text-indigo-400">
                    <Sparkles className="w-3.5 h-3.5" aria-hidden="true" />
                    <span>{joinedLabel}</span>
                  </span>
                )}
                {distanceLabel && (
                  <span className="flex items-center gap-1 shrink-0 whitespace-nowrap text-emerald-600 dark:text-emerald-400">
                    <MapPin className="w-3.5 h-3.5" aria-hidden="true" />
                    <span>{distanceLabel}</span>
                  </span>
                )}
                {sortBy === 'communityrank' && member.community_rank_score != null && (
                  <Tooltip content={t('members.community_rank_score_tooltip', 'CommunityRank score')}>
                    <span className="flex items-center gap-1 shrink-0 whitespace-nowrap text-violet-600 dark:text-violet-400 cursor-default">
                      <TrendingUp className="w-3.5 h-3.5" aria-hidden="true" />
                      <span>{Math.round(member.community_rank_score * 100)}%</span>
                    </span>
                  </Tooltip>
                )}
              </div>
            </div>
          </GlassCard>
        </article>
      </Link>
    );
  }

  return (
    <Link to={tenantPath(`/profile/${member.id}`)} aria-label={`${displayName}'s profile`} className="group">
      <article>
        <GlassCard className="p-5 hover:scale-[1.03] hover:shadow-xl hover:shadow-indigo-500/10 transition-all duration-200 text-center">
          <div className="relative inline-block mx-auto mb-3">
            <Avatar
              src={avatarSrc}
              name={displayName}
              className="w-20 h-20 ring-2 ring-theme-muted/20 group-hover:ring-indigo-400/50 transition-all duration-200"
            />
            <PresenceIndicator userId={member.id} size="md" />
          </div>
          <div className="flex items-center justify-center gap-1.5 flex-wrap">
            <h3 className="font-semibold text-theme-primary">{displayName}</h3>
            {member.is_verified && (
              <Tooltip content={t('members.verified_member', 'Verified member')}>
                <BadgeCheck className="w-4 h-4 text-teal-500 shrink-0" aria-label={t('members.verified_member', 'Verified member')} />
              </Tooltip>
            )}
            {hasGamification && level > 0 && (
              <Chip size="sm" variant="flat" className="bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 text-xs h-5 min-w-0">
                {t('level_short', { level })}
              </Chip>
            )}
          </div>
          {hasGamification && showcasedBadges.length > 0 && (
            <div className="flex items-center justify-center gap-1 mt-1.5" aria-label={t('members.showcased_badges')}>
              {showcasedBadges.map((badge) => (
                <Tooltip key={badge.badge_key} content={badge.name}>
                  <span className="text-lg leading-none cursor-default" aria-label={badge.name}>{badge.icon || '🏆'}</span>
                </Tooltip>
              ))}
            </div>
          )}
          {member.tagline && (
            <p className="text-sm text-theme-subtle line-clamp-1 mt-1">{member.tagline}</p>
          )}

          {/* Stat chips */}
          <div className="flex items-center justify-center gap-2 mt-4 flex-wrap">
            {member.rating != null && (
              <span
                className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-medium"
                aria-label={t('members.rating_aria', { rating: member.rating.toFixed(1) })}
              >
                <Star className="w-3 h-3 fill-amber-500 text-amber-500" aria-hidden="true" />
                {member.rating.toFixed(1)}
              </span>
            )}
            <span
              className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-theme-hover text-theme-muted text-xs font-medium"
              aria-label={t('members.hours_exchanged_aria', { count: (member.total_hours_given ?? 0) + (member.total_hours_received ?? 0) })}
            >
              <Clock className="w-3 h-3" aria-hidden="true" />
              {t('members.hours_short', { count: (member.total_hours_given ?? 0) + (member.total_hours_received ?? 0) })}
            </span>
            {sortBy === 'communityrank' && member.community_rank_score != null && (
              <Tooltip content={t('members.community_rank_score_tooltip', 'CommunityRank score')}>
                <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-violet-500/10 text-violet-600 dark:text-violet-400 text-xs font-medium cursor-default">
                  <TrendingUp className="w-3 h-3" aria-hidden="true" />
                  {Math.round(member.community_rank_score * 100)}%
                </span>
              </Tooltip>
            )}
          </div>

          {(member.location || distanceLabel) && (
            <p className="text-xs text-theme-subtle mt-2 flex items-center justify-center gap-1">
              <MapPin className="w-3 h-3" aria-hidden="true" />
              <span>{distanceLabel ? `${member.location ? member.location + ' · ' : ''}${distanceLabel}` : member.location}</span>
            </p>
          )}

          {joinedLabel && (
            <p className="text-xs text-indigo-500 dark:text-indigo-400 mt-1 flex items-center justify-center gap-1">
              <Sparkles className="w-3 h-3" aria-hidden="true" />
              <span>{joinedLabel}</span>
            </p>
          )}
        </GlassCard>
      </article>
    </Link>
  );
});

export default MembersPage;
