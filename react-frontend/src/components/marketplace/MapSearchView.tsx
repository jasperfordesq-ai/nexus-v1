// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MapSearchView — Map-based marketplace listing search.
 *
 * Renders an interactive Google Map with listing pins color-coded by price_type.
 * Clicking a pin opens an info popup with a listing card preview. Falls back to
 * a text-based message when Google Maps is unavailable (no API key).
 *
 * Uses existing LocationMap / @vis.gl/react-google-maps infrastructure and
 * @googlemaps/markerclusterer for dense datasets.
 */

import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Button, Spinner } from '@heroui/react';
import { MapPin, MapPinOff, Navigation, ShoppingBag } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { LocationMap, type MapMarker } from '@/components/location';
import { MAPS_ENABLED } from '@/lib/map-config';
import { PriceBadge } from './PriceBadge';
import { ConditionBadge } from './ConditionBadge';
import { useTenant } from '@/contexts';
import type { MarketplaceListingItem } from '@/types/marketplace';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface MapSearchViewProps {
  listings: MarketplaceListingItem[];
  center?: { lat: number; lng: number };
  zoom?: number;
  height?: string;
  className?: string;
  isLoading?: boolean;
  /** Called when user clicks "Use my location" */
  onRequestLocation?: () => void;
  locationLoading?: boolean;
}

// ─────────────────────────────────────────────────────────────────────────────
// Pin color mapping by price_type
// ─────────────────────────────────────────────────────────────────────────────

const PIN_COLORS: Record<string, string> = {
  free: '#22c55e',       // green
  fixed: '#3b82f6',      // blue
  negotiable: '#eab308', // yellow
  auction: '#f97316',    // orange
  contact: '#8b5cf6',    // purple
};

// ─────────────────────────────────────────────────────────────────────────────
// Info Window Content — mini listing card inside the map popup
// ─────────────────────────────────────────────────────────────────────────────

