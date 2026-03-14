// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Search Page - Global search across content
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Tabs, Tab, Avatar, Skeleton } from '@heroui/react';
import {
  Search,
  ListTodo,
  User,
  Calendar,
  Users,
  Clock,
  MapPin,
  AlertTriangle,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard, AlgorithmLabel } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { SavedSearches } from '@/components/search/SavedSearches';
import { AdvancedSearchFilters, defaultFilters } from '@/components/search/AdvancedSearchFilters';
import type { SearchFilters as AdvancedFilters } from '@/components/search/AdvancedSearchFilters';
import { FeaturedBadge } from '@/components/listings/FeaturedBadge';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import type { Listing, User as UserType, Event, Group } from '@/types/api';

interface SearchResults {
  listings: Listing[];
  users: UserType[];
  events: Event[];
  groups: Group[];
}

/**
 * Raw item shape returned by the PHP UnifiedSearchService.
 * Each item carries a `type` discriminator plus type-specific fields.
 */
interface RawSearchItem {
  type: 'listing' | 'user' | 'event' | 'group';
  id: number;
  // listing fields
  listing_type?: string;
  // user fields
  avatar_url?: string;
  bio?: string;
  // group fields
  member_count?: number;
  // shared / catch-all
  [key: string]: unknown;
}

/**
 * The PHP /api/v2/search endpoint returns a flat array of typed items.
 * This function groups them into the SearchResults shape expected by the UI,
 * normalising any field-name differences along the way.
 */
function groupSearchItems(items: RawSearchItem[]): SearchResults {
  const result: SearchResults = { listings: [], users: [], events: [], groups: [] };

  for (const item of items) {
    if (item.type === 'listing') {
      result.listings.push({
        ...item,
        // PHP returns `listing_type`; Listing type uses `type` for offer/request
        type: (item.listing_type ?? item.type) as string,
      } as unknown as Listing);
    } else if (item.type === 'user') {
      result.users.push({
        ...item,
        // PHP returns `avatar_url`; React resolveAvatarUrl expects `avatar`
        avatar: item.avatar_url,
        // PHP returns `bio`; React renders `user.tagline`
        tagline: item.bio,
      } as unknown as UserType);
    } else if (item.type === 'event') {
      result.events.push(item as unknown as Event);
    } else if (item.type === 'group') {
      result.groups.push({
        ...item,
        // PHP returns `member_count`; React renders `group.members_count`
        members_count: item.member_count,
      } as unknown as Group);
    }
  }

  return result;
}

type SearchTab = 'all' | 'listings' | 'users' | 'events' | 'groups';

