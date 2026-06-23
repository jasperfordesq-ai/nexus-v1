// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import type { ListingAnalytics } from '@/types/api';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Owner' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub GlassCard to avoid special CSS ─────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>{children}</div>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeAnalytics = (overrides: Partial<ListingAnalytics> = {}): ListingAnalytics => ({
  listing_id: 42,
  title: 'Handmade Pottery',
  period_days: 30,
  summary: {
    total_views: 150,
    unique_viewers: 80,
    total_contacts: 12,
    total_saves: 25,
    contact_rate: 8.0,
    save_rate: 16.7,
    views_trend_percent: 15,
  },
  views_over_time: [
    { date: '2026-05-01', count: 5 },
    { date: '2026-05-02', count: 10 },
    { date: '2026-05-03', count: 8 },
  ],
  contacts_over_time: [
    { date: '2026-05-01', count: 1 },
  ],
  contact_types: [
    { contact_type: 'message', count: 12 },
  ],
  ...overrides,
});

const makeResponse = (data: ListingAnalytics | null = null) => ({
  success: data !== null,
  data,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('ListingAnalyticsPanel', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeResponse(makeAnalytics()));
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    const spinner = document.querySelector('[role="status"][aria-busy="true"]');
    expect(spinner).toBeTruthy();
  });

  it('fetches analytics for correct listing id', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        '/v2/listings/42/analytics?days=30',
      );
    });
  });

  it('renders total views stat after load', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      // 150 total views appears in the stat card value
      expect(screen.getByText('150')).toBeInTheDocument();
    });
  });

  it('renders total contacts stat', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      expect(screen.getByText('12')).toBeInTheDocument();
    });
  });

  it('renders total saves stat', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      expect(screen.getByText('25')).toBeInTheDocument();
    });
  });

  it('renders positive trend with + prefix', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      expect(screen.getByText('+15%')).toBeInTheDocument();
    });
  });

  it('renders negative trend without + prefix', async () => {
    mockApi.get.mockResolvedValue(
      makeResponse(makeAnalytics({ summary: { ...makeAnalytics().summary, views_trend_percent: -8 } })),
    );
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      expect(screen.getByText('-8%')).toBeInTheDocument();
    });
  });

  it('renders sparkline bar for each day in views_over_time', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      // Each day bar has role="img" and aria-label
      const bars = screen.getAllByRole('img');
      expect(bars.length).toBe(3); // 3 days in fixture
    });
  });

  it('renders no_data message when views_over_time is empty', async () => {
    mockApi.get.mockResolvedValue(makeResponse(makeAnalytics({ views_over_time: [] })));
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      // No bar charts shown, no_data text present
      const bars = screen.queryAllByRole('img');
      expect(bars.length).toBe(0);
    });
  });

  it('shows error state when API fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      // Retry button should appear in error state
      const retryBtn = screen.getByRole('button');
      expect(retryBtn).toBeInTheDocument();
    });
  });

  it('retry button calls API again', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('network'));
    mockApi.get.mockResolvedValueOnce(makeResponse(makeAnalytics()));

    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => screen.getByRole('button'));
    const retryBtn = screen.getByRole('button');
    fireEvent.click(retryBtn);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledTimes(2);
    });
  });

  it('renders the analytics title heading', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      const heading = screen.getByRole('heading', { level: 3 });
      expect(heading).toBeInTheDocument();
    });
  });

  it('renders date range labels below sparkline', async () => {
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      // First and last date of views_over_time appear
      expect(screen.getByText('2026-05-01')).toBeInTheDocument();
      expect(screen.getByText('2026-05-03')).toBeInTheDocument();
    });
  });

  it('returns null when response has no data', async () => {
    mockApi.get.mockResolvedValue({ success: false, data: null });
    const { ListingAnalyticsPanel } = await import('./ListingAnalyticsPanel');
    const { container } = render(<ListingAnalyticsPanel listingId={42} />);

    await waitFor(() => {
      // null render — glass card should not be present
      expect(container.querySelector('[data-testid="glass-card"]')).toBeNull();
    });
  });
});
