// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── mock contexts ────────────────────────────────────────────────────────────

const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

// ── mock adminApi ────────────────────────────────────────────────────────────

const mockGetMatchingStats = vi.hoisted(() => vi.fn());

vi.mock('@/admin/api/adminApi', () => ({
  adminMatching: {
    getMatchingStats: mockGetMatchingStats,
  },
}));

// ── mock hooks ───────────────────────────────────────────────────────────────

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── helpers ──────────────────────────────────────────────────────────────────

import { SmartMatchMonitoring } from './SmartMatchMonitoring';

const FULL_DATA = {
  overview: {
    total_matches_month: 120,
    avg_match_score: 87.5,
    cache_hit_rate: 72,
    total_matches_today: 4,
    total_matches_week: 33,
    hot_matches_count: 7,
    mutual_matches_count: 11,
    avg_distance_km: 8.5,
    cache_entries: 24,
    active_users_matching: 55,
  },
  approval_rate: 65,
  broker_approval_enabled: true,
  pending_approvals: 3,
  approved_count: 48,
  rejected_count: 9,
  score_distribution: { '0-20': 5, '21-40': 12, '41-60': 30, '61-80': 45, '81-100': 28 },
  distance_distribution: { walking: 5, local: 12, city: 30, regional: 45, distant: 28 },
};

describe('SmartMatchMonitoring', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    // never resolves during this test
    mockGetMatchingStats.mockReturnValue(new Promise(() => {}));
    render(<SmartMatchMonitoring />);

    const spinner = getAllByRoleWithBusy();
    expect(spinner).toBeDefined();
  });

  it('renders stat cards after successful load', async () => {
    mockGetMatchingStats.mockResolvedValue({ success: true, data: FULL_DATA });
    render(<SmartMatchMonitoring />);

    await waitFor(() => {
      expect(screen.getByText('120')).toBeInTheDocument();
    });
    // avg match score formatted
    expect(screen.getByText('87.5%')).toBeInTheDocument();
    // approval rate
    expect(screen.getByText('65%')).toBeInTheDocument();
    // cache hit rate
    expect(screen.getByText('72%')).toBeInTheDocument();
  });

  it('renders engine status details from data', async () => {
    mockGetMatchingStats.mockResolvedValue({ success: true, data: FULL_DATA });
    render(<SmartMatchMonitoring />);

    await waitFor(() => {
      // pending approvals
      expect(screen.getByText('3')).toBeInTheDocument();
    });
    // hot matches
    expect(screen.getByText('7')).toBeInTheDocument();
  });

  it('renders score distribution rows', async () => {
    mockGetMatchingStats.mockResolvedValue({ success: true, data: FULL_DATA });
    render(<SmartMatchMonitoring />);

    await waitFor(() => {
      expect(screen.getByText('0-20')).toBeInTheDocument();
    });
    expect(screen.getByText('81-100')).toBeInTheDocument();
  });

  it('shows a retryable error when success is false', async () => {
    mockGetMatchingStats.mockResolvedValue({ success: false });
    render(<SmartMatchMonitoring />);

    await waitFor(() => {
      // loading spinner should be gone
      const busyEl = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });
    expect(screen.getByRole('alert')).toHaveTextContent(/failed to load matching stats/i);
    expect(screen.getByRole('button', { name: /retry/i })).toBeInTheDocument();
  });

  it('shows a retryable error without a toast on API rejection', async () => {
    mockGetMatchingStats.mockRejectedValue(new Error('network'));
    render(<SmartMatchMonitoring />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent(/failed to load matching stats/i);
    });
    expect(mockToast.error).not.toHaveBeenCalled();
  });

  it('shows empty score distribution state when no score data', async () => {
    mockGetMatchingStats.mockResolvedValue({
      success: true,
      data: { ...FULL_DATA, score_distribution: {} },
    });
    render(<SmartMatchMonitoring />);

    await waitFor(() => {
      expect(screen.getByText(/no score distribution/i)).toBeInTheDocument();
    });
  });
});

// ── tiny helper (avoids duplicating the aria-busy query) ─────────────────────

function getAllByRoleWithBusy() {
  return screen.getAllByRole('status').find(
    (el) => el.getAttribute('aria-busy') === 'true',
  );
}
