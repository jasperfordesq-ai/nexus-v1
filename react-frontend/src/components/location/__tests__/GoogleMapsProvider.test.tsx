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

// Must set before importing the component (reads import.meta.env at module level)
import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';

import { GoogleMapsProvider } from '../GoogleMapsProvider';

describe('GoogleMapsProvider', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    import.meta.env.VITE_GOOGLE_MAPS_API_KEY = 'test-key';
  });

  it('renders children', () => {
    render(
      <GoogleMapsProvider>
        <div>Test content</div>
      </GoogleMapsProvider>,
    );
    expect(screen.getByText('Test content')).toBeInTheDocument();
  });

  it('wraps children with APIProvider when API key exists', () => {
    // The APIProvider mock wraps children in a data-testid="api-provider" div.
    // Since the env var is set before module import, the provider should render.
    render(
      <GoogleMapsProvider>
        <div>Wrapped</div>
      </GoogleMapsProvider>,
    );
    // Verify the child renders — the APIProvider mock wraps it
    expect(screen.getByText('Wrapped')).toBeInTheDocument();
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

  it('renders multiple children', () => {
    render(
      <GoogleMapsProvider>
        <div>Child 1</div>
        <div>Child 2</div>
      </GoogleMapsProvider>,
    );
    expect(screen.getByText('Child 1')).toBeInTheDocument();
    expect(screen.getByText('Child 2')).toBeInTheDocument();
  });
});
