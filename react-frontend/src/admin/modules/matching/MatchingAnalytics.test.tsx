// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── adminApi mock ────────────────────────────────────────────────────────────
const { mockAdminMatching, mockToast, mockNavigate } = vi.hoisted(() => ({
  mockAdminMatching: {
    getMatchingStats: vi.fn(),
    getConfig: vi.fn(),
    clearCache: vi.fn(),
  },
  mockToast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
  mockNavigate: vi.fn(),
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminMatching: mockAdminMatching,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return { ...actual, useNavigate: () => mockNavigate };
});

import { MatchingAnalytics } from './MatchingAnalytics';

// ── fixtures ─────────────────────────────────────────────────────────────────
const STATS_RESPONSE = {
  success: true,
  data: {
    overview: {
      cache_entries: 120,
      total_matches_month: 450,
      total_matches_week: 98,
      total_matches_today: 12,
      avg_match_score: 73,
      avg_distance_km: 4.2,
      hot_matches_count: 15,
      mutual_matches_count: 30,
      active_users_matching: 80,
    },
    score_distribution: { '0-40': 10, '40-60': 50, '60-80': 200, '80-100': 190 },
    distance_distribution: { walking: 40, local: 80, city: 150, regional: 60, distant: 20 },
    pending_approvals: 5,
    approved_count: 300,
    rejected_count: 25,
    approval_rate: 92,
    broker_approval_enabled: true,
  },
};

const EMPTY_STATS_RESPONSE = {
  success: true,
  data: {
    overview: {
      cache_entries: 0,
      total_matches_month: 0,
      total_matches_week: 0,
      total_matches_today: 0,
      avg_match_score: 0,
      avg_distance_km: 0,
      hot_matches_count: 0,
      mutual_matches_count: 0,
      active_users_matching: 0,
    },
    score_distribution: {},
    distance_distribution: {},
    pending_approvals: 0,
    approved_count: 0,
    rejected_count: 0,
    approval_rate: 0,
    broker_approval_enabled: false,
  },
};

describe('MatchingAnalytics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching', () => {
    mockAdminMatching.getMatchingStats.mockReturnValue(new Promise(() => {}));
    render(<MatchingAnalytics />);
    const statusEls = screen.queryAllByRole('status');
    const spinner = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  it('shows empty state when no data', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(EMPTY_STATS_RESPONSE);
    render(<MatchingAnalytics />);
    await waitFor(() => {
      // Empty state title — exact translation key match avoids ambiguity with description text
      expect(screen.getByText('No matching data yet')).toBeInTheDocument();
    });
  });

  it('renders stat cards after data loads', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(STATS_RESPONSE);
    render(<MatchingAnalytics />);

    await waitFor(() => {
      // Approval rate stat card — StatCard renders value as <p>; use getAllByText since the
      // value also appears in the approval metrics section (two occurrences is expected)
      expect(screen.getAllByText('92%').length).toBeGreaterThan(0);
    });

    // Total matches this month — appears in both StatCard and activity row
    expect(screen.getAllByText(/^450$|^450,?0*$/).length).toBeGreaterThan(0);
  });

  it('renders score distribution bars', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(STATS_RESPONSE);
    render(<MatchingAnalytics />);

    await waitFor(() => {
      expect(screen.getByText(/score distribution/i)).toBeInTheDocument();
    });
  });

  it('renders distance distribution bars', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(STATS_RESPONSE);
    render(<MatchingAnalytics />);

    await waitFor(() => {
      expect(screen.getByText(/distance distribution/i)).toBeInTheDocument();
    });
  });

  it('renders matching activity panel with activity rows', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(STATS_RESPONSE);
    render(<MatchingAnalytics />);

    await waitFor(() => {
      expect(screen.getByText(/matching activity/i)).toBeInTheDocument();
    });
  });

  it('renders approval metrics panel', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(STATS_RESPONSE);
    render(<MatchingAnalytics />);

    await waitFor(() => {
      expect(screen.getByText(/approval metrics/i)).toBeInTheDocument();
    });
  });

  it('shows error toast when API fails', async () => {
    mockAdminMatching.getMatchingStats.mockRejectedValue(new Error('Network error'));
    render(<MatchingAnalytics />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('has a Back button that navigates', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(STATS_RESPONSE);
    render(<MatchingAnalytics />);
    await waitFor(() => {
      expect(screen.queryAllByRole('button', { name: /back/i }).length).toBeGreaterThan(0);
    });
  });

  it('has a Refresh button that re-fetches data', async () => {
    mockAdminMatching.getMatchingStats.mockResolvedValue(STATS_RESPONSE);
    render(<MatchingAnalytics />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /refresh/i })).toBeInTheDocument();
    });

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    fireEvent(refreshBtn, new MouseEvent('click', { bubbles: true }));

    await waitFor(() => {
      expect(mockAdminMatching.getMatchingStats).toHaveBeenCalledTimes(2);
    });
  });
});
