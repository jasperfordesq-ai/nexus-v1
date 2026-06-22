// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock refs ──────────────────────────────────────────────────────────
const { mockToast, mockNavigate } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockNavigate: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

// api is not directly imported by NewsletterList — adminNewsletters wraps it.
vi.mock('@/lib/api', () => {
  const m = { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() };
  return { default: m, api: m };
});

// Mock adminApi so we can control adminNewsletters directly
const { mockNLList, mockNLDelete, mockNLSend, mockNLDuplicate } = vi.hoisted(() => ({
  mockNLList: vi.fn(),
  mockNLDelete: vi.fn(),
  mockNLSend: vi.fn(),
  mockNLDuplicate: vi.fn(),
}));

vi.mock('../../api/adminApi', () => ({
  adminNewsletters: {
    list: mockNLList,
    delete: mockNLDelete,
    sendNewsletter: mockNLSend,
    duplicateNewsletter: mockNLDuplicate,
  },
}));

// Mock NewsletterResend so we don't need its dependencies
vi.mock('./NewsletterResend', () => ({
  NewsletterResend: ({ isOpen }: { isOpen: boolean }) =>
    isOpen ? <div data-testid="newsletter-resend-modal">Resend</div> : null,
}));

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return { ...actual, useNavigate: () => mockNavigate };
});

import { NewsletterList } from './NewsletterList';

// ── Test data ─────────────────────────────────────────────────────────────────
const DRAFT_NL = {
  id: 1,
  name: 'Draft Newsletter',
  subject: 'Hello World',
  status: 'draft',
  recipients_count: 0,
  total_recipients: 100,
  open_rate: 0,
  click_rate: 0,
  sent_at: null,
  created_at: '2026-01-01T00:00:00Z',
  is_recurring: false,
  ab_test_enabled: false,
};

const SENT_NL = {
  id: 2,
  name: 'Sent Newsletter',
  subject: 'Monthly Update',
  status: 'sent',
  recipients_count: 200,
  total_recipients: 200,
  open_rate: 45.5,
  click_rate: 12.3,
  sent_at: '2026-01-15T10:00:00Z',
  created_at: '2026-01-10T00:00:00Z',
  is_recurring: false,
  ab_test_enabled: false,
};

