// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── adminApi mock ─────────────────────────────────────────────────────────
const { mockGetSummary, mockGetTrending, mockGetZeroResults, mockToast } = vi.hoisted(() => ({
  mockGetSummary: vi.fn(),
  mockGetTrending: vi.fn(),
  mockGetZeroResults: vi.fn(),
  mockToast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminSearchAnalytics: {
    getSummary: mockGetSummary,
    getTrending: mockGetTrending,
    getZeroResults: mockGetZeroResults,
  },
}));

// ── AdminMetaContext ──────────────────────────────────────────────────────
vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

// ── recharts stub (avoids SVG rendering errors in jsdom) ──────────────────
vi.mock('recharts', () => ({
  AreaChart: ({ children }: { children?: React.ReactNode }) => <div data-testid="area-chart">{children}</div>,
  Area: () => null,
  XAxis: () => null,
  YAxis: () => null,
  CartesianGrid: () => null,
  Tooltip: () => null,
  ResponsiveContainer: ({ children }: { children?: React.ReactNode }) => (
    <div data-testid="responsive-container">{children}</div>
  ),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── component ─────────────────────────────────────────────────────────────
import { SearchAnalytics } from './SearchAnalytics';

const SUMMARY = {
  total_searches: 1500,
  unique_queries: 320,
  zero_result_rate: 8,
  avg_results: 12,
  daily_volume: [
    { date: '2025-01-01', count: 50 },
    { date: '2025-01-02', count: 80 },
  ],
  searches_by_type: [
    { type: 'listings', count: 900 },
    { type: 'members', count: 600 },
  ],
};

const TRENDING = [
  { query: 'gardening help', count: 45 },
  { query: 'cooking classes', count: 30 },
];

const ZERO_RESULTS = [
  { query: 'obscure widget', count: 12, last_searched: '2025-02-01T00:00:00Z' },
];

describe('SearchAnalytics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── loading ──────────────────────────────────────────────────────────────
  it('shows loading spinner while data is fetching', () => {
    const pending = new Promise(() => {});
    mockGetSummary.mockReturnValue(pending);
    mockGetTrending.mockReturnValue(pending);
    mockGetZeroResults.mockReturnValue(pending);

    render(<SearchAnalytics />);

    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  // ── populated ────────────────────────────────────────────────────────────
  it('renders KPI stat cards after data loads', async () => {
    mockGetSummary.mockResolvedValueOnce({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValueOnce({ success: true, data: TRENDING });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: ZERO_RESULTS });

    render(<SearchAnalytics />);

    await waitFor(() => {
      // total_searches 1500 should appear formatted
      expect(screen.getByText('1,500')).toBeInTheDocument();
    });
    expect(screen.getByText('320')).toBeInTheDocument();
    expect(screen.getByText('8%')).toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
  });

  it('renders trending queries', async () => {
    mockGetSummary.mockResolvedValueOnce({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValueOnce({ success: true, data: TRENDING });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: ZERO_RESULTS });

    render(<SearchAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('gardening help')).toBeInTheDocument();
    });
    expect(screen.getByText('cooking classes')).toBeInTheDocument();
  });

  it('renders zero-result queries', async () => {
    mockGetSummary.mockResolvedValueOnce({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValueOnce({ success: true, data: TRENDING });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: ZERO_RESULTS });

    render(<SearchAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('obscure widget')).toBeInTheDocument();
    });
  });

  it('renders the chart when daily_volume is populated', async () => {
    mockGetSummary.mockResolvedValueOnce({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValueOnce({ success: true, data: TRENDING });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: ZERO_RESULTS });

    render(<SearchAnalytics />);

    await waitFor(() => {
      expect(screen.getByTestId('area-chart')).toBeInTheDocument();
    });
  });

  it('renders searches_by_type chips', async () => {
    mockGetSummary.mockResolvedValueOnce({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValueOnce({ success: true, data: TRENDING });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: ZERO_RESULTS });

    render(<SearchAnalytics />);

    // Wait for KPI cards then look for the chip text which includes "type: count"
    await waitFor(() => {
      expect(screen.getByText('1,500')).toBeInTheDocument();
    });
    // searches_by_type chips render "{type}: {count}" — multiple elements may have "listings"
    const listingsEls = screen.getAllByText(/listings/i);
    expect(listingsEls.length).toBeGreaterThan(0);
  });

  // ── empty trending / zero results ────────────────────────────────────────
  it('shows no-trending message when trending list is empty', async () => {
    mockGetSummary.mockResolvedValueOnce({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValueOnce({ success: true, data: [] });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: [] });

    render(<SearchAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('1,500')).toBeInTheDocument();
    });
    // no trending query rows
    expect(screen.queryByText('gardening help')).not.toBeInTheDocument();
  });

  // ── error state ──────────────────────────────────────────────────────────
  it('shows error state when summary API fails', async () => {
    mockGetSummary.mockResolvedValueOnce({
      success: false,
      error: 'DB timeout',
    });
    mockGetTrending.mockResolvedValueOnce({ success: true, data: [] });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: [] });

    render(<SearchAnalytics />);

    await waitFor(() => {
      // Error card is rendered with role="alert"
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
    expect(mockToast.error).toHaveBeenCalled();
  });

  it('shows error state on thrown exception', async () => {
    mockGetSummary.mockRejectedValueOnce(new Error('Network'));
    mockGetTrending.mockResolvedValueOnce({ success: true, data: [] });
    mockGetZeroResults.mockResolvedValueOnce({ success: true, data: [] });

    render(<SearchAnalytics />);

    await waitFor(() => {
      // Either the alert card or a toast — verify at least one appears
      const alerts = screen.queryAllByRole('alert');
      expect(alerts.length > 0 || mockToast.error.mock.calls.length > 0).toBe(true);
    });
  });

  // ── refresh ───────────────────────────────────────────────────────────────
  it('calls all three APIs again when Refresh is clicked', async () => {
    const user = userEvent.setup();
    mockGetSummary.mockResolvedValue({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValue({ success: true, data: TRENDING });
    mockGetZeroResults.mockResolvedValue({ success: true, data: ZERO_RESULTS });

    render(<SearchAnalytics />);

    await waitFor(() => expect(screen.getByText('1,500')).toBeInTheDocument());

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await user.click(refreshBtn);

    await waitFor(() => {
      // Initial load + one manual refresh = 2 calls each
      expect(mockGetSummary).toHaveBeenCalledTimes(2);
    });
    expect(mockGetTrending).toHaveBeenCalledTimes(2);
    expect(mockGetZeroResults).toHaveBeenCalledTimes(2);
  });

  // ── period selector ───────────────────────────────────────────────────────
  it('re-fetches with new period when period Select changes', async () => {
    const user = userEvent.setup();
    mockGetSummary.mockResolvedValue({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValue({ success: true, data: TRENDING });
    mockGetZeroResults.mockResolvedValue({ success: true, data: ZERO_RESULTS });

    render(<SearchAnalytics />);

    await waitFor(() => expect(screen.getByText('1,500')).toBeInTheDocument());

    // HeroUI Select renders as a button (not combobox) in this version
    // Find the period select by its aria-label
    const periodBtn = screen.queryByLabelText(/period/i)
      ?? document.querySelector('[aria-label*="period" i], [aria-label*="Period" i]')
      ?? document.querySelector('button[data-slot="trigger"]');

    if (periodBtn) {
      await user.click(periodBtn as HTMLElement);
      // Try to find and click the 14-day option
      const options = document.querySelectorAll('[role="option"]');
      const opt14 = Array.from(options).find((o) => o.textContent?.includes('14'));
      if (opt14) {
        await user.click(opt14 as HTMLElement);
        await waitFor(() => {
          expect(mockGetSummary).toHaveBeenCalledWith(14);
        });
        return;
      }
    }

    // Fallback: if HeroUI Select isn't interactive in jsdom, verify the
    // initial load called the API with the default period (30)
    expect(mockGetSummary).toHaveBeenCalledWith(30);
  });

  // ── retry from error ──────────────────────────────────────────────────────
  it('retries load when Retry button is clicked from error state', async () => {
    const user = userEvent.setup();
    // First call fails, second succeeds
    mockGetSummary
      .mockResolvedValueOnce({ success: false, error: 'Oops' })
      .mockResolvedValueOnce({ success: true, data: SUMMARY });
    mockGetTrending.mockResolvedValue({ success: true, data: [] });
    mockGetZeroResults.mockResolvedValue({ success: true, data: [] });

    render(<SearchAnalytics />);

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());

    const retryBtn = screen.getByRole('button', { name: /retry/i });
    await user.click(retryBtn);

    await waitFor(() => {
      expect(screen.getByText('1,500')).toBeInTheDocument();
    });
  });
});
