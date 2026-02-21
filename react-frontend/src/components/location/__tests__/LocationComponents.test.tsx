// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Batch render tests for Location components:
 * - EntityMapView, GoogleMapsProvider, LocationMap, LocationMapCard, PlaceAutocompleteInput
 *
 * Smoke tests — verify each component renders without crashing.
 */

import { describe, it, expect, vi } from 'vitest';
import { render } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Set env before module imports ───────────────────────────────────────────
// GoogleMapsProvider reads VITE_GOOGLE_MAPS_API_KEY at module level,
// so it must be set before any component imports.
import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';

// ─── Common mocks ────────────────────────────────────────────────────────────

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', last_name: 'User', name: 'Test User', tenant_id: 2 },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() })),
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
}));

vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
}));

vi.mock('@/lib/map-styles', () => ({
  DARK_MAP_STYLES: [],
}));

vi.mock('@/lib/map-config', () => ({
  MAPS_ENABLED: true,
}));

// Mock @vis.gl/react-google-maps
vi.mock('@vis.gl/react-google-maps', () => ({
  APIProvider: ({ children }: { children: React.ReactNode }) => <div data-testid="api-provider">{children}</div>,
  Map: ({ children }: { children: React.ReactNode }) => <div data-testid="google-map">{children}</div>,
  Marker: () => <div data-testid="marker" />,
  InfoWindow: ({ children }: { children: React.ReactNode }) => <div data-testid="info-window">{children}</div>,
  useMap: vi.fn(() => ({
    setCenter: vi.fn(),
    setZoom: vi.fn(),
    fitBounds: vi.fn(),
  })),
  useApiLoadingStatus: vi.fn(() => 'LOADED'),
  APILoadingStatus: {
    LOADED: 'LOADED',
    LOADING: 'LOADING',
    FAILED: 'FAILED',
    AUTH_FAILURE: 'AUTH_FAILURE',
  },
  useMapsLibrary: vi.fn(() => ({
    PlacesService: vi.fn(),
    AutocompleteService: vi.fn(),
    AutocompleteSessionToken: vi.fn(() => ({})),
  })),
}));

// Mock Google Maps globals
(global as any).google = {
  maps: {
    LatLngBounds: vi.fn(() => ({
      extend: vi.fn(),
    })),
    places: {
      AutocompleteService: vi.fn(),
      PlacesService: vi.fn(),
    },
  },
};

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── EntityMapView ───────────────────────────────────────────────────────────

import { EntityMapView } from '../EntityMapView';

describe('EntityMapView', () => {
  it('renders without crashing', () => {
    const { container } = render(
      <W>
        <EntityMapView
          items={[]}
          getCoordinates={() => ({ lat: 53.35, lng: -6.26 })}
          getMarkerConfig={(item: any) => ({ id: item.id, title: item.title })}
          renderInfoContent={(item: any) => <div>{item.title}</div>}
          center={{ lat: 53.35, lng: -6.26 }}
        />
      </W>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });

  it('renders with entities', () => {
    const entities = [
      { id: 1, title: 'Test Listing', latitude: 53.35, longitude: -6.26 },
    ];
    const { container } = render(
      <W>
        <EntityMapView
          items={entities}
          getCoordinates={(item: any) => ({ lat: item.latitude, lng: item.longitude })}
          getMarkerConfig={(item: any) => ({ id: item.id, title: item.title })}
          renderInfoContent={(item: any) => <div>{item.title}</div>}
          center={{ lat: 53.35, lng: -6.26 }}
        />
      </W>
    );
    expect(container.querySelector('[data-testid="google-map"]')).toBeTruthy();
  });
});

// ─── GoogleMapsProvider ──────────────────────────────────────────────────────

import { GoogleMapsProvider } from '../GoogleMapsProvider';

describe('GoogleMapsProvider', () => {
  it('renders without crashing', () => {
    const { container } = render(
      <W>
        <GoogleMapsProvider>
          <div>Test content</div>
        </GoogleMapsProvider>
      </W>
    );
    expect(container.querySelector('[data-testid="api-provider"]')).toBeTruthy();
  });

  it('renders children', () => {
    const { container } = render(
      <W>
        <GoogleMapsProvider>
          <div data-testid="child-content">Test content</div>
        </GoogleMapsProvider>
      </W>
    );
    expect(container.querySelector('[data-testid="child-content"]')).toBeTruthy();
  });
});

// ─── LocationMap ─────────────────────────────────────────────────────────────

import { LocationMap } from '../LocationMap';

describe('LocationMap', () => {
  beforeEach(() => {
    // Set API key for tests
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';
  });

  it('renders without crashing', () => {
    const markers = [
      { id: 1, lat: 53.35, lng: -6.26, title: 'Test Marker' },
    ];
    const { container } = render(
      <W>
        <LocationMap markers={markers} />
      </W>
    );
    expect(container.querySelector('[data-testid="google-map"]')).toBeTruthy();
  });

  it('renders with multiple markers', () => {
    const markers = [
      { id: 1, lat: 53.35, lng: -6.26, title: 'Marker 1' },
      { id: 2, lat: 53.40, lng: -6.30, title: 'Marker 2' },
    ];
    const { container } = render(
      <W>
        <LocationMap markers={markers} />
      </W>
    );
    const markerElements = container.querySelectorAll('[data-testid="marker"]');
    expect(markerElements.length).toBe(2);
  });

  it('returns null when no API key', () => {
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY = '';
    const markers = [{ id: 1, lat: 53.35, lng: -6.26, title: 'Test' }];
    const { container } = render(
      <W>
        <LocationMap markers={markers} />
      </W>
    );
    expect(container.querySelector('[data-testid="google-map"]')).toBeNull();
  });
});

// ─── LocationMapCard ─────────────────────────────────────────────────────────

import { LocationMapCard } from '../LocationMapCard';

describe('LocationMapCard', () => {
  beforeEach(() => {
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W>
        <LocationMapCard
          title="Test Location"
          center={{ lat: 53.35, lng: -6.26 }}
          markers={[]}
        />
      </W>
    );
    expect(container.querySelector('div')).toBeTruthy();
  });

  it('displays title', () => {
    const { container } = render(
      <W>
        <LocationMapCard
          title="Test Location"
          center={{ lat: 53.35, lng: -6.26 }}
          markers={[]}
        />
      </W>
    );
    expect(container.textContent).toContain('Test Location');
  });
});

// ─── PlaceAutocompleteInput ──────────────────────────────────────────────────

import { PlaceAutocompleteInput } from '../PlaceAutocompleteInput';

describe('PlaceAutocompleteInput', () => {
  it('renders without crashing', () => {
    const { container } = render(
      <W>
        <PlaceAutocompleteInput
          value=""
          onChange={vi.fn()}
          onPlaceSelect={vi.fn()}
        />
      </W>
    );
    expect(container.querySelector('input')).toBeTruthy();
  });

  it('displays label', () => {
    const { container } = render(
      <W>
        <PlaceAutocompleteInput
          value=""
          onChange={vi.fn()}
          onPlaceSelect={vi.fn()}
          label="Location"
        />
      </W>
    );
    expect(container.textContent).toContain('Location');
  });
});
