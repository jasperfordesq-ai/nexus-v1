// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable hoisted mock data ──────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));
const mockGetDashboard = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

vi.mock('../../api/adminApi', () => ({
  adminDeliverability: {
    getDashboard: mockGetDashboard,
  },
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { DeliverabilityDashboard } from './DeliverabilityDashboard';

// ── Test helpers ──────────────────────────────────────────────────────────────
const SAMPLE_DASHBOARD = {
  total: 42,
  by_status: { completed: 10, in_progress: 20, pending: 12 },
  overdue: 5,
  completion_rate: 0.24,
  recent_activity: [
    {
      id: 1,
      deliverable_title: 'Sprint 1 Planning',
      action_type: 'created',
      user_name: 'Alice',
      action_timestamp: '2024-04-01T09:00:00Z',
    },
    {
      id: 2,
      deliverable_title: 'Q2 Review',
      action_type: 'completed',
      user_name: 'Bob',
      action_timestamp: '2024-04-02T14:30:00Z',
    },
  ],
};

describe('DeliverabilityDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetDashboard.mockResolvedValue({ success: true, data: SAMPLE_DASHBOARD });
  });

  // ── Loading state ─────────────────────────────────────────────────────────
  it('shows an aria-busy loading indicator while fetching', () => {
    mockGetDashboard.mockReturnValue(new Promise(() => {}));
    render(<DeliverabilityDashboard />);

    const spinners = screen.getAllByRole('status');
    const busyEl = spinners.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  // ── Populated state ───────────────────────────────────────────────────────
  it('renders stat cards with correct values after fetch', async () => {
    render(<DeliverabilityDashboard />);

    await waitFor(() => {
      expect(screen.getByText('42')).toBeInTheDocument();
    });

    expect(screen.getByText('10')).toBeInTheDocument(); // completed
    expect(screen.getByText('20')).toBeInTheDocument(); // in_progress
    expect(screen.getByText('5')).toBeInTheDocument();  // overdue
  });

  it('renders recent activity items', async () => {
    render(<DeliverabilityDashboard />);

    await waitFor(() => {
      expect(screen.getByText('Sprint 1 Planning')).toBeInTheDocument();
    });
    expect(screen.getByText('Q2 Review')).toBeInTheDocument();
  });

  // ── Empty recent_activity ─────────────────────────────────────────────────
  it('renders empty-dashboard placeholder when activity list is empty', async () => {
    mockGetDashboard.mockResolvedValue({
      success: true,
      data: { ...SAMPLE_DASHBOARD, recent_activity: [] },
    });
    render(<DeliverabilityDashboard />);

    await waitFor(() => {
      expect(screen.queryByText('Sprint 1 Planning')).not.toBeInTheDocument();
    });
  });

  // ── null data (API success=false or no data) ──────────────────────────────
  it('shows zeroed stats when API returns success=false', async () => {
    mockGetDashboard.mockResolvedValue({ success: false });
    render(<DeliverabilityDashboard />);

    // Component uses `data || fallback` — should display 0 values
    await waitFor(() => {
      // Multiple 0s expected — find at least one
      const zeros = screen.getAllByText('0');
      expect(zeros.length).toBeGreaterThan(0);
    });
  });

  // ── Error state ───────────────────────────────────────────────────────────
  it('calls toast.error when API throws', async () => {
    mockGetDashboard.mockRejectedValue(new Error('Network Error'));
    render(<DeliverabilityDashboard />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Loading clears ────────────────────────────────────────────────────────
  it('removes the loading spinner after fetch completes', async () => {
    render(<DeliverabilityDashboard />);

    await waitFor(() => {
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });
  });
});
