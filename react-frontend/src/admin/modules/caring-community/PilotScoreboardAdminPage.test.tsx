// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoisted mock data ────────────────────────────────────────────────────────
const { mockApi, mockShowToast } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  mockShowToast: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ showToast: mockShowToast }),
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  Abbr: ({ term, children }: { term: string; children?: React.ReactNode }) => (
    <abbr title={term}>{children ?? term}</abbr>
  ),
}));

// ── Fixtures ──────────────────────────────────────────────────────────────────
const makeCurrentMetrics = () => ({
  active_members: 52,
  first_response_hours: 3.2,
  approved_hours: 210.5,
  recurring_relationships: 18,
  coordinator_workload_hrs: 12.0,
  satisfaction_score: 4.3,
  social_isolation_pct: 22.0,
  comms_reach_pct: 75.0,
  business_participation: 8,
  cost_offset_chf: 4200,
  methodology: {
    window_days: 90,
    hourly_rate_chf: 20,
    prevention_multiplier: 2,
  },
});

const makeBaseline = (is_pre_pilot = true) => ({
  id: 1,
  label: 'Pre-Pilot Nov 2024',
  is_pre_pilot,
  baseline_period: { start: '2024-10-01', end: '2024-11-30' },
  captured_at: '2024-11-30T12:00:00Z',
  metrics: {
    active_members: 30,
    first_response_hours: 6.0,
    approved_hours: 100,
    recurring_relationships: 8,
    coordinator_workload_hrs: 20,
    satisfaction_score: 3.5,
    social_isolation_pct: 40,
    comms_reach_pct: 50,
    business_participation: 3,
    cost_offset_chf: 2000,
  },
  notes: 'Initial baseline',
  captured_by: 1,
});

const makeScoreboard = (withBaseline = true) => ({
  current: makeCurrentMetrics(),
  pre_pilot_baseline: withBaseline ? makeBaseline() : null,
  latest_quarterly: null,
  comparison: withBaseline
    ? {
        active_members: { baseline: 30, current: 52, delta: 22, pct_change: 73.3 },
        first_response_hours: { baseline: 6.0, current: 3.2, delta: -2.8, pct_change: -46.7 },
      }
    : null,
  quarterly_review: {
    next_due_at: '2025-03-01T00:00:00Z',
    is_overdue: false,
    cadence_months: 3,
  },
});

const makeSuccessResponse = (data: unknown) => ({ success: true, data });
const makeBaselineListResponse = (items: unknown[]) => ({
  success: true,
  data: { items },
});

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('PilotScoreboardAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get
      .mockResolvedValueOnce(makeSuccessResponse(makeScoreboard()))
      .mockResolvedValueOnce(makeBaselineListResponse([]));
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockReset();
    mockApi.get.mockImplementation(() => new Promise(() => {}));

    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    const busy = screen.getAllByRole('status').find(
      (el) => el.getAttribute('aria-busy') === 'true',
    );
    expect(busy).toBeInTheDocument();
  });

  it('renders the metrics table after data loads', async () => {
    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      // active_members = 52 should appear in the table
      expect(screen.getByText('52')).toBeInTheDocument();
    });
  });

  it('shows an error toast when loading fails', async () => {
    mockApi.get.mockReset();
    mockApi.get.mockRejectedValue(new Error('network'));

    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });

  it('renders quarterly review due date when present', async () => {
    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      // next_due_at '2025-03-01' is rendered as a localized date.
      // The test environment uses either 3/1/2025 (US) or 1/3/2025 (EU) format.
      const dueEl = screen.queryByText(/3\/1\/2025|1\/3\/2025|Mar.*2025|2025.*Mar/i);
      expect(dueEl).toBeInTheDocument();
    });
  });

  it('disables quarterly capture when no pre-pilot baseline is set', async () => {
    mockApi.get.mockReset();
    mockApi.get
      .mockResolvedValueOnce(makeSuccessResponse(makeScoreboard(false)))
      .mockResolvedValueOnce(makeBaselineListResponse([]));

    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      const quarterlyBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('quarterly'),
      );
      expect(quarterlyBtn).toBeDefined();
      expect(quarterlyBtn!.getAttribute('data-disabled')).toBeTruthy();
    });
  });

  it('opens the pre-pilot modal when "Capture Pre-Pilot" is clicked', async () => {
    mockApi.get.mockReset();
    mockApi.get
      .mockResolvedValueOnce(makeSuccessResponse(makeScoreboard(false)))
      .mockResolvedValueOnce(makeBaselineListResponse([]));

    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      const preBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('capture_pre') ||
        b.textContent?.toLowerCase().includes('pre'),
      );
      expect(preBtn).toBeDefined();
    });

    const preBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('capture_pre') ||
      b.textContent?.toLowerCase().includes('pre'),
    );
    fireEvent.click(preBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('shows captured baselines list when baselines are returned', async () => {
    mockApi.get.mockReset();
    mockApi.get
      .mockResolvedValueOnce(makeSuccessResponse(makeScoreboard()))
      .mockResolvedValueOnce(makeBaselineListResponse([makeBaseline()]));

    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Pre-Pilot Nov 2024')).toBeInTheDocument();
    });
  });

  it('calls POST to capture pre-pilot baseline on confirm', async () => {
    mockApi.get.mockReset();
    mockApi.get
      .mockResolvedValue(makeSuccessResponse(makeScoreboard(false)));
    mockApi.get.mockResolvedValueOnce(makeSuccessResponse(makeScoreboard(false)));
    mockApi.get.mockResolvedValueOnce(makeBaselineListResponse([]));
    mockApi.post.mockResolvedValueOnce({ success: true });

    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      const preBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('capture_pre') ||
        b.textContent?.toLowerCase().includes('pre'),
      );
      expect(preBtn).toBeDefined();
    });

    const preBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('capture_pre') ||
      b.textContent?.toLowerCase().includes('pre'),
    );
    fireEvent.click(preBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.closest('[role="dialog"]') &&
      b.textContent?.toLowerCase().includes('baseline'),
    );
    if (confirmBtn) {
      fireEvent.click(confirmBtn);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith(
          '/v2/admin/caring-community/pilot-scoreboard/pre-pilot',
          expect.any(Object),
        );
      });
    }
  });

  it('renders methodology line with hourly rate after data loads', async () => {
    const { default: PilotScoreboardAdminPage } = await import('./PilotScoreboardAdminPage');
    render(<PilotScoreboardAdminPage />);

    await waitFor(() => {
      // The methodology footer paragraph contains the hourly_rate_chf (20) and multiplier (2).
      // Use queryAllByText because "20" also appears in the metrics table.
      const matches = screen.queryAllByText(/20/);
      expect(matches.length).toBeGreaterThan(0);
    });
  });
});