function ListingInfoContent({ listing }: { listing: MarketplaceListingItem }) {
  const { tenantPath } = useTenant();
  const imageUrl = listing.image?.thumbnail_url || listing.image?.url;

  return (
    <Link
      to={tenantPath(`/marketplace/${listing.id}`)}
      className="block w-56 no-underline text-foreground"
    >
      <div className="flex gap-2">
        {/* Thumbnail */}
        <div className="w-16 h-16 shrink-0 rounded-lg overflow-hidden bg-default-100">
          {imageUrl ? (
            <img
              src={imageUrl}
              alt={listing.title}
              className="w-full h-full object-cover"
              loading="lazy"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center">
              <ShoppingBag className="w-6 h-6 text-default-300" />
            </div>
          )}
        </div>

        {/* Details */}
        <div className="flex-1 min-w-0 space-y-1">
          <p className="text-sm font-semibold line-clamp-2 leading-tight">
            {listing.title}
          </p>
          <PriceBadge
            price={listing.price}
            currency={listing.price_currency}
            priceType={listing.price_type}
            timeCreditPrice={listing.time_credit_price}
          />
          {listing.condition && (
            <ConditionBadge condition={listing.condition} />
          )}
        </div>
      </div>
    </Link>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function MapSearchView({
  listings,
  center,
  zoom = 12,
  height = '600px',
  className = '',
  isLoading = false,
  onRequestLocation,
  locationLoading = false,
}: MapSearchViewProps) {
  const { t } = useTranslation('marketplace');

  // Build map markers from listings that have location coordinates
  // Note: MarketplaceListingItem doesn't carry lat/lng, but the extended detail
  // or nearby API responses may. We rely on the page passing pre-enriched items.
  // For items without explicit lat/lng, we skip them on the map.
  const markers: MapMarker[] = useMemo(() => {
    const result: MapMarker[] = [];
    for (const listing of listings) {
      // The listing must have been enriched with lat/lng from the nearby API
      const item = listing as MarketplaceListingItem & { latitude?: number; longitude?: number };
      if (typeof item.latitude !== 'number' || typeof item.longitude !== 'number') continue;

      result.push({
        id: item.id,
        lat: item.latitude,
        lng: item.longitude,
        title: item.title,
        pinColor: PIN_COLORS[item.price_type] || PIN_COLORS.fixed,
        infoContent: <ListingInfoContent listing={listing} />,
      });
    }
    return result;
  }, [listings]);

  // ─── Fallback: Maps not enabled ───────────────────────────────────────────
  if (!MAPS_ENABLED) {
    return (
      <GlassCard className={`p-8 flex flex-col items-center justify-center gap-4 ${className}`}>
        <MapPinOff className="w-14 h-14 text-default-300" />
        <div className="text-center space-y-1">
          <h3 className="text-lg font-semibold text-foreground">
            {t('map.not_available_title', 'Map View Not Available')}
          </h3>
          <p className="text-sm text-default-500">
            {t('map.not_available_description', 'The map library is not configured. Listings are shown in the list view.')}
          </p>
        </div>
      </GlassCard>
    );
  }

  // ─── Loading state ────────────────────────────────────────────────────────
  if (isLoading) {
    return (
      <div
        className={`rounded-xl bg-default-100 animate-pulse flex items-center justify-center ${className}`}
        style={{ height }}
      >
        <Spinner size="lg" color="primary" />
      </div>
    );
  }

  // ─── Empty state ──────────────────────────────────────────────────────────
  if (markers.length === 0) {
    return (
      <GlassCard className={`flex flex-col items-center justify-center gap-4 p-8 ${className}`} style={{ minHeight: '300px' }}>
        <MapPin className="w-12 h-12 text-default-300" />
        <div className="text-center space-y-1">
          <h3 className="font-semibold text-foreground">
            {t('map.no_results_title', 'No Listings on Map')}
          </h3>
          <p className="text-sm text-default-500">
            {t('map.no_results_description', 'No listings with location data found. Try expanding your search area.')}
          </p>
        </div>
        {onRequestLocation && (
          <Button
            variant="flat"
            color="primary"
            startContent={<Navigation className="w-4 h-4" />}
            onPress={onRequestLocation}
            isLoading={locationLoading}
            size="sm"
          >
            {t('map.use_my_location', 'Use My Location')}
          </Button>
        )}
      </GlassCard>
    );
  }

  // ─── Map with markers ─────────────────────────────────────────────────────
  return (
    <div className={`relative ${className}`}>
      <LocationMap
        markers={markers}
        center={center}
        zoom={zoom}
        height={height}
        fitBounds={markers.length > 1}
        cluster={markers.length > 10}
      />

      {/* Use my location button — overlaid on map */}
      {onRequestLocation && (
        <div className="absolute bottom-4 left-4 z-10">
          <Button
            variant="solid"
            color="default"
            size="sm"
            startContent={<Navigation className="w-3.5 h-3.5" />}
            onPress={onRequestLocation}
            isLoading={locationLoading}
            className="bg-background/90 backdrop-blur-sm shadow-md"
          >
            {t('map.use_my_location', 'Use My Location')}
          </Button>
        </div>
      )}

      {/* Pin legend */}
      <div className="absolute top-4 right-4 z-10">
        <div className="bg-background/90 backdrop-blur-sm rounded-lg p-2 shadow-md space-y-1">
          <div className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-green-500" />
            <span className="text-xs text-default-600">{t('price_type.free', 'Free')}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-blue-500" />
            <span className="text-xs text-default-600">{t('price_type.fixed', 'Fixed Price')}</span>
          </div>
          <div className="flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-yellow-500" />
            <span className="text-xs text-default-600">{t('price_type.negotiable', 'Negotiable')}</span>
          </div>
        </div>
      </div>
    </div>
  );
}

export default MapSearchView;
