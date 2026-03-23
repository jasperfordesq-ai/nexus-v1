// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LocationMapCard component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Set env before module imports ───────────────────────────────────────────
import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ user: { id: 1 }, isAuthenticated: true })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantSlug: 'test',
    branding: { name: 'Test' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
}));

let mapsEnabled = true;
vi.mock('@/lib/map-config', () => ({
  get MAPS_ENABLED() { return mapsEnabled; },
}));

vi.mock('@/lib/map-styles', () => ({
  DARK_MAP_STYLES: [],
}));

vi.mock('@vis.gl/react-google-maps', () => ({
  APIProvider: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  Map: ({ children }: { children: React.ReactNode }) => <div data-testid="google-map">{children}</div>,
  Marker: () => <div data-testid="marker" />,
  AdvancedMarker: ({ children, ...props }: { children?: React.ReactNode; [key: string]: unknown }) => <div data-testid="marker" {...props}>{children}</div>,
  InfoWindow: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  useMap: vi.fn(() => ({ setCenter: vi.fn(), setZoom: vi.fn(), fitBounds: vi.fn() })),
  useAdvancedMarkerRef: vi.fn(() => [vi.fn(), null]),
  useApiLoadingStatus: vi.fn(() => 'LOADED'),
  APILoadingStatus: { LOADED: 'LOADED', LOADING: 'LOADING', FAILED: 'FAILED', AUTH_FAILURE: 'AUTH_FAILURE' },
}));

vi.mock('@googlemaps/markerclusterer', () => ({
  MarkerClusterer: vi.fn(() => ({
    addMarker: vi.fn(),
    removeMarker: vi.fn(),
    clearMarkers: vi.fn(),
    setMap: vi.fn(),
  })),
}));

(global as Record<string, unknown>).google = {
  maps: {
    LatLngBounds: vi.fn(() => ({ extend: vi.fn() })),
    Marker: { MAX_ZINDEX: 1000000 },
    marker: {
      AdvancedMarkerElement: vi.fn(),
    },
  },
};

import { LocationMapCard } from '../LocationMapCard';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter>{children}</MemoryRouter>
    </HeroUIProvider>
  );
}

describe('LocationMapCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mapsEnabled = true;
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W>
        <LocationMapCard
          title="Test Location"
          locationText="Dublin, Ireland"
          markers={[]}
          center={{ lat: 53.35, lng: -6.26 }}
        />
      </W>,
    );
    expect(container).toBeTruthy();
  });

  it('displays the title', () => {
    render(
      <W>
        <LocationMapCard
          title="Event Location"
          locationText="123 Main St"
          markers={[]}
        />
      </W>,
    );
    expect(screen.getByText('Event Location')).toBeInTheDocument();
  });

  it('displays location text', () => {
    render(
      <W>
        <LocationMapCard
          title="Location"
          locationText="Central Park, NYC"
          markers={[]}
        />
      </W>,
    );
    expect(screen.getByText('Central Park, NYC')).toBeInTheDocument();
  });

  it('returns null when no location text and no coordinates', () => {
    const { container } = render(
      <W>
        <LocationMapCard title="Location" markers={[]} />
      </W>,
    );
    // The component should return null
    expect(container.querySelector('h3')).toBeNull();
  });

  it('shows map when maps are enabled and coordinates are provided', () => {
    const markers = [{ id: 1, lat: 53.35, lng: -6.26, title: 'Test' }];
    const { container } = render(
      <W>
        <LocationMapCard
          title="Location"
          markers={markers}
          locationText="Test"
        />
      </W>,
    );
    expect(container.querySelector('[data-testid="google-map"]')).toBeTruthy();
  });

  it('does not show map when MAPS_ENABLED is false', () => {
    mapsEnabled = false;
    const markers = [{ id: 1, lat: 53.35, lng: -6.26, title: 'Test' }];
    const { container } = render(
      <W>
        <LocationMapCard
          title="Location"
          markers={markers}
          locationText="Test"
        />
      </W>,
    );
    expect(container.querySelector('[data-testid="google-map"]')).toBeNull();
  });
});
