// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceMapSearchPage — Map-based marketplace search.
 *
 * Route: /marketplace/map
 *
 * Features:
 * - Split view: filter sidebar (left) + map (right) on desktop
 * - Mobile: toggle between list view and map view
 * - Search input at top
 * - Map shows listing pins for current search results (nearby endpoint)
 * - Click pin -> popup card -> click card -> listing detail
 * - "Search this area" button when map center changes
 * - Browser geolocation for initial center
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  Button,
  Input,
  Select,
  SelectItem,
  Chip,
  Spinner,
} from '@heroui/react';
import {
  Search,
  Map as MapIcon,
  List,
  RefreshCw,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { MapSearchView } from '@/components/marketplace/MapSearchView';
import { MarketplaceListingGrid } from '@/components/marketplace';
import type { MarketplaceListingItem } from '@/types/marketplace';
import { useAuth, useToast, useTenant } from '@/contexts';
import { useGeolocation } from '@/hooks/useGeolocation';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { PageMeta } from '@/components/seo/PageMeta';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface NearbyListing extends MarketplaceListingItem {
  latitude?: number;
  longitude?: number;
  distance_km?: number;
}

interface ApiCategory {
  id: number;
  name: string;
  slug: string;
  listings_count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const DEFAULT_RADIUS_KM = 25;
const SEARCH_DEBOUNCE_MS = 400;

const RADIUS_OPTIONS = [
  { value: '5', label: '5 km' },
  { value: '10', label: '10 km' },
  { value: '25', label: '25 km' },
  { value: '50', label: '50 km' },
  { value: '100', label: '100 km' },
];

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MarketplaceMapSearchPage() {
  const { t } = useTranslation('marketplace');
  usePageTitle(t('map.page_title', 'Map Search - Marketplace'));
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const geo = useGeolocation();
  const [searchParams, setSearchParams] = useSearchParams();

  // View mode (mobile)
  const [viewMode, setViewMode] = useState<'map' | 'list'>('map');

  // Search & filter state
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [categoryId, setCategoryId] = useState(searchParams.get('category_id') || '');
  const [radiusKm, setRadiusKm] = useState(searchParams.get('radius') || String(DEFAULT_RADIUS_KM));

  // Data
  const [listings, setListings] = useState<(MarketplaceListingItem & { latitude?: number; longitude?: number; distance_km?: number })[]>([]);
  const [categories, setCategories] = useState<ApiCategory[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  // Map center state
  const [mapCenter, setMapCenter] = useState<{ lat: number; lng: number } | undefined>(
    searchParams.get('lat') && searchParams.get('lng')
      ? { lat: parseFloat(searchParams.get('lat')!), lng: parseFloat(searchParams.get('lng')!) }
      : undefined
  );

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Debounce search input
  useEffect(() => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);
    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [searchQuery]);

  // Auto-detect location on mount if no center specified
  useEffect(() => {
    if (!mapCenter && !geo.latitude && !geo.loading) {
      geo.requestLocation();
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // When geo resolves, set map center
  useEffect(() => {
    if (geo.latitude && geo.longitude && !mapCenter) {
      setMapCenter({ lat: geo.latitude, lng: geo.longitude });
    }
  }, [geo.latitude, geo.longitude]); // eslint-disable-line react-hooks/exhaustive-deps

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

  // Load nearby listings
  const loadListings = useCallback(async () => {
    if (!mapCenter) return;

    setIsLoading(true);
    try {
      const params = new URLSearchParams();
      params.set('lat', String(mapCenter.lat));
      params.set('lng', String(mapCenter.lng));
      params.set('radius_km', radiusKm);
      if (debouncedQuery) params.set('q', debouncedQuery);
      if (categoryId) params.set('category_id', categoryId);
      params.set('limit', '100');

      const response = await api.get<NearbyListing[]>(
        `/v2/marketplace/listings/nearby?${params}`
      );
      if (response.success && response.data) {
        const mapped = response.data.map((item) => ({
          ...(item as unknown as MarketplaceListingItem),
          latitude: item.latitude,
          longitude: item.longitude,
          distance_km: item.distance_km,
        }));
        setListings(mapped);
      } else {
        setListings([]);
      }
    } catch (err) {
      logError('Failed to load nearby listings', err);
      toast.error(t('map.search_failed', 'Map search failed. Please try again.'));
    } finally {
      setIsLoading(false);
    }
  }, [mapCenter, radiusKm, debouncedQuery, categoryId, toast, t]);

  // Refetch when params change
  useEffect(() => {
    loadListings();
  }, [loadListings]);

  // Sync URL
  useEffect(() => {
    const params = new URLSearchParams();
    if (debouncedQuery) params.set('q', debouncedQuery);
    if (categoryId) params.set('category_id', categoryId);
    if (radiusKm !== String(DEFAULT_RADIUS_KM)) params.set('radius', radiusKm);
    if (mapCenter) {
      params.set('lat', String(mapCenter.lat));
      params.set('lng', String(mapCenter.lng));
    }
    setSearchParams(params, { replace: true });
  }, [debouncedQuery, categoryId, radiusKm, mapCenter, setSearchParams]);

  // "Use my location" handler
  const handleUseMyLocation = useCallback(() => {
    if (geo.latitude && geo.longitude) {
      setMapCenter({ lat: geo.latitude, lng: geo.longitude });
    } else {
      geo.requestLocation();
    }
  }, [geo]);

  // Save / Unsave handlers
  const handleSave = useCallback(async (id: number) => {
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
  }, [isAuthenticated, toast, t]);

  const handleUnsave = useCallback(async (id: number) => {
    if (!isAuthenticated) return;
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
  }, [isAuthenticated, toast, t]);

  // Active filter count
  const activeFilterCount = [categoryId, radiusKm !== String(DEFAULT_RADIUS_KM) ? 'yes' : ''].filter(Boolean).length;

  return (
    <>
      <PageMeta
        title={t('map.page_title', 'Map Search - Marketplace')}
        description={t('map.meta_description', 'Find marketplace listings near you on the map.')}
      />

      <div className="max-w-7xl mx-auto px-4 py-6 space-y-4">
        {/* Header / Breadcrumb */}
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
          <span className="text-foreground font-medium">{t('map.breadcrumb', 'Map Search')}</span>
        </div>

        {/* Search bar + controls */}
        <div className="flex gap-3 items-end flex-wrap">
          <Input
            placeholder={t('map.search_placeholder', 'Search nearby listings...')}
            value={searchQuery}
            onValueChange={setSearchQuery}
            startContent={<Search className="w-4 h-4 text-default-400" />}
            size="lg"
            variant="bordered"
            classNames={{ inputWrapper: 'bg-background' }}
            isClearable
            onClear={() => setSearchQuery('')}
            className="flex-1 min-w-[200px]"
          />

          <Select
            selectedKeys={[radiusKm]}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0];
              if (selected) setRadiusKm(String(selected));
            }}
            size="lg"
            className="w-32 shrink-0"
            aria-label={t('map.radius_label', 'Search radius')}
          >
            {RADIUS_OPTIONS.map((opt) => (
              <SelectItem key={opt.value}>{opt.label}</SelectItem>
            ))}
          </Select>

          <Select
            placeholder={t('search.all_categories', 'All Categories')}
            selectedKeys={categoryId ? [categoryId] : []}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0];
              setCategoryId(selected ? String(selected) : '');
            }}
            size="lg"
            className="w-48 shrink-0 hidden sm:block"
            aria-label={t('search.category_label', 'Category')}
          >
            {categories.map((cat) => (
              <SelectItem key={String(cat.id)}>
                {cat.name} ({cat.listings_count})
              </SelectItem>
            ))}
          </Select>

          {/* Mobile view toggle */}
          <div className="flex gap-1 lg:hidden shrink-0">
            <Button
              isIconOnly
              variant={viewMode === 'map' ? 'solid' : 'bordered'}
              color={viewMode === 'map' ? 'primary' : 'default'}
              size="lg"
              onPress={() => setViewMode('map')}
              aria-label={t('map.map_view', 'Map view')}
            >
              <MapIcon className="w-4 h-4" />
            </Button>
            <Button
              isIconOnly
              variant={viewMode === 'list' ? 'solid' : 'bordered'}
              color={viewMode === 'list' ? 'primary' : 'default'}
              size="lg"
              onPress={() => setViewMode('list')}
              aria-label={t('map.list_view', 'List view')}
            >
              <List className="w-4 h-4" />
            </Button>
          </div>
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
            {radiusKm !== String(DEFAULT_RADIUS_KM) && (
              <Chip
                onClose={() => setRadiusKm(String(DEFAULT_RADIUS_KM))}
                variant="flat"
                size="sm"
              >
                {t('map.radius_chip', '{{km}} km radius', { km: radiusKm })}
              </Chip>
            )}
          </div>
        )}

        {/* Results info */}
        {!isLoading && listings.length > 0 && (
          <div className="flex items-center justify-between">
            <p className="text-sm text-default-500">
              {t('map.results_count', '{{count}} listings found nearby', { count: listings.length })}
            </p>
            <Button
              variant="flat"
              size="sm"
              startContent={<RefreshCw className="w-3.5 h-3.5" />}
              onPress={loadListings}
            >
              {t('map.refresh', 'Refresh')}
            </Button>
          </div>
        )}

        {/* Main layout */}
        <div className="flex gap-6">
          {/* Desktop: filter sidebar (compact) */}
          <aside className="hidden lg:block w-80 shrink-0">
            <div className="space-y-4 sticky top-24">
              {/* List view of results */}
              {isLoading ? (
                <div className="flex justify-center py-12">
                  <Spinner size="lg" color="primary" />
                </div>
              ) : listings.length === 0 ? (
                <EmptyState
                  icon={<Search className="w-6 h-6" />}
                  title={t('map.no_results_title', 'No Nearby Listings')}
                  description={t('map.no_results_description', 'Try expanding your search radius or moving the map.')}
                />
              ) : (
                <div className="space-y-3 max-h-[calc(100vh-250px)] overflow-y-auto pr-1 scrollbar-hide">
                  {listings.map((listing) => (
                    <Link
                      key={listing.id}
                      to={tenantPath(`/marketplace/${listing.id}`)}
                      className="block group"
                    >
                      <GlassCard hoverable className="p-3">
                        <div className="flex gap-3">
                          {/* Thumbnail */}
                          <div className="w-16 h-16 shrink-0 rounded-lg overflow-hidden bg-default-100">
                            {listing.image ? (
                              <img
                                src={listing.image.thumbnail_url || listing.image.url}
                                alt={listing.title}
                                className="w-full h-full object-cover"
                                loading="lazy"
                              />
                            ) : (
                              <div className="w-full h-full flex items-center justify-center bg-default-100">
                                <Search className="w-5 h-5 text-default-300" />
                              </div>
                            )}
                          </div>
                          {/* Details */}
                          <div className="flex-1 min-w-0">
                            <p className="text-sm font-semibold line-clamp-1 text-foreground group-hover:text-primary transition-colors">
                              {listing.title}
                            </p>
                            <p className="text-sm font-bold text-primary mt-0.5">
                              {listing.price_type === 'free' || listing.price === null || listing.price === 0
                                ? t('price.free', 'Free')
                                : new Intl.NumberFormat(undefined, {
                                    style: 'currency',
                                    currency: listing.price_currency || 'EUR',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 2,
                                  }).format(listing.price)}
                            </p>
                            {listing.distance_km != null && (
                              <p className="text-xs text-default-400 mt-0.5">
                                {listing.distance_km < 1
                                  ? t('map.distance_meters', '{{m}}m away', { m: Math.round(listing.distance_km * 1000) })
                                  : t('map.distance_km', '{{km}} km away', { km: listing.distance_km.toFixed(1) })}
                              </p>
                            )}
                          </div>
                        </div>
                      </GlassCard>
                    </Link>
                  ))}
                </div>
              )}
            </div>
          </aside>

          {/* Map area */}
          <div className="flex-1 min-w-0">
            {/* Desktop: always show map. Mobile: depends on viewMode */}
            <div className={viewMode === 'list' ? 'hidden lg:block' : ''}>
              <MapSearchView
                listings={listings}
                center={mapCenter}
                zoom={12}
                height="calc(100vh - 280px)"
                isLoading={isLoading && !listings.length}
                onRequestLocation={handleUseMyLocation}
                locationLoading={geo.loading}
              />
            </div>

            {/* Mobile list view */}
            <div className={viewMode === 'map' ? 'hidden' : 'lg:hidden'}>
              {isLoading ? (
                <div className="flex justify-center py-12">
                  <Spinner size="lg" color="primary" />
                </div>
              ) : listings.length === 0 ? (
                <EmptyState
                  icon={<Search className="w-6 h-6" />}
                  title={t('map.no_results_title', 'No Nearby Listings')}
                  description={t('map.no_results_description', 'Try expanding your search radius or moving the map.')}
                />
              ) : (
                <MarketplaceListingGrid
                  listings={listings}
                  onSave={handleSave}
                  onUnsave={handleUnsave}
                />
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
}

export default MarketplaceMapSearchPage;
