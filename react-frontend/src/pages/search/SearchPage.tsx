/**
 * Search Page - Global search across content
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Input, Tabs, Tab, Avatar } from '@heroui/react';
import {
  Search,
  ListTodo,
  User,
  Calendar,
  Users,
  Clock,
  MapPin,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Listing, User as UserType, Event, Group } from '@/types/api';

interface SearchResults {
  listings: Listing[];
  users: UserType[];
  events: Event[];
  groups: Group[];
}

type SearchTab = 'all' | 'listings' | 'users' | 'events' | 'groups';

export function SearchPage() {
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

  const performSearch = useCallback(async (searchQuery: string) => {
    if (!searchQuery.trim()) {
      setResults({ listings: [], users: [], events: [], groups: [] });
      setHasSearched(false);
      return;
    }

    try {
      setIsLoading(true);
      setHasSearched(true);
      const response = await api.get<SearchResults>(`/v2/search?q=${encodeURIComponent(searchQuery)}`);
      if (response.success && response.data) {
        setResults(response.data);
      }
    } catch (error) {
      logError('Search failed', error);
    } finally {
      setIsLoading(false);
    }
  }, []);

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
        <h1 className="text-2xl font-bold text-white flex items-center gap-3">
          <Search className="w-7 h-7 text-indigo-400" />
          Search
        </h1>
        <p className="text-white/60 mt-1">Find listings, members, events, and groups</p>
      </div>

      {/* Search Form */}
      <GlassCard className="p-4">
        <form onSubmit={handleSearch} className="flex gap-3">
          <Input
            placeholder="Search for anything..."
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            startContent={<Search className="w-5 h-5 text-white/40" />}
            size="lg"
            classNames={{
              input: 'bg-transparent text-white placeholder:text-white/40',
              inputWrapper: 'bg-white/5 border-white/10 hover:bg-white/10',
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
              tabList: 'bg-white/5 p-1 rounded-lg',
              cursor: 'bg-white/10',
              tab: 'text-white/60 data-[selected=true]:text-white',
            }}
          >
            <Tab key="all" title={`All (${totalResults})`} />
            <Tab key="listings" title={`Listings (${results.listings.length})`} />
            <Tab key="users" title={`Members (${results.users.length})`} />
            <Tab key="events" title={`Events (${results.events.length})`} />
            <Tab key="groups" title={`Groups (${results.groups.length})`} />
          </Tabs>

          {/* Results Content */}
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3, 4].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-5 bg-white/10 rounded w-1/3 mb-3" />
                  <div className="h-4 bg-white/10 rounded w-2/3" />
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
                    <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                      <ListTodo className="w-5 h-5 text-indigo-400" />
                      Listings ({results.listings.length})
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.listings.slice(0, activeTab === 'all' ? 4 : undefined).map((listing) => (
                      <motion.div key={listing.id} variants={itemVariants}>
                        <Link to={`/listings/${listing.id}`}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full">
                            <span className={`
                              text-xs px-2 py-1 rounded-full
                              ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
                            `}>
                              {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                            </span>
                            <h3 className="font-semibold text-white mt-2">{listing.title}</h3>
                            <p className="text-sm text-white/50 line-clamp-2 mt-1">{listing.description}</p>
                            <div className="flex items-center gap-2 mt-3 text-xs text-white/40">
                              <Clock className="w-3 h-3" />
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
                    <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                      <User className="w-5 h-5 text-emerald-400" />
                      Members ({results.users.length})
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.users.slice(0, activeTab === 'all' ? 4 : undefined).map((user) => (
                      <motion.div key={user.id} variants={itemVariants}>
                        <Link to={`/profile/${user.id}`}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform">
                            <div className="flex items-center gap-4">
                              <Avatar
                                src={resolveAvatarUrl(user.avatar)}
                                name={user.name}
                                size="lg"
                                className="ring-2 ring-white/20"
                              />
                              <div>
                                <h3 className="font-semibold text-white">{user.name}</h3>
                                {user.tagline && (
                                  <p className="text-sm text-white/50">{user.tagline}</p>
                                )}
                                {user.location && (
                                  <p className="text-xs text-white/40 flex items-center gap-1 mt-1">
                                    <MapPin className="w-3 h-3" />
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
                    <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                      <Calendar className="w-5 h-5 text-amber-400" />
                      Events ({results.events.length})
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.events.slice(0, activeTab === 'all' ? 4 : undefined).map((event) => (
                      <motion.div key={event.id} variants={itemVariants}>
                        <Link to={`/events/${event.id}`}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform">
                            <h3 className="font-semibold text-white">{event.title}</h3>
                            <p className="text-sm text-white/50 line-clamp-2 mt-1">{event.description}</p>
                            <div className="flex items-center gap-4 mt-3 text-xs text-white/40">
                              <span className="flex items-center gap-1">
                                <Calendar className="w-3 h-3" />
                                {new Date(event.start_date).toLocaleDateString()}
                              </span>
                              {event.location && (
                                <span className="flex items-center gap-1">
                                  <MapPin className="w-3 h-3" />
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
                    <h2 className="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                      <Users className="w-5 h-5 text-purple-400" />
                      Groups ({results.groups.length})
                    </h2>
                  )}
                  <div className="grid sm:grid-cols-2 gap-4">
                    {results.groups.slice(0, activeTab === 'all' ? 4 : undefined).map((group) => (
                      <motion.div key={group.id} variants={itemVariants}>
                        <Link to={`/groups/${group.id}`}>
                          <GlassCard className="p-5 hover:scale-[1.02] transition-transform">
                            <h3 className="font-semibold text-white">{group.name}</h3>
                            <p className="text-sm text-white/50 line-clamp-2 mt-1">{group.description}</p>
                            <div className="flex items-center gap-2 mt-3 text-xs text-white/40">
                              <Users className="w-3 h-3" />
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
          <Search className="w-16 h-16 text-white/20 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-white mb-2">Start searching</h3>
          <p className="text-white/50">
            Enter a search term to find listings, members, events, and groups
          </p>
        </GlassCard>
      )}
    </div>
  );
}

export default SearchPage;
