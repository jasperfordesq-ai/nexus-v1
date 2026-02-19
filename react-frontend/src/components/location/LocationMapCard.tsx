// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LocationMapCard — wraps LocationMap in a GlassCard with title.
 * Falls back to text-only display if no API key or no coordinates.
 */

import { MapPin } from 'lucide-react';
import { GlassCard } from '@/components/ui/GlassCard';
import { LocationMap, type MapMarker } from './LocationMap';
import { MAPS_ENABLED } from '@/lib/map-config';

export interface LocationMapCardProps {
  title: string;
  locationText?: string;
  markers: MapMarker[];
  center?: { lat: number; lng: number };
  mapHeight?: string;
  className?: string;
  zoom?: number;
}

export function LocationMapCard({
  title,
  locationText,
  markers,
  center,
  mapHeight = '300px',
  className = '',
  zoom = 14,
}: LocationMapCardProps) {
  const hasCoordinates = markers.length > 0 || !!center;
  const showMap = MAPS_ENABLED && hasCoordinates;

  // Nothing to show
  if (!locationText && !hasCoordinates) return null;

  return (
    <GlassCard animated className={`p-0 overflow-hidden ${className}`}>
      {/* Header */}
      <div className="flex items-center gap-2 px-4 pt-4 pb-2">
        <MapPin className="w-4 h-4 text-primary" />
        <h3 className="text-sm font-semibold text-theme-primary">{title}</h3>
      </div>

      {/* Map */}
      {showMap && (
        <div className="px-4">
          <LocationMap
            markers={markers}
            center={center}
            zoom={zoom}
            height={mapHeight}
            fitBounds={markers.length > 1}
          />
        </div>
      )}

      {/* Location text */}
      {locationText && (
        <div className="px-4 py-3">
          <p className="text-sm text-theme-muted">{locationText}</p>
        </div>
      )}
    </GlassCard>
  );
}
