/**
 * Listings Page - Browse all listings
 */

import { useState, useEffect, useCallback, memo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem } from '@heroui/react';
import {
  Search,
  Plus,
  Filter,
  Grid,
  List,
  ListTodo,
  MapPin,
  User,
  Tag,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { PageMeta } from '@/components/seo';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Listing, Category } from '@/types/api';

type ListingType = 'all' | 'offer' | 'request';
type ViewMode = 'grid' | 'list';

export function ListingsPage() {
  const { isAuthenticated } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const [listings, setListings] = useState<Listing[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [selectedType, setSelectedType] = useState<ListingType>((searchParams.get('type') as ListingType) || 'all');
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') || '');
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);

  const loadListings = useCallback(async (reset = false) => {
    try {
      setIsLoading(true);
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
            <h1 className="text-2xl font-bold text-white flex items-center gap-3">
              <ListTodo className="w-7 h-7 text-emerald-400" />
              Listings
            </h1>
            <p className="text-white/60 mt-1">Browse services and requests from the community</p>
          </div>
        {isAuthenticated && (
          <Link to="/listings/create">
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
              startContent={<Search className="w-4 h-4 text-white/40" />}
              classNames={{
                input: 'bg-transparent text-white placeholder:text-white/40',
                inputWrapper: 'bg-white/5 border-white/10 hover:bg-white/10',
              }}
            />
          </div>

          <div className="flex flex-wrap gap-3">
            <Select
              placeholder="Type"
              selectedKeys={selectedType ? [selectedType] : []}
              onChange={(e) => setSelectedType(e.target.value as ListingType)}
              className="w-36"
              classNames={{
                trigger: 'bg-white/5 border-white/10 hover:bg-white/10',
                value: 'text-white',
              }}
              startContent={<Filter className="w-4 h-4 text-white/40" />}
            >
              <SelectItem key="all">All Types</SelectItem>
              <SelectItem key="offer">Offers</SelectItem>
              <SelectItem key="request">Requests</SelectItem>
            </Select>

            <Select
              placeholder="Category"
              selectedKeys={selectedCategory ? [selectedCategory] : []}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="w-44"
              classNames={{
                trigger: 'bg-white/5 border-white/10 hover:bg-white/10',
                value: 'text-white',
              }}
              startContent={<Tag className="w-4 h-4 text-white/40" />}
              items={[{ slug: '', name: 'All Categories' }, ...categories]}
            >
              {(cat) => <SelectItem key={cat.slug}>{cat.name}</SelectItem>}
            </Select>

            <div className="flex rounded-lg overflow-hidden border border-white/10">
              <button
                type="button"
                onClick={() => setViewMode('grid')}
                className={`p-2 ${viewMode === 'grid' ? 'bg-white/10' : 'bg-white/5 hover:bg-white/10'}`}
              >
                <Grid className="w-4 h-4 text-white" />
              </button>
              <button
                type="button"
                onClick={() => setViewMode('list')}
                className={`p-2 ${viewMode === 'list' ? 'bg-white/10' : 'bg-white/5 hover:bg-white/10'}`}
              >
                <List className="w-4 h-4 text-white" />
              </button>
            </div>
          </div>
        </form>
      </GlassCard>

      {/* Listings Grid/List */}
      {isLoading && listings.length === 0 ? (
        <div className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4'}>
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <GlassCard key={i} className="p-6 animate-pulse">
              <div className="h-4 bg-white/10 rounded w-3/4 mb-3" />
              <div className="h-3 bg-white/10 rounded w-full mb-2" />
              <div className="h-3 bg-white/10 rounded w-2/3" />
            </GlassCard>
          ))}
        </div>
      ) : listings.length === 0 ? (
        <EmptyState
          icon={<Search className="w-12 h-12" />}
          title="No listings found"
          description="Try adjusting your filters or create a new listing"
          action={
            isAuthenticated && (
              <Link to="/listings/create">
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
                className="bg-white/5 text-white"
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
  const isGrid = viewMode === 'grid';

  return (
    <Link to={`/listings/${listing.id}`}>
      <GlassCard className={`p-5 hover:scale-[1.02] transition-transform ${isGrid ? '' : 'flex gap-6'}`}>
        <div className={isGrid ? '' : 'flex-1'}>
          {/* Type Badge */}
          <div className="flex items-center gap-2 mb-3">
            <span className={`
              text-xs px-2 py-1 rounded-full
              ${listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'}
            `}>
              {listing.type === 'offer' ? 'Offering' : 'Requesting'}
            </span>
            {listing.category_name && (
              <span className="text-xs px-2 py-1 rounded-full bg-white/10 text-white/60">
                {listing.category_name}
              </span>
            )}
          </div>

          {/* Title & Description */}
          <h3 className="font-semibold text-white text-lg mb-2">{listing.title}</h3>
          <p className="text-white/60 text-sm line-clamp-2 mb-4">{listing.description}</p>

          {/* Meta Info */}
          <div className="flex flex-wrap items-center gap-4 text-sm text-white/50">
            {listing.author_name && (
              <div className="flex items-center gap-1">
                <User className="w-4 h-4" />
                <span>{listing.author_name}</span>
              </div>
            )}
            {listing.location && (
              <div className="flex items-center gap-1">
                <MapPin className="w-4 h-4" />
                <span className="truncate max-w-[120px]">{listing.location}</span>
              </div>
            )}
          </div>
        </div>
      </GlassCard>
    </Link>
  );
});

export default ListingsPage;
