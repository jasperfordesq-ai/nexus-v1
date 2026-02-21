// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TenantContext
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, act } from '@testing-library/react';
import { TenantProvider, useTenant, useFeature, useModule } from './TenantContext';

// Mock dependencies
const mockApiGet = vi.fn();
const mockFetchCsrfToken = vi.fn().mockResolvedValue(undefined);

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    clearInflightRequests: vi.fn(),
  },
  tokenManager: {
    getTenantId: vi.fn().mockReturnValue(null),
    setTenantId: vi.fn(),
  },
  fetchCsrfToken: (...args: unknown[]) => mockFetchCsrfToken(...args),
}));

vi.mock('@/lib/tenant-routing', () => ({
  detectTenantFromUrl: vi.fn().mockReturnValue({ slug: null, source: null }),
  tenantPath: (path: string, slug: string | null) => {
    if (!slug) return path;
    const normalized = path.startsWith('/') ? path : '/' + path;
    return '/' + slug + normalized;
  },
}));

vi.mock('@/lib/api-validation', () => ({
  validateResponseIfPresent: vi.fn((_schema: unknown, data: unknown) => data),
}));

vi.mock('@/lib/api-schemas', () => ({
  tenantBootstrapSchema: {},
}));

const mockTenantConfig = {
  id: 2,
  name: 'hOUR Timebank',
  slug: 'hour-timebank',
  tagline: 'Time Exchange Community',
  features: {
    gamification: true,
    events: true,
    groups: true,
    exchange_workflow: true,
  },
  modules: {
    feed: true,
    listings: true,
    wallet: true,
    messages: false,
  },
  branding: {
    name: 'hOUR',
    primaryColor: '#6366f1',
  },
};

function TestConsumer() {
  const {
    tenant,
    isLoading,
    error,
    features,
    modules,
    branding,
    hasFeature,
    hasModule,
    tenantSlug,
    tenantPath,
  } = useTenant();

  return (
    <div>
      <div data-testid="loading">{String(isLoading)}</div>
      <div data-testid="error">{error || 'none'}</div>
      <div data-testid="tenant-name">{tenant?.name || 'none'}</div>
      <div data-testid="tenant-slug">{tenantSlug || 'none'}</div>
      <div data-testid="has-gamification">{String(hasFeature('gamification'))}</div>
      <div data-testid="has-events">{String(hasFeature('events'))}</div>
      <div data-testid="has-feed">{String(hasModule('feed'))}</div>
      <div data-testid="has-messages">{String(hasModule('messages'))}</div>
      <div data-testid="branding-name">{branding.name}</div>
      <div data-testid="branding-color">{branding.primaryColor}</div>
      <div data-testid="path-test">{tenantPath('/dashboard')}</div>
    </div>
  );
}

describe('TenantContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state initially', async () => {
    // Use a long-delayed promise instead of a never-resolving one
    // to prevent Vitest from hanging indefinitely
    let resolveBootstrap: (value: unknown) => void;
    mockApiGet.mockReturnValue(new Promise((resolve) => { resolveBootstrap = resolve; }));

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    expect(screen.getByTestId('loading')).toHaveTextContent('true');

    // Clean up: resolve the promise so Vitest can exit cleanly
    resolveBootstrap!({ success: true, data: {} });
  });

  it('loads tenant data from bootstrap API', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    expect(screen.getByTestId('tenant-name')).toHaveTextContent('hOUR Timebank');
    expect(screen.getByTestId('has-gamification')).toHaveTextContent('true');
    expect(screen.getByTestId('has-events')).toHaveTextContent('true');
    expect(screen.getByTestId('has-feed')).toHaveTextContent('true');
  });

  it('reports feature checks correctly', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    // gamification is enabled in mockTenantConfig
    expect(screen.getByTestId('has-gamification')).toHaveTextContent('true');
  });

  it('reports module checks correctly (disabled module)', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    // messages is false in mockTenantConfig
    expect(screen.getByTestId('has-messages')).toHaveTextContent('false');
  });

  it('uses branding from tenant config with fallbacks', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    // name comes from top-level tenant.name (not branding.name)
    expect(screen.getByTestId('branding-name')).toHaveTextContent('hOUR Timebank');
    expect(screen.getByTestId('branding-color')).toHaveTextContent('#6366f1');
  });

  it('handles API error', async () => {
    mockApiGet.mockResolvedValue({ success: false, error: 'Server error' });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    expect(screen.getByTestId('error')).toHaveTextContent('Server error');
    expect(screen.getByTestId('tenant-name')).toHaveTextContent('none');
  });

  it('handles network exception', async () => {
    mockApiGet.mockRejectedValue(new Error('Network failed'));

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    expect(screen.getByTestId('error')).toHaveTextContent('Network failed');
  });

  it('uses default features when tenant has none', async () => {
    mockApiGet.mockResolvedValue({
      success: true,
      data: { id: 1, name: 'Test', slug: 'test' },
    });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    // Default for gamification is false
    expect(screen.getByTestId('has-gamification')).toHaveTextContent('false');
    // Default for events is true
    expect(screen.getByTestId('has-events')).toHaveTextContent('true');
  });

  it('uses default branding when tenant has none', async () => {
    mockApiGet.mockResolvedValue({
      success: true,
      data: { id: 1, name: 'Test', slug: 'test' },
    });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    expect(screen.getByTestId('branding-name')).toHaveTextContent('Test');
    expect(screen.getByTestId('branding-color')).toHaveTextContent('#6366f1');
  });

  it('passes tenantSlug prop to bootstrap API', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    render(
      <TenantProvider tenantSlug="hour-timebank">
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    expect(mockApiGet).toHaveBeenCalledWith(
      expect.stringContaining('slug=hour-timebank'),
      expect.any(Object)
    );
  });

  it('builds tenant path with slug', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    render(
      <TenantProvider tenantSlug="hour-timebank">
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    expect(screen.getByTestId('path-test')).toHaveTextContent('/hour-timebank/dashboard');
  });

  it('throws error when useTenant is outside provider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      render(<TestConsumer />);
    }).toThrow('useTenant must be used within a TenantProvider');

    spy.mockRestore();
  });

  it('fetches CSRF token after bootstrap', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    render(
      <TenantProvider>
        <TestConsumer />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('loading')).toHaveTextContent('false');
    });

    expect(mockFetchCsrfToken).toHaveBeenCalled();
  });
});

describe('useFeature', () => {
  it('returns feature status', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    function FeatureTestComponent() {
      const hasGamification = useFeature('gamification');
      return <div data-testid="feature">{String(hasGamification)}</div>;
    }

    render(
      <TenantProvider>
        <FeatureTestComponent />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('feature')).toHaveTextContent('true');
    });
  });
});

describe('useModule', () => {
  it('returns module status', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: mockTenantConfig });

    function ModuleTestComponent() {
      const hasFeed = useModule('feed');
      return <div data-testid="module">{String(hasFeed)}</div>;
    }

    render(
      <TenantProvider>
        <ModuleTestComponent />
      </TenantProvider>
    );

    await waitFor(() => {
      expect(screen.getByTestId('module')).toHaveTextContent('true');
    });
  });
});
