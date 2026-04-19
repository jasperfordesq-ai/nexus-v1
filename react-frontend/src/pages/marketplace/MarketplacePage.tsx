// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplacePage — Hub page for the commercial marketplace module.
 *
 * Features:
 * - Search bar with debounced query
 * - Horizontal category pills row (shared CategoryChips)
 * - Responsive listing card grid (shared MarketplaceListingGrid)
 * - Cursor-based "Load more" pagination
 * - Desktop sidebar: categories with counts, "Sell Something" CTA
 * - Featured listings section
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button, Input } from '@heroui/react';
import {
  Search,
  Plus,
  Tag,
  ShoppingBag,
  ChevronRight,
  Star,
  Grid3X3,
  Heart,
  Package,
  HandCoins,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import {
  MarketplaceListingGrid,
  MarketplaceListingGridSkeleton,
  CategoryChips,
} from '@/components/marketplace';
import type { MarketplaceListingItem, MarketplaceCategory } from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

/** Category shape as returned by the API (field names differ from shared type) */
interface ApiCategory {
  id: number;
  name: string;
  slug: string;
  icon: string | null;
  listing_count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ITEMS_PER_PAGE = 24;
const SEARCH_DEBOUNCE_MS = 300;

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Map API category shape to the shared MarketplaceCategory type */
function toSharedCategory(cat: ApiCategory): MarketplaceCategory {
  return {
    id: cat.id,
    name: cat.name,
    slug: cat.slug,
    icon: cat.icon ?? undefined,
    listing_count: cat.listing_count,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplacePage() {
  const { t } = useTranslation('marketplace');
  usePageTitle(t('page_title', 'Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  // State
  const [listings, setListings] = useState<MarketplaceListingItem[]>([]);
  const [categories, setCategories] = useState<MarketplaceCategory[]>([]);
  const [apiCategories, setApiCategories] = useState<ApiCategory[]>([]);
  const [featuredListings, setFeaturedListings] = useState<MarketplaceListingItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | undefined>(
    searchParams.get('category') ? Number(searchParams.get('category')) : undefined,
  );
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const featureEnabled = hasFeature('marketplace');

  // Debounce search
  useEffect(() => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);
    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [searchQuery]);

  // Load categories
  useEffect(() => {
    if (!featureEnabled) return;
    let cancelled = false;
    const load = async () => {
      try {
        const response = await api.get<ApiCategory[]>('/v2/marketplace/categories');
        if (!cancelled && response.success && response.data) {
          setApiCategories(response.data);
          setCategories(response.data.map(toSharedCategory));
        }
      } catch (err) {
        logError('Failed to load marketplace categories', err);
      }
    };
    load();
    return () => { cancelled = true; };
  }, [featureEnabled]);

  // Load featured
  useEffect(() => {
    if (!featureEnabled) return;
    let cancelled = false;
    const load = async () => {
      try {
        const response = await api.get<MarketplaceListingItem[]>('/v2/marketplace/listings/featured');
        if (!cancelled && response.success && response.data) {
          setFeaturedListings(response.data as MarketplaceListingItem[]);
        }
      } catch (err) {
        logError('Failed to load featured listings', err);
      }
    };
    load();
    return () => { cancelled = true; };
  }, [featureEnabled]);

  // Load listings
  const loadListings = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (selectedCategoryId != null) params.set('category_id', String(selectedCategoryId));
      params.set('limit', String(ITEMS_PER_PAGE));
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }

      const response = await api.get<MarketplaceListingItem[]>(`/v2/marketplace/listings?${params}`);
      if (response.success && response.data) {
        const mapped = response.data as MarketplaceListingItem[];
        if (append) {
          setListings((prev) => [...prev, ...mapped]);
        } else {
          setListings(mapped);
        }
        cursorRef.current = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
        setHasMore(response.meta?.has_more ?? response.data.length >= ITEMS_PER_PAGE);
      } else if (!append) {
        setError(t('hub.unable_to_load', 'Unable to load listings'));
      }
    } catch (err) {
      logError('Failed to load marketplace listings', err);
      if (!append) {
        setError(t('hub.unable_to_load', 'Unable to load listings'));
      } else {
        toast.error(t('hub.load_more_failed', 'Failed to load more listings'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, selectedCategoryId, toast, t])

  // Refetch on filter change
  useEffect(() => {
    if (!featureEnabled) return;
    cursorRef.current = null;
    setHasMore(true);
    loadListings();
  }, [debouncedQuery, selectedCategoryId, featureEnabled]); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (debouncedQuery) params.set('q', debouncedQuery);
    if (selectedCategoryId != null) params.set('category', String(selectedCategoryId));
    setSearchParams(params, { replace: true });
  }, [debouncedQuery, selectedCategoryId, setSearchParams]);

  // Save / Unsave handlers (separate for MarketplaceListingGrid onSave/onUnsave props)
  const handleSave = async (id: number) => {
    if (!isAuthenticated) {
      toast.error(t('common.sign_in_to_save', 'Please sign in to save listings'));
      return;
    }
    try {
      await api.post(`/v2/marketplace/listings/${id}/save`);
      const updateSaved = (list: MarketplaceListingItem[]) =>
        list.map((l) => (l.id === id ? { ...l, is_saved: true } : l));
      setListings(updateSaved);
      setFeaturedListings(updateSaved);
      toast.success(t('common.saved_for_later', 'Saved for later'));
    } catch (err) {
      logError('Failed to save listing', err);
      toast.error(t('common.save_failed', 'Failed to update saved status'));
    }
  };

  const handleUnsave = async (id: number) => {
    if (!isAuthenticated) {
      toast.error(t('common.sign_in_to_save', 'Please sign in to save listings'));
      return;
    }
    try {
      await api.delete(`/v2/marketplace/listings/${id}/save`);
      const updateSaved = (list: MarketplaceListingItem[]) =>
        list.map((l) => (l.id === id ? { ...l, is_saved: false } : l));
      setListings(updateSaved);
      setFeaturedListings(updateSaved);
      toast.success(t('common.removed_from_saved', 'Removed from saved'));
    } catch (err) {
      logError('Failed to unsave listing', err);
      toast.error(t('common.save_failed', 'Failed to update saved status'));
    }
  };

  // Feature gate -- rendered after all hooks
  if (!featureEnabled) {
    return (
      <div className="max-w-5xl mx-auto px-4 py-12">
        <EmptyState
          icon={<ShoppingBag className="w-8 h-8" />}
          title={t('hub_feature_gate.title', 'Marketplace Not Available')}
          description={t('hub_feature_gate.description', 'The marketplace feature is not enabled for this community.')}
        />
      </div>
    );
  }

  return (
    <>
      <PageMeta
        title={t('page_title', 'Marketplace')}
        description={t('meta_description', 'Buy, sell, and trade items in your community marketplace.')}
      />

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Hero Banner */}
        <div className="relative overflow-hidden rounded-2xl bg-linear-to-br from-teal-600 via-cyan-500 to-blue-500 p-6 sm:p-8">
          <div className="absolute -right-8 -bottom-8 w-40 h-40 rounded-full bg-white/10 blur-2xl pointer-events-none" aria-hidden="true" />
          <div className="absolute -left-4 -top-4 w-32 h-32 rounded-full bg-white/10 blur-2xl pointer-events-none" aria-hidden="true" />
          <div className="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <div className="flex items-center gap-3 mb-2">
                <div className="p-2 bg-white/20 rounded-xl backdrop-blur-sm">
                  <ShoppingBag className="w-6 h-6 text-white" aria-hidden="true" />
                </div>
                <h1 className="text-2xl sm:text-3xl font-bold text-white">{t('page_title', 'Marketplace')}</h1>
              </div>
              <p className="text-white/80 text-sm">{t('hub.subtitle', 'Buy, sell, and trade items in your community')}</p>
            </div>
            {isAuthenticated && (
              <Button
                as={Link}
                to={tenantPath('/marketplace/sell')}
                className="bg-white text-teal-700 font-semibold hover:bg-white/90 shrink-0 shadow-lg"
                startContent={<Plus className="w-4 h-4" />}
              >
                {t('hub.sell_something', 'Sell Something')}
              </Button>
            )}
          </div>
        </div>

        {/* Search bar */}
        <div className="max-w-2xl">
          <Input
            placeholder={t('hub.search_placeholder', 'Search marketplace...')}
            value={searchQuery}
            onValueChange={setSearchQuery}
            startContent={<Search className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            size="lg"
            variant="bordered"
            classNames={{ inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover', input: 'text-theme-primary placeholder:text-theme-subtle' }}
            isClearable
            onClear={() => setSearchQuery('')}
          />
        </div>

        {/* Category pills -- shared CategoryChips component */}
        {categories.length > 0 && (
          <CategoryChips
            categories={categories}
            activeId={selectedCategoryId}
            onSelect={(id) => setSelectedCategoryId(id ?? undefined)}
          />
        )}

        {/* Main content layout */}
        <div className="flex gap-6">
          {/* Listings grid */}
          <div className="flex-1 min-w-0">
            {/* Featured listings */}
            {featuredListings.length > 0 && !debouncedQuery && selectedCategoryId == null && (
              <div className="mb-8">
                <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                  <Star className="w-5 h-5 text-warning" />
                  {t('hub.featured_listings', 'Featured Listings')}
                </h2>
                <MarketplaceListingGrid
                  listings={featuredListings.slice(0, 4)}
                  onSave={handleSave}
                  onUnsave={handleUnsave}
                />
              </div>
            )}

            {/* All listings */}
            {isLoading ? (
              <MarketplaceListingGridSkeleton />
            ) : error ? (
              <GlassCard className="p-8 text-center">
                <p className="text-danger mb-4">{error}</p>
                <Button color="primary" variant="flat" onPress={() => loadListings()}>
                  {t('common.try_again', 'Try Again')}
                </Button>
              </GlassCard>
            ) : listings.length === 0 ? (
              <EmptyState
                icon={<ShoppingBag className="w-8 h-8" />}
                title={t('hub.no_listings_title', 'No Listings Found')}
                description={
                  debouncedQuery || selectedCategoryId != null
                    ? t('hub.no_listings_filtered', 'Try adjusting your search or filters.')
                    : t('hub.no_listings_empty', 'Be the first to list something for sale!')
                }
                action={
                  isAuthenticated
                    ? { label: t('hub.sell_something', 'Sell Something'), onClick: () => window.location.href = tenantPath('/marketplace/sell') }
                    : undefined
                }
              />
            ) : (
              <>
                <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
                  <Grid3X3 className="w-5 h-5 text-default-400" />
                  {debouncedQuery || selectedCategoryId != null ? t('hub.search_results', 'Search Results') : t('hub.latest_listings', 'Latest Listings')}
                </h2>
                <MarketplaceListingGrid
                  listings={listings}
                  onSave={handleSave}
                  onUnsave={handleUnsave}
                />

                {/* Load more */}
                {hasMore && (
                  <div className="flex justify-center mt-8">
                    <Button
                      variant="flat"
                      color="primary"
                      onPress={() => loadListings(true)}
                      isLoading={isLoadingMore}
                    >
                      {t('hub.load_more', 'Load More')}
                    </Button>
                  </div>
                )}
              </>
            )}
          </div>

          {/* Desktop sidebar */}
          <aside className="hidden lg:block w-72 shrink-0 space-y-6">
            {/* Sell CTA */}
            {isAuthenticated && (
              <GlassCard className="p-5 text-center space-y-3">
                <ShoppingBag className="w-10 h-10 text-primary mx-auto" />
                <h3 className="font-semibold text-theme-primary">{t('hub.sidebar_cta_title', 'Got something to sell?')}</h3>
                <p className="text-sm text-theme-muted">
                  {t('hub.sidebar_cta_description', 'List your items and reach your community.')}
                </p>
                <Button
                  as={Link}
                  to={tenantPath('/marketplace/sell')}
                  color="primary"
                  fullWidth
                  startContent={<Plus className="w-4 h-4" />}
                >
                  {t('hub.sell_something', 'Sell Something')}
                </Button>
              </GlassCard>
            )}

            {/* Categories list */}
            {apiCategories.length > 0 && (
              <GlassCard className="p-5">
                <h3 className="font-semibold text-theme-primary mb-3 flex items-center gap-2">
                  <Tag className="w-4 h-4 text-primary" />
                  {t('hub.categories', 'Categories')}
                </h3>
                <div className="space-y-1">
                  {apiCategories.map((cat) => (
                    <Link
                      key={cat.id}
                      to={tenantPath(`/marketplace/category/${cat.slug}`)}
                      className="flex items-center justify-between py-2 px-2 rounded-lg hover:bg-default-100 transition-colors text-sm group"
                    >
                      <span className="text-theme-primary group-hover:text-primary transition-colors">
                        {cat.name}
                      </span>
                      <div className="flex items-center gap-1">
                        <span className="text-xs text-default-400">{cat.listing_count}</span>
                        <ChevronRight className="w-3.5 h-3.5 text-default-300 group-hover:text-primary transition-colors" />
                      </div>
                    </Link>
                  ))}
                </div>
              </GlassCard>
            )}

            {/* Quick links */}
            <GlassCard className="p-5">
              <h3 className="font-semibold text-theme-primary mb-3">{t('hub.quick_links', 'Quick Links')}</h3>
              <div className="space-y-2">
                <Link
                  to={tenantPath('/marketplace/search')}
                  className="flex items-center gap-2 text-sm text-default-600 hover:text-primary transition-colors"
                >
                  <Search className="w-4 h-4" />
                  {t('hub.advanced_search', 'Advanced Search')}
                </Link>
                {isAuthenticated && (
                  <>
                    <Link
                      to={tenantPath('/marketplace/my-listings')}
                      className="flex items-center gap-2 text-sm text-default-600 hover:text-primary transition-colors"
                    >
                      <Package className="w-4 h-4" />
                      {t('hub.my_listings', 'My Listings')}
                    </Link>
                    <Link
                      to={tenantPath('/marketplace/my-offers')}
                      className="flex items-center gap-2 text-sm text-default-600 hover:text-primary transition-colors"
                    >
                      <HandCoins className="w-4 h-4" />
                      {t('hub.my_offers', 'My Offers')}
                    </Link>
                    <Link
                      to={tenantPath('/marketplace/orders')}
                      className="flex items-center gap-2 text-sm text-default-600 hover:text-primary transition-colors"
                    >
                      <ShoppingBag className="w-4 h-4" />
                      {t('hub.my_orders', 'My Orders')}
                    </Link>
                    <Link
                      to={tenantPath('/marketplace/collections')}
                      className="flex items-center gap-2 text-sm text-default-600 hover:text-primary transition-colors"
                    >
                      <Heart className="w-4 h-4" />
                      {t('hub.saved_items', 'Saved Items')}
                    </Link>
                  </>
                )}
              </div>
            </GlassCard>
          </aside>
        </div>
      </div>
    </>
  );
}

export default MarketplacePage;
