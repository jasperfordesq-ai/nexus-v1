// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Stub LocationMap (heavy Leaflet/Google dependency) ──────────────────────
vi.mock('./LocationMap', () => ({
  LocationMap: ({ height }: { height?: string }) => (
    <div data-testid="location-map" style={{ height }}>map-stub</div>
  ),
}));

// ─── Stub map-config so we can control MAPS_ENABLED ──────────────────────────
const { mockMapsEnabled } = vi.hoisted(() => ({ mockMapsEnabled: { value: true } }));

vi.mock('@/lib/map-config', () => ({
  get MAPS_ENABLED() { return mockMapsEnabled.value; },
}));

// ─── Stub GlassCard (avoids HeroUI jsdom issues) ─────────────────────────────
vi.mock('@/components/ui/GlassCard', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

// ─── Mock @/contexts ──────────────────────────────────────────────────────────
const mockHasFeature = vi.fn(() => true);

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const singleMarker = [{ lat: 53.3498, lng: -6.2603, title: 'Dublin' }];
const center = { lat: 53.3498, lng: -6.2603 };

describe('LocationMapCard', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockMapsEnabled.value = true;
  });

  it('renders nothing visible when no locationText and no markers/center', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="Location" markers={[]} />
    );
    // Component returns null — no GlassCard, no title rendered
    expect(screen.queryByTestId('glass-card')).toBeNull();
    expect(screen.queryByText('Location')).toBeNull();
  });

  it('renders title when locationText is provided', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="Event Location" locationText="Dublin, Ireland" markers={[]} />
    );
    expect(screen.getByText('Event Location')).toBeInTheDocument();
  });

  it('renders locationText paragraph', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="Location" locationText="Cork, Ireland" markers={[]} />
    );
    expect(screen.getByText('Cork, Ireland')).toBeInTheDocument();
  });

  it('renders the map stub when MAPS_ENABLED, hasFeature(maps)=true, and markers present', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="Map" markers={singleMarker} />
    );
    expect(screen.getByTestId('location-map')).toBeInTheDocument();
  });

  it('does NOT render map when hasFeature("maps") returns false', async () => {
    mockHasFeature.mockReturnValue(false);
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="No Map" markers={singleMarker} locationText="Somewhere" />
    );
    expect(screen.queryByTestId('location-map')).toBeNull();
  });

  it('does NOT render map when MAPS_ENABLED is false', async () => {
    mockMapsEnabled.value = false;
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="No Map" markers={singleMarker} locationText="Somewhere" />
    );
    expect(screen.queryByTestId('location-map')).toBeNull();
  });

  it('renders map when center is provided (even without markers)', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="Center" markers={[]} center={center} />
    );
    expect(screen.getByTestId('location-map')).toBeInTheDocument();
  });

  it('renders GlassCard wrapper', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="Title" locationText="Place" markers={[]} />
    );
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });

  it('renders header with MapPin icon and title', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="My Location" locationText="Dublin" markers={[]} />
    );
    // h3 heading
    expect(screen.getByRole('heading', { level: 3, name: 'My Location' })).toBeInTheDocument();
  });

  it('passes mapHeight to the LocationMap stub', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard title="Map" markers={singleMarker} mapHeight="500px" />
    );
    const mapEl = screen.getByTestId('location-map');
    expect(mapEl).toHaveStyle({ height: '500px' });
  });

  it('renders both map and locationText together', async () => {
    const { LocationMapCard } = await import('./LocationMapCard');
    render(
      <LocationMapCard
        title="Full Card"
        locationText="Galway, Ireland"
        markers={singleMarker}
      />
    );
    expect(screen.getByTestId('location-map')).toBeInTheDocument();
    expect(screen.getByText('Galway, Ireland')).toBeInTheDocument();
  });
});
