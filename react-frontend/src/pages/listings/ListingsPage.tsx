// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Listings Page - Browse all listings
 */

import { useState, useEffect, useCallback, memo, useRef, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Input, Select, SelectItem, Avatar } from '@heroui/react';
import {
  Search,
  Plus,
  Filter,
  Grid,
  List,
  ListTodo,
  Map as MapIcon,
  MapPin,
  Tag,
  Clock,
  Calendar,
  Heart,
  AlertTriangle,
  RefreshCw,
  Monitor,
  ArrowRightLeft,
  Star,
  SlidersHorizontal,
  X,
} from 'lucide-react';
import { GlassCard, AlgorithmLabel, ListingSkeleton, ImagePlaceholder } from '@/components/ui';
import { FeaturedBadge } from '@/components/listings/FeaturedBadge';
import { VerificationBadgeRow } from '@/components/verification/VerificationBadge';
import { EntityMapView } from '@/components/location';
import { EmptyState } from '@/components/feedback';
import { PageMeta } from '@/components/seo';
import { useAuth, useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { MAPS_ENABLED } from '@/lib/map-config';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveAssetUrl } from '@/lib/helpers';
import type { Listing, Category } from '@/types/api';

type ListingType = 'all' | 'offer' | 'request';
type ViewMode = 'grid' | 'list' | 'map';

