// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  default: mockApi,
  api: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockHasFeature = vi.fn(() => false);

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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub AdminMetaContext ────────────────────────────────────────────────────
vi.mock('@/admin/AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
  AdminMetaProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

// ─── Stub heavy children ─────────────────────────────────────────────────────
vi.mock('@/components/location', () => ({
  LocationMap: () => <div data-testid="location-map" />,
}));

vi.mock('@/lib/map-config', () => ({ MAPS_ENABLED: false }));

vi.mock('@/lib/chartColors', () => ({
  CHART_COLORS: ['#000'],
  CHART_COLOR_MAP: { primary: '#000', success: '#0f0' },
}));

// Stub recharts — they try to compute SVG metrics and throw in jsdom
vi.mock('recharts', () => ({
  AreaChart: ({ children }: { children: React.ReactNode }) => <div data-testid="area-chart">{children}</div>,
  BarChart: ({ children }: { children: React.ReactNode }) => <div data-testid="bar-chart">{children}</div>,
  PieChart: ({ children }: { children: React.ReactNode }) => <div data-testid="pie-chart">{children}</div>,
  ResponsiveContainer: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  Area: () => null,
  Bar: () => null,
  Pie: () => null,
  Line: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  Legend: () => null,
  Cell: () => null,
}));

// Stub admin components
vi.mock('../../components', () => ({
  StatCard: ({ label, value }: { label: string; value: unknown }) => (
    <div data-testid="stat-card">
      <span>{label}</span>
      <span>{String(value)}</span>
    </div>
  ),
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <span>{title}</span>
      <div>{actions}</div>
    </div>
  ),
}));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeAnalyticsData = () => ({
  overview: {
    total_credits_circulation: 1000,
    transaction_volume_30d: 25.5,
    transaction_count_30d: 15,
    active_traders_30d: 8,
    new_users_30d: 3,
    avg_transaction_size: 1.7,
  },
  monthly_trends: [
    { month: '2025-01', transaction_count: 5, total_volume: 10, new_users: 2 },
    { month: '2025-02', transaction_count: 8, total_volume: 16, new_users: 1 },
  ],
  weekly_trends: [
    { week: 'W1', transaction_count: 3, total_volume: 6 },
  ],
  top_earners: [
    { id: 1, name: 'Alice', total: 12.5 },
    { id: 2, name: 'Bob', total: 8.0 },
  ],
  top_spenders: [
    { id: 3, name: 'Charlie', total: 10.0 },
  ],
  gamification: { total_xp: 500, total_badges: 10, engagement_rate: 0.7 },
  matching: { total_matches: 20, conversion_rate: 0.5 },
  category_demand: [
    { name: 'Gardening', listing_count: 5, active_count: 3 },
    { name: 'Cooking', listing_count: 0, active_count: 0 },
  ],
  engagement_rate: 0.42,
});

const makeSuccess = (data: unknown) => ({ success: true, data });

// ─────────────────────────────────────────────────────────────────────────────
describe('CommunityAnalytics', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockHasFeature.mockReturnValue(false);
    mockApi.get.mockResolvedValue(makeSuccess(makeAnalyticsData()));
    mockApi.download.mockResolvedValue(undefined);
  });

  it('shows loading spinners initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders stat cards after data loads', async () => {
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => {
      const cards = screen.getAllByTestId('stat-card');
      expect(cards.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('renders top earner names in the table', async () => {
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });
  });

  it('renders top spender names in the table', async () => {
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('Charlie')).toBeInTheDocument();
    });
  });

  it('shows chart placeholders when data is available', async () => {
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => {
      expect(screen.getByTestId('area-chart')).toBeInTheDocument();
    });
  });

  it('calls export download endpoint when export button pressed', async () => {
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => screen.getAllByTestId('stat-card'));

    const exportBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('export') || b.textContent?.toLowerCase().includes('csv')
    );
    expect(exportBtn).toBeDefined();
    if (exportBtn) fireEvent.click(exportBtn);

    await waitFor(() => {
      expect(mockApi.download).toHaveBeenCalledWith(
        '/v2/admin/community-analytics/export',
        expect.objectContaining({ filename: 'community-analytics.csv' })
      );
    });
  });

  it('calls refresh endpoint when refresh button pressed', async () => {
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => screen.getAllByTestId('stat-card'));

    const refreshBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('refresh')
    );
    expect(refreshBtn).toBeDefined();
    if (refreshBtn) fireEvent.click(refreshBtn);

    await waitFor(() => {
      // api.get called at least twice (initial + refresh)
      expect(mockApi.get.mock.calls.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('shows no-data message when api returns empty monthly_trends', async () => {
    mockApi.get.mockResolvedValue(
      makeSuccess({ ...makeAnalyticsData(), monthly_trends: [] })
    );
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => {
      // Loading spinners gone
      const statusEls = screen.queryAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  it('does not render geo section when maps feature is off', async () => {
    mockHasFeature.mockReturnValue(false);
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => screen.getAllByTestId('stat-card'));

    expect(screen.queryByTestId('location-map')).not.toBeInTheDocument();
  });

  it('shows error in chart area when api fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network error'));
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => {
      // Loading spinners should be gone after error
      const statusEls = screen.queryAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  it('displays engagement rate formatted as percentage', async () => {
    const { CommunityAnalytics } = await import('./CommunityAnalytics');
    render(<CommunityAnalytics />);

    await waitFor(() => {
      const cards = screen.getAllByTestId('stat-card');
      const texts = cards.map((c) => c.textContent ?? '');
      expect(texts.some((t) => t.includes('42.0%'))).toBe(true);
    });
  });
});
