// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OpenStreetMapView component.
 *
 * Real tile/canvas rendering is NOT tested here — Leaflet requires a browser
 * canvas/WebGL environment that jsdom does not provide. Instead:
 *  - 'react-leaflet' is mocked with simple div passthroughs (MapContainer,
 *    TileLayer, useMap) so the component tree renders without error.
 *  - 'leaflet' (and all its CSS imports) is fully mocked.
 *  - 'leaflet.markercluster' and its CSS imports are stubbed to empty modules.
 *  - 'react-dom/server' renderToStaticMarkup is stubbed (used by buildPinIcon).
 *  - GoogleMapsProvider.fetchMapsRuntimeConfig resolves immediately with no
 *    override so the component uses fallback OSM tile URL/attribution.
 *
 * We assert:
 *  - The map wrapper element mounts with nexus-osm-map-wrapper class.
 *  - The wrapper receives the correct height style.
 *  - The TileLayer receives OSM attribution and URL by default.
 *  - The MapContainer (mocked) appears in the DOM.
 *  - Various prop combinations mount without throwing.
 *
 * NOTE: vi.hoisted() is used to define mock factory values before vi.mock()
 * hoisting — without it, const references inside vi.mock factories would be
 * in the temporal dead zone (TDZ) and cause ReferenceError.
 *
 * SKIPPED (unreachable without real Leaflet DOM):
 *  - Real tile rendering, canvas painting, popup DOM insertion.
 *  - fitBounds zoom animation and markerClusterGroup visual output.
 *  - onMarkerClick fired from real marker DOM events.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

// ---------------------------------------------------------------------------
// vi.hoisted — define stable values that vi.mock factories can safely close
// over (avoids TDZ ReferenceError from hoisted mocks referencing later consts)
// ---------------------------------------------------------------------------

const { MapContainerMock, TileLayerMock, useMapMock, fakeMap } = vi.hoisted(() => {
  const fakeMap = {
    addLayer: vi.fn().mockReturnThis(),
    removeLayer: vi.fn().mockReturnThis(),
    setView: vi.fn().mockReturnThis(),
    getZoom: vi.fn().mockReturnValue(12),
    fitBounds: vi.fn().mockReturnThis(),
  };

  const MapContainerMock = vi.fn(
    ({ children }: { children?: import('react').ReactNode }) => (
      <div data-testid="map-container">{children}</div>
    )
  );

  const TileLayerMock = vi.fn(
    ({ attribution, url }: { attribution?: string; url?: string }) => (
      <div data-testid="tile-layer" data-attribution={attribution} data-url={url} />
    )
  );

  const useMapMock = vi.fn(() => fakeMap);

  return { MapContainerMock, TileLayerMock, useMapMock, fakeMap };
});

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock('react-leaflet', () => ({
  MapContainer: MapContainerMock,
  TileLayer: TileLayerMock,
  useMap: useMapMock,
}));

vi.mock('leaflet', () => {
  const layerGroupInstance = {
    addTo: vi.fn().mockReturnThis(),
    removeFrom: vi.fn().mockReturnThis(),
  };
  const markerInstance = {
    bindPopup: vi.fn().mockReturnThis(),
    on: vi.fn().mockReturnThis(),
    addTo: vi.fn().mockReturnThis(),
  };
  const markerClusterGroupInstance = {
    addTo: vi.fn().mockReturnThis(),
    removeFrom: vi.fn().mockReturnThis(),
  };
  const leafletMock = {
    divIcon: vi.fn(() => ({})),
    marker: vi.fn(() => markerInstance),
    layerGroup: vi.fn(() => layerGroupInstance),
    markerClusterGroup: vi.fn(() => markerClusterGroupInstance),
    latLngBounds: vi.fn(() => ({ extend: vi.fn(), isValid: vi.fn(() => true) })),
  };
  return { default: leafletMock, ...leafletMock };
});

vi.mock('leaflet/dist/leaflet.css', () => ({}));
vi.mock('leaflet.markercluster', () => ({}));
vi.mock('leaflet.markercluster/dist/MarkerCluster.css', () => ({}));
vi.mock('leaflet.markercluster/dist/MarkerCluster.Default.css', () => ({}));

// renderToStaticMarkup is used inside buildPinIcon
vi.mock('react-dom/server', () => ({
  renderToStaticMarkup: vi.fn(() => '<svg data-testid="pin-svg"></svg>'),
}));

// fetchMapsRuntimeConfig resolves immediately with empty config (uses fallback tile URL)
vi.mock('@/components/location/GoogleMapsProvider', () => ({
  fetchMapsRuntimeConfig: vi.fn().mockResolvedValue({}),
}));

