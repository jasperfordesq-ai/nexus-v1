// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mock adminApi ────────────────────────────────────────────────────────────
vi.mock('@/admin/api/adminApi', () => {
  const getNexusScoreStats = vi.fn();
  return {
    adminDiagnostics: { getNexusScoreStats },
  };
});

// ── Mock contexts ────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

// ── AdminMetaContext: useAdminPageMeta is a hook that needs a Provider.
//    Stub it as a no-op so NexusScoreAnalytics can render without a Provider. ──
vi.mock('@/admin/AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

// ── Stable test data ─────────────────────────────────────────────────────────
const MOCK_DATA = vi.hoisted(() => ({
  total_badges_awarded: 500,
  active_users: 120,
  total_xp_awarded: 9000,
  active_campaigns: 4,
  badge_distribution: [
    { badge_name: 'Connector', count: 75 },
    { badge_name: 'Helper', count: 50 },
  ],
  avg_nexus_score: 62.5,
  top_10_threshold: 90,
  active_users_scored: 98,
  score_trend_30d: 5,
}));

const MOCK_DATA_NO_OPTIONALS = vi.hoisted(() => ({
  total_badges_awarded: 300,
  active_users: 80,
  total_xp_awarded: 4000,
  active_campaigns: 2,
  badge_distribution: [],
  // No avg_nexus_score, top_10_threshold, active_users_scored, score_trend_30d
}));

import { adminDiagnostics } from '@/admin/api/adminApi';
import { NexusScoreAnalytics } from './NexusScoreAnalytics';

describe('NexusScoreAnalytics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Loading state ────────────────────────────────────────────────────────
  it('shows busy spinner while fetching', () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockReturnValue(new Promise(() => {}));
    render(<NexusScoreAnalytics />);

    const statusEls = screen.getAllByRole('status');
    const spinner = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  it('removes busy spinner after data resolves', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      const spinner = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true'
      );
      expect(spinner).toBeUndefined();
    });
  });

  // ── Populated state with optional fields ─────────────────────────────────
  it('shows avg_nexus_score formatted to 1 decimal place', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('62.5')).toBeInTheDocument();
    });
  });

  it('shows +trend when score_trend_30d is positive', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('+5%')).toBeInTheDocument();
    });
  });

  it('renders badge_distribution items', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('Connector')).toBeInTheDocument();
      expect(screen.getByText('Helper')).toBeInTheDocument();
      expect(screen.getByText('75')).toBeInTheDocument();
    });
  });

  // ── Fallback when optional fields absent ────────────────────────────────
  it('falls back to total_xp_awarded for avg_nexus_score card when field absent', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA_NO_OPTIONALS,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      // total_xp_awarded=4000 displayed as fallback
      expect(screen.getByText('4000')).toBeInTheDocument();
    });
  });

  it('shows score_distribution_empty placeholder when badge_distribution is empty', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA_NO_OPTIONALS,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      expect(screen.queryByText('Connector')).not.toBeInTheDocument();
    });
  });

  // ── Score factors panel (always rendered) ────────────────────────────────
  it('renders score_factors section regardless of data', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      // Each factor has a static weight string
      expect(screen.getByText('25%')).toBeInTheDocument();
      expect(screen.getByText('20%')).toBeInTheDocument();
    });
  });

  // ── Error state ──────────────────────────────────────────────────────────
  it('calls toast.error when getNexusScoreStats rejects', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockRejectedValueOnce(new Error('Oops'));

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── API call ─────────────────────────────────────────────────────────────
  it('calls adminDiagnostics.getNexusScoreStats exactly once on mount', async () => {
    vi.mocked(adminDiagnostics.getNexusScoreStats).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<NexusScoreAnalytics />);

    await waitFor(() => {
      expect(adminDiagnostics.getNexusScoreStats).toHaveBeenCalledTimes(1);
    });
  });
});
