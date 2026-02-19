// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * EntityMapView — full-page map view for browse pages.
 * Generic component that renders any entity type as map markers.
 */

import { type ReactNode, useMemo } from 'react';
import { MapPin, MapPinOff } from 'lucide-react';
import { LocationMap, type MapMarker } from './LocationMap';
import { MAPS_ENABLED } from '@/lib/map-config';

export interface EntityMapViewProps<T> {
  items: T[];
  getCoordinates: (item: T) => { lat: number; lng: number } | null;
  getMarkerConfig: (item: T) => { id: number | string; title: string; pinColor?: string; pinGlyph?: string };
  renderInfoContent: (item: T) => ReactNode;
  height?: string;
  center?: { lat: number; lng: number };
  className?: string;
  isLoading?: boolean;
  emptyMessage?: string;
}

export function EntityMapView<T>({
  items,
  getCoordinates,
  getMarkerConfig,
  renderInfoContent,
  height = '600px',
  center,
  className = '',
  isLoading = false,
  emptyMessage = 'No items with location data',
}: EntityMapViewProps<T>) {
  const markers: MapMarker[] = useMemo(() => {
    const result: MapMarker[] = [];
    for (const item of items) {
      const coords = getCoordinates(item);
      if (!coords) continue;
      const config = getMarkerConfig(item);
      result.push({
        id: config.id,
        lat: coords.lat,
        lng: coords.lng,
        title: config.title,
        pinColor: config.pinColor,
        pinGlyph: config.pinGlyph,
        infoContent: renderInfoContent(item),
      });
    }
    return result;
  }, [items, getCoordinates, getMarkerConfig, renderInfoContent]);

  if (!MAPS_ENABLED) {
    return (
      <div className={`flex flex-col items-center justify-center gap-3 py-16 ${className}`}>
        <MapPinOff className="w-12 h-12 text-theme-subtle" />
        <p className="text-theme-muted text-sm">Map view is not available</p>
      </div>
    );
  }

  if (isLoading) {
    return (
      <div
        className={`rounded-xl bg-theme-surface/50 animate-pulse ${className}`}
        style={{ height }}
      />
    );
  }

  if (markers.length === 0) {
    return (
      <div className={`flex flex-col items-center justify-center gap-3 py-16 ${className}`}>
        <MapPin className="w-12 h-12 text-theme-subtle" />
        <p className="text-theme-muted text-sm">{emptyMessage}</p>
      </div>
    );
  }

  return (
    <LocationMap
      markers={markers}
      center={center}
      height={height}
      className={className}
      fitBounds
    />
  );
}
