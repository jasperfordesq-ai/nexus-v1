/**
 * Search Page - Global search across content
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Tabs, Tab, Avatar } from '@heroui/react';
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
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import { usePageTitle } from '@/hooks';
import type { Listing, User as UserType, Event, Group } from '@/types/api';

interface SearchResults {
  listings: Listing[];
  users: UserType[];
  events: Event[];
  groups: Group[];
}

type SearchTab = 'all' | 'listings' | 'users' | 'events' | 'groups';

export function SearchPage() {
  usePageTitle('Search');
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

  const performSearch = useCallback(async (searchQuery: string) => {
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
      const response = await api.get<SearchResults>(`/v2/search?q=${encodeURIComponent(searchQuery)}`);
      if (response.success && response.data) {
        setResults(response.data);
      }
    } catch (error) {
      logError('Search failed', error);
      setSearchError('Search failed. Please try again.');
      toast.error('Search failed');
    } finally {
      setIsLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    const initialQuery = searchParams.get('q');
    if (initialQuery) {
      performSearch(initialQuery);
    }
  }, []);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    setSearchParams(query ? { q: query } : {});
    performSearch(query);
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
          Search
        </h1>
        <p className="text-theme-muted mt-1">Find listings, members, events, and groups</p>
      </div>

      {/* Search Form */}
      <GlassCard className="p-4">
        <form onSubmit={handleSearch} className="flex gap-3">
          <Input
            placeholder="Search for anything..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            startContent={<Search className="w-5 h-5 text-theme-subtle" />}
            size="lg"
            classNames={{
              input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
          />
        </form>
      </GlassCard>

      {/* Results */}
      {hasSearched && (
        <>
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
            <Tab key="all" title={`All (${totalResults})`} />
            <Tab key="listings" title={`Listings (${results.listings.length})`} />
            <Tab key="users" title={`Members (${results.users.length})`} />
            <Tab key="events" title={`Events (${results.events.length})`} />
            <Tab key="groups" title={`Groups (${results.groups.length})`} />
          </Tabs>

          {/* Results Content */}
          {searchError ? (
            <GlassCard className="p-8 text-center">
              <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
              <h2 className="text-lg font-semibold text-theme-primary mb-2">Search Error</h2>
              <p className="text-theme-muted mb-4">{searchError}</p>
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={() => performSearch(query)}
              >
                Try Again
              </Button>
            </GlassCard>
          ) : isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
                  <div className="h-4 bg-theme-hover rounded w-2/3" />
                </GlassCard>
              ))}
            </div>
          ) : totalResults === 0 ? (
            <EmptyState
              icon={<Search className="w-12 h-12" />}
              title="No results found"
              description={`No results for "${query}". Try a different search term.`}
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
                      Listings ({results.listings.length})
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.listings.slice(0, activeTab === 'all' ? 4 : undefined).map((listing) => (
                      <motion.div key={listing.id} variants={itemVariants}>
                        <Link to={tenantPath(`/listings/${listing.id}`)}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full">
                            <span className={`
                              text-xs px-2 py-1 rounded-full
                              ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'}
                            `}>
                              {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                            </span>
                            <h3 className="font-semibold text-theme-primary mt-2">{listing.title}</h3>
                            <p className="text-sm text-theme-subtle line-clamp-2 mt-1">{listing.description}</p>
                            <div className="flex items-center gap-2 mt-3 text-xs text-theme-subtle">
                              <Clock className="w-3 h-3" aria-hidden="true" />
                              {listing.hours_estimate ?? listing.estimated_hours ?? '—'}h
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
                      Members ({results.users.length})
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
                      Events ({results.events.length})
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
                                {new Date(event.start_date).toLocaleDateString()}
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
                      Groups ({results.groups.length})
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
                              {group.members_count} members
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
          <h3 className="text-lg font-medium text-theme-primary mb-2">Start searching</h3>
          <p className="text-theme-subtle">
            Enter a search term to find listings, members, events, and groups
          </p>
        </GlassCard>
      )}
    </div>
  );
}

export default SearchPage;
