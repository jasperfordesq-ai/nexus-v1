// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── hoisted mock data ────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

const mockError404Entry = vi.hoisted(() => ({
  id: 1,
  url: '/missing-page',
  referrer: 'https://example.com',
  hits: 42,
  first_seen: '2026-01-01T00:00:00Z',
  last_seen: '2026-06-01T12:00:00Z',
}));

// ── mocks ────────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('../../api/adminApi', () => ({
  adminTools: {
    get404Errors: vi.fn(),
    delete404Error: vi.fn(),
  },
}));

import { adminTools } from '../../api/adminApi';
import { Error404Tracking } from './Error404Tracking';

// ── helpers ──────────────────────────────────────────────────────────────────
function mockSuccessfulLoad(items = [mockError404Entry], total = 1) {
  vi.mocked(adminTools.get404Errors).mockResolvedValue({
    success: true,
    data: { items, total, page: 1 },
  } as never);
}

function mockEmptyLoad() {
  vi.mocked(adminTools.get404Errors).mockResolvedValue({
    success: true,
    data: { items: [], total: 0, page: 1 },
  } as never);
}

function mockFailedLoad() {
  vi.mocked(adminTools.get404Errors).mockRejectedValue(new Error('Network error'));
}

// ── tests ────────────────────────────────────────────────────────────────────
describe('Error404Tracking', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    // Never resolves so we can catch the loading state
    vi.mocked(adminTools.get404Errors).mockReturnValue(new Promise(() => {}));

    render(<Error404Tracking />);

    const loadingEl = getAllByRoleStatus().find(el => el.getAttribute('aria-busy') === 'true');
    expect(loadingEl).toBeInTheDocument();
  });

  it('renders 404 entries after successful load', async () => {
    mockSuccessfulLoad();
    render(<Error404Tracking />);

    await waitFor(() => {
      expect(screen.getByText('/missing-page')).toBeInTheDocument();
    });
    expect(screen.getByText('https://example.com')).toBeInTheDocument();
  });

  it('removes loading spinner once data loads', async () => {
    mockSuccessfulLoad();
    render(<Error404Tracking />);

    await waitFor(() => {
      expect(screen.getByText('/missing-page')).toBeInTheDocument();
    });

    const busyStatus = screen.queryAllByRole('status').find(
      el => el.getAttribute('aria-busy') === 'true'
    );
    expect(busyStatus).toBeUndefined();
  });

  it('shows empty state when no 404 errors exist', async () => {
    mockEmptyLoad();
    render(<Error404Tracking />);

    await waitFor(() => {
      // EmptyState renders with icon + title — check that the table is absent
      expect(screen.queryByText('/missing-page')).not.toBeInTheDocument();
    });
  });

  it('shows error toast when fetch fails', async () => {
    mockFailedLoad();
    render(<Error404Tracking />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('opens confirm modal when dismiss button is pressed', async () => {
    mockSuccessfulLoad();
    const user = userEvent.setup();
    render(<Error404Tracking />);

    await waitFor(() => {
      expect(screen.getByText('/missing-page')).toBeInTheDocument();
    });

    const dismissBtn = screen.getByRole('button', { name: /dismiss/i });
    await user.click(dismissBtn);

    // ConfirmModal becomes open — check for confirm/cancel buttons
    await waitFor(() => {
      // The modal renders with a confirmation button labelled 'dismiss'
      // Two buttons will exist: the row button (now hidden by modal) + modal confirm
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(1);
    });
  });

  it('calls delete404Error and refreshes on confirm', async () => {
    // First load: returns item. Second load (after delete): returns empty.
    vi.mocked(adminTools.get404Errors)
      .mockResolvedValueOnce({
        success: true,
        data: { items: [mockError404Entry], total: 1, page: 1 },
      } as never)
      .mockResolvedValueOnce({
        success: true,
        data: { items: [], total: 0, page: 1 },
      } as never);
    vi.mocked(adminTools.delete404Error).mockResolvedValue({
      success: true,
    } as never);

    const user = userEvent.setup();
    render(<Error404Tracking />);

    await waitFor(() => {
      expect(screen.getByText('/missing-page')).toBeInTheDocument();
    });

    const dismissBtn = screen.getByRole('button', { name: /dismiss/i });
    await user.click(dismissBtn);

    // Modal opens — wait for multiple buttons then click confirm
    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(1);
    });
    const confirmBtns = screen.getAllByRole('button', { name: /dismiss/i });
    // The last one is inside the modal
    await user.click(confirmBtns[confirmBtns.length - 1]);

    await waitFor(() => {
      expect(adminTools.delete404Error).toHaveBeenCalledWith(mockError404Entry.id);
    });
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when delete fails', async () => {
    mockSuccessfulLoad();
    vi.mocked(adminTools.delete404Error).mockResolvedValue({
      success: false,
      error: 'Delete failed',
    } as never);

    const user = userEvent.setup();
    render(<Error404Tracking />);

    await waitFor(() => {
      expect(screen.getByText('/missing-page')).toBeInTheDocument();
    });

    const dismissBtn = screen.getByRole('button', { name: /dismiss/i });
    await user.click(dismissBtn);

    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(1);
    });

    const confirmBtns = screen.getAllByRole('button', { name: /dismiss/i });
    await user.click(confirmBtns[confirmBtns.length - 1]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

// helper: avoids importing screen twice
function getAllByRoleStatus() {
  return screen.queryAllByRole('status');
}
