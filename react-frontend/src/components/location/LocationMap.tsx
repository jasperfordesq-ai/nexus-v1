// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LocationMap — renders an interactive Google Map with markers.
 *
 * Uses @vis.gl/react-google-maps components (Map, Marker, InfoWindow).
 * Returns null if no API key is configured or if the API fails to load
 * (e.g., billing not enabled) — graceful degradation.
 */

import { useState, useEffect, useCallback, type ReactNode } from 'react';
import { Map, Marker, InfoWindow, useMap, useApiLoadingStatus, APILoadingStatus } from '@vis.gl/react-google-maps';
import { useTheme } from '@/contexts/ThemeContext';
import { DARK_MAP_STYLES } from '@/lib/map-styles';

export interface MapMarker {
  id: number | string;
  lat: number;
  lng: number;
  title: string;
  infoContent?: ReactNode;
  pinColor?: string;
  pinGlyph?: string;
}

export interface LocationMapProps {
  markers: MapMarker[];
  center?: { lat: number; lng: number };
  zoom?: number;
  height?: string;
  className?: string;
  onMarkerClick?: (marker: MapMarker) => void;
  fitBounds?: boolean;
}

/** Default center: Ireland */
const DEFAULT_CENTER = { lat: 53.35, lng: -6.26 };
const DEFAULT_ZOOM = 12;

function LocationMapInner({
  markers,
  center,
  zoom = DEFAULT_ZOOM,
  height = '400px',
  className = '',
  onMarkerClick,
  fitBounds = true,
}: LocationMapProps) {
  const { resolvedTheme } = useTheme();
  const map = useMap();
  const status = useApiLoadingStatus();
  const [activeMarkerId, setActiveMarkerId] = useState<number | string | null>(null);

  // Auto-fit bounds when markers change
  useEffect(() => {
    if (!map || !fitBounds || markers.length === 0) return;

    if (markers.length === 1) {
      map.setCenter({ lat: markers[0].lat, lng: markers[0].lng });
      map.setZoom(zoom);
      return;
    }

    const bounds = new google.maps.LatLngBounds();
    markers.forEach((m) => bounds.extend({ lat: m.lat, lng: m.lng }));
    map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
  }, [map, markers, fitBounds, zoom]);

  // Gracefully degrade if API auth fails (billing not enabled, key restricted, etc.)
  if (status === APILoadingStatus.AUTH_FAILURE || status === APILoadingStatus.FAILED) {
    return null;
  }

  const mapCenter = center ?? (markers.length === 1
    ? { lat: markers[0].lat, lng: markers[0].lng }
    : DEFAULT_CENTER);

  const handleMarkerClick = useCallback(
    (marker: MapMarker) => {
      setActiveMarkerId((prev) => (prev === marker.id ? null : marker.id));
      onMarkerClick?.(marker);
    },
    [onMarkerClick]
  );

  const activeMarker = markers.find((m) => m.id === activeMarkerId);

  return (
    <div className={`rounded-xl overflow-hidden ${className}`} style={{ height }}>
      <Map
        defaultCenter={mapCenter}
        defaultZoom={zoom}
        gestureHandling="cooperative"
        disableDefaultUI={false}
        mapTypeControl={false}
        streetViewControl={false}
        fullscreenControl={true}
        zoomControl={true}
        styles={resolvedTheme === 'dark' ? DARK_MAP_STYLES : undefined}
        clickableIcons={false}
      >
        {markers.map((marker) => (
          <Marker
            key={marker.id}
            position={{ lat: marker.lat, lng: marker.lng }}
            title={marker.title}
            onClick={() => handleMarkerClick(marker)}
          />
        ))}

        {activeMarker?.infoContent && (
          <InfoWindow
            position={{ lat: activeMarker.lat, lng: activeMarker.lng }}
            onCloseClick={() => setActiveMarkerId(null)}
            pixelOffset={[0, -40]}
          >
            {activeMarker.infoContent}
          </InfoWindow>
        )}
      </Map>
    </div>
  );
}

export function LocationMap(props: LocationMapProps) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  if (!apiKey) return null;
  return <LocationMapInner {...props} />;
}
