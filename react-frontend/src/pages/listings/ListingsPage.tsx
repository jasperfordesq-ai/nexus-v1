/**
 * Listings Page - Browse all listings
 */

import { useState, useEffect, useCallback, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem, Avatar } from '@heroui/react';
import {
  Search,
  Plus,
  Filter,
  Grid,
  List,
  ListTodo,
  MapPin,
  Tag,
  Clock,
  AlertTriangle,
  RefreshCw,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { PageMeta } from '@/components/seo';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Listing, Category } from '@/types/api';

type ListingType = 'all' | 'offer' | 'request';
type ViewMode = 'grid' | 'list';

export function ListingsPage() {
  usePageTitle('Listings');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [listings, setListings] = useState<Listing[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [selectedType, setSelectedType] = useState<ListingType>((searchParams.get('type') as ListingType) || 'all');
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') || '');
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);

  const loadListings = useCallback(async (reset = false) => {
    try {
      setIsLoading(true);
      if (reset) setLoadError(null);
      const params = new URLSearchParams();

      if (searchQuery) params.set('q', searchQuery);
      if (selectedType !== 'all') params.set('type', selectedType);
      if (selectedCategory) params.set('category', selectedCategory);
      if (!reset && cursor) params.set('cursor', cursor);
      params.set('limit', '20');

      const response = await api.get<Listing[]>(`/v2/listings?${params}`);

      if (response.success && response.data) {
        if (reset) {
          setListings(response.data);
        } else {
          setListings((prev) => [...prev, ...response.data!]);
        }

        // Handle pagination meta if present
        const nextCursor = response.meta?.cursor ?? null;
        setCursor(nextCursor);
        setHasMore(response.meta?.has_more ?? false);
      } else {
        if (reset) {
          setListings([]);
        }
        setHasMore(false);
      }
    } catch (error) {
      logError('Failed to load listings', error);
      if (reset && listings.length === 0) {
        setLoadError('Failed to load listings. Please try again.');
      } else {
        toast.error('Failed to load more listings');
      }
    } finally {
      setIsLoading(false);
    }
  }, [searchQuery, selectedType, selectedCategory, cursor]);

  const loadCategories = useCallback(async () => {
    try {
      const response = await api.get<Category[]>('/v2/categories');
      if (response.success && response.data) {
        setCategories(response.data);
      }
    } catch (error) {
      logError('Failed to load categories', error);
    }
  }, []);

  useEffect(() => {
    loadCategories();
  }, [loadCategories]);

  useEffect(() => {
    loadListings(true);

    // Update URL params
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedType !== 'all') params.set('type', selectedType);
    if (selectedCategory) params.set('category', selectedCategory);
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedType, selectedCategory]);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    loadListings(true);
  }

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
    <>
      <PageMeta
        title="Listings"
        description="Browse services and requests from the community. Find offers and requests for time-banked services."
        keywords="listings, services, offers, requests, time banking"
      />
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
              <ListTodo className="w-7 h-7 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              Listings
            </h1>
            <p className="text-theme-muted mt-1">Browse services and requests from the community</p>
          </div>
        {isAuthenticated && (
          <Link to={tenantPath('/listings/create')}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" />}
            >
              New Listing
            </Button>
          </Link>
        )}
      </div>

      {/* Filters */}
      <GlassCard className="p-4">
        <form onSubmit={handleSearch} className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder="Search listings..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          <div className="flex flex-col sm:flex-row flex-wrap gap-3">
            <Select
              placeholder="Type"
              selectedKeys={selectedType ? [selectedType] : []}
              onChange={(e) => setSelectedType(e.target.value as ListingType)}
              className="w-full sm:w-36"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Filter className="w-4 h-4 text-theme-subtle" />}
            >
              <SelectItem key="all">All Types</SelectItem>
              <SelectItem key="offer">Offers</SelectItem>
              <SelectItem key="request">Requests</SelectItem>
            </Select>

            <Select
              placeholder="Category"
              selectedKeys={selectedCategory ? [selectedCategory] : []}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="w-full sm:w-44"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" />}
              items={[{ slug: '', name: 'All Categories' }, ...categories]}
            >
              {(cat) => <SelectItem key={cat.slug}>{cat.name}</SelectItem>}
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
            </div>
          </div>
        </form>
      </GlassCard>

      {/* Listings Grid/List */}
      {isLoading && listings.length === 0 ? (
        <div className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4'}>
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <GlassCard key={i} className="p-6 animate-pulse">
              <div className="h-4 bg-theme-hover rounded w-3/4 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-full mb-2" />
              <div className="h-3 bg-theme-hover rounded w-2/3" />
            </GlassCard>
          ))}
        </div>
      ) : loadError ? (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Listings</h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadListings(true)}
          >
            Try Again
          </Button>
        </GlassCard>
      ) : listings.length === 0 ? (
        <EmptyState
          icon={<Search className="w-12 h-12" />}
          title="No listings found"
          description="Try adjusting your filters or create a new listing"
          action={
            isAuthenticated && (
              <Link to={tenantPath('/listings/create')}>
                <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                  Create Listing
                </Button>
              </Link>
            )
          }
        />
      ) : (
        <>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4'}
          >
            {listings.map((listing) => (
              <motion.div key={listing.id} variants={itemVariants}>
                <ListingCard listing={listing} viewMode={viewMode} />
              </motion.div>
            ))}
          </motion.div>

          {/* Load More */}
          {hasMore && (
            <div className="text-center pt-4">
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                onClick={() => loadListings()}
                isLoading={isLoading}
              >
                Load More
              </Button>
            </div>
          )}
        </>
      )}
      </div>
    </>
  );
}

