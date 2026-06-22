// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable hoisted mock data ──────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));
const mockGetAnalytics = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

vi.mock('../../api/adminApi', () => ({
  adminDeliverability: {
    getAnalytics: mockGetAnalytics,
  },
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { DeliverabilityAnalytics } from './DeliverabilityAnalytics';

// ── Test helpers ──────────────────────────────────────────────────────────────
const SAMPLE_ANALYTICS = {
  completion_trends: [
    { date: '2024-03-01', count: 5 },
    { date: '2024-03-02', count: 8 },
    { date: '2024-03-03', count: 3 },
  ],
  priority_distribution: { high: 12, medium: 8, low: 4 },
  avg_days_to_complete: 7.5,
  risk_distribution: { low: 15, medium: 9, high: 3 },
};

describe('DeliverabilityAnalytics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetAnalytics.mockResolvedValue({ success: true, data: SAMPLE_ANALYTICS });
  });

  // ── Loading state ─────────────────────────────────────────────────────────
  it('shows an aria-busy loading indicator while fetching', () => {
    mockGetAnalytics.mockReturnValue(new Promise(() => {}));
    render(<DeliverabilityAnalytics />);

    const spinners = screen.getAllByRole('status');
    const busyEl = spinners.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  // ── Loading clears ────────────────────────────────────────────────────────
  it('removes the loading spinner after fetch completes', async () => {
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });
  });

  // ── Populated state ───────────────────────────────────────────────────────
  it('renders stat cards after successful fetch', async () => {
    render(<DeliverabilityAnalytics />);

    // avg_days_to_complete = 7.5 is unique — confirms the populated view
    await waitFor(() => {
      expect(screen.getByText('7.5')).toBeInTheDocument();
    });

    // Multiple stat cards should be present
    const allStatValues = screen.getAllByText('3');
    expect(allStatValues.length).toBeGreaterThan(0);
  });

  it('renders priority distribution entries', async () => {
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      // 12 is unique to priority_distribution (high)
      expect(screen.getByText('12')).toBeInTheDocument();
    });
    // 8 (medium) and 4 (low) are also unique in this dataset
    expect(screen.getByText('8')).toBeInTheDocument();
    expect(screen.getByText('4')).toBeInTheDocument();
  });

  it('renders risk distribution entries', async () => {
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      // 15 is unique to risk_distribution (low)
      expect(screen.getByText('15')).toBeInTheDocument();
    });
    // 9 (medium risk) is unique in this dataset
    expect(screen.getByText('9')).toBeInTheDocument();
  });

  // ── null/empty data branch ────────────────────────────────────────────────
  it('shows no-analytics-data empty state when API returns success=true but no data', async () => {
    // success=true but res.data is undefined/null → component leaves data=null
    mockGetAnalytics.mockResolvedValue({ success: true, data: null });
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      // The "no data" Card is rendered when data === null
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });

    // Priority/risk distribution tables should NOT be present
    expect(screen.queryByText('12')).not.toBeInTheDocument();
  });

  it('shows empty priority panel when priority_distribution is empty', async () => {
    mockGetAnalytics.mockResolvedValue({
      success: true,
      data: { ...SAMPLE_ANALYTICS, priority_distribution: {} },
    });
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      // No priority count values rendered inside priority card
      expect(screen.queryByText('12')).not.toBeInTheDocument();
    });
  });

  it('shows empty risk panel when risk_distribution is empty', async () => {
    mockGetAnalytics.mockResolvedValue({
      success: true,
      data: { ...SAMPLE_ANALYTICS, risk_distribution: {} },
    });
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      expect(screen.queryByText('15')).not.toBeInTheDocument();
    });
  });

  // ── Error state ───────────────────────────────────────────────────────────
  it('calls toast.error when API throws', async () => {
    mockGetAnalytics.mockRejectedValue(new Error('Server error'));
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── avg_days_to_complete = null ───────────────────────────────────────────
  it('renders -- for null avg_days_to_complete', async () => {
    mockGetAnalytics.mockResolvedValue({
      success: true,
      data: { ...SAMPLE_ANALYTICS, avg_days_to_complete: null },
    });
    render(<DeliverabilityAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('--')).toBeInTheDocument();
    });
  });
});
