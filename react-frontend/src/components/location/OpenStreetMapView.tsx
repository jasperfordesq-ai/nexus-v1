// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OpenStreetMapView — react-leaflet implementation matching LocationMapProps.
 *
 * Renders an interactive Leaflet map with OSM tiles, custom HTML pins, popups,
 * and optional fitBounds. Used as the OSM branch of LocationMap when the
 * tenant has selected `map_provider = openstreetmap`.
 *
 * Tile provider: standard OSM tiles. Subject to the OSM Foundation tile
 * usage policy (https://operations.osmfoundation.org/policies/tiles/).
 * For high-traffic deployments, switch the TileLayer URL to a paid host
 * (MapTiler, Stadia Maps) in this file.
 *
 * Marker clustering is intentionally not implemented in this first pass
 * to keep dependencies minimal. To add later: install leaflet.markercluster
 * and react-leaflet-markercluster, then plug them in around the Marker loop.
 */

import { useEffect, useMemo, useRef } from 'react';
import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useTheme } from '@/contexts/ThemeContext';
import type { LocationMapProps, MapMarker } from './LocationMap';

const DEFAULT_CENTER: [number, number] = [20, 0];
const DEFAULT_ZOOM = 12;

const OSM_TILE_URL = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const OSM_TILE_ATTRIBUTION =
  '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';

/**
 * Build a DivIcon styled to match the Google AdvancedMarker visual language
 * (pin shape, primary color fill, white inner glyph). Using DivIcon avoids
 * the well-known leaflet image-asset path problem in bundlers like Vite.
 */
function buildPinIcon(color: string, glyph?: string): L.DivIcon {
  const safeGlyph = (glyph ?? '').slice(0, 2);
  const html = `
    <span class="nexus-osm-pin" aria-hidden="true">
      <span class="nexus-osm-pin-body" style="background:${color}"></span>
      ${safeGlyph ? `<span class="nexus-osm-pin-glyph">${safeGlyph}</span>` : ''}
    </span>
  `;
  return L.divIcon({
    className: 'nexus-osm-pin-wrapper',
    html,
    iconSize: [28, 36],
    iconAnchor: [14, 36],
    popupAnchor: [0, -32],
  });
}

/**
 * Inner component that has access to the Leaflet map instance.
 * Handles fitBounds when markers change.
 */
function FitBoundsHandler({ markers, fitBounds }: { markers: MapMarker[]; fitBounds: boolean }) {
  const map = useMap();
  const lastSignatureRef = useRef<string>('');

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
}: LocationMapProps) {
  const { theme } = useTheme();

  const initialCenter = useMemo<[number, number]>(() => {
    if (center) return [center.lat, center.lng];
    if (markers.length > 0 && markers[0]) return [markers[0].lat, markers[0].lng];
    return DEFAULT_CENTER;
  }, [center, markers]);

  // Resolve effective theme so style swap follows system preference too.
  const effectiveTheme = useMemo(() => {
    if (theme === 'system') {
      return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
    }
    return theme;
  }, [theme]);

  return (
    <div className={`nexus-osm-map-wrapper rounded-xl overflow-hidden ${className}`} style={{ height }}>
      {/*
        Inline style block — Leaflet's DivIcon HTML lives outside React's
        component tree, so utility-class theming via Tailwind classes does
        not reliably reach the icons. A scoped <style> block keeps the pin
        styles co-located with the component without leaking globally.
      */}
      <style>{`
        .nexus-osm-pin-wrapper { background: transparent !important; border: 0 !important; }
        .nexus-osm-pin { position: relative; display: block; width: 28px; height: 36px; }
        .nexus-osm-pin-body {
          position: absolute; inset: 0;
          clip-path: path('M14 0C6.27 0 0 6.27 0 14c0 9.5 14 22 14 22s14-12.5 14-22C28 6.27 21.73 0 14 0z');
          box-shadow: 0 2px 6px rgba(0,0,0,0.25);
        }
        .nexus-osm-pin-glyph {
          position: absolute; top: 5px; left: 0; width: 28px; text-align: center;
          color: #fff; font-weight: 700; font-size: 12px; line-height: 18px;
          font-family: system-ui, -apple-system, sans-serif;
        }
        .nexus-osm-map-wrapper .leaflet-container { width: 100%; height: 100%; background: var(--color-surface, #f5f5f5); }
        .nexus-osm-map-wrapper.dark-tiles .leaflet-tile { filter: invert(1) hue-rotate(180deg) brightness(0.9) contrast(0.9); }
        .nexus-osm-map-wrapper .leaflet-popup-content-wrapper { border-radius: 12px; }
      `}</style>
      <MapContainer
        center={initialCenter}
        zoom={zoom}
        scrollWheelZoom
        className={effectiveTheme === 'dark' ? 'dark-tiles' : ''}
      >
        <TileLayer attribution={OSM_TILE_ATTRIBUTION} url={OSM_TILE_URL} maxZoom={19} />
        <FitBoundsHandler markers={markers} fitBounds={fitBounds} />
        {markers.map((marker) => {
          const icon = buildPinIcon(marker.pinColor ?? '#1976D2', marker.pinGlyph);
          return (
            <Marker
              key={marker.id}
              position={[marker.lat, marker.lng]}
              icon={icon}
              eventHandlers={onMarkerClick ? { click: () => onMarkerClick(marker) } : undefined}
              title={marker.title}
            >
              {marker.infoContent ? (
                <Popup>
                  <div className="text-sm">{marker.infoContent}</div>
                </Popup>
              ) : (
                <Popup>
                  <div className="text-sm font-medium">{marker.title}</div>
                </Popup>
              )}
            </Marker>
          );
        })}
      </MapContainer>
    </div>
  );
}
