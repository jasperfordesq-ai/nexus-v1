// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Verifies that OpenStreetMapView consumes the runtime tile URL from
 * /v2/config/google-maps. When a tenant has set a MapTiler API key, the
 * server returns a MapTiler URL with the key embedded; the component
 * renders those tiles instead of the free OSM tile service.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, waitFor } from '@testing-library/react';

vi.mock('react-leaflet', () => {
  const mockTileLayer = vi.fn(() => null);
  return {
    MapContainer: ({ children }: { children?: React.ReactNode }) => (
      <div data-testid="map-container">{children}</div>
    ),
    TileLayer: (props: { url: string; attribution: string }) => {
      mockTileLayer(props);
      return <div data-testid="tile-layer" data-url={props.url} data-attribution={props.attribution} />;
    },
    useMap: () => ({ setView: vi.fn(), fitBounds: vi.fn(), getZoom: () => 12 }),
    __mockTileLayer: mockTileLayer,
  };
});

vi.mock('leaflet', async () => ({
  default: {
    divIcon: vi.fn(() => ({})),
    latLngBounds: vi.fn(() => ({})),
    marker: vi.fn(() => ({
      bindPopup: vi.fn().mockReturnThis(),
      on: vi.fn().mockReturnThis(),
      addTo: vi.fn().mockReturnThis(),
    })),
    layerGroup: vi.fn(() => ({
      addTo: vi.fn().mockReturnThis(),
      removeFrom: vi.fn().mockReturnThis(),
    })),
    markerClusterGroup: vi.fn(() => ({
      addTo: vi.fn().mockReturnThis(),
      removeFrom: vi.fn().mockReturnThis(),
    })),
  },
}));

vi.mock('leaflet.markercluster', () => ({}));
vi.mock('leaflet/dist/leaflet.css', () => ({}));
vi.mock('leaflet.markercluster/dist/MarkerCluster.css', () => ({}));
vi.mock('leaflet.markercluster/dist/MarkerCluster.Default.css', () => ({}));

import { OpenStreetMapView } from '../OpenStreetMapView';
import { resetGoogleMapsConfigForTests } from '../GoogleMapsProvider';

describe('OpenStreetMapView — runtime tile URL', () => {
  beforeEach(() => {
    resetGoogleMapsConfigForTests();
  });

  it('uses MapTiler tiles when the runtime config returns a MapTiler URL (tenant key set)', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () =>
        new Response(
          JSON.stringify({
            data: {
              enabled: false,
              apiKey: '',
              mapId: null,
              osmTileUrl: 'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}@2x.png?key=secret123',
              osmTileAttribution: '&copy; MapTiler &copy; OSM',
              osmTileProvider: 'maptiler',
            },
          }),
          { status: 200 }
        )
      )
    );

    const { container } = render(
      <OpenStreetMapView markers={[{ id: 1, lat: 53.35, lng: -6.26, title: 'Dublin' }]} />
    );

    await waitFor(() => {
      const tile = container.querySelector('[data-testid="tile-layer"]');
      expect(tile?.getAttribute('data-url')).toContain('api.maptiler.com');
    });
  });

  it('falls back to free OSM tiles when no MapTiler key is configured', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () =>
        new Response(
          JSON.stringify({
            data: {
              enabled: false,
              apiKey: '',
              mapId: null,
              osmTileUrl: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              osmTileAttribution: '&copy; OSM contributors',
              osmTileProvider: 'osm',
            },
          }),
          { status: 200 }
        )
      )
    );

    const { container } = render(
      <OpenStreetMapView markers={[{ id: 1, lat: 53.35, lng: -6.26, title: 'Dublin' }]} />
    );

    await waitFor(() => {
      const tile = container.querySelector('[data-testid="tile-layer"]');
      expect(tile?.getAttribute('data-url')).toContain('tile.openstreetmap.org');
    });
  });

  it('uses fallback OSM URL when the config fetch fails entirely', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response('boom', { status: 500 })));

    const { container } = render(
      <OpenStreetMapView markers={[{ id: 1, lat: 53.35, lng: -6.26, title: 'Dublin' }]} />
    );

    // The initial state already uses the OSM fallback URL; even after the
    // failed fetch resolves, the URL must remain valid (not blank).
    await waitFor(() => {
      const tile = container.querySelector('[data-testid="tile-layer"]');
      expect(tile?.getAttribute('data-url')).toContain('tile.openstreetmap.org');
    });
  });
});
