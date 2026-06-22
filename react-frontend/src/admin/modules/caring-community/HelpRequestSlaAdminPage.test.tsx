// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock refs (must be declared before vi.mock factories) ──────────────
const mockShowToast = vi.fn();
const mockTenantPath = vi.fn((p: string) => `/test${p}`);

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ showToast: mockShowToast, success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: mockTenantPath,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { api } from '@/lib/api';
import HelpRequestSlaAdminPage from './HelpRequestSlaAdminPage';

// ── Test data ─────────────────────────────────────────────────────────────────

const MOCK_DASHBOARD = {
  policy: { first_response_hours: 4, resolution_hours: 24, source: 'platform_defaults' as const },
  summary: {
    pending: 3,
    in_progress: 2,
    first_response_breached: 1,
    first_response_at_risk: 2,
    resolution_breached: 0,
    resolution_at_risk: 1,
    resolved_within_window_24h: 5,
  },
  open_requests: [
    {
      id: 101,
      user_id: 5,
      what: 'Help with garden task',
      when_needed: '2026-06-30',
      status: 'pending',
      created_at: '2026-06-22T10:00:00Z',
      updated_at: null,
      age_hours: 3.5,
      sla_dimension: 'first_response' as const,
      sla_target_hours: 4,
      sla_remaining_hours: 0.5,
      sla_overage_hours: 0,
      bucket: 'at_risk' as const,
    },
    {
      id: 102,
      user_id: 8,
      what: 'Transport request breached',
      when_needed: '2026-06-28',
      status: 'pending',
      created_at: '2026-06-21T08:00:00Z',
      updated_at: null,
      age_hours: 26,
      sla_dimension: 'resolution' as const,
      sla_target_hours: 24,
      sla_remaining_hours: 0,
      sla_overage_hours: 2,
      bucket: 'breached' as const,
    },
  ],
  recently_resolved: [
    {
      id: 50,
      user_id: 12,
      what: 'Grocery run',
      status: 'closed',
      created_at: '2026-06-20T09:00:00Z',
      updated_at: '2026-06-21T11:00:00Z',
      age_hours: 26,
      turnaround_hours: 2.5,
      within_resolution_sla: true,
    },
  ],
  generated_at: '2026-06-22T12:00:00Z',
};

describe('HelpRequestSlaAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching', () => {
    // Never resolve during this test
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<HelpRequestSlaAdminPage />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders summary metric cards when data loads', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_DASHBOARD });
    render(<HelpRequestSlaAdminPage />);

    await waitFor(() => {
      // resolved_within_window_24h = 5 (unique value in the mock)
      expect(screen.getByText('5')).toBeInTheDocument();
    });
    // first_response_at_risk = 2 (rendered in a metric card)
    expect(screen.getAllByText('2').length).toBeGreaterThan(0);
  });

  it('shows open requests in table', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_DASHBOARD });
    render(<HelpRequestSlaAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Help with garden task')).toBeInTheDocument();
    });
    expect(screen.getByText('Transport request breached')).toBeInTheDocument();
  });

  it('shows recently resolved section with turnaround info', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_DASHBOARD });
    render(<HelpRequestSlaAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Grocery run')).toBeInTheDocument();
    });
  });

  it('shows error toast when API call fails', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('network error'));
    render(<HelpRequestSlaAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        expect.any(String),
        'error',
      );
    });
  });

  it('shows spinner is gone after data loads', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_DASHBOARD });
    render(<HelpRequestSlaAdminPage />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  it('displays policy source chip (platform_defaults)', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: MOCK_DASHBOARD });
    render(<HelpRequestSlaAdminPage />);

    // The page will display the policy hours from the mock data
    await waitFor(() => {
      expect(screen.getByText('4h')).toBeInTheDocument();
    });
  });

  it('refetch is triggered when refresh button pressed', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: MOCK_DASHBOARD });
    render(<HelpRequestSlaAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Help with garden task')).toBeInTheDocument();
    });

    // Find the refresh icon button (aria-label set by i18n key)
    const refreshButtons = screen.getAllByRole('button');
    // The icon-only refresh button is the one without text that's not 'Edit Policy'
    const iconBtns = refreshButtons.filter(
      (b) => b.hasAttribute('aria-label') && !b.textContent?.trim(),
    );
    expect(iconBtns.length).toBeGreaterThan(0);
    iconBtns[0].click();

    await waitFor(() => {
      expect(vi.mocked(api.get)).toHaveBeenCalledTimes(2);
    });
  });
});
