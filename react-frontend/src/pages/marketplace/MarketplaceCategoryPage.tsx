// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceCategoryPage — Browse listings within a specific category.
 *
 * Features:
 * - Category header with name, description, icon
 * - Breadcrumbs: Marketplace > Category Name
 * - Listing grid with filters (same as search, pre-filtered by category)
 * - Dynamic template fields in filter sidebar from category template endpoint
 * - Cursor-based pagination
 * - Uses shared MarketplaceListingGrid for listing display
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useParams, Link, useNavigate, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Checkbox,
  CheckboxGroup,
  Chip,
  Spinner,
  Divider,
} from '@heroui/react';
import {
  Search,
  SlidersHorizontal,
  ShoppingBag,
  ChevronRight,
  Tag,
  RotateCcw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { MarketplaceListingGrid } from '@/components/marketplace';
import type { MarketplaceListingItem } from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface CategoryDetail {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  icon: string | null;
  listings_count: number;
  parent_id: number | null;
}

interface TemplateField {
  key: string;
  label: string;
  type: 'text' | 'number' | 'select';
  options?: string[];
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

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplaceCategoryPage() {
  const { slug } = useParams<{ slug: string }>();
  const navigate = useNavigate();
  const { t } = useTranslation('marketplace');
  usePageTitle(t('page_title', 'Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  // Category state
  const [category, setCategory] = useState<CategoryDetail | null>(null);
  const [templateFields, setTemplateFields] = useState<TemplateField[]>([]);
  const [isCategoryLoading, setIsCategoryLoading] = useState(true);
  const [categoryError, setCategoryError] = useState<string | null>(null);

  // Filter state
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [priceMin, setPriceMin] = useState(searchParams.get('price_min') || '');
  const [priceMax, setPriceMax] = useState(searchParams.get('price_max') || '');
  const [selectedConditions, setSelectedConditions] = useState<string[]>(
    searchParams.get('condition')?.split(',').filter(Boolean) || []
  );
  const [sortBy, setSortBy] = useState(searchParams.get('sort') || 'newest');
  const [templateFilterValues, setTemplateFilterValues] = useState<Record<string, string>>({});
  const [showFilters, setShowFilters] = useState(false);

  // Listings state
  const [listings, setListings] = useState<MarketplaceListingItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const cursorRef = useRef<string | null>(null);
  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Load category detail + template
  useEffect(() => {
    if (!slug) return;
    let cancelled = false;

    const load = async () => {
      setIsCategoryLoading(true);
      setCategoryError(null);

      try {
        // Load all categories and find by slug
        const response = await api.get<CategoryDetail[]>('/v2/marketplace/categories');
        if (cancelled) return;

        if (response.success && response.data) {
          const found = response.data.find((c) => c.slug === slug);
          if (found) {
            setCategory(found);

            // Load template fields for this category
            try {
              const templateResponse = await api.get<{ fields: TemplateField[] }>(
                `/v2/marketplace/categories/${found.id}/template`
              );
              if (!cancelled && templateResponse.success && templateResponse.data?.fields) {
                setTemplateFields(templateResponse.data.fields);
              }
            } catch {
              // No template fields -- that is fine
            }
          } else {
            setCategoryError(t('category.not_found_title', 'Category not found'));
          }
        } else {
          setCategoryError(t('category.not_found_description', 'Unable to load category'));
        }
      } catch (err) {
        if (!cancelled) {
          logError('Failed to load category', err);
          setCategoryError(t('category.not_found_description', 'Unable to load category'));
        }
      } finally {
        if (!cancelled) setIsCategoryLoading(false);
      }
    };

    load();
    return () => { cancelled = true; };
  }, [slug]);

  // Update page title
  useEffect(() => {
    if (category?.name) {
      document.title = `${category.name} - ${t('page_title', 'Marketplace')}`;
    }
  }, [category?.name]);

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

  // Load listings
  const loadListings = useCallback(async (append = false) => {
    if (!category) return;

    try {
      if (!append) {
        setIsLoading(true);
      } else {
        setIsLoadingMore(true);
      }

      const params = new URLSearchParams();
      params.set('category_id', String(category.id));
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (priceMin) params.set('price_min', priceMin);
      if (priceMax) params.set('price_max', priceMax);
      if (selectedConditions.length > 0) params.set('condition', selectedConditions.join(','));
      if (sortBy !== 'newest') params.set('sort', sortBy);
      params.set('limit', String(ITEMS_PER_PAGE));
      if (append && cursorRef.current) {
        params.set('cursor', cursorRef.current);
      }

      // Add template filter values
      Object.entries(templateFilterValues).forEach(([key, value]) => {
        if (value) params.set(`tf_${key}`, value);
      });

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
      logError('Failed to load category listings', err);
      if (!append) toast.error(t('hub.unable_to_load', 'Failed to load listings'));
    } finally {
      setIsLoading(false);
      setIsLoadingMore(false);
    }
  }, [category, debouncedQuery, priceMin, priceMax, selectedConditions, sortBy, templateFilterValues, toast]);

  // Refetch on filter/category change
  useEffect(() => {
    if (!category) return;
    cursorRef.current = null;
    setHasMore(true);
    loadListings();
  }, [category, debouncedQuery, priceMin, priceMax, selectedConditions, sortBy, templateFilterValues]); // eslint-disable-line react-hooks/exhaustive-deps

  // Sync URL
  useEffect(() => {
    const params = new URLSearchParams();
    if (debouncedQuery) params.set('q', debouncedQuery);
    if (priceMin) params.set('price_min', priceMin);
    if (priceMax) params.set('price_max', priceMax);
    if (selectedConditions.length > 0) params.set('condition', selectedConditions.join(','));
    if (sortBy !== 'newest') params.set('sort', sortBy);
    setSearchParams(params, { replace: true });
  }, [debouncedQuery, priceMin, priceMax, selectedConditions, sortBy, setSearchParams]);

  // Active filter count
  const activeFilterCount = [
    priceMin,
    priceMax,
    selectedConditions.length > 0 ? 'yes' : '',
    ...Object.values(templateFilterValues).filter(Boolean),
  ].filter(Boolean).length;

  // Reset filters
  const resetFilters = () => {
    setPriceMin('');
    setPriceMax('');
    setSelectedConditions([]);
    setSortBy('newest');
    setTemplateFilterValues({});
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

  // Category loading/error
  if (isCategoryLoading) {
    return (
      <div className="flex justify-center py-24">
        <Spinner size="lg" color="primary" />
      </div>
    );
  }

  if (categoryError || !category) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-12">
        <EmptyState
          icon={<Tag className="w-8 h-8" />}
          title={t('category.not_found_title', 'Category Not Found')}
          description={categoryError || t('category.not_found_description', 'This category does not exist.')}
          action={{ label: t('category.back_to_marketplace', 'Back to Marketplace'), onClick: () => navigate(tenantPath('/marketplace')) }}
        />
      </div>
    );
  }

  // Filter sidebar content
  const filterContent = (
    <div className="space-y-5">
      {/* Price range */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('category.price_range', 'Price Range')}</label>
        <div className="flex gap-2 items-center">
          <Input
            size="sm"
            type="number"
            placeholder={t('category.price_min', 'Min')}
            min={0}
            value={priceMin}
            onValueChange={setPriceMin}
          />
          <span className="text-default-400">-</span>
          <Input
            size="sm"
            type="number"
            placeholder={t('category.price_max', 'Max')}
            min={0}
            value={priceMax}
            onValueChange={setPriceMax}
          />
        </div>
      </div>

      {/* Condition */}
      <div>
        <label className="text-sm font-medium text-foreground mb-2 block">{t('category.condition_label', 'Condition')}</label>
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

      {/* Dynamic template fields */}
      {templateFields.length > 0 && (
        <div className="space-y-3">
          <Divider />
          <p className="text-sm font-medium text-default-500">{t('category.category_details', '{{name}} Details', { name: category.name })}</p>
          {templateFields.map((field) => {
            if (field.type === 'select' && field.options) {
              return (
                <Select
                  key={field.key}
                  label={field.label}
                  placeholder={`Any ${field.label}`}
                  size="sm"
                  selectedKeys={templateFilterValues[field.key] ? [templateFilterValues[field.key]] : []}
                  onSelectionChange={(keys) => {
                    const selected = Array.from(keys)[0];
                    setTemplateFilterValues((prev) => ({
                      ...prev,
                      [field.key]: selected ? String(selected) : '',
                    }));
                  }}
                >
                  {field.options.map((opt) => (
                    <SelectItem key={opt}>{opt}</SelectItem>
                  ))}
                </Select>
              );
            }
            return (
              <Input
                key={field.key}
                label={field.label}
                placeholder={`Filter by ${field.label.toLowerCase()}`}
                size="sm"
                type={field.type === 'number' ? 'number' : 'text'}
                value={templateFilterValues[field.key] || ''}
                onValueChange={(val) =>
                  setTemplateFilterValues((prev) => ({ ...prev, [field.key]: val }))
                }
              />
            );
          })}
        </div>
      )}

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
          {t('category.reset_filters', 'Reset Filters ({{count}})', { count: activeFilterCount })}
        </Button>
      )}
    </div>
  );

  return (
    <>
      <PageMeta
        title={`${category.name} - ${t('page_title', 'Marketplace')}`}
        description={category.description || t('category.browse_description', 'Browse {{name}} listings in the marketplace.', { name: category.name })}
      />

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-6">
        {/* Breadcrumbs */}
        <nav className="flex items-center gap-2 text-sm flex-wrap">
          <Link
            to={tenantPath('/marketplace')}
            className="text-default-500 hover:text-primary transition-colors"
          >
            {t('category.marketplace', 'Marketplace')}
          </Link>
          <ChevronRight className="w-3.5 h-3.5 text-default-300" />
          <span className="text-foreground font-medium">{category.name}</span>
        </nav>

        {/* Category header */}
        <GlassCard className="p-6">
          <div className="flex items-center gap-4">
            <div className="w-14 h-14 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
              {category.icon ? (
                <span className="text-2xl">{category.icon}</span>
              ) : (
                <Tag className="w-7 h-7 text-primary" />
              )}
            </div>
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-bold text-foreground">{category.name}</h1>
              {category.description && (
                <p className="text-sm text-default-500 mt-1">{category.description}</p>
              )}
              <p className="text-xs text-default-400 mt-1">
                {t('category.listings_count', '{{count}} listing', { count: category.listings_count })}{category.listings_count !== 1 ? 's' : ''}
              </p>
            </div>
          </div>
        </GlassCard>

        {/* Search + Sort */}
        <div className="flex gap-3 items-end">
          <Input
            placeholder={t('category.search_placeholder', 'Search in {{name}}...', { name: category.name })}
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
          <Button
            variant="bordered"
            size="lg"
            className="lg:hidden shrink-0"
            startContent={<SlidersHorizontal className="w-4 h-4" />}
            onPress={() => setShowFilters(!showFilters)}
          >
            {t('category.filters', 'Filters')}
            {activeFilterCount > 0 && (
              <Chip size="sm" color="primary" variant="solid" className="ml-1">
                {activeFilterCount}
              </Chip>
            )}
          </Button>
        </div>

        {/* Mobile filters */}
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
                {t('category.filters_title', 'Filters')}
              </h3>
              {filterContent}
            </GlassCard>
          </aside>

          {/* Listings */}
          <div className="flex-1 min-w-0">
            {isLoading ? (
              <div className="flex justify-center py-16">
                <Spinner size="lg" color="primary" />
              </div>
            ) : listings.length === 0 ? (
              <EmptyState
                icon={<ShoppingBag className="w-8 h-8" />}
                title={t('category.no_listings_title', 'No Listings Found')}
                description={
                  debouncedQuery || activeFilterCount > 0
                    ? t('category.no_listings_filtered', 'Try adjusting your search or filters.')
                    : t('category.no_listings_empty', 'No listings in {{name}} yet. Be the first to list something!', { name: category.name })
                }
                action={
                  activeFilterCount > 0
                    ? { label: t('category.clear_filters', 'Clear Filters'), onClick: resetFilters }
                    : isAuthenticated
                      ? { label: t('category.sell_something', 'Sell Something'), onClick: () => navigate(tenantPath('/marketplace/sell')) }
                      : undefined
                }
              />
            ) : (
              <>
                <div className="flex items-center justify-between mb-4">
                  <p className="text-sm text-default-500">
                    {t('category.results_count', '{{count}} result', { count: listings.length })}{listings.length !== 1 ? 's' : ''}
                  </p>
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
                      {t('category.load_more', 'Load More')}
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

export default MarketplaceCategoryPage;
