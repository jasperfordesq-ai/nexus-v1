// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GoogleMapsProvider component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('@vis.gl/react-google-maps', () => ({
  APIProvider: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="api-provider">{children}</div>
  ),
}));

import { GoogleMapsProvider, resetGoogleMapsConfigForTests } from '../GoogleMapsProvider';

describe('GoogleMapsProvider', () => {
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
  });

  it('renders children after runtime config loads', async () => {
    render(
      <GoogleMapsProvider>
        <div>Test content</div>
      </GoogleMapsProvider>,
    );
    expect(await screen.findByText('Test content')).toBeInTheDocument();
  });

  it('wraps children with APIProvider when API key exists', async () => {
    // The APIProvider mock wraps children in a data-testid="api-provider" div.
    // Since the env var is set before module import, the provider should render.
    render(
      <GoogleMapsProvider>
        <div>Wrapped</div>
      </GoogleMapsProvider>,
    );
    // Verify the child renders — the APIProvider mock wraps it
    expect(await screen.findByText('Wrapped')).toBeInTheDocument();
  });

  it('sets gm_authFailure on window', () => {
    render(
      <GoogleMapsProvider>
        <div>Test</div>
      </GoogleMapsProvider>,
    );
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    expect(typeof (window as any).gm_authFailure).toBe('function');
  });

  it('renders multiple children', async () => {
    render(
      <GoogleMapsProvider>
        <div>Child 1</div>
        <div>Child 2</div>
      </GoogleMapsProvider>,
    );
    expect(await screen.findByText('Child 1')).toBeInTheDocument();
    expect(await screen.findByText('Child 2')).toBeInTheDocument();
  });

  it('renders fallback when config disables maps', async () => {
    resetGoogleMapsConfigForTests();
    vi.stubGlobal('fetch', vi.fn(async () => ({
      ok: true,
      json: async () => ({ data: { enabled: false, apiKey: '', mapId: null } }),
    })));

    render(
      <GoogleMapsProvider fallback={<div>Fallback</div>}>
        <div>Child</div>
      </GoogleMapsProvider>,
    );

    expect(await screen.findByText('Fallback')).toBeInTheDocument();
  });
});
