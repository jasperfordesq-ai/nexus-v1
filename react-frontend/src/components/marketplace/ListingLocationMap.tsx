// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ListingLocationMap — Small map on listing detail showing approximate item location.
 *
 * Privacy: offsets the exact coordinates by a small random amount so the precise
 * address is not revealed. Shows the location name below the map and a
 * "Get Directions" link that opens Google Maps.
 *
 * Falls back to a text-only display when Google Maps is not configured.
 */

import { useMemo } from 'react';
import { Button } from '@heroui/react';
import { MapPin, Navigation, MapPinOff } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { LocationMap, type MapMarker } from '@/components/location';
import { MAPS_ENABLED } from '@/lib/map-config';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface ListingLocationMapProps {
  latitude: number;
  longitude: number;
  location: string;
  className?: string;
  mapHeight?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Privacy: small random offset so exact address is not shown
// ─────────────────────────────────────────────────────────────────────────────

function offsetCoord(value: number): number {
  // Offset by roughly 100-300 meters (approx 0.001-0.003 degrees)
  const offset = (Math.random() - 0.5) * 0.005;
  return value + offset;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function ListingLocationMap({
  latitude,
  longitude,
  location,
  className = '',
  mapHeight = '200px',
}: ListingLocationMapProps) {
  const { t } = useTranslation('marketplace');

  // Compute a stable offset per render (privacy)
  const offsetPosition = useMemo(
    () => ({
      lat: offsetCoord(latitude),
      lng: offsetCoord(longitude),
    }),
    [latitude, longitude]
  );

  const markers: MapMarker[] = useMemo(
    () => [
      {
        id: 'listing-location',
        lat: offsetPosition.lat,
        lng: offsetPosition.lng,
        title: location,
      },
    ],
    [offsetPosition, location]
  );

  const googleMapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${latitude},${longitude}`;

  return (
    <GlassCard className={`p-0 overflow-hidden ${className}`}>
      {/* Header */}
      <div className="flex items-center gap-2 px-4 pt-4 pb-2">
        <MapPin className="w-4 h-4 text-primary" />
        <h3 className="text-sm font-semibold text-foreground">
          {t('listing.location_title', 'Location')}
        </h3>
      </div>

      {/* Map or fallback */}
      {MAPS_ENABLED ? (
        <div className="px-4">
          <LocationMap
            markers={markers}
            center={offsetPosition}
            zoom={14}
            height={mapHeight}
            fitBounds={false}
            className="rounded-lg"
          />
        </div>
      ) : (
        <div className="px-4 py-6 flex flex-col items-center gap-2">
          <MapPinOff className="w-10 h-10 text-default-300" />
          <p className="text-xs text-default-400">
            {t('map.not_available_short', 'Map not available')}
          </p>
        </div>
      )}

      {/* Location text + directions */}
      <div className="px-4 py-3 flex items-center justify-between gap-2">
        <div className="min-w-0">
          <p className="text-sm text-foreground truncate">{location}</p>
          <p className="text-xs text-default-400">
            {t('listing.approximate_location', 'Approximate location')}
          </p>
        </div>
        <Button
          as="a"
          href={googleMapsUrl}
          target="_blank"
          rel="noopener noreferrer"
          variant="flat"
          size="sm"
          color="primary"
          startContent={<Navigation className="w-3.5 h-3.5" />}
        >
          {t('listing.get_directions', 'Directions')}
        </Button>
      </div>
    </GlassCard>
  );
}

export default ListingLocationMap;
