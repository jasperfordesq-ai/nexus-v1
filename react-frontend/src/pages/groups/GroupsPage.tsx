/**
 * Groups Page - Community groups listing
 */

import { useState, useEffect, useCallback, useRef, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem, Avatar, AvatarGroup } from '@heroui/react';
import {
  Search,
  Users,
  Plus,
  Filter,
  Lock,
  Globe,
  MessageSquare,
  RefreshCw,
  AlertTriangle,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import type { Group } from '@/types/api';

type GroupFilter = 'all' | 'joined' | 'public' | 'private';

const ITEMS_PER_PAGE = 20;
const SEARCH_DEBOUNCE_MS = 300;

export function GroupsPage() {
  usePageTitle('Groups');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [groups, setGroups] = useState<Group[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [filter, setFilter] = useState<GroupFilter>('all');

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Debounce search query
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

  const loadGroups = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const offset = append ? groups.length : 0;
      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (filter !== 'all') params.set('filter', filter);
      params.set('limit', String(ITEMS_PER_PAGE));
      params.set('offset', String(offset));

      const response = await api.get<Group[]>(`/v2/groups?${params}`);
      if (response.success && response.data) {
        if (append) {
          setGroups((prev) => [...prev, ...response.data!]);
        } else {
          setGroups(response.data);
        }
        setHasMore(response.data.length >= ITEMS_PER_PAGE);
      } else {
        if (!append) {
          setError('Failed to load groups. Please try again.');
        }
      }
    } catch (err) {
      logError('Failed to load groups', err);
      if (!append) {
        setError('Failed to load groups. Please try again.');
      } else {
        toast.error('Failed to load more groups');
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, filter, groups.length]);

  // Load groups when filter or debounced query changes
  useEffect(() => {
    loadGroups();
    setHasMore(true);
  }, [debouncedQuery, filter]); // eslint-disable-line react-hooks/exhaustive-deps

  // Update URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    setSearchParams(params, { replace: true });
  }, [searchQuery, setSearchParams]);

  const loadMoreGroups = useCallback(() => {
    if (isLoadingMore || !hasMore) return;
    loadGroups(true);
  }, [isLoadingMore, hasMore, loadGroups]);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Users className="w-7 h-7 text-purple-600 dark:text-purple-400" aria-hidden="true" />
            Groups
          </h1>
          <p className="text-theme-muted mt-1">Join groups to connect with like-minded community members</p>
        </div>
        {isAuthenticated && (
          <Link to={tenantPath('/groups/create')}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            >
              Create Group
            </Button>
          </Link>
        )}
      </div>

      {/* Filters */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder="Search groups..."
              aria-label="Search groups"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          <Select
            placeholder="Filter"
            aria-label="Filter groups by type"
            selectedKeys={[filter]}
            onChange={(e) => setFilter(e.target.value as GroupFilter)}
            className="w-40"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={<Filter className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          >
            <SelectItem key="all">All Groups</SelectItem>
            {isAuthenticated ? <SelectItem key="joined">My Groups</SelectItem> : null}
            <SelectItem key="public">Public</SelectItem>
            <SelectItem key="private">Private</SelectItem>
          </Select>
        </div>
      </GlassCard>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Groups</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadGroups()}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Groups Grid */}
      {!error && (
        <>
          {isLoading ? (
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-5 bg-theme-hover rounded w-2/3 mb-3" />
                  <div className="h-4 bg-theme-hover rounded w-full mb-2" />
                  <div className="h-4 bg-theme-hover rounded w-3/4 mb-4" />
                  <div className="h-3 bg-theme-hover rounded w-1/3" />
                </GlassCard>
              ))}
            </div>
          ) : groups.length === 0 ? (
            <EmptyState
              icon={<Users className="w-12 h-12" aria-hidden="true" />}
              title="No groups found"
              description="Start a new group or try a different search"
              action={
                isAuthenticated && (
                  <Link to={tenantPath('/groups/create')}>
                    <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                      Create Group
                    </Button>
                  </Link>
                )
              }
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-6"
            >
              <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {groups.map((group) => (
                  <motion.div key={group.id} variants={itemVariants}>
                    <GroupCard group={group} />
                  </motion.div>
                ))}
              </div>

              {/* Load More Button */}
              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={loadMoreGroups}
                    isLoading={isLoadingMore}
                  >
                    Load More Groups
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}
    </div>
  );
}

interface GroupCardProps {
  group: Group;
}

const GroupCard = memo(function GroupCard({ group }: GroupCardProps) {
  const { tenantPath } = useTenant();
  const memberCount = group.member_count ?? group.members_count ?? 0;

  return (
    <Link to={tenantPath(`/groups/${group.id}`)} aria-label={`${group.name} - ${memberCount} members`}>
      <article>
        <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full flex flex-col">
          <div className="flex items-start justify-between gap-3 mb-3">
            <h3 className="font-semibold text-theme-primary text-lg">{group.name}</h3>
            {group.visibility === 'private' ? (
              <span className="flex-shrink-0 p-1.5 rounded-full bg-amber-500/20" title="Private group">
                <Lock className="w-4 h-4 text-amber-400" aria-hidden="true" />
              </span>
            ) : (
              <span className="flex-shrink-0 p-1.5 rounded-full bg-emerald-500/20" title="Public group">
                <Globe className="w-4 h-4 text-emerald-400" aria-hidden="true" />
              </span>
            )}
          </div>

          <p className="text-theme-muted text-sm line-clamp-2 flex-1 mb-4">
            {group.description || 'No description provided'}
          </p>

          {/* Group Stats */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4 text-sm text-theme-subtle">
              <span className="flex items-center gap-1">
                <Users className="w-4 h-4" aria-hidden="true" />
                {memberCount}
              </span>
              {group.posts_count !== undefined && (
                <span className="flex items-center gap-1">
                  <MessageSquare className="w-4 h-4" aria-hidden="true" />
                  {group.posts_count}
                </span>
              )}
            </div>

            {group.recent_members && group.recent_members.length > 0 && (
              <AvatarGroup max={3} size="sm">
                {group.recent_members.map((member) => (
                  <Avatar
                    key={member.id}
                    src={resolveAvatarUrl(member.avatar_url || member.avatar)}
                    name={member.name || `${member.first_name ?? ''} ${member.last_name ?? ''}`.trim()}
                    className="ring-2 ring-black/50"
                  />
                ))}
              </AvatarGroup>
            )}
          </div>

          {/* Member Status */}
          {(group.is_member || group.viewer_membership?.status === 'active') && (
            <div className="mt-4 pt-4 border-t border-theme-default">
              <span className="text-xs px-2 py-1 rounded-full bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                Member
              </span>
            </div>
          )}
        </GlassCard>
      </article>
    </Link>
  );
});

export default GroupsPage;
