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
  useMemo,
  useRef,
  type ReactNode,
} from 'react';
import {
  Map as GoogleMap,
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
  type Cluster,
} from '@googlemaps/markerclusterer';
import { useTheme } from '@/contexts/ThemeContext';
import { DARK_MAP_STYLES } from '@/lib/map-styles';
import { GoogleMapsProvider, useGoogleMapsConfig } from './GoogleMapsProvider';
import { useTenant } from '@/contexts/TenantContext';
import { useTranslation } from 'react-i18next';

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
/** Stable pixel offset for InfoWindows above pin tip. */
const INFO_PIXEL_OFFSET: [number, number] = [0, -40];
/**
 * If a cluster's lat/lng span (in degrees) is below this threshold, treat the
 * markers as stacked and show a chooser InfoWindow instead of fitBounds — at
 * SuperCluster's maxZoom, fitBounds would just zoom to max without breaking
 * the cluster apart.
 */
const STACKED_CLUSTER_SPAN_DEGREES = 0.0005; // ~55m at the equator

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
      gmpClickable: true,
    });
  },
};

// ---------------------------------------------------------------------------
// Single AdvancedMarker that registers itself with a MarkerClusterer
// ---------------------------------------------------------------------------

interface ClusteredMarkerProps {
  marker: MapMarker;
  clusterer: MarkerClusterer | null;
  onClick: (marker: MapMarker, element: google.maps.marker.AdvancedMarkerElement | null) => void;
  registerElement: (id: MapMarker['id'], element: google.maps.marker.AdvancedMarkerElement | null) => void;
}

function ClusteredMarkerItem({ marker, clusterer, onClick, registerElement }: ClusteredMarkerProps) {
  const [markerRef, advancedMarker] = useAdvancedMarkerRef();

  // Register / unregister with the clusterer whenever the marker element changes
  useEffect(() => {
    if (!clusterer || !advancedMarker) return;
    clusterer.addMarker(advancedMarker);
    return () => {
      clusterer.removeMarker(advancedMarker);
    };
  }, [clusterer, advancedMarker]);

  // Track the AdvancedMarkerElement so the parent can use it as the
  // InfoWindow anchor — anchor-based positioning renders content reliably
  // (position-only InfoWindows can render blank on first open with mapId).
  useEffect(() => {
    registerElement(marker.id, advancedMarker);
    return () => registerElement(marker.id, null);
  }, [marker.id, advancedMarker, registerElement]);

  return (
    <AdvancedMarker
      ref={markerRef}
      position={{ lat: marker.lat, lng: marker.lng }}
      title={marker.title}
      onClick={() => onClick(marker, advancedMarker)}
    />
  );
}

// ---------------------------------------------------------------------------
// Clustered markers sub-component — owns the MarkerClusterer lifecycle
// ---------------------------------------------------------------------------

interface ClusteredMarkersProps {
  markers: MapMarker[];
  onMarkerClick: (marker: MapMarker, element: google.maps.marker.AdvancedMarkerElement | null) => void;
  onClusterClick: (cluster: Cluster, map: google.maps.Map) => void;
  registerElement: (id: MapMarker['id'], element: google.maps.marker.AdvancedMarkerElement | null) => void;
}