function resolveList(items: unknown[]) {
  mockNLList.mockResolvedValue({ success: true, data: { data: items, meta: { total: items.length } } });
}

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('NewsletterList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    // Never resolves — keeps component in loading state
    mockNLList.mockReturnValue(new Promise(() => {}));
    render(<NewsletterList />);
    // DataTable renders a loading overlay / spinner internally; the refresh button
    // should also appear as loading (isLoading prop)
    const spinners = getAllByRoleStatus(document.body);
    expect(spinners.length).toBeGreaterThan(0);
  });

  it('renders newsletter rows after load', async () => {
    resolveList([DRAFT_NL, SENT_NL]);
    render(<NewsletterList />);
    await waitFor(() => {
      expect(screen.getByText('Hello World')).toBeInTheDocument();
      expect(screen.getByText('Monthly Update')).toBeInTheDocument();
    });
  });

  it('shows empty-state message when list is empty', async () => {
    resolveList([]);
    render(<NewsletterList />);
    await waitFor(() => {
      expect(screen.getByText(/no newsletters found/i)).toBeInTheDocument();
    });
  });

  it('navigates to create page when Create Newsletter button is pressed', async () => {
    resolveList([]);
    render(<NewsletterList />);
    await waitFor(() => screen.getByText(/create newsletter/i));

    await userEvent.click(screen.getByText(/create newsletter/i));
    expect(mockNavigate).toHaveBeenCalledWith(expect.stringContaining('/admin/newsletters/create'));
  });

  it('opens delete confirm modal and calls delete on confirm', async () => {
    const user = userEvent.setup();
    resolveList([DRAFT_NL]);
    mockNLDelete.mockResolvedValue({ success: true });
    mockNLList
      .mockResolvedValueOnce({ success: true, data: { data: [DRAFT_NL], meta: { total: 1 } } })
      .mockResolvedValue({ success: true, data: { data: [], meta: { total: 0 } } });

    render(<NewsletterList />);
    await waitFor(() => screen.getByText('Hello World'));

    // Open the dropdown for the first row
    const actionBtn = screen.getAllByRole('button', { name: /actions/i })[0];
    await user.click(actionBtn);

    // The Dropdown portal exposes text items
    await waitFor(() =>
      expect(screen.getByText(/^delete$/i)).toBeInTheDocument()
    );
    await user.click(screen.getByText(/^delete$/i));

    // Confirm modal should appear
    await waitFor(() =>
      expect(screen.getByText(/delete newsletter/i)).toBeInTheDocument()
    );

    // Confirm deletion — button with text "Delete" inside the modal
    const confirmBtns = screen.getAllByRole('button', { name: /^delete$/i });
    await user.click(confirmBtns[confirmBtns.length - 1]);

    await waitFor(() => {
      expect(mockNLDelete).toHaveBeenCalledWith(1);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when delete fails', async () => {
    const user = userEvent.setup();
    resolveList([DRAFT_NL]);
    mockNLDelete.mockResolvedValue({ success: false });

    render(<NewsletterList />);
    await waitFor(() => screen.getByText('Hello World'));

    const actionBtn = screen.getAllByRole('button', { name: /actions/i })[0];
    await user.click(actionBtn);

    await waitFor(() => expect(screen.getByText(/^delete$/i)).toBeInTheDocument());
    await user.click(screen.getByText(/^delete$/i));

    await waitFor(() => screen.getByText(/delete newsletter/i));

    const confirmBtns = screen.getAllByRole('button', { name: /^delete$/i });
    await user.click(confirmBtns[confirmBtns.length - 1]);

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });

  it('shows send confirm modal and calls sendNewsletter on confirm', async () => {
    const user = userEvent.setup();
    resolveList([DRAFT_NL]);
    mockNLSend.mockResolvedValue({ success: true, data: { message: 'Queued' } });
    // After send, list re-fetches
    mockNLList
      .mockResolvedValueOnce({ success: true, data: { data: [DRAFT_NL], meta: { total: 1 } } })
      .mockResolvedValue({ success: true, data: { data: [], meta: { total: 0 } } });

    render(<NewsletterList />);
    await waitFor(() => screen.getByText('Hello World'));

    const actionBtn = screen.getAllByRole('button', { name: /actions/i })[0];
    await user.click(actionBtn);

    // "Send Now" option appears in dropdown
    await waitFor(() => expect(screen.getByText(/send now/i)).toBeInTheDocument());
    await user.click(screen.getByText(/send now/i));

    // Send confirm modal
    await waitFor(() =>
      expect(screen.getByText(/send newsletter now/i)).toBeInTheDocument()
    );

    // Confirm via the modal confirm button
    const sendBtns = screen.getAllByRole('button', { name: /send now/i });
    await user.click(sendBtns[sendBtns.length - 1]);

    await waitFor(() => {
      expect(mockNLSend).toHaveBeenCalledWith(1);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows open_rate percentage when available', async () => {
    resolveList([SENT_NL]);
    render(<NewsletterList />);
    await waitFor(() => {
      expect(screen.getByText(/45\.5%/)).toBeInTheDocument();
    });
  });

  it('gracefully handles API error on initial load', async () => {
    mockNLList.mockRejectedValue(new Error('Network error'));
    render(<NewsletterList />);
    // Should not crash — empty list with no items rendered
    await waitFor(() => {
      // DataTable will show empty state or just no rows; no uncaught error
      expect(screen.queryByText(/hello world/i)).not.toBeInTheDocument();
    });
  });
});

// Helper: find elements that are spinner/status with aria-busy=true
function getAllByRoleStatus(container: HTMLElement) {
  return Array.from(container.querySelectorAll('[role="status"]')).filter(
    (el) => el.getAttribute('aria-busy') === 'true'
  );
}
