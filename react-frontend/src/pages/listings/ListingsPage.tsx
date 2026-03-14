// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Listings Page - Browse all listings
 */

import { useState, useEffect, useCallback, memo, useRef } from 'react';
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
  Heart,
  AlertTriangle,
  RefreshCw,
} from 'lucide-react';
import { GlassCard, AlgorithmLabel, ListingSkeleton } from '@/components/ui';
import { FeaturedBadge } from '@/components/listings/FeaturedBadge';
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
  const [hasMore, setHasMore] = useState(false);

  // Use a ref for cursor to avoid infinite re-render loop (same pattern as FeedPage)
  const cursorRef = useRef<string | null>(null);
  // Track in-flight save requests to prevent double-clicks
  const [savingIds, setSavingIds] = useState<Set<number>>(new Set());

  const loadListings = useCallback(async (reset = false) => {
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

      const response = await api.get<Listing[]>(`/v2/listings?${params}`);

      if (response.success && response.data) {
        if (reset) {
          setListings(response.data);
        } else {
          setListings((prev) => [...prev, ...response.data!]);
        }

        // Handle pagination meta if present
        cursorRef.current = response.meta?.cursor ?? null;
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
        setLoadError(t('load_error'));
      } else {
        toast.error(t('load_more_error'));
      }
    } finally {
      setIsLoading(false);
    }
  }, [searchQuery, selectedType, selectedCategory]);

  const loadCategories = useCallback(async () => {
    try {
      const response = await api.get<Category[]>('/v2/categories?type=listing');
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
  }, [savingIds, t]);

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
        <form onSubmit={handleSearch} className="flex flex-col lg:flex-row gap-4">
          <div className="flex-1">
            <Input
              placeholder={t('search_placeholder')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" />}
              aria-label={t('search_placeholder')}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          <div className="flex flex-col sm:flex-row flex-wrap gap-3">
            <Select
              placeholder={t('filter_type_label')}
              selectedKeys={selectedType ? [selectedType] : []}
              onChange={(e) => setSelectedType(e.target.value as ListingType)}
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
              placeholder={t('filter_category_label')}
              selectedKeys={selectedCategory ? [selectedCategory] : []}
              onChange={(e) => setSelectedCategory(e.target.value)}
              className="w-full sm:w-44"
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                value: 'text-theme-primary',
              }}
              startContent={<Tag className="w-4 h-4 text-theme-subtle" />}
              items={[{ slug: '', name: t('filter_all_categories') }, ...categories]}
            >
              {(cat) => <SelectItem key={cat.slug}>{cat.name}</SelectItem>}
            </Select>

            <div className="flex rounded-lg overflow-hidden border border-theme-default" role="group" aria-label="View mode">
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
      </GlassCard>

      {/* Listings Grid/List */}
      {isLoading && listings.length === 0 ? (
        <div
          className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4'}
          aria-label="Loading listings"
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
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className={viewMode === 'grid' ? 'grid sm:grid-cols-2 lg:grid-cols-3 gap-4' : 'space-y-4'}
            >
              {listings.map((listing) => (
                <motion.div key={listing.id} variants={itemVariants}>
                  <ListingCard
                    listing={listing}
                    viewMode={viewMode}
                    isSaving={savingIds.has(listing.id)}
                    onToggleSave={isAuthenticated ? handleToggleSave : undefined}
                  />
                </motion.div>
              ))}
            </motion.div>
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
                {listing.category_name && (
                  <span className="text-[10px] px-2 py-0.5 rounded-full bg-theme-hover text-theme-muted">
                    {listing.category_name}
                  </span>
                )}
              </div>
              <h3 className="font-semibold text-theme-primary truncate">{listing.title}</h3>
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
        {imageUrl && (
          <img
            src={imageUrl}
            alt={listing.title || 'Listing image'}
            className="w-full h-36 object-cover"
            loading="lazy"
          />
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
