// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

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

// ── API mock ─────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

// ── Context mocks ─────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ showToast: mockShowToast }),
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ── Stub heavy admin sub-components ──────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  Abbr: ({ term }: { term: string }) => <abbr>{term}</abbr>,
}));

// ── Fixture helpers ───────────────────────────────────────────────────────────
const makeBaseline = (overrides = {}) => ({
  id: 1,
  label: 'Q1 2025 Baseline',
  captured_at: '2025-01-01T10:00:00Z',
  baseline_period: { start: '2024-01-01', end: '2024-12-31' },
  metrics: {
    volunteer_hours: 120,
    member_count: 45,
    recipient_count: 30,
    active_relationships: 20,
    total_exchanges: 80,
    avg_response_hours: 4.5,
    engagement_rate_pct: 62,
  },
  notes: 'First pilot baseline',
  captured_by: 1,
  ...overrides,
});

const makeComparison = () => ({
  baseline: makeBaseline(),
  current: { volunteer_hours: 180, member_count: 60 },
  comparison: {
    volunteer_hours: { baseline: 120, current: 180, delta: 60, pct_change: 50 },
    member_count:    { baseline: 45,  current: 60,  delta: 15, pct_change: 33.3 },
  },
});

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('KpiBaselineAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
  });

  it('shows a loading spinner initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    const busy = screen.getAllByRole('status').find(
      (el) => el.getAttribute('aria-busy') === 'true',
    );
    expect(busy).toBeInTheDocument();
  });

  it('renders empty state when no baselines exist', async () => {
    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => {
      // The translated text for the empty state
      expect(screen.getByText(/No baselines captured yet/i)).toBeInTheDocument();
    });
  });

  it('renders baselines table when data is returned', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [makeBaseline()] });
    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Q1 2025 Baseline')).toBeInTheDocument();
    });
  });

  it('shows baseline notes in table row', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [makeBaseline()] });
    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('First pilot baseline')).toBeInTheDocument();
    });
  });

  it('shows an error toast when loading fails', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('network'));
    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(
        expect.any(String),
        'error',
      );
    });
  });

  it('opens the capture modal when "Capture Now" button is pressed', async () => {
    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => {
      // Loading must have completed first
      expect(
        screen.queryAllByRole('status').find(
          (el) => el.getAttribute('aria-busy') === 'true',
        ),
      ).toBeUndefined();
    });

    const captureBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('capture'),
    );
    expect(captureBtn).toBeDefined();
    fireEvent.click(captureBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('shows comparison panel after clicking Compare on a baseline', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeBaseline()] })
      .mockResolvedValueOnce({ success: true, data: makeComparison() });

    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => screen.getByText('Q1 2025 Baseline'));

    const compareBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('compare'),
    );
    expect(compareBtn).toBeDefined();
    fireEvent.click(compareBtn!);

    await waitFor(() => {
      // Comparison panel uses 'comparing' i18n key in ComparisonPanel header
      expect(screen.getByText(/comparing/i)).toBeInTheDocument();
    });
  });

  it('hides comparison panel when toggled off', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeBaseline()] })
      .mockResolvedValueOnce({ success: true, data: makeComparison() });

    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => screen.getByText('Q1 2025 Baseline'));

    const compareBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('compare'),
    );
    fireEvent.click(compareBtn!);

    await waitFor(() => screen.getByText(/comparing/i));

    // Clicking the same button again (now labelled "close") should hide the panel
    const closeBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase() === 'close' || b.textContent?.toLowerCase().includes('close'),
    );
    if (closeBtn) fireEvent.click(closeBtn);

    await waitFor(() => {
      expect(screen.queryByText(/comparing/i)).toBeNull();
    });
  });

  it('calls POST to capture a baseline and shows success toast', async () => {
    const user = userEvent.setup();
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post.mockResolvedValue({ success: true, data: makeBaseline() });

    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() =>
      expect(
        screen.queryAllByRole('status').find(
          (el) => el.getAttribute('aria-busy') === 'true',
        ),
      ).toBeUndefined(),
    );

    // Open modal
    const captureBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('capture'),
    );
    await user.click(captureBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Find and click the confirm button inside the modal
    const modalConfirmBtn = screen.getAllByRole('button').find(
      (b) =>
        b.closest('[role="dialog"]') &&
        (b.textContent?.toLowerCase().includes('capture') ||
          b.textContent?.toLowerCase().includes('snapshot')),
    );
    if (modalConfirmBtn) {
      await user.click(modalConfirmBtn);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith(
          '/v2/admin/caring-community/kpi-baselines',
          expect.any(Object),
        );
        expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
      });
    }
  });

  it('shows comparison failure toast when compare API fails', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeBaseline()] })
      .mockRejectedValueOnce(new Error('compare failed'));

    const { default: KpiBaselineAdminPage } = await import('./KpiBaselineAdminPage');
    render(<KpiBaselineAdminPage />);

    await waitFor(() => screen.getByText('Q1 2025 Baseline'));

    const compareBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('compare'),
    );
    fireEvent.click(compareBtn!);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });
});
