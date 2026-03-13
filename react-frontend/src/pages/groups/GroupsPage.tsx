// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

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
  Star,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard, GroupCardSkeleton } from '@/components/ui';
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
  const { t } = useTranslation('groups');
  usePageTitle(t('title'));
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [groups, setGroups] = useState<Group[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [cursor, setCursor] = useState<string | null>(null);
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

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      // Map the UI filter value to the PHP-supported query params
      if (filter === 'public') {
        params.set('visibility', 'public');
      } else if (filter === 'private') {
        params.set('visibility', 'private');
      } else if (filter === 'joined' && user?.id) {
        params.set('user_id', String(user.id));
      }
      params.set('per_page', String(ITEMS_PER_PAGE));
      // Cursor-based pagination: only send cursor when loading more pages
      if (append && cursor) {
        params.set('cursor', cursor);
      }

      const response = await api.get<Group[]>(`/v2/groups?${params}`);
      if (response.success && response.data) {
        if (append) {
          setGroups((prev) => [...prev, ...response.data!]);
        } else {
          setGroups(response.data);
        }
        // Prefer server-reported has_more; fall back to length heuristic
        const nextCursor = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
        setCursor(nextCursor);
        if (response.meta !== undefined) {
          setHasMore(response.meta.has_more);
        } else {
          setHasMore(response.data.length >= ITEMS_PER_PAGE);
        }
      } else {
        if (!append) {
          setError(t('load_error'));
        }
      }
    } catch (err) {
      logError('Failed to load groups', err);
      if (!append) {
        setError(t('load_error'));
      } else {
        toast.error(t('load_more_error'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, filter, cursor, user?.id]);

  // Load groups when filter or debounced query changes; reset cursor for a fresh page-1 fetch
  useEffect(() => {
    setCursor(null);
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
            {t('title')}
          </h1>
          <p className="text-theme-muted mt-1">{t('subtitle')}</p>
        </div>
        {isAuthenticated && (
          <Link to={tenantPath('/groups/create')}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
            >
              {t('create_group')}
            </Button>
          </Link>
        )}
      </div>

      {/* Filters */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder={t('search_placeholder')}
              aria-label={t('search_placeholder')}
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
            placeholder={t('filter_placeholder')}
            aria-label={t('filter_placeholder')}
            selectedKeys={[filter]}
            onChange={(e) => setFilter(e.target.value as GroupFilter)}
            className="w-32 sm:w-40"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={<Filter className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          >
            <SelectItem key="all">{t('filter_all')}</SelectItem>
            {isAuthenticated ? <SelectItem key="joined">{t('filter_my')}</SelectItem> : null}
            <SelectItem key="public">{t('filter_public')}</SelectItem>
            <SelectItem key="private">{t('filter_private')}</SelectItem>
          </Select>
        </div>
      </GlassCard>

      {/* Error State */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('unable_to_load')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadGroups()}
          >
            {t('try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Groups Grid */}
      {!error && (
        <>
          {isLoading ? (
            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" aria-label="Loading groups" aria-busy="true">
              {[1, 2, 3, 4, 5, 6].map((i) => (
                <GroupCardSkeleton key={i} />
              ))}
            </div>
          ) : groups.length === 0 ? (
            <EmptyState
              icon={<Users className="w-12 h-12" aria-hidden="true" />}
              title={t('no_groups')}
              description={t('no_groups_desc')}
              action={
                isAuthenticated && (
                  <Link to={tenantPath('/groups/create')}>
                    <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                      {t('create_group')}
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
              {/* Featured Groups Section */}
              {(() => {
                const featuredGroups = groups.filter((g) => g.is_featured);
                const regularGroups = groups.filter((g) => !g.is_featured);

                return (
                  <>
                    {featuredGroups.length > 0 && (
                      <div className="space-y-3">
                        <h2 className="text-lg font-semibold text-theme-primary flex items-center gap-2">
                          <Star className="w-5 h-5 text-amber-500" aria-hidden="true" />
                          {t('featured_groups')}
                        </h2>
                        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                          {featuredGroups.map((group) => (
                            <motion.div key={group.id} variants={itemVariants}>
                              <GroupCard group={group} featured />
                            </motion.div>
                          ))}
                        </div>
                      </div>
                    )}

                    {/* All Groups Section */}
                    {regularGroups.length > 0 && (
                      <div className="space-y-3">
                        {featuredGroups.length > 0 && (
                          <h2 className="text-lg font-semibold text-theme-primary">
                            {t('all_groups')}
                          </h2>
                        )}
                        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                          {regularGroups.map((group) => (
                            <motion.div key={group.id} variants={itemVariants}>
                              <GroupCard group={group} />
                            </motion.div>
                          ))}
                        </div>
                      </div>
                    )}
                  </>
                );
              })()}

              {/* Load More Button */}
              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={loadMoreGroups}
                    isLoading={isLoadingMore}
                  >
                    {t('load_more')}
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
  featured?: boolean;
}

const GroupCard = memo(function GroupCard({ group, featured }: GroupCardProps) {
  const { t } = useTranslation('groups');
  const { tenantPath } = useTenant();
  const memberCount = group.member_count ?? group.members_count ?? 0;

  return (
    <Link to={tenantPath(`/groups/${group.id}`)} aria-label={`${group.name} - ${memberCount} members`}>
      <article>
        <GlassCard className={`p-5 hover:scale-[1.02] transition-transform h-full flex flex-col${featured ? ' ring-1 ring-amber-500/30' : ''}`}>
          {featured && (
            <div className="flex items-center gap-1.5 mb-2">
              <Star className="w-3.5 h-3.5 text-amber-500 fill-amber-500" aria-hidden="true" />
              <span className="text-xs font-medium text-amber-600 dark:text-amber-400">
                {t('featured_badge')}
              </span>
            </div>
          )}
          <div className="flex items-start justify-between gap-3 mb-3">
            <h3 className="font-semibold text-theme-primary text-lg">{group.name}</h3>
            {group.visibility === 'private' ? (
              <span className="shrink-0 p-1.5 rounded-full bg-amber-500/20" title={t('private_title')}>
                <Lock className="w-4 h-4 text-amber-400" aria-hidden="true" />
              </span>
            ) : (
              <span className="shrink-0 p-1.5 rounded-full bg-emerald-500/20" title={t('public_title')}>
                <Globe className="w-4 h-4 text-emerald-400" aria-hidden="true" />
              </span>
            )}
          </div>

          <p className="text-theme-muted text-sm line-clamp-2 flex-1 mb-4">
            {group.description || t('no_description')}
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
                {t('member_status')}
              </span>
            </div>
          )}
        </GlassCard>
      </article>
    </Link>
  );
});

export default GroupsPage;
