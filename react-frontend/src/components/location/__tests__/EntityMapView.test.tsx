// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for EntityMapView component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Set env before module imports ───────────────────────────────────────────
import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Test User' },
    isAuthenticated: true,
  })),
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
  APIProvider: ({ children }: { children: React.ReactNode }) => <div data-testid="api-provider">{children}</div>,
  Map: ({ children }: { children: React.ReactNode }) => <div data-testid="google-map">{children}</div>,
  Marker: () => <div data-testid="marker" />,
  InfoWindow: ({ children }: { children: React.ReactNode }) => <div data-testid="info-window">{children}</div>,
  useMap: vi.fn(() => ({ setCenter: vi.fn(), setZoom: vi.fn(), fitBounds: vi.fn() })),
  useApiLoadingStatus: vi.fn(() => 'LOADED'),
  APILoadingStatus: { LOADED: 'LOADED', LOADING: 'LOADING', FAILED: 'FAILED', AUTH_FAILURE: 'AUTH_FAILURE' },
}));

(global as Record<string, unknown>).google = {
  maps: {
    LatLngBounds: vi.fn(() => ({ extend: vi.fn() })),
  },
};

import { EntityMapView } from '../EntityMapView';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

interface TestItem {
  id: number;
  title: string;
  lat: number;
  lng: number;
}

const getCoordinates = (item: TestItem) => ({ lat: item.lat, lng: item.lng });
const getMarkerConfig = (item: TestItem) => ({ id: item.id, title: item.title });
const renderInfoContent = (item: TestItem) => <div>{item.title}</div>;

describe('EntityMapView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mapsEnabled = true;
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';
  });

  it('renders without crashing with empty items', () => {
    const { container } = render(
      <W>
        <EntityMapView
          items={[]}
          getCoordinates={getCoordinates}
          getMarkerConfig={getMarkerConfig}
          renderInfoContent={renderInfoContent}
        />
      </W>,
    );
    expect(container).toBeTruthy();
  });

  it('shows empty message when no items have coordinates', () => {
    render(
      <W>
        <EntityMapView
          items={[]}
          getCoordinates={getCoordinates}
          getMarkerConfig={getMarkerConfig}
          renderInfoContent={renderInfoContent}
          emptyMessage="No items found"
        />
      </W>,
    );
    expect(screen.getByText('No items found')).toBeInTheDocument();
  });

  it('renders map when items have coordinates', () => {
    const items: TestItem[] = [{ id: 1, title: 'Test', lat: 53, lng: -6 }];
    const { container } = render(
      <W>
        <EntityMapView
          items={items}
          getCoordinates={getCoordinates}
          getMarkerConfig={getMarkerConfig}
          renderInfoContent={renderInfoContent}
        />
      </W>,
    );
    expect(container.querySelector('[data-testid="google-map"]')).toBeTruthy();
  });

  it('shows loading state', () => {
    const { container } = render(
      <W>
        <EntityMapView
          items={[]}
          getCoordinates={getCoordinates}
          getMarkerConfig={getMarkerConfig}
          renderInfoContent={renderInfoContent}
          isLoading
        />
      </W>,
    );
    expect(container.querySelector('.animate-pulse')).toBeTruthy();
  });

  it('shows maps-disabled message when MAPS_ENABLED is false', () => {
    mapsEnabled = false;
    render(
      <W>
        <EntityMapView
          items={[{ id: 1, title: 'X', lat: 0, lng: 0 }]}
          getCoordinates={getCoordinates}
          getMarkerConfig={getMarkerConfig}
          renderInfoContent={renderInfoContent}
        />
      </W>,
    );
    expect(screen.getByText('Map view is not available')).toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <W>
        <EntityMapView
          items={[]}
          getCoordinates={getCoordinates}
          getMarkerConfig={getMarkerConfig}
          renderInfoContent={renderInfoContent}
          className="custom-class"
        />
      </W>,
    );
    expect(container.querySelector('.custom-class')).toBeTruthy();
  });
});
