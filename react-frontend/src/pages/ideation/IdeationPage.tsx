// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Ideation Challenges Page - Browse and discover community challenges
 *
 * Features:
 * - Grid of challenge cards with status chips
 * - Filter tabs: All, Open, Voting, Evaluating, Closed, Archived, Favorites
 * - Category filter dropdown (I1)
 * - Tag chips on challenge cards (I1)
 * - Status lifecycle badges with color coding (I11)
 * - Favorite heart toggle (I8)
 * - Create Challenge button (admin only)
 * - Link to Campaigns page (I7)
 * - Link to Outcomes Dashboard (I10)
 * - Cursor-based pagination
 * - Empty state
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import {
  Button,
  Chip,
  Input,
  Tabs,
  Tab,
  Spinner,
  Select,
  SelectItem,
} from '@heroui/react';
import {
  Lightbulb,
  Plus,
  RefreshCw,
  AlertTriangle,
  Calendar,
  MessageSquarePlus,
  Trophy,
  Heart,
  Eye,
  Star,
  Layers,
  BarChart3,
  Search,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAssetUrl } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Challenge {
  id: number;
  tenant_id: number;
  user_id: number;
  title: string;
  description: string;
  category: string | null;
  status: 'draft' | 'open' | 'voting' | 'evaluating' | 'closed' | 'archived';
  ideas_count: number;
  submission_deadline: string | null;
  voting_deadline: string | null;
  prize_description: string | null;
  max_ideas_per_user: number | null;
  created_at: string;
  tags: string[];
  cover_image: string | null;
  is_favorited: boolean;
  favorites_count: number;
  views_count: number;
  is_featured: boolean;
  creator: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

interface Category {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
  color: string | null;
  challenges_count: number;
}

type FilterTab = 'all' | 'open' | 'voting' | 'evaluating' | 'closed' | 'archived' | 'favorites';

const STATUS_COLOR_MAP: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'secondary' | 'primary'> = {
  draft: 'default',
  open: 'success',
  voting: 'warning',
  evaluating: 'primary',
  closed: 'danger',
  archived: 'secondary',
};

/* ───────────────────────── Main Component ───────────────────────── */

export function IdeationPage() {
  const { t } = useTranslation('ideation');
  usePageTitle(t('page_title'));
  const { user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [challenges, setChallenges] = useState<Challenge[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<FilterTab>('all');
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [favoritingIds, setFavoritingIds] = useState<Set<number>>(new Set());

  // Category filter (I1)
  const [categories, setCategories] = useState<Category[]>([]);
  const [selectedCategory, setSelectedCategory] = useState<string>('');

  // Search + tag filters
  const [searchQuery, setSearchQuery] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [availableTags, setAvailableTags] = useState<{tag: string; count: number}[]>([]);
  const [selectedTags, setSelectedTags] = useState<string[]>([]);

  const isAdmin = user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role);

  // Fetch categories on mount
  useEffect(() => {
    const fetchCategories = async () => {
      try {
        const response = await api.get<Category[]>('/v2/ideation-categories');
        if (response.success && response.data) {
          setCategories(Array.isArray(response.data) ? response.data : []);
        }
      } catch (err) {
        logError('Failed to fetch ideation categories', err);
      }
    };
    fetchCategories();
  }, []);

  // Debounce search input
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(searchQuery);
    }, 300);
    return () => clearTimeout(timer);
  }, [searchQuery]);

  // Fetch available tags for filtering
  useEffect(() => {
    const fetchTags = async () => {
      try {
        const response = await api.get<{tag: string; count: number}[]>('/v2/ideation-tags/popular');
        if (response.success && response.data) {
          setAvailableTags(Array.isArray(response.data) ? response.data : []);
        }
      } catch (err) {
        logError('Failed to fetch ideation tags', err);
      }
    };
    fetchTags();
  }, []);

  const fetchChallenges = useCallback(async (tab: FilterTab, loadMore = false, categoryFilter?: string, searchTerm?: string, tagFilter?: string[]) => {
    try {
      if (loadMore) {
        setIsLoadingMore(true);
      } else {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');

      if (tab === 'favorites') {
        params.set('favorites', '1');
      } else if (tab !== 'all') {
        params.set('status', tab);
      }

      if (categoryFilter) {
        params.set('category', categoryFilter);
      }

      if (searchTerm) {
        params.set('search', searchTerm);
      }

      if (tagFilter && tagFilter.length > 0) {
        params.set('tags', tagFilter.join(','));
      }

      if (loadMore && cursor) {
        params.set('cursor', cursor);
      }

      const response = await api.get<Challenge[]>(`/v2/ideation-challenges?${params}`);

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];
        if (loadMore) {
          setChallenges(prev => [...prev, ...items]);
        } else {
          setChallenges(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        setCursor(response.meta?.cursor ?? undefined);
      } else {
        if (!loadMore) setError(t('challenges.load_error'));
      }
    } catch (err) {
      logError('Failed to fetch challenges', err);
      if (!loadMore) {
        setError(t('challenges.load_error'));
      } else {
        toast.error(t('challenges.load_error'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [cursor, t, toast]);

  useEffect(() => {
    setCursor(undefined);
    fetchChallenges(activeTab, false, selectedCategory, debouncedSearch, selectedTags);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [activeTab, selectedCategory, debouncedSearch, selectedTags]);

  const handleTabChange = (key: React.Key) => {
    setActiveTab(key as FilterTab);
  };

  const handleCategoryChange = (keys: Set<React.Key> | 'all') => {
    if (keys === 'all') {
      setSelectedCategory('');
      return;
    }
    const selected = Array.from(keys)[0];
    setSelectedCategory(selected ? String(selected) : '');
  };

  const handleToggleFavorite = async (e: React.MouseEvent, challengeId: number) => {
    e.preventDefault();
    e.stopPropagation();
    if (favoritingIds.has(challengeId)) return;

    setFavoritingIds(prev => new Set(prev).add(challengeId));

    try {
      const response = await api.post<{ favorited: boolean; favorites_count: number }>(
        `/v2/ideation-challenges/${challengeId}/favorite`
      );

      if (response.data) {
        setChallenges(prev => prev.map(c => {
          if (c.id === challengeId) {
            return {
              ...c,
              is_favorited: response.data!.favorited,
              favorites_count: response.data!.favorites_count,
            };
          }
          return c;
        }));
      }
    } catch (err) {
      logError('Failed to toggle favorite', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setFavoritingIds(prev => {
        const next = new Set(prev);
        next.delete(challengeId);
        return next;
      });
    }
  };

  const truncate = (text: string, maxLength: number) => {
    if (text.length <= maxLength) return text;
    return text.slice(0, maxLength).trimEnd() + '...';
  };

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return null;
    try {
      return new Date(dateStr).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
      });
    } catch {
      return dateStr;
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text)]">
            {t('title')}
          </h1>
          <p className="text-sm text-[var(--color-text-secondary)] mt-1">
            {t('subtitle')}
          </p>
        </div>

        <div className="flex items-center gap-2">
          {/* Campaigns link */}
          <Button
            variant="flat"
            size="sm"
            startContent={<Layers className="w-4 h-4" />}
            onPress={() => navigate(tenantPath('/ideation/campaigns'))}
          >
            {t('campaigns.title')}
          </Button>

          {/* Outcomes Dashboard link */}
          <Button
            variant="flat"
            size="sm"
            startContent={<BarChart3 className="w-4 h-4" />}
            onPress={() => navigate(tenantPath('/ideation/outcomes'))}
          >
            {t('outcomes.dashboard')}
          </Button>

          {isAdmin && (
            <Button
              color="primary"
              startContent={<Plus className="w-4 h-4" />}
              onPress={() => navigate(tenantPath('/ideation/create'))}
            >
              {t('challenges.create')}
            </Button>
          )}
        </div>
      </div>

      {/* Filter Row: Tabs + Category dropdown */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-4 mb-6">
        <div className="flex-1 overflow-x-auto">
          <Tabs
            selectedKey={activeTab}
            onSelectionChange={handleTabChange}
            variant="underlined"
            color="primary"
            aria-label={t('tabs.all')}
          >
            <Tab key="all" title={t('tabs.all')} />
            <Tab key="open" title={t('tabs.open')} />
            <Tab key="voting" title={t('tabs.voting')} />
            <Tab key="evaluating" title={t('tabs.evaluating')} />
            <Tab key="closed" title={t('tabs.closed')} />
            <Tab key="archived" title={t('tabs.archived')} />
            <Tab key="favorites" title={t('tabs.favorites')} />
          </Tabs>
        </div>

        {/* Category Filter (I1) */}
        {categories.length > 0 && (
          <div className="w-48 shrink-0">
            <Select
              size="sm"
              label={t('categories.title')}
              placeholder={t('categories.all')}
              selectedKeys={selectedCategory ? new Set([selectedCategory]) : new Set<string>()}
              onSelectionChange={handleCategoryChange}
              variant="bordered"
              className="max-w-xs"
            >
              {categories.map((cat) => (
                <SelectItem key={cat.slug}>
                  {cat.name}
                </SelectItem>
              ))}
            </Select>
          </div>
        )}
      </div>

      {/* Search + Tag Filters */}
      <div className="space-y-3 mb-6">
        {/* Search Bar */}
        <Input
          placeholder={t('search.placeholder')}
          value={searchQuery}
          onValueChange={setSearchQuery}
          aria-label={t('search.placeholder')}
          startContent={<Search className="w-4 h-4 text-[var(--color-text-tertiary)]" />}
          endContent={searchQuery ? (
            <Button
              isIconOnly
              size="sm"
              variant="light"
              onPress={() => setSearchQuery('')}
              className="p-0.5 rounded hover:bg-[var(--color-surface-hover)] min-w-0 w-auto h-auto"
              aria-label={t('search.clear', 'Clear search')}
            >
              <X className="w-3.5 h-3.5 text-[var(--color-text-tertiary)]" />
            </Button>
          ) : null}
          variant="bordered"
          size="sm"
          className="max-w-md"
        />

        {/* Tag Filter Chips */}
        {availableTags.length > 0 && (
          <div className="flex flex-wrap gap-1.5">
            {availableTags.slice(0, 15).map(({ tag, count }) => (
              <Chip
                key={tag}
                size="sm"
                variant={selectedTags.includes(tag) ? 'solid' : 'bordered'}
                color={selectedTags.includes(tag) ? 'primary' : 'default'}
                className="cursor-pointer"
                onClick={() => {
                  setSelectedTags(prev =>
                    prev.includes(tag)
                      ? prev.filter(t => t !== tag)
                      : [...prev, tag]
                  );
                }}
              >
                {tag} ({count})
              </Chip>
            ))}
            {selectedTags.length > 0 && (
              <Chip
                size="sm"
                variant="flat"
                color="danger"
                className="cursor-pointer"
                onClick={() => setSelectedTags([])}
              >
                {t('search.clear_tags')}
              </Chip>
            )}
          </div>
        )}
      </div>

      {/* Loading */}
      {isLoading && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      )}

      {/* Error */}
      {error && !isLoading && (
        <EmptyState
          icon={<AlertTriangle className="w-10 h-10 text-theme-subtle" />}
          title={t('challenges.load_error')}
          description={error}
          action={
            <Button
              color="primary"
              variant="flat"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={() => fetchChallenges(activeTab, false, selectedCategory, debouncedSearch, selectedTags)}
            >
              {t('actions.retry', { defaultValue: 'Retry' })}
            </Button>
          }
        />
      )}

      {/* Empty State */}
      {!isLoading && !error && challenges.length === 0 && (
        <EmptyState
          icon={<Lightbulb className="w-10 h-10 text-theme-subtle" />}
          title={
            activeTab === 'favorites'
              ? t('favorites.empty_title')
              : t('challenges.empty_title')
          }
          description={
            activeTab === 'favorites'
              ? t('favorites.empty_description')
              : activeTab === 'all'
                ? t('challenges.empty_description')
                : t('challenges.empty_filtered')
          }
        />
      )}

      {/* Challenge Grid */}
      {!isLoading && !error && challenges.length > 0 && (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {challenges.map((challenge) => (
              <Link
                key={challenge.id}
                to={tenantPath(`/ideation/${challenge.id}`)}
                className="block"
              >
                <GlassCard className="h-full hover:shadow-lg transition-shadow cursor-pointer overflow-hidden">
                  {/* Cover Image */}
                  {challenge.cover_image && (
                    <div className="w-full h-40 overflow-hidden">
                      <img
                        src={resolveAssetUrl(challenge.cover_image)}
                        alt={challenge.title}
                        className="w-full h-full object-cover"
                        loading="lazy"
                      />
                    </div>
                  )}

                  <div className="p-5">
                    <div className="flex items-start justify-between gap-3 mb-3">
                      <div className="flex items-center gap-2 flex-1 min-w-0">
                        <h3 className="text-lg font-semibold text-[var(--color-text)] line-clamp-2">
                          {challenge.title}
                        </h3>
                        {challenge.is_featured && (
                          <Chip
                            size="sm"
                            color="warning"
                            variant="flat"
                            startContent={<Star className="w-3 h-3 fill-current" />}
                          >
                            {t('featured')}
                          </Chip>
                        )}
                      </div>
                      <div className="flex items-center gap-2 shrink-0">
                        {/* Status Badge (I11) */}
                        <Chip
                          size="sm"
                          color={STATUS_COLOR_MAP[challenge.status] ?? 'default'}
                          variant="flat"
                        >
                          {t(`status.${challenge.status}`)}
                        </Chip>
                        {/* Favorite Button (I8) — uses onClick to stop Link propagation */}
                        <span
                          role="button"
                          tabIndex={0}
                          onClick={(e) => handleToggleFavorite(e, challenge.id)}
                          onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); void handleToggleFavorite(e as unknown as React.MouseEvent, challenge.id); } }}
                          aria-label={challenge.is_favorited ? t('favorites.remove') : t('favorites.add')}
                          aria-disabled={favoritingIds.has(challenge.id)}
                          className="p-1 rounded-full hover:bg-[var(--color-surface-hover)] transition-colors inline-flex items-center"
                        >
                          <Heart
                            className={`w-4 h-4 ${
                              challenge.is_favorited
                                ? 'text-red-500 fill-current'
                                : 'text-[var(--color-text-tertiary)]'
                            }`}
                          />
                        </span>
                      </div>
                    </div>

                    <p className="text-sm text-[var(--color-text-secondary)] mb-3 line-clamp-3">
                      {truncate(challenge.description, 150)}
                    </p>

                    {/* Tags (I1) */}
                    {challenge.tags && challenge.tags.length > 0 && (
                      <div className="flex flex-wrap gap-1.5 mb-3">
                        {challenge.tags.map((tag) => (
                          <Chip key={tag} size="sm" variant="bordered" className="text-xs">
                            {tag}
                          </Chip>
                        ))}
                      </div>
                    )}

                    <div className="flex flex-wrap items-center gap-3 text-xs text-[var(--color-text-tertiary)]">
                      {/* Ideas count */}
                      <span className="flex items-center gap-1">
                        <MessageSquarePlus className="w-3.5 h-3.5" />
                        {t('challenge.ideas_count', { count: challenge.ideas_count })}
                      </span>

                      {/* Views count */}
                      <span className="flex items-center gap-1">
                        <Eye className="w-3.5 h-3.5" />
                        {challenge.views_count} {t('views')}
                      </span>

                      {/* Favorites count */}
                      <span className="flex items-center gap-1">
                        <Heart className="w-3.5 h-3.5" />
                        {challenge.favorites_count}
                      </span>

                      {/* Submission deadline */}
                      {challenge.submission_deadline && (
                        <span className="flex items-center gap-1">
                          <Calendar className="w-3.5 h-3.5" />
                          {t('challenge.submission_deadline', { date: formatDate(challenge.submission_deadline) })}
                        </span>
                      )}

                      {/* Prize indicator */}
                      {challenge.prize_description && (
                        <span className="flex items-center gap-1">
                          <Trophy className="w-3.5 h-3.5 text-amber-500" />
                          {t('challenge.prize')}
                        </span>
                      )}

                      {/* Category (I1) */}
                      {challenge.category && (
                        <Chip size="sm" variant="flat" className="text-xs">
                          {challenge.category}
                        </Chip>
                      )}
                    </div>
                  </div>
                </GlassCard>
              </Link>
            ))}
          </div>

          {/* Load More */}
          {hasMore && (
            <div className="flex justify-center mt-6">
              <Button
                variant="flat"
                isLoading={isLoadingMore}
                onPress={() => fetchChallenges(activeTab, true, selectedCategory, debouncedSearch, selectedTags)}
              >
                {t('challenges.load_more')}
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

export default IdeationPage;
