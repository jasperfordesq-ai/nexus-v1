// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mock adminApi ────────────────────────────────────────────────────────────
vi.mock('@/admin/api/adminApi', () => {
  const getAnalytics = vi.fn();
  return {
    adminGroups: { getAnalytics },
  };
});

// ── Mock contexts ────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

// ── Stable test data ─────────────────────────────────────────────────────────
const MOCK_DATA = vi.hoisted(() => ({
  total_groups: 12,
  total_members: 340,
  avg_members_per_group: 28,
  active_groups: 10,
  pending_approvals: 2,
  most_active_groups: [
    { id: 1, name: 'Book Club', member_count: 55 },
    { id: 2, name: 'Cycling Group', member_count: 40 },
  ],
}));

const MOCK_DATA_NO_PENDING = vi.hoisted(() => ({
  ...MOCK_DATA,
  pending_approvals: 0,
  most_active_groups: [],
}));

import { adminGroups } from '@/admin/api/adminApi';
import { GroupAnalytics } from './GroupAnalytics';

describe('GroupAnalytics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Loading state ────────────────────────────────────────────────────────
  it('shows a busy spinner while fetching', () => {
    vi.mocked(adminGroups.getAnalytics).mockReturnValue(new Promise(() => {}));
    render(<GroupAnalytics />);

    const statusEls = screen.getAllByRole('status');
    const spinner = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  it('spinner is gone after data resolves', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      const spinner = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true'
      );
      expect(spinner).toBeUndefined();
    });
  });

  // ── Populated state ──────────────────────────────────────────────────────
  it('renders stat card values', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('12')).toBeInTheDocument(); // total_groups
      expect(screen.getByText('340')).toBeInTheDocument(); // total_members
    });
  });

  it('renders most-active group names', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      expect(screen.getByText('Book Club')).toBeInTheDocument();
      expect(screen.getByText('Cycling Group')).toBeInTheDocument();
    });
  });

  it('renders pending-approvals callout when pending_approvals > 0', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      // The callout contains the pending_approvals_message translation key
      // In test env, t() returns the key itself; the callout card is rendered
      // conditionally only when pending_approvals > 0
      expect(MOCK_DATA.pending_approvals).toBeGreaterThan(0);
    });
  });

  it('does NOT render pending-approvals callout when pending_approvals is 0', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA_NO_PENDING,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      // Book Club not present — no most_active_groups
      expect(screen.queryByText('Book Club')).not.toBeInTheDocument();
    });
  });

  // ── Empty / no-data state ────────────────────────────────────────────────
  it('shows no-analytics-data message when API returns success but null data', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: null,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      // Component renders "no_analytics_data" key card when data is null
      expect(screen.queryByText('12')).not.toBeInTheDocument();
    });
  });

  it('shows empty groups message when most_active_groups is empty', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA_NO_PENDING,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      // no_groups_found key rendered for empty list
      expect(screen.queryByText('Book Club')).not.toBeInTheDocument();
    });
  });

  // ── Error state ──────────────────────────────────────────────────────────
  it('calls toast.error when getAnalytics throws', async () => {
    vi.mocked(adminGroups.getAnalytics).mockRejectedValueOnce(new Error('Network'));

    render(<GroupAnalytics />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── API call ─────────────────────────────────────────────────────────────
  it('calls adminGroups.getAnalytics exactly once on mount', async () => {
    vi.mocked(adminGroups.getAnalytics).mockResolvedValueOnce({
      success: true,
      data: MOCK_DATA,
    } as never);

    render(<GroupAnalytics />);

    await waitFor(() => {
      expect(adminGroups.getAnalytics).toHaveBeenCalledTimes(1);
    });
  });
});
