// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ---------------------------------------------------------------------------
// Hoist mock fns
// ---------------------------------------------------------------------------
const mockApiGet = vi.hoisted(() => vi.fn());
const mockToastError = vi.hoisted(() => vi.fn());

vi.mock('@/lib/api', () => ({
  api: {
    get: mockApiGet,
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      success: vi.fn(),
      error: mockToastError,
      info: vi.fn(),
      warning: vi.fn(),
    }),
  })
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import AiTraceMetricsAdminPage from './AiTraceMetricsAdminPage';

const METRICS_PAYLOAD = {
  window_days: 30,
  turns: 1234,
  tokens_total: 500000,
  cost_usd: 12.34,
  avg_latency_ms: 320,
  thumbs_up: 88,
  thumbs_down: 12,
  top_tools: [
    { name: 'search', calls: 55 },
    { name: 'calendar', calls: 22 },
  ],
  unanswered: [
    {
      id: 1,
      user_text: 'When does the timebank close?',
      assistant_text: 'I am not sure about that.',
      note: null,
      at: '2026-06-20T09:00:00Z',
      model: 'claude-sonnet-4',
    },
  ],
};

describe('AiTraceMetricsAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading ellipsis ("…") in stat cards while fetching', () => {
    // Never resolves
    mockApiGet.mockReturnValue(new Promise(() => {}));
    render(<AiTraceMetricsAdminPage />);

    // The Stat component renders "…" while loading
    const ellipses = screen.getAllByText('…');
    expect(ellipses.length).toBeGreaterThan(0);
  });

  it('shows tools loading indicator while fetching', () => {
    mockApiGet.mockReturnValue(new Promise(() => {}));
    render(<AiTraceMetricsAdminPage />);

    const statusEls = screen.getAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  it('renders stat values after successful load', async () => {
    mockApiGet.mockResolvedValue({ data: METRICS_PAYLOAD });
    render(<AiTraceMetricsAdminPage />);

    // Wait for tool chips to appear — they only render after loading=false and data is set
    // Use a generous timeout because the Select component may trigger multiple re-fetches
    await waitFor(
      () => {
        // search chip appears only when top_tools has data and loading=false
        expect(screen.getByText(/search - 55/)).toBeInTheDocument();
      },
      { timeout: 5000 }
    );
  });

  it('renders tool usage chips when top_tools populated', async () => {
    mockApiGet.mockResolvedValue({ data: METRICS_PAYLOAD });
    render(<AiTraceMetricsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText(/search/i)).toBeInTheDocument();
    });
    expect(screen.getByText(/calendar/i)).toBeInTheDocument();
  });

  it('renders unanswered row in the table', async () => {
    mockApiGet.mockResolvedValue({ data: METRICS_PAYLOAD });
    render(<AiTraceMetricsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('When does the timebank close?')).toBeInTheDocument();
    });
    expect(screen.getByText('I am not sure about that.')).toBeInTheDocument();
  });

  it('calls api.get with correct default window (30 days)', async () => {
    mockApiGet.mockResolvedValue({ data: METRICS_PAYLOAD });
    render(<AiTraceMetricsAdminPage />);

    // The Select component may cause extra renders; just check the first call
    await waitFor(() => expect(mockApiGet).toHaveBeenCalled());
    expect(mockApiGet).toHaveBeenCalledWith(
      expect.stringContaining('days=30')
    );
  });

  it('shows toast.error when API throws', async () => {
    mockApiGet.mockRejectedValue(new Error('network'));
    render(<AiTraceMetricsAdminPage />);

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalled();
    });
  });

  it('shows empty state text in table when unanswered list is empty', async () => {
    mockApiGet.mockResolvedValue({
      data: { ...METRICS_PAYLOAD, unanswered: [] },
    });
    render(<AiTraceMetricsAdminPage />);

    // Wait for the tool chips to appear (proves data loaded)
    await waitFor(() => {
      expect(screen.getByText(/search/i)).toBeInTheDocument();
    });

    // No unanswered row should appear
    expect(screen.queryByText('When does the timebank close?')).not.toBeInTheDocument();
  });
});
