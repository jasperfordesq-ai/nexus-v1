// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ListingLocationMap component
 *
 * Actual map rendering (Google Maps / Leaflet) is SKIPPED — LocationMap is
 * mocked to a plain div so that tests focus on the wrapper UI (header, location
 * text, "Get Directions" link) without requiring a browser Maps API.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

// ─── Mock the heavy map internals ─────────────────────────────────────────────
// LocationMap dispatches on map provider and may load Google / Leaflet.
// We replace it with a lightweight stub that just renders a sentinel div.
vi.mock('@/components/location', async () => {
  const { forwardRef } = await import('react');
  return {
    LocationMap: forwardRef((_props: unknown, _ref: unknown) => (
      <div data-testid="location-map-stub" />
    )),
  };
});

// Also handle direct import path used by ListingLocationMap
vi.mock('@/components/location/LocationMap', async () => {
  const { forwardRef } = await import('react');
  return {
    LocationMap: forwardRef((_props: unknown, _ref: unknown) => (
      <div data-testid="location-map-stub" />
    )),
    MapMarker: {},
  };
});

// map-config: keep MAPS_ENABLED=true so the map branch renders (not the fallback)
vi.mock('@/lib/map-config', () => ({ MAPS_ENABLED: true }));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const mockHasFeature = vi.fn(() => true);

vi.mock('@/contexts', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (p: string) => '/test' + p,
    hasFeature: mockHasFeature,
    hasModule: vi.fn(() => true),
    mapProvider: 'google',
  }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { ListingLocationMap } from './ListingLocationMap';

const DEFAULT_PROPS = {
  latitude: 53.3498,
  longitude: -6.2603,
  location: 'Dublin 2, Ireland',
};

describe('ListingLocationMap — map enabled', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('renders the location section heading', () => {
    render(<ListingLocationMap {...DEFAULT_PROPS} />);
    // Translation key 'listing.location_title' — we assert an h3 is rendered
    const headings = screen.getAllByRole('heading', { level: 3 });
    expect(headings.length).toBeGreaterThan(0);
  });

  it('renders the location name text', () => {
    render(<ListingLocationMap {...DEFAULT_PROPS} />);
    expect(screen.getByText('Dublin 2, Ireland')).toBeInTheDocument();
  });

  it('renders a "Get Directions" link pointing to Google Maps', () => {
    render(<ListingLocationMap {...DEFAULT_PROPS} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', expect.stringContaining('google.com/maps'));
    expect(link).toHaveAttribute('href', expect.stringContaining('53.3498'));
    expect(link).toHaveAttribute('href', expect.stringContaining('-6.2603'));
  });

  it('opens the directions link in a new tab', () => {
    render(<ListingLocationMap {...DEFAULT_PROPS} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', expect.stringContaining('noopener'));
  });

  it('renders the map stub when maps feature is enabled', () => {
    render(<ListingLocationMap {...DEFAULT_PROPS} />);
    expect(screen.getByTestId('location-map-stub')).toBeInTheDocument();
  });

  // NOTE: Actual map rendering (marker placement, zoom, Google API calls)
  // is SKIPPED — LocationMap is fully mocked to a stub div.
});

describe('ListingLocationMap — map disabled (hasFeature returns false)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(false);
  });

  it('does not render the map stub when maps feature is off', () => {
    render(<ListingLocationMap {...DEFAULT_PROPS} />);
    expect(screen.queryByTestId('location-map-stub')).not.toBeInTheDocument();
  });

  it('renders the map-not-available fallback UI', () => {
    render(<ListingLocationMap {...DEFAULT_PROPS} />);
    // The fallback branch renders a MapPinOff icon + text from 'map.not_available_short'
    // We verify at least the location text and directions link still appear
    expect(screen.getByText('Dublin 2, Ireland')).toBeInTheDocument();
    expect(screen.getByRole('link')).toBeInTheDocument();
  });
});

describe('ListingLocationMap — custom className and mapHeight', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('accepts a custom className prop without crashing', () => {
    const { container } = render(
      <ListingLocationMap {...DEFAULT_PROPS} className="custom-class" />,
    );
    expect(container).toBeTruthy();
  });

  it('accepts a custom mapHeight prop without crashing', () => {
    const { container } = render(
      <ListingLocationMap {...DEFAULT_PROPS} mapHeight="300px" />,
    );
    expect(container).toBeTruthy();
  });
});
