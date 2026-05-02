// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LocationMap — renders an interactive Google Map with markers.
 *
 * Uses @vis.gl/react-google-maps components (Map, AdvancedMarker, InfoWindow).
 * Supports optional marker clustering via @googlemaps/markerclusterer for
 * dense datasets. Returns null if no API key is configured or if the API
 * fails to load (e.g., billing not enabled) — graceful degradation.
 */

import {
  useState,
  useEffect,
  useCallback,
  type ReactNode,
} from 'react';
import {
  Map,
  AdvancedMarker,
  InfoWindow,
  useMap,
  useApiLoadingStatus,
  useAdvancedMarkerRef,
  APILoadingStatus,
} from '@vis.gl/react-google-maps';
import {
  MarkerClusterer,
  type Renderer,
} from '@googlemaps/markerclusterer';
import { useTheme } from '@/contexts/ThemeContext';
import { DARK_MAP_STYLES } from '@/lib/map-styles';
import { GoogleMapsProvider } from './GoogleMapsProvider';

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
  /** Enable marker clustering. Defaults to true when markers.length > 10. */
  cluster?: boolean;
  /** Called once when the Maps API fails to load (billing/key issue). */
  onMapsFailed?: () => void;
}

/** Default center: neutral global fallback */
const DEFAULT_CENTER = { lat: 20, lng: 0 };
const DEFAULT_ZOOM = 12;
const CLUSTER_AUTO_THRESHOLD = 10;

// ---------------------------------------------------------------------------
// Custom cluster renderer — primary-colored circle with inverse text
// Leaflet/Google Maps inject cluster DOM outside React, so inline styles are
// required. We read theme tokens from :root once per render instead of
// hardcoding hex values, so clusters respect user accent color + theme.
// ---------------------------------------------------------------------------

function readToken(name: string, fallback: string): string {
  if (typeof window === 'undefined') return fallback;
  const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return v || fallback;
}

const clusterRenderer: Renderer = {
  render({ count, position }) {
    const size = count < 10 ? 32 : count < 100 ? 38 : 44;
    const bg = readToken('--color-primary', '#4f46e5');
    const fg = readToken('--text-inverse', '#ffffff');
    const shadow = readToken('--shadow-md', '0 2px 6px rgba(0,0,0,0.35)');
    const el = document.createElement('div');
    el.style.cssText = [
      `width:${size}px`,
      `height:${size}px`,
      'border-radius:50%',
      `background:${bg}`,
      `color:${fg}`,
      `font-size:12px`,
      'font-weight:700',
      'display:flex',
      'align-items:center',
      'justify-content:center',
      `box-shadow:${shadow}`,
      'cursor:pointer',
    ].join(';');
    el.textContent = String(count);

    return new google.maps.marker.AdvancedMarkerElement({
      position,
      content: el,
      // clusters sit above regular markers
      zIndex: Number(google.maps.Marker.MAX_ZINDEX) + count,
    });
  },
};

// ---------------------------------------------------------------------------
// Single AdvancedMarker that registers itself with a MarkerClusterer
// ---------------------------------------------------------------------------

interface ClusteredMarkerProps {
  marker: MapMarker;
  clusterer: MarkerClusterer | null;
  onClick: (marker: MapMarker) => void;
}

function ClusteredMarkerItem({ marker, clusterer, onClick }: ClusteredMarkerProps) {
  const [markerRef, advancedMarker] = useAdvancedMarkerRef();

  // Register / unregister with the clusterer whenever the marker element changes
  useEffect(() => {
    if (!clusterer || !advancedMarker) return;
    clusterer.addMarker(advancedMarker);
    return () => {
      clusterer.removeMarker(advancedMarker);
    };
  }, [clusterer, advancedMarker]);

  return (
    <AdvancedMarker
      ref={markerRef}
      position={{ lat: marker.lat, lng: marker.lng }}
      title={marker.title}
      onClick={() => onClick(marker)}
    />
  );
}

// ---------------------------------------------------------------------------
// Clustered markers sub-component — owns the MarkerClusterer lifecycle
// ---------------------------------------------------------------------------

interface ClusteredMarkersProps {
  markers: MapMarker[];
  onMarkerClick: (marker: MapMarker) => void;
}

