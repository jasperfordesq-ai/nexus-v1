// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MapSearchView tests.
 *
 * Real map rendering (Google Maps / LocationMap) is SKIPPED — it would require
 * live API keys and heavy browser APIs not available in jsdom. The LocationMap
 * component is stubbed to a plain div with a data-testid. Tests cover:
 *   - maps-disabled fallback UI
 *   - loading skeleton
 *   - empty state (no markers / no coordinates on listings)
 *   - map renders when listings carry lat/lng
 *   - "Use my location" button in empty and map states
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Stable mock references ────────────────────────────────────────────────
const mockHasFeature = vi.fn((f: string) => f === 'maps');
const mockTenantPath = (p: string) => `/test${p}`;

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), upload: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: mockTenantPath,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ─── Stub LocationMap — real map requires Google Maps API key & DOM APIs ───
vi.mock('@/components/location', () => ({
  LocationMap: ({ markers }: { markers: Array<{ id: number }> }) => (
    <div data-testid="location-map" data-marker-count={markers.length} />
  ),
}));

// ─── Stub the local sub-components to keep tests lean ─────────────────────
vi.mock('./PriceBadge', () => ({
  PriceBadge: () => <span data-testid="price-badge" />,
}));
vi.mock('./ConditionBadge', () => ({
  ConditionBadge: () => <span data-testid="condition-badge" />,
}));

// ─── MAPS_ENABLED is a module-level const — mock the whole module ──────────
// Default: MAPS_ENABLED = true (VITE_GOOGLE_MAPS_ENABLED !== '0')
vi.mock('@/lib/map-config', () => ({ MAPS_ENABLED: true }));

import { MapSearchView } from './MapSearchView';
import type { MarketplaceListingItem } from '@/types/marketplace';

const BASE_LISTING: MarketplaceListingItem = {
  id: 1, title: 'Red Widget', price: 10, price_currency: 'EUR', price_type: 'fixed',
  condition: 'good', delivery_method: 'pickup', seller_type: 'private', status: 'active',
  image: null, image_count: 0, is_saved: false, is_own: false, is_promoted: false,
  views_count: 0, created_at: '2026-01-01',
};

// Listings that carry lat/lng (the enriched shape MapSearchView expects)
const LISTING_WITH_COORDS = { ...BASE_LISTING, latitude: 53.3498, longitude: -6.2603 } as MarketplaceListingItem;
const LISTING_NO_COORDS = { ...BASE_LISTING, id: 2, title: 'No-Coords Item' };

describe('MapSearchView — maps disabled (MAPS_ENABLED=true but feature=off)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Turn the feature off so mapDisplayEnabled = false
    mockHasFeature.mockImplementation(() => false);
  });

  it('shows the "not available" fallback', () => {
    render(<MapSearchView listings={[LISTING_WITH_COORDS]} />);
    // i18n key falls back to the key itself in test env
    // We look for the heading-level element or the fallback card
    const card = screen.getByRole('heading', { hidden: true });
    expect(card).toBeInTheDocument();
  });

  it('does NOT render LocationMap', () => {
    render(<MapSearchView listings={[LISTING_WITH_COORDS]} />);
    expect(screen.queryByTestId('location-map')).toBeNull();
  });
});

describe('MapSearchView — maps enabled', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockImplementation((f: string) => f === 'maps');
  });

  it('renders loading skeleton when isLoading=true', () => {
    render(<MapSearchView listings={[]} isLoading />);
    // Multiple role="status" elements exist (ToastProvider also renders one);
    // the component's skeleton is the one carrying aria-busy="true".
    const skeleton = screen.getAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true');
    expect(skeleton).toBeInTheDocument();
  });

  it('does not render LocationMap when loading', () => {
    render(<MapSearchView listings={[]} isLoading />);
    expect(screen.queryByTestId('location-map')).toBeNull();
  });

  it('shows empty state when no listings have coordinates', () => {
    render(<MapSearchView listings={[LISTING_NO_COORDS]} />);
    expect(screen.queryByTestId('location-map')).toBeNull();
  });

  it('renders "Use my location" button in empty state when callback provided', () => {
    const onRequestLocation = vi.fn();
    render(<MapSearchView listings={[LISTING_NO_COORDS]} onRequestLocation={onRequestLocation} />);
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
    fireEvent.click(btn);
    expect(onRequestLocation).toHaveBeenCalledTimes(1);
  });

  it('renders LocationMap stub when listings have coordinates', () => {
    render(<MapSearchView listings={[LISTING_WITH_COORDS]} />);
    expect(screen.getByTestId('location-map')).toBeInTheDocument();
    expect(screen.getByTestId('location-map')).toHaveAttribute('data-marker-count', '1');
  });

  it('passes only coordinate-bearing listings as markers', () => {
    render(<MapSearchView listings={[LISTING_WITH_COORDS, LISTING_NO_COORDS]} />);
    expect(screen.getByTestId('location-map')).toHaveAttribute('data-marker-count', '1');
  });

  it('renders "Use my location" overlay button on map when callback provided', () => {
    const onRequestLocation = vi.fn();
    render(
      <MapSearchView listings={[LISTING_WITH_COORDS]} onRequestLocation={onRequestLocation} />,
    );
    // Button should be present (overlaid on map)
    const btns = screen.getAllByRole('button');
    expect(btns.length).toBeGreaterThan(0);
    fireEvent.click(btns[0]);
    expect(onRequestLocation).toHaveBeenCalledTimes(1);
  });

  it('does NOT render location button when no callback provided', () => {
    render(<MapSearchView listings={[LISTING_WITH_COORDS]} />);
    expect(screen.queryByRole('button')).toBeNull();
  });
});