function ClusteredMarkers({ markers, onMarkerClick, onClusterClick, registerElement }: ClusteredMarkersProps) {
  const map = useMap();
  const [clusterer, setClusterer] = useState<MarkerClusterer | null>(null);

  // Keep a stable ref to the latest cluster click handler so we don't have to
  // tear down the MarkerClusterer when the handler identity changes.
  const onClusterClickRef = useRef(onClusterClick);
  onClusterClickRef.current = onClusterClick;

  // Create/destroy the clusterer when the map instance changes
  useEffect(() => {
    if (!map) return;
    const instance = new MarkerClusterer({
      map,
      renderer: clusterRenderer,
      onClusterClick: (_event, cluster, clickedMap) => {
        onClusterClickRef.current(cluster, clickedMap);
      },
    });
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
          registerElement={registerElement}
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
  onMarkerClick: (marker: MapMarker, element: google.maps.marker.AdvancedMarkerElement | null) => void;
  registerElement: (id: MapMarker['id'], element: google.maps.marker.AdvancedMarkerElement | null) => void;
}

function PlainMarkerItem({
  marker,
  onMarkerClick,
  registerElement,
}: {
  marker: MapMarker;
  onMarkerClick: (marker: MapMarker, element: google.maps.marker.AdvancedMarkerElement | null) => void;
  registerElement: (id: MapMarker['id'], element: google.maps.marker.AdvancedMarkerElement | null) => void;
}) {
  const [markerRef, advancedMarker] = useAdvancedMarkerRef();

  useEffect(() => {
    registerElement(marker.id, advancedMarker);
    return () => registerElement(marker.id, null);
  }, [marker.id, advancedMarker, registerElement]);

  return (
    <AdvancedMarker
      ref={markerRef}
      position={{ lat: marker.lat, lng: marker.lng }}
      title={marker.title}
      onClick={() => onMarkerClick(marker, advancedMarker)}
    />
  );
}

function PlainMarkers({ markers, onMarkerClick, registerElement }: PlainMarkersProps) {
  return (
    <>
      {markers.map((marker) => (
        <PlainMarkerItem
          key={marker.id}
          marker={marker}
          onMarkerClick={onMarkerClick}
          registerElement={registerElement}
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
  const mapsConfig = useGoogleMapsConfig();
  const map = useMap();
  const status = useApiLoadingStatus();
  const [activeMarkerId, setActiveMarkerId] = useState<number | string | null>(null);
  // When the user clicks a cluster of markers stacked at the same point, we
  // open a chooser InfoWindow listing them — fitBounds can't break the
  // cluster apart at SuperCluster's maxZoom.
  const [stackedCluster, setStackedCluster] = useState<{
    position: { lat: number; lng: number };
    markers: MapMarker[];
  } | null>(null);

  // Keep a Map of marker.id → AdvancedMarkerElement so InfoWindow can use
  // anchor-based positioning. Anchor mode is the reliable rendering path —
  // position-only InfoWindows + mapId can render blank on first open.
  const elementsRef = useRef(new Map<MapMarker['id'], google.maps.marker.AdvancedMarkerElement>());
  const registerElement = useCallback(
    (id: MapMarker['id'], element: google.maps.marker.AdvancedMarkerElement | null) => {
      if (element) {
        elementsRef.current.set(id, element);
      } else {
        elementsRef.current.delete(id);
      }
    },
    [],
  );

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
      setStackedCluster(null);
      setActiveMarkerId((prev) => (prev === marker.id ? null : marker.id));
      onMarkerClick?.(marker);
    },
    [onMarkerClick],
  );

  const handleClusterClick = useCallback(
    (cluster: Cluster, clickedMap: google.maps.Map) => {
      const bounds = cluster.bounds;
      if (!bounds) return;
      const ne = bounds.getNorthEast();
      const sw = bounds.getSouthWest();
      const latSpan = Math.abs(ne.lat() - sw.lat());
      const lngSpan = Math.abs(ne.lng() - sw.lng());

      // Find the underlying MapMarker entries that belong to this cluster.
      // markerclusterer holds google.maps.marker.AdvancedMarkerElement
      // references; we map each back to its MapMarker via lat/lng equality
      // (within a tiny epsilon to absorb FP rounding).
      const eps = 1e-9;
      const memberIds = new Set<MapMarker['id']>();
      cluster.markers?.forEach((m) => {
        const pos = (m as google.maps.marker.AdvancedMarkerElement).position;
        if (!pos) return;
        const lat = typeof pos.lat === 'function' ? pos.lat() : (pos as google.maps.LatLngLiteral).lat;
        const lng = typeof pos.lng === 'function' ? pos.lng() : (pos as google.maps.LatLngLiteral).lng;
        markers.forEach((mm) => {
          if (Math.abs(mm.lat - lat) < eps && Math.abs(mm.lng - lng) < eps) {
            memberIds.add(mm.id);
          }
        });
      });
      const memberMarkers = markers.filter((mm) => memberIds.has(mm.id));

      const isStacked =
        latSpan < STACKED_CLUSTER_SPAN_DEGREES &&
        lngSpan < STACKED_CLUSTER_SPAN_DEGREES &&
        memberMarkers.length > 1;

      if (isStacked) {
        const anchor = memberMarkers[0];
        if (!anchor) return;
        // Close any single-marker InfoWindow first
        setActiveMarkerId(null);
        setStackedCluster({
          position: { lat: anchor.lat, lng: anchor.lng },
          markers: memberMarkers,
        });
        return;
      }

      setStackedCluster(null);
      clickedMap.fitBounds(bounds, 60);
    },
    [markers],
  );

  const activeMarker = useMemo(
    () => markers.find((m) => m.id === activeMarkerId) ?? null,
    [markers, activeMarkerId],
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

  const activeAnchor = activeMarker ? elementsRef.current.get(activeMarker.id) ?? null : null;
  const stackedAnchor = stackedCluster && stackedCluster.markers[0]
    ? elementsRef.current.get(stackedCluster.markers[0].id) ?? null
    : null;

  return (
    <div className={`rounded-xl overflow-hidden ${className}`} style={{ height }}>
      <GoogleMap
        defaultCenter={mapCenter}
        defaultZoom={zoom}
        gestureHandling="cooperative"
        disableDefaultUI={false}
        mapTypeControl={false}
        streetViewControl={false}
        fullscreenControl={true}
        zoomControl={true}
        styles={mapsConfig?.mapId ? undefined : (resolvedTheme === 'dark' ? DARK_MAP_STYLES : undefined)}
        clickableIcons={false}
        mapId={mapsConfig?.mapId || undefined}
      >
        {clusteringEnabled ? (
          <ClusteredMarkers
            markers={markers}
            onMarkerClick={handleMarkerClick}
            onClusterClick={handleClusterClick}
            registerElement={registerElement}
          />
        ) : (
          <PlainMarkers
            markers={markers}
            onMarkerClick={handleMarkerClick}
            registerElement={registerElement}
          />
        )}

        {activeMarker?.infoContent && (
          <InfoWindow
            // Force remount per-marker so children portal in before open()
            key={`marker-${activeMarker.id}`}
            anchor={activeAnchor}
            position={activeAnchor ? undefined : { lat: activeMarker.lat, lng: activeMarker.lng }}
            onCloseClick={() => setActiveMarkerId(null)}
            pixelOffset={INFO_PIXEL_OFFSET}
          >
            {activeMarker.infoContent}
          </InfoWindow>
        )}

        {stackedCluster && (
          <InfoWindow
            key={`cluster-${stackedCluster.position.lat}-${stackedCluster.position.lng}-${stackedCluster.markers.length}`}
            anchor={stackedAnchor}
            position={stackedAnchor ? undefined : stackedCluster.position}
            onCloseClick={() => setStackedCluster(null)}
            pixelOffset={INFO_PIXEL_OFFSET}
          >
            <ClusterChooser
              markers={stackedCluster.markers}
              onPick={(m) => {
                setStackedCluster(null);
                setActiveMarkerId(m.id);
                onMarkerClick?.(m);
              }}
            />
          </InfoWindow>
        )}
      </GoogleMap>
    </div>
  );
}

// ---------------------------------------------------------------------------
// ClusterChooser — list of marker titles when many markers share a coord.
// Plain HTML (no HeroUI) so it renders cleanly inside Google's portal node.
// ---------------------------------------------------------------------------

function ClusterChooser({
  markers,
  onPick,
}: {
  markers: MapMarker[];
  onPick: (marker: MapMarker) => void;
}) {
  return (
    <div className="max-w-[260px] max-h-[280px] overflow-y-auto">
      <div className="font-semibold text-[13px] mb-1.5 text-gray-900">
        {markers.length} listings here
      </div>
      <ul className="list-none p-0 m-0">
        {markers.map((m) => (
          <li key={m.id} className="border-t border-gray-200 first:border-t-0">
            <button
              type="button"
              onClick={() => onPick(m)}
              className="block w-full text-left px-1 py-2 bg-transparent border-0 cursor-pointer text-[13px] text-gray-800 hover:bg-gray-50"
            >
              {m.title}
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}

// ---------------------------------------------------------------------------
// OpenStreetMap branch — placeholder.
//
// When the tenant selects `map_provider = openstreetmap`, an OSM-backed map
// renderer would go here (e.g. react-leaflet + OSM tiles). Building it
// requires adding `leaflet` + `react-leaflet` deps and porting marker logic.
// For now, we render an accessible placeholder so map cards degrade
// predictably; address autocomplete continues to work via Nominatim.
// ---------------------------------------------------------------------------

function OpenStreetMapPlaceholder({ className, height }: Pick<LocationMapProps, 'className' | 'height'>) {
  const { t } = useTranslation('common');
  return (
    <div
      role="img"
      aria-label={t('location.osm_placeholder_aria')}
      className={`flex items-center justify-center rounded-xl border border-glass-border bg-default-50 dark:bg-default-100/10 text-theme-muted text-sm ${className ?? ''}`}
      style={{ height: height ?? '400px' }}
    >
      <span className="px-4 text-center">{t('location.osm_placeholder')}</span>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Public export — dispatches on tenant's map provider
// ---------------------------------------------------------------------------

export function LocationMap(props: LocationMapProps) {
  const { mapProvider, hasFeature } = useTenant();

  // Maps kill switch: when off, render nothing (existing behavior).
  if (!hasFeature('maps')) {
    return null;
  }

  if (mapProvider === 'openstreetmap') {
    return <OpenStreetMapPlaceholder className={props.className} height={props.height} />;
  }

  return (
    <GoogleMapsProvider>
      <LocationMapInner {...props} />
    </GoogleMapsProvider>
  );
}
