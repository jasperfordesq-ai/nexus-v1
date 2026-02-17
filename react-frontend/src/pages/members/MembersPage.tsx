/**
 * Members Page - Community member directory
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Input, Select, SelectItem, Avatar, Button, Chip } from '@heroui/react';
import {
  Search,
  Users,
  Map as MapIcon,
  MapPin,
  Star,
  Clock,
  Filter,
  Grid,
  List,
  RefreshCw,
  AlertTriangle,
  Sparkles,
  TrendingUp,
} from 'lucide-react';
import { GlassCard, MemberCardSkeleton } from '@/components/ui';
import { EntityMapView } from '@/components/location';
import { EmptyState } from '@/components/feedback';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { MAPS_ENABLED } from '@/lib/map-config';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import type { User } from '@/types/api';

type SortOption = 'name' | 'joined' | 'rating' | 'hours_given';
type ViewMode = 'grid' | 'list' | 'map';
type QuickFilter = 'all' | 'new' | 'active';

const ITEMS_PER_PAGE = 24;
const SEARCH_DEBOUNCE_MS = 300;

export function MembersPage() {
  usePageTitle('Members');
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
  const [sortBy, setSortBy] = useState<SortOption>(
    (searchParams.get('sort') as SortOption) || 'name'
  );
  const [viewMode, setViewMode] = useState<ViewMode>(
    (localStorage.getItem('members_view_mode') as ViewMode) || 'grid'
  );

  // Refs
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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

  // Persist view mode
  useEffect(() => {
    localStorage.setItem('members_view_mode', viewMode);
  }, [viewMode]);

  // Handle quick filter selection
  const handleQuickFilter = useCallback((filter: QuickFilter) => {
    setQuickFilter(filter);
    if (filter === 'new') {
      setSortBy('joined');
    } else if (filter === 'active') {
      setSortBy('hours_given');
    } else {
      setSortBy('name');
    }
  }, []);

  // Sync quickFilter when sortBy changes from the Select dropdown
  useEffect(() => {
    if (sortBy === 'joined') {
      setQuickFilter('new');
    } else if (sortBy === 'hours_given') {
      setQuickFilter('active');
    } else {
      setQuickFilter('all');
    }
  }, [sortBy]);

  // Load members
  const loadMembers = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      params.set('sort', sortBy);
      // Quick filters imply descending order for joined and hours_given
      if (sortBy === 'joined' || sortBy === 'hours_given') {
        params.set('order', 'desc');
      }
      params.set('limit', ITEMS_PER_PAGE.toString());

      if (append && members.length > 0) {
        params.set('offset', members.length.toString());
      }

      const response = await api.get<User[]>(`/v2/users?${params}`);

      if (response.success && response.data) {
        if (append) {
          setMembers((prev) => [...prev, ...response.data!]);
        } else {
          setMembers(response.data);
        }
        setHasMore(response.data.length >= ITEMS_PER_PAGE);

        // Get total count from response meta if available
        if (response.meta?.total_items !== undefined) {
          setTotalCount(response.meta.total_items);
        }
      } else {
        if (!append) {
          setError('Failed to load members. Please try again.');
        } else {
          toast.error('Failed to load more members');
        }
      }
    } catch (err) {
      logError('Failed to load members', err);
      if (!append) {
        setError('Failed to load members. Please try again.');
      } else {
        toast.error('Failed to load more members');
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, sortBy, members.length, toast]);

  // Load more
  const loadMoreMembers = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadMembers(true);
  }, [isLoadingMore, hasMore, loadMembers]);

  // Load on mount and when filters change
  useEffect(() => {
    loadMembers();
    // Reset hasMore when filters change
    setHasMore(true);
  }, [debouncedQuery, sortBy]); // eslint-disable-line react-hooks/exhaustive-deps

  // Update URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (debouncedQuery) params.set('q', debouncedQuery);
    if (sortBy !== 'name') params.set('sort', sortBy);
    setSearchParams(params, { replace: true });
  }, [debouncedQuery, sortBy, setSearchParams]);

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

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Users className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
            Members
          </h1>
          <p className="text-theme-muted mt-1">
            Connect with members of the community
          </p>
        </div>
      </div>

      {/* Quick Filters */}
      <div className="flex flex-wrap items-center gap-2">
        <Button
          size="sm"
          variant={quickFilter === 'all' ? 'solid' : 'flat'}
          className={
            quickFilter === 'all'
              ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
              : 'bg-theme-elevated text-theme-muted'
          }
          startContent={<Users className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={() => handleQuickFilter('all')}
          aria-pressed={quickFilter === 'all'}
        >
          All Members
        </Button>
        <Button
          size="sm"
          variant={quickFilter === 'new' ? 'solid' : 'flat'}
          className={
            quickFilter === 'new'
              ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
              : 'bg-theme-elevated text-theme-muted'
          }
          startContent={<Sparkles className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={() => handleQuickFilter('new')}
          aria-pressed={quickFilter === 'new'}
        >
          New Members
        </Button>
        <Button
          size="sm"
          variant={quickFilter === 'active' ? 'solid' : 'flat'}
          className={
            quickFilter === 'active'
              ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white'
              : 'bg-theme-elevated text-theme-muted'
          }
          startContent={<TrendingUp className="w-3.5 h-3.5" aria-hidden="true" />}
          onPress={() => handleQuickFilter('active')}
          aria-pressed={quickFilter === 'active'}
        >
          Most Active
        </Button>

        {/* Member count */}
        {totalCount !== null && !isLoading && (
          <Chip
            variant="flat"
            size="sm"
            className="bg-theme-elevated text-theme-muted ml-auto"
          >
            Showing {members.length.toLocaleString()} of {totalCount.toLocaleString()} members
          </Chip>
        )}
      </div>

      {/* Search & Sort Filters */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder="Search members..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              aria-label="Search members by name"
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          <div className="flex gap-3">
            <Select
              placeholder="Sort by"
              selectedKeys={[sortBy]}
              onChange={(e) => setSortBy(e.target.value as SortOption)}
              className="w-44"
              aria-label="Sort members by"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Filter className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            >
              <SelectItem key="name">Name</SelectItem>
              <SelectItem key="joined">Newest</SelectItem>
              <SelectItem key="rating">Highest Rated</SelectItem>
              <SelectItem key="hours_given">Most Active</SelectItem>
            </Select>

            <div className="flex rounded-lg overflow-hidden border border-theme-default" role="group" aria-label="View mode">
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className={`rounded-none ${viewMode === 'grid' ? 'bg-theme-hover' : 'bg-theme-elevated'}`}
                aria-label="Grid view"
                aria-pressed={viewMode === 'grid'}
                onPress={() => setViewMode('grid')}
              >
                <Grid className="w-4 h-4 text-theme-primary" aria-hidden="true" />
              </Button>
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className={`rounded-none ${viewMode === 'list' ? 'bg-theme-hover' : 'bg-theme-elevated'}`}
                aria-label="List view"
                aria-pressed={viewMode === 'list'}
                onPress={() => setViewMode('list')}
              >
                <List className="w-4 h-4 text-theme-primary" aria-hidden="true" />
              </Button>
              {MAPS_ENABLED && (
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  className={`rounded-none rounded-r-lg ${viewMode === 'map' ? 'bg-primary/10 text-primary' : 'bg-theme-elevated'}`}
                  aria-label="Map view"
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
          <h3 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Members</h3>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadMembers()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Members Grid/List */}
      {!error && (
        <>
          {isLoading ? (
            <div className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4' : 'space-y-3'}>
              {[1, 2, 3, 4, 5, 6, 7, 8].map((i) => (
                <MemberCardSkeleton key={i} />
              ))}
            </div>
          ) : members.length === 0 ? (
            <EmptyState
              icon={<Users className="w-12 h-12" />}
              title="No members found"
              description={debouncedQuery ? 'Try a different search term' : 'No members in this community yet'}
            />
          ) : (
            <>
              {/* Results count with search context */}
              {debouncedQuery && (
                <p className="text-sm text-theme-muted">
                  {members.length.toLocaleString()}
                  {totalCount !== null && ` of ${totalCount.toLocaleString()}`} members
                  {` matching "${debouncedQuery}"`}
                </p>
              )}

              {viewMode === 'map' ? (
                <EntityMapView
                  items={members}
                  getCoordinates={(m) =>
                    m.latitude && m.longitude ? { lat: m.latitude, lng: m.longitude } : null
                  }
                  getMarkerConfig={(m) => ({
                    id: m.id,
                    title: `${m.first_name || ''} ${m.last_name || ''}`.trim() || m.name || 'Member',
                  })}
                  renderInfoContent={(m) => (
                    <div className="p-2 max-w-[200px]">
                      <div className="flex items-center gap-2">
                        {m.avatar_url && (
                          <img src={m.avatar_url} alt="" className="w-8 h-8 rounded-full" />
                        )}
                        <div>
                          <h4 className="font-semibold text-sm text-gray-900">
                            {m.first_name} {m.last_name}
                          </h4>
                          {m.tagline && (
                            <p className="text-xs text-gray-600">{m.tagline}</p>
                          )}
                        </div>
                      </div>
                    </div>
                  )}
                  isLoading={isLoading}
                  emptyMessage="No members with location data"
                />
              ) : (
                <motion.div
                  variants={containerVariants}
                  initial="hidden"
                  animate="visible"
                  className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4' : 'space-y-3'}
                >
                  {members.map((member) => (
                    <motion.div key={member.id} variants={itemVariants}>
                      <MemberCard member={member} viewMode={viewMode} />
                    </motion.div>
                  ))}
                </motion.div>
              )}

              {/* Load More Button */}
              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={loadMoreMembers}
                    isLoading={isLoadingMore}
                  >
                    Load More
                  </Button>
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
}

const MemberCard = memo(function MemberCard({ member, viewMode }: MemberCardProps) {
  const { tenantPath } = useTenant();
  // Handle empty names gracefully - fallback to "Member" or first_name/last_name
  const displayName = member.name?.trim()
    || `${member.first_name || ''} ${member.last_name || ''}`.trim()
    || 'Member';

  if (viewMode === 'list') {
    return (
      <Link to={tenantPath(`/profile/${member.id}`)}>
        <article aria-label={`${displayName}'s profile`}>
          <GlassCard className="p-4 hover:bg-theme-hover transition-colors">
            <div className="flex items-center gap-4">
              <Avatar
                src={resolveAvatarUrl(member.avatar)}
                name={displayName}
                size="lg"
                className="ring-2 ring-theme-muted/20"
              />
              <div className="flex-1 min-w-0">
                <h3 className="font-semibold text-theme-primary">{displayName}</h3>
                {member.tagline && (
                  <p className="text-sm text-theme-subtle truncate">{member.tagline}</p>
                )}
              </div>
              <div className="flex items-center gap-6 text-sm text-theme-subtle">
                {member.location && (
                  <span className="flex items-center gap-1">
                    <MapPin className="w-4 h-4" aria-hidden="true" />
                    <span>{member.location}</span>
                  </span>
                )}
                {member.rating && (
                  <span className="flex items-center gap-1" aria-label={`Rating: ${member.rating.toFixed(1)} out of 5`}>
                    <Star className="w-4 h-4 text-amber-400" aria-hidden="true" />
                    <span>{member.rating.toFixed(1)}</span>
                  </span>
                )}
                <span className="flex items-center gap-1" aria-label={`${member.total_hours_given ?? 0} hours given`}>
                  <Clock className="w-4 h-4" aria-hidden="true" />
                  <span>{member.total_hours_given ?? 0}h</span>
                </span>
              </div>
            </div>
          </GlassCard>
        </article>
      </Link>
    );
  }

  return (
    <Link to={tenantPath(`/profile/${member.id}`)}>
      <article aria-label={`${displayName}'s profile`}>
        <GlassCard className="p-5 hover:scale-[1.02] transition-transform text-center">
          <Avatar
            src={resolveAvatarUrl(member.avatar)}
            name={displayName}
            className="w-16 h-16 mx-auto ring-2 ring-theme-muted/20 mb-3"
          />
          <h3 className="font-semibold text-theme-primary">{displayName}</h3>
          {member.tagline && (
            <p className="text-sm text-theme-subtle line-clamp-1 mt-1">{member.tagline}</p>
          )}

          <div className="flex items-center justify-center gap-4 mt-4 text-xs text-theme-subtle">
            {member.rating && (
              <span className="flex items-center gap-1" aria-label={`Rating: ${member.rating.toFixed(1)} out of 5`}>
                <Star className="w-3 h-3 text-amber-400" aria-hidden="true" />
                <span>{member.rating.toFixed(1)}</span>
              </span>
            )}
            <span className="flex items-center gap-1" aria-label={`${member.total_hours_given ?? 0} hours given`}>
              <Clock className="w-3 h-3" aria-hidden="true" />
              <span>{member.total_hours_given ?? 0}h</span>
            </span>
          </div>

          {member.location && (
            <p className="text-xs text-theme-subtle mt-2 flex items-center justify-center gap-1">
              <MapPin className="w-3 h-3" aria-hidden="true" />
              <span>{member.location}</span>
            </p>
          )}
        </GlassCard>
      </article>
    </Link>
  );
});

export default MembersPage;
