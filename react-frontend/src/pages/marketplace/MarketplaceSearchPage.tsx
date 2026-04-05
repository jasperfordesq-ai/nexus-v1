// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceSearchPage — Advanced search with filters.
 *
 * Features:
 * - Search input with debounce
 * - Filter sidebar (desktop) / bottom sheet concept (mobile via collapsible)
 * - Category, price range, condition, seller type, delivery, sort, posted-within
 * - URL query param sync for shareable search URLs
 * - Results grid with cursor pagination (shared MarketplaceListingGrid)
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Checkbox,
  CheckboxGroup,
  Chip,
} from '@heroui/react';
import {
  Search,
  SlidersHorizontal,
  RotateCcw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { MarketplaceListingGrid, MarketplaceListingGridSkeleton } from '@/components/marketplace';
import type { MarketplaceListingItem } from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ApiCategory {
  id: number;
  name: string;
  slug: string;
  listings_count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ITEMS_PER_PAGE = 24;
const SEARCH_DEBOUNCE_MS = 300;

const CONDITION_OPTIONS = [
  { value: 'new', label: 'New' },
  { value: 'like_new', label: 'Like New' },
  { value: 'good', label: 'Good' },
  { value: 'fair', label: 'Fair' },
  { value: 'poor', label: 'Poor' },
];

const SORT_OPTIONS = [
  { value: 'newest', label: 'Newest First' },
  { value: 'price_asc', label: 'Price: Low to High' },
  { value: 'price_desc', label: 'Price: High to Low' },
  { value: 'popular', label: 'Most Popular' },
];

const POSTED_WITHIN_OPTIONS = [
  { value: '', label: 'Any Time' },
  { value: '1', label: 'Today' },
  { value: '3', label: 'Last 3 Days' },
  { value: '7', label: 'Last 7 Days' },
  { value: '30', label: 'Last 30 Days' },
];

const CONDITION_COLORS: Record<string, 'success' | 'primary' | 'warning' | 'danger' | 'default'> = {
  new: 'success',
  like_new: 'primary',
  good: 'warning',
  fair: 'danger',
  poor: 'default',
};

const CONDITION_LABELS: Record<string, string> = {
  new: 'New',
  like_new: 'Like New',
  good: 'Good',
  fair: 'Fair',
  poor: 'Poor',
};

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplaceSearchPage() {
  const { t } = useTranslation('marketplace');
  usePageTitle(t('search.page_title', 'Search Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  // Filter state -- initialized from URL
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [categoryId, setCategoryId] = useState(searchParams.get('category_id') || '');
  const [priceMin, setPriceMin] = useState(searchParams.get('price_min') || '');
  const [priceMax, setPriceMax] = useState(searchParams.get('price_max') || '');
  const [selectedConditions, setSelectedConditions] = useState<string[]>(
    searchParams.get('condition')?.split(',').filter(Boolean) || []
  );
  const [sellerType, setSellerType] = useState(searchParams.get('seller_type') || '');
  const [deliveryMethod, setDeliveryMethod] = useState(searchParams.get('delivery') || '');
  const [sortBy, setSortBy] = useState(searchParams.get('sort') || 'newest');
  const [postedWithin, setPostedWithin] = useState(searchParams.get('days') || '');
  const [showFilters, setShowFilters] = useState(false);

  // Data state
  const [listings, setListings] = useState<MarketplaceListingItem[]>([]);
  const [categories, setCategories] = useState<ApiCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

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
    let cancelled = false;
    const load = async () => {
      try {
        const response = await api.get<ApiCategory[]>('/v2/marketplace/categories');
        if (!cancelled && response.success && response.data) {
          setCategories(response.data);
        }
      } catch (err) {
        logError('Failed to load categories', err);
      }
    };
    load();
    return () => { cancelled = true; };
  }, []);

  // Load listings
  const loadListings = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (categoryId) params.set('category_id', categoryId);
      if (priceMin) params.set('price_min', priceMin);
      if (priceMax) params.set('price_max', priceMax);
      if (selectedConditions.length > 0) params.set('condition', selectedConditions.join(','));
      if (sellerType) params.set('seller_type', sellerType);
      if (deliveryMethod) params.set('delivery', deliveryMethod);
      if (sortBy !== 'newest') params.set('sort', sortBy);
      if (postedWithin) params.set('days', postedWithin);
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
        setListings([]);
        setHasMore(false);
      }
    } catch (err) {
      logError('Failed to search marketplace', err);
      if (!append) {
        toast.error(t('search.search_failed', 'Search failed. Please try again.'));
      }
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [debouncedQuery, categoryId, priceMin, priceMax, selectedConditions, sellerType, deliveryMethod, sortBy, postedWithin, toast]);

  // Refetch on filter change
  useEffect(() => {
    cursorRef.current = null;
    setHasMore(true);
    loadListings();
  }, [debouncedQuery, categoryId, priceMin, priceMax, selectedConditions, sellerType, deliveryMethod, sortBy, postedWithin]); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync URL
  useEffect(() => {
    const params = new URLSearchParams();
    if (debouncedQuery) params.set('q', debouncedQuery);
    if (categoryId) params.set('category_id', categoryId);
    if (priceMin) params.set('price_min', priceMin);
    if (priceMax) params.set('price_max', priceMax);
    if (selectedConditions.length > 0) params.set('condition', selectedConditions.join(','));
    if (sellerType) params.set('seller_type', sellerType);
    if (deliveryMethod) params.set('delivery', deliveryMethod);
    if (sortBy !== 'newest') params.set('sort', sortBy);
    if (postedWithin) params.set('days', postedWithin);
    setSearchParams(params, { replace: true });
  }, [debouncedQuery, categoryId, priceMin, priceMax, selectedConditions, sellerType, deliveryMethod, sortBy, postedWithin, setSearchParams]);

  // Count active filters
  const activeFilterCount = [
    categoryId,
    priceMin,
    priceMax,
    selectedConditions.length > 0 ? 'yes' : '',
    sellerType,
    deliveryMethod,
    postedWithin,
  ].filter(Boolean).length;

  // Reset filters
  const resetFilters = () => {
    setCategoryId('');
    setPriceMin('');
    setPriceMax('');
    setSelectedConditions([]);
    setSellerType('');
    setDeliveryMethod('');
    setSortBy('newest');
    setPostedWithin('');
  };

  // Save / Unsave
  const handleSave = async (id: number) => {
    if (!isAuthenticated) {
      toast.error(t('common.sign_in_to_save', 'Please sign in to save listings'));
      return;
    }
    try {
      await api.post(`/v2/marketplace/listings/${id}/save`);
      setListings((prev) =>
        prev.map((l) => (l.id === id ? { ...l, is_saved: true } : l))
      );
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
      setListings((prev) =>
        prev.map((l) => (l.id === id ? { ...l, is_saved: false } : l))
      );
      toast.success(t('common.removed_from_saved', 'Removed from saved'));
    } catch (err) {
      logError('Failed to unsave listing', err);
      toast.error(t('common.save_failed', 'Failed to update saved status'));
    }
  };

  // Filter sidebar content
  const filterContent = (
    <div className="space-y-5">
      {/* Category */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('search.category_label', 'Category')}</label>
        <Select
          placeholder={t('search.all_categories', 'All Categories')}
          selectedKeys={categoryId ? [categoryId] : []}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0];
            setCategoryId(selected ? String(selected) : '');
          }}
          size="sm"
        >
          {categories.map((cat) => (
            <SelectItem key={String(cat.id)}>
              {cat.name} ({cat.listings_count})
            </SelectItem>
          ))}
        </Select>
      </div>

      {/* Price range */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('search.price_range', 'Price Range')}</label>
        <div className="flex gap-2 items-center">
          <Input
            size="sm"
            type="number"
            placeholder={t('search.price_min', 'Min')}
            min={0}
            value={priceMin}
            onValueChange={setPriceMin}
          />
          <span className="text-default-400">-</span>
          <Input
            size="sm"
            type="number"
            placeholder={t('search.price_max', 'Max')}
            min={0}
            value={priceMax}
            onValueChange={setPriceMax}
          />
        </div>
      </div>

      {/* Condition */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('search.condition_label', 'Condition')}</label>
        <CheckboxGroup
          value={selectedConditions}
          onValueChange={setSelectedConditions}
          size="sm"
        >
          {CONDITION_OPTIONS.map((opt) => (
            <Checkbox key={opt.value} value={opt.value}>
              {t(`condition.${opt.value}`, opt.label)}
            </Checkbox>
          ))}
        </CheckboxGroup>
      </div>

      {/* Seller type */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('search.seller_type_label', 'Seller Type')}</label>
        <Select
          placeholder={t('search.all_sellers', 'All Sellers')}
          selectedKeys={sellerType ? [sellerType] : []}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0];
            setSellerType(selected ? String(selected) : '');
          }}
          size="sm"
        >
          <SelectItem key="private">{t('search.private', 'Private')}</SelectItem>
          <SelectItem key="business">{t('search.business', 'Business')}</SelectItem>
        </Select>
      </div>

      {/* Delivery method */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('search.delivery_label', 'Delivery')}</label>
        <Select
          placeholder={t('search.any_delivery', 'Any Delivery')}
          selectedKeys={deliveryMethod ? [deliveryMethod] : []}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0];
            setDeliveryMethod(selected ? String(selected) : '');
          }}
          size="sm"
        >
          <SelectItem key="pickup">{t('search.delivery_pickup', 'Pickup Only')}</SelectItem>
          <SelectItem key="shipping">{t('search.delivery_shipping', 'Shipping Only')}</SelectItem>
          <SelectItem key="both">{t('search.delivery_both', 'Pickup or Shipping')}</SelectItem>
        </Select>
      </div>

      {/* Posted within */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('search.posted_within', 'Posted Within')}</label>
        <Select
          placeholder={t('search.any_time', 'Any Time')}
          selectedKeys={postedWithin ? [postedWithin] : []}
          onSelectionChange={(keys) => {
            const selected = Array.from(keys)[0];
            setPostedWithin(selected ? String(selected) : '');
          }}
          size="sm"
        >
          {POSTED_WITHIN_OPTIONS.filter((o) => o.value).map((opt) => (
            <SelectItem key={opt.value}>{opt.label}</SelectItem>
          ))}
        </Select>
      </div>

      {/* Reset */}
      {activeFilterCount > 0 && (
        <Button
          variant="flat"
          color="danger"
          fullWidth
          size="sm"
          startContent={<RotateCcw className="w-3.5 h-3.5" />}
          onPress={resetFilters}
        >
          {t('search.reset_filters', 'Reset Filters ({{count}})', { count: activeFilterCount })}
        </Button>
      )}
    </div>
  );

  return (
    <>
      <PageMeta
        title={t('search.page_title', 'Search Marketplace')}
        description={t('search.meta_description', 'Search and filter items in the community marketplace.')}
      />

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Header */}
        <div className="space-y-4">
          <div className="flex items-center gap-3">
            <Button
              as={Link}
              to={tenantPath('/marketplace')}
              variant="light"
              size="sm"
            >
              {t('page_title', 'Marketplace')}
            </Button>
            <span className="text-default-300">/</span>
            <span className="text-foreground font-medium">{t('search.breadcrumb_search', 'Search')}</span>
          </div>

          {/* Search bar + sort */}
          <div className="flex gap-3 items-end">
            <Input
              placeholder={t('search.search_placeholder', 'Search marketplace...')}
              value={searchQuery}
              onValueChange={setSearchQuery}
              startContent={<Search className="w-4 h-4 text-default-400" />}
              size="lg"
              variant="bordered"
              classNames={{ inputWrapper: 'bg-background' }}
              isClearable
              onClear={() => setSearchQuery('')}
              className="flex-1"
            />
            <Select
              selectedKeys={[sortBy]}
              onSelectionChange={(keys) => {
                const selected = Array.from(keys)[0];
                if (selected) setSortBy(String(selected));
              }}
              size="lg"
              className="w-48 shrink-0 hidden sm:block"
              aria-label={t('common.sort_by', 'Sort by')}
            >
              {SORT_OPTIONS.map((opt) => (
                <SelectItem key={opt.value}>{t(`sort.${opt.value}`, opt.label)}</SelectItem>
              ))}
            </Select>

            {/* Mobile filter toggle */}
            <Button
              variant="bordered"
              size="lg"
              className="lg:hidden shrink-0"
              startContent={<SlidersHorizontal className="w-4 h-4" />}
              onPress={() => setShowFilters(!showFilters)}
            >
              {t('search.filters', 'Filters')}
              {activeFilterCount > 0 && (
                <Chip size="sm" color="primary" variant="solid" className="ml-1">
                  {activeFilterCount}
                </Chip>
              )}
            </Button>
          </div>

          {/* Active filter chips */}
          {activeFilterCount > 0 && (
            <div className="flex gap-2 flex-wrap">
              {categoryId && (
                <Chip
                  onClose={() => setCategoryId('')}
                  variant="flat"
                  size="sm"
                >
                  {categories.find((c) => String(c.id) === categoryId)?.name || 'Category'}
                </Chip>
              )}
              {(priceMin || priceMax) && (
                <Chip
                  onClose={() => { setPriceMin(''); setPriceMax(''); }}
                  variant="flat"
                  size="sm"
                >
                  {priceMin && priceMax ? `${priceMin} - ${priceMax}` :
                    priceMin ? t('search.price_from', 'From {{min}}', { min: priceMin }) : t('search.price_up_to', 'Up to {{max}}', { max: priceMax })}
                </Chip>
              )}
              {selectedConditions.map((c) => (
                <Chip
                  key={c}
                  onClose={() => setSelectedConditions((prev) => prev.filter((x) => x !== c))}
                  variant="flat"
                  size="sm"
                  color={CONDITION_COLORS[c] || 'default'}
                >
                  {t(`condition.${c}`, CONDITION_LABELS[c] || c)}
                </Chip>
              ))}
              {sellerType && (
                <Chip onClose={() => setSellerType('')} variant="flat" size="sm">
                  {sellerType === 'business' ? t('search.business', 'Business') : t('search.private', 'Private')}
                </Chip>
              )}
              {deliveryMethod && (
                <Chip onClose={() => setDeliveryMethod('')} variant="flat" size="sm">
                  {deliveryMethod.charAt(0).toUpperCase() + deliveryMethod.slice(1)}
                </Chip>
              )}
              {postedWithin && (
                <Chip onClose={() => setPostedWithin('')} variant="flat" size="sm">
                  Last {postedWithin} day{postedWithin !== '1' ? 's' : ''}
                </Chip>
              )}
            </div>
          )}
        </div>

        {/* Mobile filters (collapsible) */}
        {showFilters && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            className="lg:hidden"
          >
            <GlassCard className="p-5">
              {filterContent}
            </GlassCard>
          </motion.div>
        )}

        {/* Main layout */}
        <div className="flex gap-6">
          {/* Desktop filter sidebar */}
          <aside className="hidden lg:block w-64 shrink-0">
            <GlassCard className="p-5 sticky top-24">
              <h3 className="font-semibold text-foreground mb-4 flex items-center gap-2">
                <SlidersHorizontal className="w-4 h-4 text-primary" />
                {t('search.filters_title', 'Filters')}
              </h3>
              {filterContent}
            </GlassCard>
          </aside>

          {/* Results */}
          <div className="flex-1 min-w-0">
            {isLoading ? (
              <MarketplaceListingGridSkeleton />
            ) : listings.length === 0 ? (
              <EmptyState
                icon={<Search className="w-8 h-8" />}
                title={t('search.no_results_title', 'No Results Found')}
                description={t('search.no_results_description', "Try adjusting your search or filters to find what you're looking for.")}
                action={
                  activeFilterCount > 0
                    ? { label: t('search.clear_filters', 'Clear Filters'), onClick: resetFilters }
                    : undefined
                }
              />
            ) : (
              <>
                <div className="flex items-center justify-between mb-4">
                  <p className="text-sm text-default-500">
                    {t('search.results_count', '{{count}} result', { count: listings.length })}{listings.length !== 1 ? 's' : ''}
                    {debouncedQuery && (
                      <> {t('search.results_for', 'for')} &quot;<span className="font-medium text-foreground">{debouncedQuery}</span>&quot;</>
                    )}
                  </p>
                  {/* Mobile sort */}
                  <Select
                    selectedKeys={[sortBy]}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0];
                      if (selected) setSortBy(String(selected));
                    }}
                    size="sm"
                    className="w-44 sm:hidden"
                    aria-label={t('common.sort_by', 'Sort by')}
                  >
                    {SORT_OPTIONS.map((opt) => (
                      <SelectItem key={opt.value}>{t(`sort.${opt.value}`, opt.label)}</SelectItem>
                    ))}
                  </Select>
                </div>

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
                      {t('search.load_more', 'Load More')}
                    </Button>
                  </div>
                )}
              </>
            )}
          </div>
        </div>
      </div>
    </>
  );
}

export default MarketplaceSearchPage;
