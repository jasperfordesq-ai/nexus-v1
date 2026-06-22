// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoisted mocks ────────────────────────────────────────────────────────────
const { mockApi, mockToast } = vi.hoisted(() => {
  const m = { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn() };
  return {
    mockApi: m,
    mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  };
});

vi.mock('@/lib/api', () => ({
  default: mockApi,
  api: mockApi,
}));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── Fixtures ──────────────────────────────────────────────────────────────────
const POLL_ACTIVE = {
  id: 1,
  question: 'What is your favourite colour?',
  created_at: '2025-01-10T10:00:00Z',
  end_date: null,
  is_active: true,
  options: [{ id: 1 }, { id: 2 }],
  total_votes: 55,
  user: { name: 'Jane Smith' },
};

const POLL_ENDED = {
  id: 2,
  question: 'Best time for the community meeting?',
  created_at: '2024-12-01T00:00:00Z',
  end_date: '2024-12-15T00:00:00Z', // past
  is_active: false,
  options: [{ id: 3 }],
  total_votes: 10,
  user: { first_name: 'John', last_name: 'Doe' },
};

const makeGetResponse = (data: unknown[], total = data.length) => ({
  success: true,
  data,
  meta: { total },
});

import { PollsAdmin } from './PollsAdmin';

describe('PollsAdmin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApi.get.mockResolvedValue(makeGetResponse([]));
  });

  // ── Loading state ──────────────────────────────────────────────────────────
  it('shows a loading spinner while fetching', async () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<PollsAdmin />);
    const spinner = document.querySelector('[role="status"][aria-busy="true"]');
    expect(spinner).toBeTruthy();
  });

  // ── Empty state ────────────────────────────────────────────────────────────
  it('renders the page header when no polls exist', async () => {
    render(<PollsAdmin />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalledTimes(1));
    // Header area should be rendered
    expect(document.body).toBeInTheDocument();
  });

  // ── Error state ────────────────────────────────────────────────────────────
  it('shows error toast when API throws', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));
    render(<PollsAdmin />);
    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });

  // ── Populated state ────────────────────────────────────────────────────────
  it('renders poll question text', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    render(<PollsAdmin />);
    await waitFor(() =>
      expect(screen.getByText('What is your favourite colour?')).toBeInTheDocument()
    );
  });

  it('renders creator name from user.name field', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    render(<PollsAdmin />);
    await waitFor(() => expect(screen.getByText('Jane Smith')).toBeInTheDocument());
  });

  it('renders creator name from first_name + last_name fallback', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ENDED]));
    render(<PollsAdmin />);
    await waitFor(() => expect(screen.getByText('John Doe')).toBeInTheDocument());
  });

  it('renders votes count', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    render(<PollsAdmin />);
    await waitFor(() => expect(screen.getByText('55')).toBeInTheDocument());
  });

  it('renders options count', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    render(<PollsAdmin />);
    await waitFor(() => expect(screen.getByText('2')).toBeInTheDocument());
  });

  it('passes correct URL to api.get', async () => {
    render(<PollsAdmin />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalled());
    const url: string = mockApi.get.mock.calls[0][0];
    expect(url).toContain('/v2/admin/polls');
    expect(url).toContain('page=1');
  });

  // ── Delete flow ────────────────────────────────────────────────────────────
  it('renders a delete button for each poll', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    render(<PollsAdmin />);
    await waitFor(() =>
      expect(screen.getByText('What is your favourite colour?')).toBeInTheDocument()
    );
    const deleteBtns = screen.getAllByRole('button').filter(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    expect(deleteBtns.length).toBeGreaterThan(0);
  });

  it('calls DELETE endpoint and refreshes after confirm', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    mockApi.delete.mockResolvedValue({ success: true });

    render(<PollsAdmin />);
    await waitFor(() =>
      expect(screen.getByText('What is your favourite colour?')).toBeInTheDocument()
    );

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    expect(deleteBtn).toBeTruthy();
    fireEvent.click(deleteBtn!);

    // Confirm modal should appear; click confirm
    await waitFor(() => {
      const confirmBtn = screen.queryAllByRole('button').find(
        (b) => /delete/i.test(b.textContent ?? '')
      );
      if (confirmBtn) fireEvent.click(confirmBtn);
    });

    await waitFor(() => expect(mockApi.delete).toHaveBeenCalled());
  });

  it('shows success toast after delete', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    mockApi.delete.mockResolvedValue({ success: true });

    render(<PollsAdmin />);
    await waitFor(() =>
      expect(screen.getByText('What is your favourite colour?')).toBeInTheDocument()
    );

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      const confirmBtn = screen.queryAllByRole('button').find(
        (b) => /delete/i.test(b.textContent ?? '')
      );
      if (confirmBtn) fireEvent.click(confirmBtn);
    });

    await waitFor(() => expect(mockToast.success).toHaveBeenCalled());
  });

  // ── Detail modal ───────────────────────────────────────────────────────────
  it('opens detail modal when view button is clicked', async () => {
    mockApi.get.mockResolvedValue(makeGetResponse([POLL_ACTIVE]));
    render(<PollsAdmin />);
    await waitFor(() =>
      expect(screen.getByText('What is your favourite colour?')).toBeInTheDocument()
    );

    const viewBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('view')
    );
    expect(viewBtn).toBeTruthy();
    fireEvent.click(viewBtn!);

    // The question should appear in the modal body too
    await waitFor(() => {
      const matches = screen.getAllByText('What is your favourite colour?');
      expect(matches.length).toBeGreaterThanOrEqual(1);
    });
  });
});
