/**
 * Federation Listings Page - Browse listings from partner communities
 *
 * Features:
 * - Search input with debounce
 * - Type filter (All / Offers / Requests) as Chip group
 * - Partner community Select dropdown
 * - Responsive grid (1/2/3 cols)
 * - Cursor-based pagination with Load More
 * - Loading skeletons and error states
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Chip,
  Avatar,
} from '@heroui/react';
import {
  Search,
  Globe,
  Hand,
  Clock,
  MapPin,
  AlertTriangle,
  RefreshCw,
  ListTodo,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { FederatedListing, FederationPartner } from '@/types/api';

type ListingTypeFilter = 'all' | 'offer' | 'request';

const SEARCH_DEBOUNCE_MS = 300;
const PER_PAGE = 20;

export function FederationListingsPage() {
  usePageTitle('Federated Listings');
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  // Data
  const [listings, setListings] = useState<FederatedListing[]>([]);
  const [partners, setPartners] = useState<FederationPartner[]>([]);

  // State
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);

  // Filters
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [selectedType, setSelectedType] = useState<ListingTypeFilter>(
    (searchParams.get('type') as ListingTypeFilter) || 'all'
  );
  const [selectedPartner, setSelectedPartner] = useState(
    searchParams.get('partner_id') || ''
  );

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // ── Debounce search ──────────────────────────────────────────────────────
  useEffect(() => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [searchQuery]);

  // ── Load partners for dropdown ───────────────────────────────────────────
  const loadPartners = useCallback(async () => {
    try {
      const response = await api.get<FederationPartner[]>('/v2/federation/partners');
      if (response.success && response.data) {
        setPartners(response.data);
      }
    } catch (error) {
      logError('Failed to load federation partners for filter', error);
    }
  }, []);

  useEffect(() => {
    loadPartners();
  }, [loadPartners]);

  // ── Load listings ────────────────────────────────────────────────────────
  const loadListings = useCallback(
    async (append = false) => {
      try {
        if (!append) {
          setIsLoading(true);
          setLoadError(null);
        } else {
          setIsLoadingMore(true);
        }

        const params = new URLSearchParams();
        if (debouncedQuery) params.set('q', debouncedQuery);
        if (selectedType !== 'all') params.set('type', selectedType);
        if (selectedPartner) params.set('partner_id', selectedPartner);
        if (append && cursor) params.set('cursor', cursor);
        params.set('per_page', String(PER_PAGE));

        const response = await api.get<FederatedListing[]>(
          `/v2/federation/listings?${params}`
        );

        if (response.success && response.data) {
          if (append) {
            setListings((prev) => [...prev, ...response.data!]);
          } else {
            setListings(response.data);
          }
          const nextCursor = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
          setCursor(nextCursor);
          setHasMore(response.meta?.has_more ?? response.data.length >= PER_PAGE);
        } else {
          if (!append) setListings([]);
          setHasMore(false);
        }
      } catch (error) {
        logError('Failed to load federated listings', error);
        if (!append) {
          setLoadError('Failed to load federated listings. Please try again.');
        } else {
          toast.error('Failed to load more listings');
        }
      } finally {
        setIsLoading(false);
        setIsLoadingMore(false);
      }
    },
    [debouncedQuery, selectedType, selectedPartner, cursor, toast]
  );

  // Reload on filter change
  useEffect(() => {
    setCursor(null);
    setHasMore(false);
    loadListings(false);
  }, [debouncedQuery, selectedType, selectedPartner]); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedType !== 'all') params.set('type', selectedType);
    if (selectedPartner) params.set('partner_id', selectedPartner);
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedType, selectedPartner, setSearchParams]);

  function handleLoadMore() {
    if (!isLoadingMore && hasMore) {
      loadListings(true);
    }
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
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: 'Federation', href: '/federation' },
          { label: 'Listings' },
        ]}
      />

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <ListTodo className="w-7 h-7 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
          Federated Listings
        </h1>
        <p className="text-theme-muted mt-1">
          Services offered across partner communities
        </p>
      </div>

      {/* Filter Bar */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          {/* Search */}
          <div className="flex-1">
            <Input
              placeholder="Search federated listings..."
              aria-label="Search federated listings"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          {/* Partner filter */}
          <Select
            placeholder="All Communities"
            aria-label="Filter by community"
            selectedKeys={selectedPartner ? [selectedPartner] : []}
            onChange={(e) => setSelectedPartner(e.target.value)}
            className="w-full lg:w-52"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={<Globe className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          >
            {[
              { id: '', name: 'All Communities' },
              ...partners.map((p) => ({ id: String(p.id), name: p.name })),
            ].map((item) => (
              <SelectItem key={item.id}>{item.name}</SelectItem>
            ))}
          </Select>
        </div>

        {/* Type Chips */}
        <div className="flex flex-wrap gap-2 mt-3" role="group" aria-label="Filter by listing type">
          {[
            { key: 'all' as const, label: 'All' },
            { key: 'offer' as const, label: 'Offers' },
            { key: 'request' as const, label: 'Requests' },
          ].map((item) => {
            const isActive = selectedType === item.key;
            return (
              <Chip
                key={item.key}
                variant={isActive ? 'solid' : 'flat'}
                className={
                  isActive
                    ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white cursor-pointer'
                    : 'bg-theme-elevated text-theme-muted cursor-pointer hover:bg-theme-hover'
                }
                onClick={() => setSelectedType(item.key)}
                aria-pressed={isActive}
              >
                {item.label}
              </Chip>
            );
          })}
        </div>
      </GlassCard>

      {/* Loading State */}
      {isLoading && listings.length === 0 && (
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-4 bg-theme-hover rounded w-2/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-full mb-2" />
              <div className="h-3 bg-theme-hover rounded w-3/4 mb-4" />
              <div className="h-3 bg-theme-hover rounded w-1/2" />
            </GlassCard>
          ))}
        </div>
      )}

      {/* Error State */}
      {!isLoading && loadError && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            Unable to Load Listings
          </h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => { setCursor(null); loadListings(false); }}
          >
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Empty State */}
      {!isLoading && !loadError && listings.length === 0 && (
        <EmptyState
          icon={<Search className="w-12 h-12" />}
          title="No federated listings found"
          description="Try adjusting your filters or check back later for new listings from partner communities."
        />
      )}

      {/* Listings Grid */}
      {!isLoading && !loadError && listings.length > 0 && (
        <>
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4"
          >
            {listings.map((listing) => (
              <motion.div key={`${listing.timebank.id}-${listing.id}`} variants={itemVariants}>
                <FederatedListingCard listing={listing} />
              </motion.div>
            ))}
          </motion.div>

          {/* Load More */}
          {hasMore && (
            <div className="text-center pt-4">
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                onPress={handleLoadMore}
                isLoading={isLoadingMore}
              >
                Load More
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Federated Listing Card
// ─────────────────────────────────────────────────────────────────────────────

interface FederatedListingCardProps {
  listing: FederatedListing;
}

function FederatedListingCard({ listing }: FederatedListingCardProps) {
  const isOffer = listing.type === 'offer';
  const avatarSrc = resolveAvatarUrl(listing.author?.avatar);
  const imageSrc = listing.image_url ? resolveAssetUrl(listing.image_url) : null;

  return (
    <GlassCard className="p-5 hover:scale-[1.02] transition-transform h-full flex flex-col">
      {/* Image or Type Icon */}
      {imageSrc ? (
        <div className="w-full h-36 rounded-lg overflow-hidden mb-3 bg-theme-hover">
          <img
            src={imageSrc}
            alt={listing.title}
            className="w-full h-full object-cover"
            loading="lazy"
          />
        </div>
      ) : (
        <div
          className={`w-full h-20 rounded-lg mb-3 flex items-center justify-center ${
            isOffer
              ? 'bg-emerald-500/10'
              : 'bg-amber-500/10'
          }`}
        >
          <Hand
            className={`w-8 h-8 ${
              isOffer
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-amber-600 dark:text-amber-400'
            }`}
            aria-hidden="true"
          />
        </div>
      )}

      {/* Badges */}
      <div className="flex items-center gap-2 mb-2 flex-wrap">
        <Chip
          size="sm"
          variant="flat"
          className={
            isOffer
              ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
              : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
          }
        >
          {isOffer ? 'Offer' : 'Request'}
        </Chip>
        {listing.category_name && (
          <Chip size="sm" variant="flat" className="bg-theme-hover text-theme-muted">
            {listing.category_name}
          </Chip>
        )}
      </div>

      {/* Title & Description */}
      <h3 className="font-semibold text-theme-primary text-lg mb-1 line-clamp-2">
        {listing.title}
      </h3>
      <p className="text-theme-muted text-sm line-clamp-2 mb-3 flex-1">
        {listing.description}
      </p>

      {/* Meta */}
      <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-3">
        {listing.estimated_hours && (
          <span className="flex items-center gap-1">
            <Clock className="w-3 h-3" aria-hidden="true" />
            {listing.estimated_hours}h
          </span>
        )}
        {listing.location && (
          <span className="flex items-center gap-1">
            <MapPin className="w-3 h-3" aria-hidden="true" />
            <span className="truncate max-w-[100px]">{listing.location}</span>
          </span>
        )}
      </div>

      {/* Footer: Author + Community */}
      <div className="flex items-center justify-between pt-3 border-t border-theme-default">
        <div className="flex items-center gap-2 min-w-0">
          <Avatar
            src={avatarSrc}
            name={listing.author?.name || 'User'}
            size="sm"
            className="flex-shrink-0 w-6 h-6"
          />
          <span className="text-sm text-theme-subtle truncate">
            {listing.author?.name}
          </span>
        </div>
        <Chip
          size="sm"
          variant="flat"
          className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
          startContent={<Globe className="w-3 h-3" aria-hidden="true" />}
        >
          {listing.timebank.name}
        </Chip>
      </div>
    </GlassCard>
  );
}

export default FederationListingsPage;