export function SearchPage() {
  const { t } = useTranslation('search_page');
  usePageTitle(t('page_title'));
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [searchParams, setSearchParams] = useSearchParams();
  const [query, setQuery] = useState(searchParams.get('q') || '');
  const [activeTab, setActiveTab] = useState<SearchTab>('all');
  const [results, setResults] = useState<SearchResults>({
    listings: [],
    users: [],
    events: [],
    groups: [],
  });
  const [isLoading, setIsLoading] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);
  const [searchError, setSearchError] = useState<string | null>(null);
  const [advancedFilters, setAdvancedFilters] = useState<AdvancedFilters>({ ...defaultFilters });

  const performSearch = useCallback(async (searchQuery: string, filters?: AdvancedFilters) => {
    if (!searchQuery.trim()) {
      setResults({ listings: [], users: [], events: [], groups: [] });
      setHasSearched(false);
      setSearchError(null);
      return;
    }

    try {
      setIsLoading(true);
      setHasSearched(true);
      setSearchError(null);

      const params = new URLSearchParams();
      params.set('q', searchQuery);

      // Apply advanced filters
      const f = filters || advancedFilters;
      if (f.type && f.type !== 'all') params.set('type', f.type);
      if (f.category_id) params.set('category_id', f.category_id);
      if (f.date_from) params.set('date_from', f.date_from);
      if (f.date_to) params.set('date_to', f.date_to);
      if (f.sort && f.sort !== 'relevance') params.set('sort', f.sort);
      if (f.skills) params.set('skills', f.skills);
      if (f.location) params.set('location', f.location);

      const response = await api.get<RawSearchItem[]>(`/v2/search?${params}`);
      if (response.success && response.data) {
        setResults(groupSearchItems(response.data));
      }
    } catch (error) {
      logError('Search failed', error);
      setSearchError(t('error_message'));
      toast.error(t('toast.search_failed'));
    } finally {
      setIsLoading(false);
    }
  }, [toast, advancedFilters]);

  useEffect(() => {
    const urlQuery = searchParams.get('q');
    if (urlQuery) {
      setQuery(urlQuery);
      performSearch(urlQuery);
    }
  }, [searchParams]); // eslint-disable-line react-hooks/exhaustive-deps

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    if (query.trim()) {
      setSearchParams({ q: query.trim() });
    }
  }

  function handleRunSavedSearch(queryParams: Record<string, string>) {
    const savedQuery = queryParams.q || '';
    setQuery(savedQuery);
    if (queryParams.type) {
      setActiveTab(queryParams.type as SearchTab);
    }
    // Build filters from saved search params
    const savedFilters: AdvancedFilters = {
      type: queryParams.type || 'all',
      category_id: queryParams.category_id || '',
      date_from: queryParams.date_from || '',
      date_to: queryParams.date_to || '',
      sort: queryParams.sort || 'relevance',
      skills: queryParams.skills || '',
      location: queryParams.location || '',
    };
    setAdvancedFilters(savedFilters);
    setSearchParams(savedQuery ? { q: savedQuery } : {});
    performSearch(savedQuery, savedFilters);
  }

  const totalResults =
    results.listings.length +
    results.users.length +
    results.events.length +
    results.groups.length;

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
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Search Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <Search className="w-7 h-7 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          {t('title')}
        </h1>
        <p className="text-theme-muted mt-1">{t('subtitle')}</p>
      </div>

      {/* Search Form */}
      <GlassCard className="p-4">
        <form onSubmit={handleSearch} className="flex flex-col sm:flex-row gap-3">
          <Input
            placeholder={t('search_placeholder')}
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            startContent={<Search className="w-5 h-5 text-theme-subtle" />}
            aria-label={t('search_placeholder')}
            size="lg"
            classNames={{
              base: 'flex-1',
              input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
          />
          <Button
            type="submit"
            color="primary"
            size="lg"
            isLoading={isLoading}
            className="sm:w-auto w-full"
          >
            {t('search_button', 'Search')}
          </Button>
        </form>
      </GlassCard>

      {/* Advanced Filters */}
      <AdvancedSearchFilters
        filters={advancedFilters}
        onChange={setAdvancedFilters}
        onApply={() => performSearch(query, advancedFilters)}
        onReset={() => performSearch(query, { ...defaultFilters })}
      />

      {/* Saved Searches */}
      <SavedSearches
        onRunSearch={handleRunSavedSearch}
        currentQuery={hasSearched ? query : undefined}
        currentFilters={hasSearched ? {
          ...(advancedFilters.type !== 'all' ? { type: advancedFilters.type } : {}),
          ...(advancedFilters.category_id ? { category_id: advancedFilters.category_id } : {}),
          ...(advancedFilters.skills ? { skills: advancedFilters.skills } : {}),
        } : undefined}
      />

      {/* Results */}
      {hasSearched && (
        <>
          {/* Algorithm indicator */}
          <div className="flex justify-end">
            <AlgorithmLabel area="matching" />
          </div>

          {/* Tabs */}
          <Tabs
            selectedKey={activeTab}
            onSelectionChange={(key) => setActiveTab(key as SearchTab)}
            classNames={{
              tabList: 'bg-theme-elevated p-1 rounded-lg',
              cursor: 'bg-theme-hover',
              tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
            }}
          >
            <Tab key="all" title={t('tab_all', { count: totalResults })} />
            <Tab key="listings" title={t('tab_listings', { count: results.listings.length })} />
            <Tab key="users" title={t('tab_members', { count: results.users.length })} />
            <Tab key="events" title={t('tab_events', { count: results.events.length })} />
            <Tab key="groups" title={t('tab_groups', { count: results.groups.length })} />
          </Tabs>

          {/* Results Content */}
          {searchError ? (
            <GlassCard className="p-8 text-center">
              <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
              <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('error_title')}</h2>
              <p className="text-theme-muted mb-4">{searchError}</p>
              <Button
                className="bg-linear-to-r from-indigo-500 to-purple-600 text-white"
                onPress={() => performSearch(query)}
              >
                {t('try_again')}
              </Button>
            </GlassCard>
          ) : isLoading ? (
            <div aria-label="Loading search results" aria-busy="true" className="space-y-4">
              {Array.from({ length: 6 }).map((_, i) => (
                <GlassCard key={i} className="p-4">
                  <div className="flex items-center gap-3">
                    <Skeleton className="rounded-full flex-shrink-0">
                      <div className="w-10 h-10 rounded-full bg-default-300" />
                    </Skeleton>
                    <div className="flex-1 space-y-2">
                      <Skeleton className="rounded-lg">
                        <div className="h-4 rounded-lg bg-default-300 w-2/3" />
                      </Skeleton>
                      <Skeleton className="rounded-lg">
                        <div className="h-3 rounded-lg bg-default-200 w-1/2" />
                      </Skeleton>
                    </div>
                  </div>
                </GlassCard>
              ))}
            </div>
          ) : totalResults === 0 ? (
            <EmptyState
              icon={<Search className="w-12 h-12" />}
              title={t('no_results_title')}
              description={t('no_results_desc', { query })}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-6"
            >
              {/* Listings */}
              {(activeTab === 'all' || activeTab === 'listings') && results.listings.length > 0 && (
                <section>
                  {activeTab === 'all' && (
                    <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                      <ListTodo className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                      {t('section_listings', { count: results.listings.length })}
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.listings.slice(0, activeTab === 'all' ? 4 : undefined).map((listing) => (
                      <motion.div key={listing.id} variants={itemVariants}>
                        <Link to={tenantPath(`/listings/${listing.id}`)}>
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
                              <span className={`
                                text-xs px-2 py-1 rounded-full self-start
                                ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'}
                              `}>
                                {listing.type === 'offer' ? t('listing_offering') : t('listing_requesting')}
                              </span>
                              {listing.is_featured && <FeaturedBadge />}
                              <h3 className="font-semibold text-theme-primary mt-2">{listing.title}</h3>
                              <p className="text-sm text-theme-subtle line-clamp-2 mt-1">{listing.description}</p>
                              <div className="flex items-center gap-2 mt-3 text-xs text-theme-subtle">
                                <Clock className="w-3 h-3" aria-hidden="true" />
                                {listing.hours_estimate ?? listing.estimated_hours ?? '—'}h
                              </div>
                            </div>
                          </GlassCard>
                        </Link>
                      </motion.div>
                    ))}
                  </div>
                </section>
              )}

              {/* Users */}
              {(activeTab === 'all' || activeTab === 'users') && results.users.length > 0 && (
                <section>
                  {activeTab === 'all' && (
                    <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                      <User className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                      {t('section_members', { count: results.users.length })}
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.users.slice(0, activeTab === 'all' ? 4 : undefined).map((user) => (
                      <motion.div key={user.id} variants={itemVariants}>
                        <Link to={tenantPath(`/profile/${user.id}`)}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform">
                            <div className="flex items-center gap-4">
                              <Avatar
                                src={resolveAvatarUrl(user.avatar)}
                                name={user.name}
                                size="lg"
                                className="ring-2 ring-theme-default"
                              />
                              <div>
                                <h3 className="font-semibold text-theme-primary">{user.name}</h3>
                                {user.tagline && (
                                  <p className="text-sm text-theme-subtle">{user.tagline}</p>
                                )}
                                {user.location && (
                                  <p className="text-xs text-theme-subtle flex items-center gap-1 mt-1">
                                    <MapPin className="w-3 h-3" aria-hidden="true" />
                                    {user.location}
                                  </p>
                                )}
                              </div>
                            </div>
                          </GlassCard>
                        </Link>
                      </motion.div>
                    ))}
                  </div>
                </section>
              )}

              {/* Events */}
              {(activeTab === 'all' || activeTab === 'events') && results.events.length > 0 && (
                <section>
                  {activeTab === 'all' && (
                    <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                      <Calendar className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                      {t('section_events', { count: results.events.length })}
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.events.slice(0, activeTab === 'all' ? 4 : undefined).map((event) => (
                      <motion.div key={event.id} variants={itemVariants}>
                        <Link to={tenantPath(`/events/${event.id}`)}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform">
                            <h3 className="font-semibold text-theme-primary">{event.title}</h3>
                            <p className="text-sm text-theme-subtle line-clamp-2 mt-1">{event.description}</p>
                            <div className="flex items-center gap-4 mt-3 text-xs text-theme-subtle">
                              <span className="flex items-center gap-1">
                                <Calendar className="w-3 h-3" aria-hidden="true" />
                                {event.start_date ? new Date(event.start_date).toLocaleDateString() : '—'}
                              </span>
                              {event.location && (
                                <span className="flex items-center gap-1">
                                  <MapPin className="w-3 h-3" aria-hidden="true" />
                                  {event.location}
                                </span>
                              )}
                            </div>
                          </GlassCard>
                        </Link>
                      </motion.div>
                    ))}
                  </div>
                </section>
              )}

              {/* Groups */}
              {(activeTab === 'all' || activeTab === 'groups') && results.groups.length > 0 && (
                <section>
                  {activeTab === 'all' && (
                    <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                      <Users className="w-5 h-5 text-purple-600 dark:text-purple-400" aria-hidden="true" />
                      {t('section_groups', { count: results.groups.length })}
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.groups.slice(0, activeTab === 'all' ? 4 : undefined).map((group) => (
                      <motion.div key={group.id} variants={itemVariants}>
                        <Link to={tenantPath(`/groups/${group.id}`)}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform">
                            <h3 className="font-semibold text-theme-primary">{group.name}</h3>
                            <p className="text-sm text-theme-subtle line-clamp-2 mt-1">{group.description}</p>
                            <div className="flex items-center gap-2 mt-3 text-xs text-theme-subtle">
                              <Users className="w-3 h-3" aria-hidden="true" />
                              {t('members_count', { count: group.members_count })}
                            </div>
                          </GlassCard>
                        </Link>
                      </motion.div>
                    ))}
                  </div>
                </section>
              )}
            </motion.div>
          )}
        </>
      )}

      {/* Initial State */}
      {!hasSearched && (
        <GlassCard className="p-12 text-center">
          <Search className="w-16 h-16 text-theme-subtle mx-auto mb-4" />
          <h3 className="text-lg font-medium text-theme-primary mb-2">{t('initial_title')}</h3>
          <p className="text-theme-subtle">
            {t('initial_desc')}
          </p>
        </GlassCard>
      )}
    </div>
  );
}

export default SearchPage;
