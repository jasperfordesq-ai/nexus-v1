// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mocks ──────────────────────────────────────────────────────────────────────
vi.mock('../../api/adminApi', () => ({
  adminTimebanking: {
    getStats: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts());

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

import { adminTimebanking } from '../../api/adminApi';
import { TimebankingDashboard } from './TimebankingDashboard';
import type { TimebankingStats } from '../../api/types';

const MOCK_STATS: TimebankingStats = {
  total_transactions: 1234,
  total_volume: 5678,
  avg_transaction: 4,
  active_alerts: 3,
  top_earners: [
    { user_id: 1, user_name: 'Top Earner', amount: 100 },
    { user_id: 2, user_name: 'Second Earner', amount: 80 },
  ],
  top_spenders: [
    { user_id: 3, user_name: 'Top Spender', amount: 90 },
  ],
};

describe('TimebankingDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders loading spinners while data is fetching', () => {
    vi.mocked(adminTimebanking.getStats).mockReturnValue(new Promise(() => {}));
    render(<TimebankingDashboard />);
    // Component renders two explicit role="status" aria-busy="true" divs (earners + spenders panels)
    const spinners = screen.getAllByRole('status');
    const busyOnes = spinners.filter((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyOnes.length).toBeGreaterThanOrEqual(1);
  });

  it('renders stat cards and top earners after load', async () => {
    vi.mocked(adminTimebanking.getStats).mockResolvedValue({
      success: true,
      data: MOCK_STATS,
    });
    render(<TimebankingDashboard />);

    await waitFor(() => {
      expect(screen.getByText('Top Earner')).toBeInTheDocument();
    });
    expect(screen.getByText('Top Spender')).toBeInTheDocument();
    expect(screen.getByText('Second Earner')).toBeInTheDocument();
  });

  it('shows "no transaction data" message when earners array is empty', async () => {
    vi.mocked(adminTimebanking.getStats).mockResolvedValue({
      success: true,
      data: {
        ...MOCK_STATS,
        top_earners: [],
        top_spenders: [],
      },
    });
    render(<TimebankingDashboard />);
    await waitFor(() => {
      // Two panels each with "no transaction data" message
      const msgs = screen.getAllByText(/no transaction data/i);
      expect(msgs.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('shows all four quick-link buttons', async () => {
    vi.mocked(adminTimebanking.getStats).mockResolvedValue({
      success: true,
      data: MOCK_STATS,
    });
    render(<TimebankingDashboard />);

    await waitFor(() => {
      expect(screen.getByText('Top Earner')).toBeInTheDocument();
    });

    expect(screen.getAllByText(/fraud alerts/i).length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText(/community fund/i).length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText(/user report/i).length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText(/org wallets/i).length).toBeGreaterThanOrEqual(1);
  });

  it('re-fetches stats when Refresh button is clicked', async () => {
    vi.mocked(adminTimebanking.getStats).mockResolvedValue({
      success: true,
      data: MOCK_STATS,
    });
    render(<TimebankingDashboard />);

    await waitFor(() => {
      expect(adminTimebanking.getStats).toHaveBeenCalledTimes(1);
    });

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await userEvent.click(refreshBtn);

    await waitFor(() => {
      expect(adminTimebanking.getStats).toHaveBeenCalledTimes(2);
    });
  });

  it('does not crash when getStats succeeds but returns no data', async () => {
    vi.mocked(adminTimebanking.getStats).mockResolvedValue({
      success: false,
      data: null,
    });
    render(<TimebankingDashboard />);
    // Should not throw; spinner should disappear after resolve
    await waitFor(() => {
      const busySpinners = screen
        .queryAllByRole('status')
        .filter((el) => el.getAttribute('aria-busy') === 'true');
      expect(busySpinners).toHaveLength(0);
    });
  });
});