// LocationMap provides type exports only — satisfy TypeScript's module graph
vi.mock('./LocationMap', () => ({}));

import { OpenStreetMapView } from './OpenStreetMapView';
import type { MapMarker } from './LocationMap';

// ---------------------------------------------------------------------------
// Test data
// ---------------------------------------------------------------------------

const MARKER_A: MapMarker = { id: 1, lat: 53.349, lng: -6.26, title: 'Dublin City' };
const MARKER_B: MapMarker = { id: 2, lat: 51.898, lng: -8.475, title: 'Cork City', pinColor: '#FF0000' };

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('OpenStreetMapView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    fakeMap.addLayer.mockReturnThis();
    fakeMap.removeLayer.mockReturnThis();
    fakeMap.getZoom.mockReturnValue(12);
  });

  it('renders the map wrapper div with nexus-osm-map-wrapper class', () => {
    render(<OpenStreetMapView markers={[MARKER_A]} />);
    const wrapper = document.querySelector('.nexus-osm-map-wrapper');
    expect(wrapper).toBeInTheDocument();
  });

  it('applies the height style to the wrapper div', () => {
    render(<OpenStreetMapView markers={[MARKER_A]} height="300px" />);
    const wrapper = document.querySelector('.nexus-osm-map-wrapper') as HTMLElement;
    expect(wrapper.style.height).toBe('300px');
  });

  it('defaults height to 400px when not specified', () => {
    render(<OpenStreetMapView markers={[MARKER_A]} />);
    const wrapper = document.querySelector('.nexus-osm-map-wrapper') as HTMLElement;
    expect(wrapper.style.height).toBe('400px');
  });

  it('appends extra className to the wrapper', () => {
    render(<OpenStreetMapView markers={[MARKER_A]} className="my-custom-class" />);
    const wrapper = document.querySelector('.nexus-osm-map-wrapper');
    expect(wrapper).toHaveClass('my-custom-class');
  });

  it('renders the MapContainer (react-leaflet mock)', () => {
    render(<OpenStreetMapView markers={[MARKER_A]} />);
    expect(screen.getByTestId('map-container')).toBeInTheDocument();
  });

  it('renders the TileLayer with OSM fallback attribution by default', () => {
    render(<OpenStreetMapView markers={[MARKER_A]} />);
    const tile = screen.getByTestId('tile-layer');
    expect(tile.dataset.attribution).toContain('OpenStreetMap');
  });

  it('passes the fallback OSM tile URL to TileLayer by default', () => {
    render(<OpenStreetMapView markers={[MARKER_A]} />);
    const tile = screen.getByTestId('tile-layer');
    expect(tile.dataset.url).toContain('tile.openstreetmap.org');
  });

  it('mounts without throwing when markers array is empty', () => {
    expect(() => {
      render(<OpenStreetMapView markers={[]} />);
    }).not.toThrow();
    expect(screen.getByTestId('map-container')).toBeInTheDocument();
  });

  it('mounts without throwing with multiple markers', () => {
    expect(() => {
      render(<OpenStreetMapView markers={[MARKER_A, MARKER_B]} />);
    }).not.toThrow();
    expect(screen.getByTestId('map-container')).toBeInTheDocument();
  });

  it('accepts a custom center prop without throwing', () => {
    expect(() => {
      render(
        <OpenStreetMapView
          markers={[]}
          center={{ lat: 48.8566, lng: 2.3522 }}
        />
      );
    }).not.toThrow();
    expect(screen.getByTestId('map-container')).toBeInTheDocument();
  });

  it('accepts a custom zoom prop without throwing', () => {
    expect(() => {
      render(<OpenStreetMapView markers={[MARKER_A]} zoom={14} />);
    }).not.toThrow();
  });

  it('accepts fitBounds=true without throwing', () => {
    expect(() => {
      render(<OpenStreetMapView markers={[MARKER_A, MARKER_B]} fitBounds />);
    }).not.toThrow();
  });

  it('accepts cluster=true without throwing', () => {
    expect(() => {
      render(<OpenStreetMapView markers={[MARKER_A, MARKER_B]} cluster />);
    }).not.toThrow();
  });

  // NOTE: real tile rendering, canvas painting, and popup DOM insertion are
  // skipped — jsdom has no canvas and Leaflet's DOM methods are all stubbed.
  // Similarly, fitBounds zoom animation and markerClusterGroup visual output
  // are unreachable without a real Leaflet map instance and layout engine.
  // onMarkerClick cannot be triggered because L.marker is stubbed (no real DOM).
});
