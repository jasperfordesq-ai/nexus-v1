// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OpenStreetMapView — react-leaflet implementation matching LocationMapProps.
 *
 * Renders an interactive Leaflet map with OSM tiles, custom SVG pins, popups,
 * optional fitBounds, and marker clustering. Used as the OSM branch of
 * LocationMap when the tenant has selected `map_provider = openstreetmap`.
 *
 * Tile provider: standard OSM tiles. Subject to the OSM Foundation tile
 * usage policy (https://operations.osmfoundation.org/policies/tiles/).
 * For high-traffic deployments, switch the TileLayer URL to a paid host
 * (MapTiler, Stadia Maps) in this file.
 *
 * Dark mode: not supported with free OSM raster tiles. Tenants requiring
 * dark map tiles should stay on Google or upgrade to a paid tile host
 * with a dark style.
 */

import { useEffect, useMemo, useRef } from 'react';
import { MapContainer, TileLayer, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import { renderToStaticMarkup } from 'react-dom/server';
import type { LocationMapProps, MapMarker } from './LocationMap';

const DEFAULT_CENTER: [number, number] = [20, 0];
const DEFAULT_ZOOM = 12;
const CLUSTER_AUTO_THRESHOLD = 10;

const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const OSM_TILE_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';

/**
 * Build a DivIcon containing an inline SVG pin. SVG path data renders
 * identically across Chrome / Firefox / Safari, unlike CSS clip-path which
 * has subpixel rounding differences. Using DivIcon also avoids the
 * well-known leaflet image-asset path problem in bundlers like Vite.
 */
function buildPinIcon(color: string, glyph?: string): L.DivIcon {
  const safeGlyph = (glyph ?? '').replace(/[<>&"]/g, '').slice(0, 2);
  const svg = renderToStaticMarkup(
    <svg
      width="28"
      height="36"
      viewBox="0 0 28 36"
      xmlns="http://www.w3.org/2000/svg"
      role="presentation"
    >
      <path
        d="M14 0C6.27 0 0 6.27 0 14c0 9.5 14 22 14 22s14-12.5 14-22C28 6.27 21.73 0 14 0z"
        fill={color}
        stroke="rgba(0,0,0,0.15)"
        strokeWidth="1"
      />
      <circle cx="14" cy="14" r="5" fill="white" />
      {safeGlyph ? (
        <text
          x="14"
          y="18"
          textAnchor="middle"
          fontSize="10"
          fontFamily="system-ui, sans-serif"
          fontWeight="700"
          fill={color}
        >
          {safeGlyph}
        </text>
      ) : null}
    </svg>
  );
  return L.divIcon({
    className: 'nexus-osm-pin-wrapper',
    html: svg,
    iconSize: [28, 36],
    iconAnchor: [14, 36],
    popupAnchor: [0, -32],
  });
}

/**
 * Inner component that has access to the Leaflet map instance.
 * Uses leaflet.markercluster for marker grouping at low zoom levels and
 * handles fitBounds when the marker set changes.
 */
function MarkersAndBounds({
  markers,
  fitBounds,
  cluster,
  onMarkerClick,
}: Pick<LocationMapProps, 'markers' | 'fitBounds' | 'cluster' | 'onMarkerClick'>) {
  const map = useMap();
  const clusterGroupRef = useRef<L.MarkerClusterGroup | L.LayerGroup | null>(null);
  const lastSignatureRef = useRef<string>('');

  const useCluster = cluster ?? markers.length > CLUSTER_AUTO_THRESHOLD;

  useEffect(() => {
    // Build (or rebuild) the layer that holds all markers
    const layer = useCluster
      ? L.markerClusterGroup({ chunkedLoading: true, showCoverageOnHover: false })
      : L.layerGroup();

    for (const m of markers) {
      const leafletMarker = L.marker([m.lat, m.lng], {
        icon: buildPinIcon(m.pinColor ?? '#1976D2', m.pinGlyph),
        title: m.title,
      });
      const popupHtml = m.title.replace(/[<>&"]/g, (c) =>
        ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' })[c] ?? c
      );
      leafletMarker.bindPopup(`<div style="font-weight:600">${popupHtml}</div>`);
      if (onMarkerClick) {
        leafletMarker.on('click', () => onMarkerClick(m));
      }
      leafletMarker.addTo(layer);
    }

    layer.addTo(map);
    clusterGroupRef.current = layer;

    return () => {
      layer.removeFrom(map);
      clusterGroupRef.current = null;
    };
  }, [markers, useCluster, onMarkerClick, map]);

  useEffect(() => {
    if (!fitBounds || markers.length === 0) return;

    const signature = markers.map((m) => `${m.id}:${m.lat},${m.lng}`).join('|');
    if (signature === lastSignatureRef.current) return;
    lastSignatureRef.current = signature;

    if (markers.length === 1) {
      const only = markers[0]!;
      map.setView([only.lat, only.lng], Math.max(map.getZoom(), 14), { animate: true });
      return;
    }

    const bounds = L.latLngBounds(markers.map((m) => [m.lat, m.lng] as [number, number]));
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16, animate: true });
  }, [markers, fitBounds, map]);

  return null;
}

export function OpenStreetMapView({
  markers,
  center,
  zoom = DEFAULT_ZOOM,
  height = '400px',
  className = '',
  onMarkerClick,
  fitBounds = false,
  cluster,
}: LocationMapProps) {
  const initialCenter = useMemo<[number, number]>(() => {
    if (center) return [center.lat, center.lng];
    if (markers.length > 0 && markers[0]) return [markers[0].lat, markers[0].lng];
    return DEFAULT_CENTER;
  }, [center, markers]);

  return (
    <div
      className={`nexus-osm-map-wrapper rounded-xl overflow-hidden ${className}`}
      style={{ height }}
    >
      <style>{`
        .nexus-osm-pin-wrapper { background: transparent !important; border: 0 !important; }
        .nexus-osm-map-wrapper .leaflet-container {
          width: 100%; height: 100%;
          background: var(--color-surface, #f5f5f5);
        }
        .nexus-osm-map-wrapper .leaflet-popup-content-wrapper { border-radius: 12px; }
      `}</style>
      <MapContainer center={initialCenter} zoom={zoom} scrollWheelZoom>
        <TileLayer attribution={OSM_TILE_ATTRIBUTION} url={OSM_TILE_URL} maxZoom={19} />
        <MarkersAndBounds
          markers={markers}
          fitBounds={fitBounds}
          cluster={cluster}
          onMarkerClick={onMarkerClick}
        />
      </MapContainer>
    </div>
  );
}