export function ListingsPage() {
  const { t } = useTranslation('listings');
  usePageTitle(t('title'));
  const { isAuthenticated, user } = useAuth();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();

  const [listings, setListings] = useState<Listing[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [searchInput, setSearchInput] = useState(searchParams.get('q') || '');
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const validTypes: ListingType[] = ['all', 'offer', 'request'];
  const validHours = ['any', 'quick', 'short', 'half_day', 'full_day'];
  const validService = ['any', 'remote', 'in_person'];
  const validPosted = ['any', '1', '7', '30'];

  const [selectedType, setSelectedType] = useState<ListingType>(() => {
    const v = searchParams.get('type');
    return v && validTypes.includes(v as ListingType) ? (v as ListingType) : 'all';
  });
  const [selectedCategory, setSelectedCategory] = useState(searchParams.get('category') || '');
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [hasMore, setHasMore] = useState(false);
  const [totalItems, setTotalItems] = useState<number | null>(null);
  const [nearMeEnabled, setNearMeEnabled] = useState(searchParams.get('near_me') === '1');
  const [radiusKm, setRadiusKm] = useState(() => {
    const v = Number(searchParams.get('radius'));
    return [5, 10, 25, 50, 100].includes(v) ? v : 25;
  });
  const [hoursRange, setHoursRange] = useState(() => {
    const v = searchParams.get('hours');
    return v && validHours.includes(v) ? v : 'any';
  });
  const [serviceMode, setServiceMode] = useState(() => {
    const v = searchParams.get('service');
    return v && validService.includes(v) ? v : 'any';
  });
  const [postedWithin, setPostedWithin] = useState(() => {
    const v = searchParams.get('posted');
    return v && validPosted.includes(v) ? v : 'any';
  });
  const [showAdvancedFilters, setShowAdvancedFilters] = useState(
    // Auto-expand if any advanced filter is active from URL
    !!(searchParams.get('hours') || searchParams.get('service') || searchParams.get('posted') || searchParams.get('near_me')),
  );

  // Count of active advanced filters (shown as badge on the toggle button)
  const activeFilterCount = useMemo(() => {
    let count = 0;
    if (hoursRange !== 'any') count++;
    if (serviceMode !== 'any') count++;
    if (postedWithin !== 'any') count++;
    if (nearMeEnabled) count++;
    return count;
  }, [hoursRange, serviceMode, postedWithin, nearMeEnabled]);

  // Use a ref for cursor to avoid infinite re-render loop (same pattern as FeedPage)
  const cursorRef = useRef<string | null>(null);
  // Track in-flight save requests to prevent double-clicks
  const [savingIds, setSavingIds] = useState<Set<number>>(new Set());

  // Stable refs for values used in loadListings but that should NOT trigger re-creation
  const toastRef = useRef(toast);
  toastRef.current = toast;
  const tRef = useRef(t);
  tRef.current = t;

  // AbortController ref to cancel stale requests (prevents StrictMode double-fire race condition)
  const abortRef = useRef<AbortController | null>(null);

  const loadListings = useCallback(async (reset = false) => {
    // Cancel any in-flight request to prevent stale responses overwriting fresh data
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      if (reset) {
        setLoadError(null);
        cursorRef.current = null;
      }
      const params = new URLSearchParams();

      if (searchQuery) params.set('q', searchQuery);
      if (selectedType !== 'all') params.set('type', selectedType);
      if (selectedCategory) params.set('category', selectedCategory);
      if (!reset && cursorRef.current) params.set('cursor', cursorRef.current);
      params.set('per_page', '20');

      // Faceted filters
      if (hoursRange !== 'any') {
        const hoursMap: Record<string, { min?: string; max?: string }> = {
          'quick': { max: '1' },
          'short': { min: '1', max: '3' },
          'half_day': { min: '3', max: '6' },
          'full_day': { min: '6' },
        };
        const range = hoursMap[hoursRange];
        if (range?.min) params.set('min_hours', range.min);
        if (range?.max) params.set('max_hours', range.max);
      }
      if (serviceMode !== 'any') {
        params.set('service_type', serviceMode === 'remote' ? 'remote_only,hybrid' : 'physical_only');
      }
      if (postedWithin !== 'any') {
        params.set('posted_within', postedWithin);
      }

      let endpoint = '/v2/listings';
      if (nearMeEnabled && user?.latitude != null && user?.longitude != null) {
        endpoint = '/v2/listings/nearby';
        params.set('lat', String(user.latitude));
        params.set('lon', String(user.longitude));
        params.set('radius_km', String(radiusKm));
      }

      const response = await api.get<Listing[]>(`${endpoint}?${params}`);

      // If this request was aborted while awaiting, discard the result
      if (controller.signal.aborted) return;

      if (response.success && response.data) {
        if (reset) {
          setListings(response.data);
        } else {
          setListings((prev) => [...prev, ...response.data!]);
        }

        // Handle pagination meta if present
        cursorRef.current = response.meta?.cursor ?? null;
        setHasMore(response.meta?.has_more ?? false);
        if (reset && response.meta?.total_items != null) {
          setTotalItems(response.meta.total_items);
        }
      } else {
        if (reset) {
          setListings([]);
        }
        setHasMore(false);
      }
    } catch (error) {
      if (controller.signal.aborted) return;
      logError('Failed to load listings', error);
      if (reset) {
        setLoadError(tRef.current('load_error'));
      } else {
        toastRef.current.error(tRef.current('load_more_error'));
      }
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
      }
    }
  }, [searchQuery, selectedType, selectedCategory, nearMeEnabled, user?.latitude, user?.longitude, radiusKm, hoursRange, serviceMode, postedWithin]);

  // Keep a ref so effects always call the latest version without depending on it
  const loadListingsRef = useRef(loadListings);
  loadListingsRef.current = loadListings;

  // Stable items array for the category Select — memoized so HeroUI's
  // collection doesn't rebuild on every render (new array ref = rebuild = selected key lost)
  const categoryItems = useMemo(
    () => [{ slug: 'all', name: t('filter_all_categories') }, ...categories],
    [categories, t],
  );

  // Load categories once on mount
  useEffect(() => {
    api.get<Category[]>('/v2/categories?type=listing').then((response) => {
      if (response.success && response.data) {
        setCategories(response.data);
      }
    }).catch((error) => logError('Failed to load categories', error));
  }, []);

  // Debounce search input → searchQuery (300ms)
  useEffect(() => {
    const timer = setTimeout(() => setSearchQuery(searchInput), 300);
    return () => clearTimeout(timer);
  }, [searchInput]);

  // Load listings when filters change — separate from URL sync to avoid reset loops
  useEffect(() => {
    loadListingsRef.current(true);
    return () => { abortRef.current?.abort(); };
  }, [searchQuery, selectedType, selectedCategory, nearMeEnabled, user?.latitude, user?.longitude, radiusKm, hoursRange, serviceMode, postedWithin]);

  // Sync URL params with filter state (harmless if it re-runs)
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchInput) params.set('q', searchInput);
    if (selectedType !== 'all') params.set('type', selectedType);
    if (selectedCategory) params.set('category', selectedCategory);
    if (hoursRange !== 'any') params.set('hours', hoursRange);
    if (serviceMode !== 'any') params.set('service', serviceMode);
    if (postedWithin !== 'any') params.set('posted', postedWithin);
    if (nearMeEnabled) params.set('near_me', '1');
    if (nearMeEnabled && radiusKm !== 25) params.set('radius', String(radiusKm));
    setSearchParams(params, { replace: true });
  }, [searchInput, selectedType, selectedCategory, hoursRange, serviceMode, postedWithin, nearMeEnabled, radiusKm, setSearchParams]);

  function handleSearch(e: React.FormEvent) {
    e.preventDefault();
    setSearchQuery(searchInput); // Immediately apply (bypass debounce)
  }

  function handleNearMeToggle() {
    if (nearMeEnabled) {
      setNearMeEnabled(false);
      return;
    }
    if (!user?.latitude || !user?.longitude) {
      toast.error(t('near_me_no_location', 'Set your location in your profile to use Near me'));
      return;
    }
    setNearMeEnabled(true);
  }

  /**
   * Toggle save/unsave a listing with optimistic UI update.
   * Reverts on failure.
   */
  const handleToggleSave = useCallback(async (listingId: number, currentlySaved: boolean) => {
    if (savingIds.has(listingId)) return; // Prevent double-click

    // Optimistic update
    setSavingIds((prev) => new Set(prev).add(listingId));
    setListings((prev) =>
      prev.map((l) => l.id === listingId ? { ...l, is_favorited: !currentlySaved } : l)
    );

    try {
      if (currentlySaved) {
        await api.delete(`/v2/listings/${listingId}/save`);
        toast.success(t('unsaved_success', 'Listing removed from saved'));
      } else {
        await api.post(`/v2/listings/${listingId}/save`);
        toast.success(t('saved_success', 'Listing saved'));
      }
    } catch (error) {
      logError('Failed to toggle save listing', error);
      // Revert optimistic update
      setListings((prev) =>
        prev.map((l) => l.id === listingId ? { ...l, is_favorited: currentlySaved } : l)
      );
      toast.error(t('save_error', 'Failed to update saved listing'));
    } finally {
      setSavingIds((prev) => {
        const next = new Set(prev);
        next.delete(listingId);
        return next;
      });
    }
  }, [savingIds, t, toast]);

  return (
    <>
      <PageMeta
        title={t('title')}
        description={t("page_meta_description")}
        keywords={t("page_meta_keywords")}
      />
      <div className="space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
              <ListTodo className="w-7 h-7 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              {t('title')}
            </h1>
            <div className="flex items-center gap-2 mt-1">
              <p className="text-theme-muted">{t('page_subtitle')}</p>
              <AlgorithmLabel area="listings" />
            </div>
          </div>
        {isAuthenticated && (
          <Link to={tenantPath('/listings/create')}>
            <Button
              className="bg-linear-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<Plus className="w-4 h-4" />}
            >
              {t('create')}
            </Button>
          </Link>
        )}
      </div>

      {/* Filters */}
      <GlassCard className="p-4">
        {/* Row 1: Search + primary filters */}
        <form onSubmit={handleSearch} className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1 min-w-0">
            <Input
              placeholder={t('search_placeholder')}
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" />}
              aria-label={t('search_placeholder')}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          <div className="flex flex-col sm:flex-row flex-wrap gap-3 items-stretch sm:items-center">
            <Select
              aria-label={t('filter_type_label')}
              placeholder={t('filter_type_label')}
              selectedKeys={[selectedType]}
              disallowEmptySelection
              onSelectionChange={(keys) => {
                const val = keys instanceof Set ? ([...keys][0] as string) : 'all';
                setSelectedType((val || 'all') as ListingType);
              }}
              className="w-full sm:w-36"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Filter className="w-4 h-4 text-theme-subtle" />}
            >
              <SelectItem key="all">{t('filter_all_types')}</SelectItem>
              <SelectItem key="offer">{t('filters.offers')}</SelectItem>
              <SelectItem key="request">{t('filters.requests')}</SelectItem>
            </Select>

            <Select
              aria-label={t('filter_category_label')}
              placeholder={t('filter_category_label')}
              selectedKeys={[categories.length > 0 ? (selectedCategory || 'all') : 'all']}
              disallowEmptySelection
              onSelectionChange={(keys) => {
                const val = keys instanceof Set ? ([...keys][0] as string) : 'all';
                setSelectedCategory(val === 'all' ? '' : (val || ''));
              }}
              className="w-full sm:w-44"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" />}
              items={categoryItems}
            >
              {(cat) => <SelectItem key={cat.slug}>{cat.name}</SelectItem>}
            </Select>

            {/* Filters toggle */}
            <Button
              variant={showAdvancedFilters ? 'solid' : 'flat'}
              className={showAdvancedFilters
                ? 'bg-primary text-white min-h-[40px]'
                : 'bg-theme-elevated text-theme-primary min-h-[40px]'}
              startContent={<SlidersHorizontal className="w-4 h-4" aria-hidden="true" />}
              onPress={() => setShowAdvancedFilters((prev) => !prev)}
              aria-expanded={showAdvancedFilters}
              aria-label={t('more_filters', 'More filters')}
            >
              {t('filters_label', 'Filters')}
              {activeFilterCount > 0 && (
                <span className="ml-1 inline-flex items-center justify-center w-5 h-5 rounded-full bg-white/20 text-xs font-bold">
                  {activeFilterCount}
                </span>
              )}
            </Button>

            <div className="flex rounded-lg overflow-hidden border border-theme-default" role="group" aria-label={t('aria.view_mode', 'View mode')}>
              <Button
                isIconOnly
                variant="light"
                className={`rounded-none min-w-[44px] min-h-[44px] ${viewMode === 'grid' ? 'bg-theme-hover' : 'bg-theme-elevated'}`}
                aria-label={t('aria_grid_view')}
                aria-pressed={viewMode === 'grid'}
                onPress={() => setViewMode('grid')}
              >
                <Grid className="w-4 h-4 text-theme-primary" aria-hidden="true" />
              </Button>
              <Button
                isIconOnly
                variant="light"
                className={`rounded-none min-w-[44px] min-h-[44px] ${viewMode === 'list' ? 'bg-theme-hover' : 'bg-theme-elevated'}`}
                aria-label={t('aria_list_view')}
                aria-pressed={viewMode === 'list'}
                onPress={() => setViewMode('list')}
              >
                <List className="w-4 h-4 text-theme-primary" aria-hidden="true" />
              </Button>
              {MAPS_ENABLED && (
                <Button
                  isIconOnly
                  variant="light"
                  className={`rounded-none rounded-r-lg min-w-[44px] min-h-[44px] ${viewMode === 'map' ? 'bg-primary/10 text-primary' : 'bg-theme-elevated'}`}
                  aria-label={t('aria_map_view')}
                  aria-pressed={viewMode === 'map'}
                  onPress={() => setViewMode('map')}
                >
                  <MapIcon className="w-4 h-4" aria-hidden="true" />
                </Button>
              )}
            </div>
          </div>
        </form>

        {/* Row 2: Advanced filters (toggled) */}
        {showAdvancedFilters && (
          <div className="flex flex-col sm:flex-row flex-wrap gap-3 mt-3 pt-3 border-t border-theme-default">
            <Select
              aria-label={t('filter_hours', 'Duration')}
              placeholder={t('filter_hours', 'Duration')}
              selectedKeys={[hoursRange]}
              disallowEmptySelection
              onSelectionChange={(keys) => {
                const val = keys instanceof Set ? ([...keys][0] as string) : 'any';
                setHoursRange(val || 'any');
              }}
              className="w-full sm:w-40"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Clock className="w-4 h-4 text-theme-subtle" />}
            >
              <SelectItem key="any">{t('filter_any_duration', 'Any duration')}</SelectItem>
              <SelectItem key="quick">{t('filter_quick', 'Quick (under 1h)')}</SelectItem>
              <SelectItem key="short">{t('filter_short', 'Short (1-3h)')}</SelectItem>
              <SelectItem key="half_day">{t('filter_half_day', 'Half day (3-6h)')}</SelectItem>
              <SelectItem key="full_day">{t('filter_full_day', 'Full day (6h+)')}</SelectItem>
            </Select>

            <Select
              aria-label={t('filter_service_mode', 'Service mode')}
              placeholder={t('filter_service_mode', 'Service mode')}
              selectedKeys={[serviceMode]}
              disallowEmptySelection
              onSelectionChange={(keys) => {
                const val = keys instanceof Set ? ([...keys][0] as string) : 'any';
                setServiceMode(val || 'any');
              }}
              className="w-full sm:w-44"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<MapIcon className="w-4 h-4 text-theme-subtle" />}
            >
              <SelectItem key="any">{t('filter_any_mode', 'Any mode')}</SelectItem>
              <SelectItem key="remote">{t('filter_remote', 'Remote available')}</SelectItem>
              <SelectItem key="in_person">{t('filter_in_person', 'In-person only')}</SelectItem>
            </Select>

            <Select
              aria-label={t('filter_posted_date', 'Posted date')}
              placeholder={t('filter_posted_date', 'Posted date')}
              selectedKeys={[postedWithin]}
              disallowEmptySelection
              onSelectionChange={(keys) => {
                const val = keys instanceof Set ? ([...keys][0] as string) : 'any';
                setPostedWithin(val || 'any');
              }}
              className="w-full sm:w-36"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Calendar className="w-4 h-4 text-theme-subtle" />}
            >
              <SelectItem key="any">{t('filter_any_time', 'Any time')}</SelectItem>
              <SelectItem key="1">{t('filter_today', 'Today')}</SelectItem>
              <SelectItem key="7">{t('filter_this_week', 'This week')}</SelectItem>
              <SelectItem key="30">{t('filter_this_month', 'This month')}</SelectItem>
            </Select>

            <Button
              variant={nearMeEnabled ? 'solid' : 'flat'}
              className={nearMeEnabled
                ? 'bg-primary text-white min-h-[40px]'
                : 'bg-theme-elevated text-theme-primary min-h-[40px]'}
              startContent={<MapPin className="w-4 h-4" aria-hidden="true" />}
              onPress={handleNearMeToggle}
              aria-pressed={nearMeEnabled}
              aria-label={t('near_me', 'Near me')}
            >
              {t('near_me', 'Near me')}
            </Button>

            {nearMeEnabled && (
              <Select
                aria-label={t('radius_label', 'Radius')}
                placeholder={t('radius_label', 'Radius')}
                selectedKeys={[String(radiusKm)]}
                disallowEmptySelection
                onSelectionChange={(keys) => {
                  const val = keys instanceof Set ? ([...keys][0] as string) : '25';
                  setRadiusKm(Number(val) || 25);
                }}
                className="w-full sm:w-32"
                classNames={{
                  trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                  value: 'text-theme-primary',
                }}
              >
                <SelectItem key="5">5 km</SelectItem>
                <SelectItem key="10">10 km</SelectItem>
                <SelectItem key="25">25 km</SelectItem>
                <SelectItem key="50">50 km</SelectItem>
                <SelectItem key="100">100 km</SelectItem>
              </Select>
            )}

            {activeFilterCount > 0 && (
              <Button
                variant="light"
                className="text-theme-muted hover:text-theme-primary min-h-[40px]"
                startContent={<X className="w-4 h-4" aria-hidden="true" />}
                onPress={() => {
                  setHoursRange('any');
                  setServiceMode('any');
                  setPostedWithin('any');
                  setNearMeEnabled(false);
                }}
                aria-label={t('clear_filters', 'Clear filters')}
              >
                {t('clear_filters', 'Clear filters')}
              </Button>
            )}
          </div>
        )}
      </GlassCard>

      {/* Results count */}
      {!isLoading && totalItems != null && listings.length > 0 && (
        <p className="text-sm text-theme-muted">
          {t('results_count', '{{count}} listings found', { count: totalItems })}
        </p>
      )}

      {/* Listings Grid/List */}
      {isLoading && listings.length === 0 ? (
        <div
          className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4'}
          aria-label={t('aria.loading_listings', 'Loading listings')}
          aria-busy="true"
        >
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <ListingSkeleton key={i} />
          ))}
        </div>
      ) : loadError ? (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('load_error_title')}</h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-linear-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadListings(true)}
          >
            {t('try_again')}
          </Button>
        </GlassCard>
      ) : listings.length === 0 ? (
        <EmptyState
          icon={<Search className="w-12 h-12" />}
          title={t('empty')}
          description={t('empty_subtitle')}
          action={
            isAuthenticated && (
              <Link to={tenantPath('/listings/create')}>
                <Button className="bg-linear-to-r from-indigo-500 to-purple-600 text-white">
                  {t('create')}
                </Button>
              </Link>
            )
          }
        />
      ) : (
        <>
          {viewMode === 'map' ? (
            <EntityMapView
              items={listings}
              getCoordinates={(l) =>
                l.latitude && l.longitude ? { lat: Number(l.latitude), lng: Number(l.longitude) } : null
              }
              getMarkerConfig={(l) => ({
                id: l.id,
                title: l.title,
                pinColor: l.type === 'offer' ? '#10b981' : '#f59e0b',
              })}
              renderInfoContent={(l) => (
                <div className="p-2 max-w-[250px]">
                  <div className="flex items-center gap-1 mb-1">
                    <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-medium ${
                      l.type === 'offer' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
                    }`}>
                      {l.type === 'offer' ? t('offer') : t('request')}
                    </span>
                  </div>
                  <h4 className="font-semibold text-sm text-gray-900">{l.title}</h4>
                  <p className="text-xs text-gray-600 line-clamp-2 mt-0.5">{l.description}</p>
                  {l.location && <p className="text-xs text-gray-500 mt-1">{l.location}</p>}
                </div>
              )}
              isLoading={isLoading}
              emptyMessage={t('map_empty')}
            />
          ) : (
            <div
              className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4'}
            >
              {listings.map((listing) => (
                <motion.div
                  key={listing.id}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.3 }}
                >
                  <ListingCard
                    listing={listing}
                    viewMode={viewMode}
                    isSaving={savingIds.has(listing.id)}
                    onToggleSave={isAuthenticated ? handleToggleSave : undefined}
                  />
                </motion.div>
              ))}
            </div>
          )}

          {/* Load More */}
          {hasMore && (
            <div className="text-center pt-4">
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                onPress={() => loadListings()}
                isLoading={isLoading}
              >
                {t('load_more')}
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
  isSaving?: boolean;
  onToggleSave?: (listingId: number, currentlySaved: boolean) => void;
}

const ListingCard = memo(function ListingCard({ listing, viewMode, isSaving, onToggleSave }: ListingCardProps) {
  const { t } = useTranslation('listings');
  const { tenantPath } = useTenant();
  const isGrid = viewMode === 'grid';
  const hours = listing.estimated_hours || listing.hours_estimate;
  const avatarSrc = resolveAvatarUrl(listing.author_avatar || listing.user?.avatar);
  const isFavorited = listing.is_favorited === true;

  function handleSaveClick() {
    if (onToggleSave && !isSaving) {
      onToggleSave(listing.id, isFavorited);
    }
  }

  const imageUrl = listing.image_url ? resolveAssetUrl(listing.image_url) : null;

  if (!isGrid) {
    // ─── List View ───
    return (
      <Link to={tenantPath(`/listings/${listing.id}`)}>
        <GlassCard className="p-4 hover:bg-theme-hover transition-colors">
          <div className="flex items-start gap-4">
            {imageUrl ? (
              <img
                src={imageUrl}
                alt={listing.title || 'Listing image'}
                className="w-16 h-16 rounded-lg object-cover shrink-0"
                width={64}
                height={64}
                loading="lazy"
              />
            ) : (
              <Avatar
                src={avatarSrc}
                name={listing.author_name || 'User'}
                size="md"
                className="shrink-0 ring-2 ring-theme-muted/20"
              />
            )}
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 mb-1">
                <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${
                  listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                }`}>
                  {listing.type === 'offer' ? t('offering') : t('requesting')}
                </span>
                {listing.service_type === 'remote_only' && (
                  <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-500/20 text-blue-600 dark:text-blue-400 font-medium flex items-center gap-0.5">
                    <Monitor className="w-2.5 h-2.5" aria-hidden="true" />
                    Remote
                  </span>
                )}
                {listing.service_type === 'hybrid' && (
                  <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-teal-500/20 text-teal-600 dark:text-teal-400 font-medium flex items-center gap-0.5">
                    <ArrowRightLeft className="w-2.5 h-2.5" aria-hidden="true" />
                    Remote Available
                  </span>
                )}
                {listing.category_name && (
                  <span className="text-[10px] px-2 py-0.5 rounded-full bg-theme-hover text-theme-muted">
                    {listing.category_name}
                  </span>
                )}
              </div>
              <div className="flex items-center gap-1.5">
                <h3 className="font-semibold text-theme-primary truncate">{listing.title}</h3>
                <VerificationBadgeRow userId={listing.user_id} size="sm" />
              </div>
              <p className="text-theme-muted text-sm line-clamp-1 mt-0.5">{listing.description}</p>
            </div>
            <div className="flex flex-col items-end gap-1 text-xs text-theme-subtle shrink-0">
              {hours && (
                <span className="flex items-center gap-1">
                  <Clock className="w-3 h-3" aria-hidden="true" />
                  {hours}h
                </span>
              )}
              {listing.location && (
                <span className="flex items-center gap-1">
                  <MapPin className="w-3 h-3" aria-hidden="true" />
                  <span className="truncate max-w-[120px]">{listing.location}</span>
                </span>
              )}
              {onToggleSave && (
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  onPress={handleSaveClick}
                  isDisabled={isSaving}
                  aria-label={isFavorited ? t('unsave_listing', 'Unsave listing') : t('save_listing', 'Save listing')}
                  className="p-1 rounded transition-colors hover:bg-theme-hover min-w-0 w-auto h-auto"
                >
                  <Heart
                    className={`w-4 h-4 transition-colors ${isFavorited ? 'fill-rose-500 text-rose-500' : 'text-theme-muted hover:text-rose-400'}`}
                    aria-hidden="true"
                  />
                </Button>
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
      <GlassCard className="hover:scale-[1.02] transition-transform h-full flex flex-col overflow-hidden">
        {/* Listing Image */}
        {imageUrl ? (
          <img
            src={imageUrl}
            alt={listing.title || 'Listing image'}
            className="w-full h-36 object-cover"
            width={800}
            height={450}
            loading="lazy"
          />
        ) : (
          <ImagePlaceholder size="sm" />
        )}

        <div className="p-5 flex flex-col flex-1">
        {/* Type + Category Badges */}
        <div className="flex items-center gap-2 mb-3">
          <span className={`text-xs px-2 py-1 rounded-full font-medium ${
            listing.type === 'offer' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
          }`}>
            {listing.type === 'offer' ? t('offering') : t('requesting')}
          </span>
          {listing.is_featured && <FeaturedBadge />}
          {listing.service_type === 'remote_only' && (
            <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-blue-500/20 text-blue-600 dark:text-blue-400 font-medium flex items-center gap-0.5">
              <Monitor className="w-2.5 h-2.5" aria-hidden="true" />
              Remote
            </span>
          )}
          {listing.service_type === 'hybrid' && (
            <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-teal-500/20 text-teal-600 dark:text-teal-400 font-medium flex items-center gap-0.5">
              <ArrowRightLeft className="w-2.5 h-2.5" aria-hidden="true" />
              Remote Available
            </span>
          )}
          {listing.category_name && (
            <span className="text-xs px-2 py-1 rounded-full bg-theme-hover text-theme-muted">
              {listing.category_name}
            </span>
          )}
          {onToggleSave && (
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              onPress={handleSaveClick}
              isDisabled={isSaving}
              aria-label={isFavorited ? t('unsave_listing', 'Unsave listing') : t('save_listing', 'Save listing')}
              className="ml-auto p-1 rounded transition-colors hover:bg-theme-hover min-w-0 w-auto h-auto"
            >
              <Heart
                className={`w-4 h-4 transition-colors ${isFavorited ? 'fill-rose-500 text-rose-500' : 'text-theme-muted hover:text-rose-400'}`}
                aria-hidden="true"
              />
            </Button>
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
              className="shrink-0 w-6 h-6"
            />
            <span className="text-sm text-theme-subtle truncate">{listing.author_name}</span>
            <VerificationBadgeRow userId={listing.user_id} size="sm" />
            {listing.author_rating != null && listing.author_rating > 0 && (
              <span className="flex items-center gap-0.5 text-[11px] text-amber-500 shrink-0">
                <Star className="w-3 h-3 fill-amber-500" aria-hidden="true" />
                {listing.author_rating.toFixed(1)}
              </span>
            )}
          </div>
          <div className="flex items-center gap-2 text-xs text-theme-subtle min-w-0 overflow-hidden">
            {hours && (
              <span className="flex items-center gap-1 shrink-0" aria-label={`${hours} hours estimated`}>
                <Clock className="w-3 h-3" aria-hidden="true" />
                {hours}h
              </span>
            )}
            {listing.location && (
              <span className="flex items-center gap-1 min-w-0" aria-label={`Location: ${listing.location}`}>
                <MapPin className="w-3 h-3 shrink-0" aria-hidden="true" />
                <span className="truncate">{listing.location}</span>
              </span>
            )}
          </div>
        </div>
        </div>{/* end p-5 wrapper */}
      </GlassCard>
    </Link>
  );
});

export default ListingsPage;