function ClusteredMarkers({ markers, onMarkerClick }: ClusteredMarkersProps) {
  const map = useMap();
  const [clusterer, setClusterer] = useState<MarkerClusterer | null>(null);

  // Create/destroy the clusterer when the map instance changes
  useEffect(() => {
    if (!map) return;
    const instance = new MarkerClusterer({ map, renderer: clusterRenderer });
    setClusterer(instance);
    return () => {
      instance.clearMarkers();
      instance.setMap(null);
    };
  }, [map]);

  return (
    <>
      {markers.map((marker) => (
        <ClusteredMarkerItem
          key={marker.id}
          marker={marker}
          clusterer={clusterer}
          onClick={onMarkerClick}
        />
      ))}
    </>
  );
}

// ---------------------------------------------------------------------------
// Plain (non-clustered) markers sub-component
// ---------------------------------------------------------------------------

interface PlainMarkersProps {
  markers: MapMarker[];
  onMarkerClick: (marker: MapMarker) => void;
}

function PlainMarkers({ markers, onMarkerClick }: PlainMarkersProps) {
  return (
    <>
      {markers.map((marker) => (
        <AdvancedMarker
          key={marker.id}
          position={{ lat: marker.lat, lng: marker.lng }}
          title={marker.title}
          onClick={() => onMarkerClick(marker)}
        />
      ))}
    </>
  );
}

// ---------------------------------------------------------------------------
// LocationMapInner — the real map component (rendered inside APIProvider)
// ---------------------------------------------------------------------------

function LocationMapInner({
  markers,
  center,
  zoom = DEFAULT_ZOOM,
  height = '400px',
  className = '',
  onMarkerClick,
  fitBounds = true,
  cluster,
  onMapsFailed,
}: LocationMapProps) {
  const { resolvedTheme } = useTheme();
  const map = useMap();
  const status = useApiLoadingStatus();
  const [activeMarkerId, setActiveMarkerId] = useState<number | string | null>(null);

  // Resolve whether clustering should be active
  const clusteringEnabled =
    cluster !== undefined ? cluster : markers.length > CLUSTER_AUTO_THRESHOLD;

  // Notify parent when Maps fails to load (e.g. billing disabled, key restricted,
  // or double-load from prerendered HTML). Parent can switch to a fallback view.
  useEffect(() => {
    if (status === APILoadingStatus.AUTH_FAILURE || status === APILoadingStatus.FAILED) {
      onMapsFailed?.();
    }
  }, [status, onMapsFailed]);

  // Auto-fit bounds when markers change
  useEffect(() => {
    if (!map || !fitBounds || markers.length === 0) return;

    if (markers.length === 1) {
      const first = markers[0];
      if (!first) return;
      map.setCenter({ lat: first.lat, lng: first.lng });
      map.setZoom(zoom);
      return;
    }

    const bounds = new google.maps.LatLngBounds();
    markers.forEach((m) => bounds.extend({ lat: m.lat, lng: m.lng }));
    map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
  }, [map, markers, fitBounds, zoom]);

  const handleMarkerClick = useCallback(
    (marker: MapMarker) => {
      setActiveMarkerId((prev) => (prev === marker.id ? null : marker.id));
      onMarkerClick?.(marker);
    },
    [onMarkerClick]
  );

  // Gracefully degrade if API auth fails (billing not enabled, key restricted, etc.)
  if (status === APILoadingStatus.AUTH_FAILURE || status === APILoadingStatus.FAILED) {
    return null;
  }

  const mapCenter =
    center ??
    (markers.length === 1 && markers[0]
      ? { lat: markers[0].lat, lng: markers[0].lng }
      : DEFAULT_CENTER);

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
        styles={import.meta.env.VITE_GOOGLE_MAPS_MAP_ID ? undefined : (resolvedTheme === 'dark' ? DARK_MAP_STYLES : undefined)}
        clickableIcons={false}
        mapId={import.meta.env.VITE_GOOGLE_MAPS_MAP_ID || undefined}
      >
        {clusteringEnabled ? (
          <ClusteredMarkers
            markers={markers}
            onMarkerClick={handleMarkerClick}
          />
        ) : (
          <PlainMarkers
            markers={markers}
            onMarkerClick={handleMarkerClick}
          />
        )}

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

// ---------------------------------------------------------------------------
// Public export — guards against missing API key
// ---------------------------------------------------------------------------

export function LocationMap(props: LocationMapProps) {
  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  if (!apiKey) return null;
  return (
    <GoogleMapsProvider>
      <LocationMapInner {...props} />
    </GoogleMapsProvider>
  );
}
