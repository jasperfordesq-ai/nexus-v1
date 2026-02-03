/**
 * Listings Page - Browse service offers and requests
 *
 * Features:
 * - Filter tabs: All / Offers / Requests
 * - Search input with debounce
 * - Load more pagination
 * - URL query string sync for filters
 * - Link to listing detail pages
 */

import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Chip,
  Spinner,
  Avatar,
  Button,
  Tabs,
  Tab,
  Input,
} from '@heroui/react';
import { getListings, type Listing, ApiClientError } from '../api';
import { useTenant } from '../tenant';

// ===========================================
// HELPERS
// ===========================================

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

// Search icon component
function SearchIcon({ className }: { className?: string }) {
  return (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
  );
}

// ===========================================
// LISTING CARD
// ===========================================

function ListingCard({ listing }: { listing: Listing }) {
  const tenant = useTenant();
  const timeUnit = tenant.config?.time_unit || 'hour';
  const timeUnitPlural = tenant.config?.time_unit_plural || 'hours';

  // Get author info - prefer new format, fallback to legacy user object
  const authorName = listing.author_name || (listing.user
    ? `${listing.user.first_name}${listing.user.last_name ? ` ${listing.user.last_name.charAt(0)}.` : ''}`
    : 'Unknown');
  const authorAvatar = listing.author_avatar || listing.user?.avatar_url;

  return (
    <Link to={`/listings/${listing.id}`} className="block">
      <Card className="hover:shadow-md transition-shadow cursor-pointer">
        <CardBody className="p-4">
          <div className="flex gap-4">
            {listing.image_url && (
              <img
                src={listing.image_url}
                alt={listing.title}
                className="w-24 h-24 object-cover rounded-lg flex-shrink-0"
              />
            )}

            <div className="flex-1 min-w-0">
              <div className="flex items-start justify-between gap-2 mb-2">
                <h3 className="font-semibold text-lg truncate">{listing.title}</h3>
                <Chip
                  size="sm"
                  color={listing.type === 'offer' ? 'success' : 'warning'}
                  variant="flat"
                >
                  {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                </Chip>
              </div>

              {listing.description && (
                <p className="text-gray-600 text-sm line-clamp-2 mb-3">
                  {listing.description}
                </p>
              )}

              {/* Category badge */}
              {listing.category_name && (
                <Chip
                  size="sm"
                  variant="flat"
                  className="mb-2"
                  style={listing.category_color ? { backgroundColor: `${listing.category_color}20`, color: listing.category_color } : undefined}
                >
                  {listing.category_name}
                </Chip>
              )}

              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <Avatar
                    src={authorAvatar || undefined}
                    name={authorName}
                    size="sm"
                  />
                  <span className="text-sm text-gray-600">{authorName}</span>
                </div>

                <div className="flex items-center gap-3 text-sm text-gray-500">
                  {listing.time_credits !== undefined && listing.time_credits > 0 && (
                    <span className="font-medium text-primary">
                      {listing.time_credits} {listing.time_credits === 1 ? timeUnit : timeUnitPlural}
                    </span>
                  )}
                  <span>{formatDate(listing.created_at)}</span>
                </div>
              </div>

              {/* Location */}
              {listing.location && (
                <p className="text-xs text-gray-400 mt-2 truncate">
                  {listing.location}
                </p>
              )}
            </div>
          </div>
        </CardBody>
      </Card>
    </Link>
  );
}

// ===========================================
// LISTINGS PAGE
// ===========================================

type FilterType = 'all' | 'offer' | 'request';

export function ListingsPage() {
  const [searchParams, setSearchParams] = useSearchParams();

  // Get initial values from URL
  const initialType = (searchParams.get('type') as FilterType) || 'all';
  const initialSearch = searchParams.get('q') || '';

  const [listings, setListings] = useState<Listing[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<FilterType>(initialType);
  const [searchQuery, setSearchQuery] = useState(initialSearch);
  const [searchInput, setSearchInput] = useState(initialSearch);
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();

  // Update URL when filters change
  const updateUrl = useCallback((type: FilterType, query: string) => {
    const params = new URLSearchParams();
    if (type !== 'all') {
      params.set('type', type);
    }
    if (query) {
      params.set('q', query);
    }
    setSearchParams(params, { replace: true });
  }, [setSearchParams]);

  // Fetch listings
  const fetchListings = useCallback(async (reset = false) => {
    if (reset) {
      setLoading(true);
    } else {
      setLoadingMore(true);
    }
    setError(null);

    try {
      const response = await getListings({
        per_page: 20,
        cursor: reset ? undefined : cursor,
        type: filter === 'all' ? undefined : filter,
        q: searchQuery || undefined,
      });

      if (reset) {
        setListings(response.data);
      } else {
        setListings(prev => [...prev, ...response.data]);
      }

      setHasMore(response.meta.has_more);
      setCursor(response.meta.cursor);
    } catch (err) {
      if (err instanceof ApiClientError) {
        setError(err.message);
      } else {
        setError('Failed to load listings');
      }
    } finally {
      setLoading(false);
      setLoadingMore(false);
    }
  }, [cursor, filter, searchQuery]);

  // Fetch on mount and when filter/search changes
  useEffect(() => {
    fetchListings(true);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filter, searchQuery]);

  // Handle filter change
  const handleFilterChange = (newFilter: FilterType) => {
    setFilter(newFilter);
    updateUrl(newFilter, searchQuery);
  };

  // Handle search submit
  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSearchQuery(searchInput);
    updateUrl(filter, searchInput);
  };

  // Handle search clear
  const handleSearchClear = () => {
    setSearchInput('');
    setSearchQuery('');
    updateUrl(filter, '');
  };

  // Handle load more
  const handleLoadMore = () => {
    if (!loading && !loadingMore && hasMore) {
      fetchListings(false);
    }
  };

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader className="flex flex-col gap-4 px-6 pt-6">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 w-full">
            <div>
              <h1 className="text-2xl font-bold">Listings</h1>
              <p className="text-gray-500 text-sm">
                Browse services offered and requested by the community
              </p>
            </div>

            <Tabs
              selectedKey={filter}
              onSelectionChange={(key) => handleFilterChange(key as FilterType)}
              size="sm"
            >
              <Tab key="all" title="All" />
              <Tab key="offer" title="Offers" />
              <Tab key="request" title="Requests" />
            </Tabs>
          </div>

          {/* Search bar */}
          <form onSubmit={handleSearchSubmit} className="w-full">
            <Input
              placeholder="Search listings..."
              value={searchInput}
              onValueChange={setSearchInput}
              startContent={<SearchIcon className="w-4 h-4 text-gray-400" />}
              endContent={
                searchInput ? (
                  <Button
                    size="sm"
                    variant="light"
                    isIconOnly
                    onPress={handleSearchClear}
                    className="min-w-unit-6 w-unit-6 h-unit-6"
                  >
                    <span className="text-lg">&times;</span>
                  </Button>
                ) : null
              }
              classNames={{
                inputWrapper: 'bg-default-100',
              }}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  handleSearchSubmit(e);
                }
              }}
            />
            {searchQuery && (
              <p className="text-sm text-gray-500 mt-2">
                Showing results for "{searchQuery}"
                <Button
                  size="sm"
                  variant="light"
                  className="ml-2 text-primary"
                  onPress={handleSearchClear}
                >
                  Clear
                </Button>
              </p>
            )}
          </form>
        </CardHeader>

        <CardBody className="px-6 pb-6">
          {error && (
            <div className="bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded mb-4">
              {error}
              <Button
                size="sm"
                variant="light"
                className="ml-2"
                onPress={() => fetchListings(true)}
              >
                Retry
              </Button>
            </div>
          )}

          {loading && listings.length === 0 ? (
            <div className="flex justify-center py-12">
              <Spinner size="lg" />
            </div>
          ) : listings.length === 0 ? (
            <div className="text-center py-12">
              <svg
                className="w-16 h-16 mx-auto text-gray-300 mb-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                strokeWidth={1}
              >
                <path strokeLinecap="round" strokeLinejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
              </svg>
              <p className="text-gray-500 mb-2">No listings found</p>
              {(searchQuery || filter !== 'all') && (
                <p className="text-sm text-gray-400 mb-4">
                  Try adjusting your filters or search terms
                </p>
              )}
              <Button
                variant="flat"
                onPress={() => {
                  setFilter('all');
                  setSearchInput('');
                  setSearchQuery('');
                  updateUrl('all', '');
                }}
              >
                Clear Filters
              </Button>
            </div>
          ) : (
            <>
              <div className="space-y-4">
                {listings.map((listing) => (
                  <ListingCard key={listing.id} listing={listing} />
                ))}
              </div>

              {hasMore && (
                <div className="text-center mt-6">
                  <Button
                    variant="flat"
                    onPress={handleLoadMore}
                    isLoading={loadingMore}
                  >
                    Load More
                  </Button>
                </div>
              )}

              {!hasMore && listings.length > 0 && (
                <p className="text-center text-sm text-gray-400 mt-6">
                  Showing all {listings.length} listing{listings.length !== 1 ? 's' : ''}
                </p>
              )}
            </>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