interface ListingCardProps {
  listing: Listing;
  viewMode: ViewMode;
}

const ListingCard = memo(function ListingCard({ listing, viewMode }: ListingCardProps) {
  const { tenantPath } = useTenant();
  const isGrid = viewMode === 'grid';
  const hours = listing.estimated_hours || listing.hours_estimate;
  const avatarSrc = resolveAvatarUrl(listing.author_avatar || listing.user?.avatar);

  if (!isGrid) {
    // ─── List View ───
    return (
      <Link to={tenantPath(`/listings/${listing.id}`)}>
        <GlassCard className="p-4 hover:bg-theme-hover transition-colors">
          <div className="flex items-start gap-4">
            <Avatar
              src={avatarSrc}
              name={listing.author_name || 'User'}
              size="md"
              className="flex-shrink-0 ring-2 ring-theme-muted/20"
            />
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-1">
                <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${
                  listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                }`}>
                  {listing.type === 'offer' ? 'Offering' : 'Requesting'}
                </span>
                {listing.category_name && (
                  <span className="text-[10px] px-2 py-0.5 rounded-full bg-theme-hover text-theme-muted">
                    {listing.category_name}
                  </span>
                )}
              </div>
              <h3 className="font-semibold text-theme-primary truncate">{listing.title}</h3>
              <p className="text-theme-muted text-sm line-clamp-1 mt-0.5">{listing.description}</p>
            </div>
            <div className="flex flex-col items-end gap-1 text-xs text-theme-subtle flex-shrink-0">
              {hours && (
                <span className="flex items-center gap-1">
                  <Clock className="w-3 h-3" aria-hidden="true" />
                  {hours}h
                </span>
              )}
              {listing.location && (
                <span className="flex items-center gap-1">
                  <MapPin className="w-3 h-3" aria-hidden="true" />
                  <span className="truncate max-w-[80px]">{listing.location}</span>
                </span>
              )}
            </div>
          </div>
        </GlassCard>
      </Link>
    );
  }

  // ─── Grid View ───
  return (
    <Link to={tenantPath(`/listings/${listing.id}`)}>
      <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full flex flex-col">
        {/* Type + Category Badges */}
        <div className="flex items-center gap-2 mb-3">
          <span className={`text-xs px-2 py-1 rounded-full font-medium ${
            listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
          }`}>
            {listing.type === 'offer' ? 'Offering' : 'Requesting'}
          </span>
          {listing.category_name && (
            <span className="text-xs px-2 py-1 rounded-full bg-theme-hover text-theme-muted">
              {listing.category_name}
            </span>
          )}
        </div>

        {/* Title & Description */}
        <h3 className="font-semibold text-theme-primary text-lg mb-2 line-clamp-2">{listing.title}</h3>
        <p className="text-theme-muted text-sm line-clamp-2 mb-4 flex-1">{listing.description}</p>

        {/* Footer: Author + Meta */}
        <div className="flex items-center justify-between pt-3 border-t border-theme-default">
          <div className="flex items-center gap-2 min-w-0">
            <Avatar
              src={avatarSrc}
              name={listing.author_name || 'User'}
              size="sm"
              className="flex-shrink-0 w-6 h-6"
            />
            <span className="text-sm text-theme-subtle truncate">{listing.author_name}</span>
          </div>
          <div className="flex items-center gap-3 text-xs text-theme-subtle flex-shrink-0">
            {hours && (
              <span className="flex items-center gap-1" aria-label={`${hours} hours estimated`}>
                <Clock className="w-3 h-3" aria-hidden="true" />
                {hours}h
              </span>
            )}
            {listing.location && (
              <span className="flex items-center gap-1" aria-label={`Location: ${listing.location}`}>
                <MapPin className="w-3 h-3" aria-hidden="true" />
              </span>
            )}
          </div>
        </div>
      </GlassCard>
    </Link>
  );
});

export default ListingsPage;
