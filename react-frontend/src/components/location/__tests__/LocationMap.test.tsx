// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LocationMap component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Set env before module imports ───────────────────────────────────────────

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('@/contexts/ThemeContext', () => ({
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
}));

vi.mock('@/lib/map-styles', () => ({
  DARK_MAP_STYLES: [],
}));

const mockUseApiLoadingStatus = vi.fn(() => 'LOADED');

vi.mock('@vis.gl/react-google-maps', () => ({
  APIProvider: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  Map: ({ children }: { children: React.ReactNode }) => <div data-testid="google-map">{children}</div>,
  Marker: ({ title }: { title?: string }) => <div data-testid="marker" data-title={title} />,
  AdvancedMarker: ({ title }: { title?: string }) => <div data-testid="marker" data-title={title} />,
  InfoWindow: ({ children }: { children: React.ReactNode }) => <div data-testid="info-window">{children}</div>,
  useMap: vi.fn(() => ({ setCenter: vi.fn(), setZoom: vi.fn(), fitBounds: vi.fn() })),
  useAdvancedMarkerRef: vi.fn(() => [vi.fn(), null]),
  useApiLoadingStatus: () => mockUseApiLoadingStatus(),
  APILoadingStatus: { LOADED: 'LOADED', LOADING: 'LOADING', FAILED: 'FAILED', AUTH_FAILURE: 'AUTH_FAILURE' },
}));

(global as Record<string, unknown>).google = {
  maps: {
    LatLngBounds: vi.fn(() => ({ extend: vi.fn() })),
  },
};

import { resetGoogleMapsConfigForTests } from '../GoogleMapsProvider';
import { LocationMap } from '../LocationMap';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter>{children}</MemoryRouter>
    </HeroUIProvider>
  );
}

describe('LocationMap', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    resetGoogleMapsConfigForTests();
    vi.stubGlobal('fetch', vi.fn(async () => ({
      ok: true,
      json: async () => ({
        data: {
          enabled: true,
          apiKey: 'test-key',
          mapId: null,
        },
      }),
    })));
    mockUseApiLoadingStatus.mockReturnValue('LOADED');
  });

  it('renders without crashing with one marker', async () => {
    const markers = [{ id: 1, lat: 53.35, lng: -6.26, title: 'Test Marker' }];
    const { container } = render(
      <W><LocationMap markers={markers} /></W>,
    );
    await waitFor(() => {
      expect(container.querySelector('[data-testid="google-map"]')).toBeTruthy();
    });
  });

  it('renders multiple markers', async () => {
    const markers = [
      { id: 1, lat: 53.35, lng: -6.26, title: 'Marker 1' },
      { id: 2, lat: 53.40, lng: -6.30, title: 'Marker 2' },
    ];
    const { container } = render(
      <W><LocationMap markers={markers} /></W>,
    );
    await waitFor(() => {
      const markerElements = container.querySelectorAll('[data-testid="marker"]');
      expect(markerElements.length).toBe(2);
    });
  });

  it('returns null when Maps config is disabled', async () => {
    resetGoogleMapsConfigForTests();
    vi.stubGlobal('fetch', vi.fn(async () => ({
      ok: true,
      json: async () => ({ data: { enabled: false, apiKey: '', mapId: null } }),
    })));
    const markers = [{ id: 1, lat: 53.35, lng: -6.26, title: 'Test' }];
    const { container } = render(
      <W><LocationMap markers={markers} /></W>,
    );
    await waitFor(() => {
      expect(container.querySelector('[data-testid="google-map"]')).toBeNull();
    });
  });

  it('returns null when API auth fails', async () => {
    mockUseApiLoadingStatus.mockReturnValue('AUTH_FAILURE');
    const markers = [{ id: 1, lat: 53.35, lng: -6.26, title: 'Test' }];
    const { container } = render(
      <W><LocationMap markers={markers} /></W>,
    );
    await waitFor(() => {
      expect(container.querySelector('[data-testid="google-map"]')).toBeNull();
    });
  });

  it('applies custom className and height', async () => {
    const markers = [{ id: 1, lat: 53.35, lng: -6.26, title: 'Test' }];
    const { container } = render(
      <W><LocationMap markers={markers} className="custom-map" height="500px" /></W>,
    );
    await waitFor(() => {
      const wrapper = container.querySelector('.custom-map');
      expect(wrapper).toBeTruthy();
      expect(wrapper?.getAttribute('style')).toContain('500px');
    });
  });
});
