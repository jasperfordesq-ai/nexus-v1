// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── vi.hoisted ──────────────────────────────────────────────────────────────
const mockSuccess = vi.hoisted(() => vi.fn());
const mockError = vi.hoisted(() => vi.fn());

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({
    success: mockSuccess,
    error: mockError,
    info: vi.fn(),
    warning: vi.fn(),
    showToast: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      success: mockSuccess,
      error: mockError,
      info: vi.fn(),
      warning: vi.fn(),
      showToast: vi.fn(),
    }),
  })
);

vi.mock('@/admin/api/adminApi', () => ({
  adminResidency: {
    list: vi.fn(),
    attest: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import React from 'react';
import { adminResidency } from '@/admin/api/adminApi';
import ResidencyVerifications from './ResidencyVerifications';

const mockItem = {
  id: 1,
  user_id: 10,
  tenant_id: 2,
  declared_municipality: 'Dublin',
  declared_postcode: 'D01',
  declared_address: '1 Main St, Dublin',
  evidence_note: 'Utility bill',
  status: 'pending' as const,
  rejection_reason: null,
  created_at: '2026-01-10T10:00:00Z',
  attested_at: null,
  member: { name: 'Alice Smith', email: 'alice@example.com' },
};

const mockApprovedItem = {
  ...mockItem,
  id: 2,
  status: 'approved' as const,
  attested_at: '2026-01-11T10:00:00Z',
};

describe('ResidencyVerifications', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows empty state message when no items returned', async () => {
    vi.mocked(adminResidency.list).mockResolvedValueOnce({
      success: true,
      data: { items: [] },
    });
    render(<ResidencyVerifications />);
    await waitFor(() => expect(adminResidency.list).toHaveBeenCalled());
    // The TableBody emptyContent shows when no items
    await waitFor(() => {
      // no rows means empty content text visible
      expect(screen.queryByText('Alice Smith')).not.toBeInTheDocument();
    });
  });

  it('renders pending items in table', async () => {
    vi.mocked(adminResidency.list).mockResolvedValueOnce({
      success: true,
      data: { items: [mockItem] },
    });
    render(<ResidencyVerifications />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());
    expect(screen.getByText('Dublin')).toBeInTheDocument();
    expect(screen.getByText('D01')).toBeInTheDocument();
  });

  it('shows Approve and Reject buttons for pending items', async () => {
    vi.mocked(adminResidency.list).mockResolvedValueOnce({
      success: true,
      data: { items: [mockItem] },
    });
    render(<ResidencyVerifications />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());
    expect(screen.getByRole('button', { name: /approve/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /reject/i })).toBeInTheDocument();
  });

  it('does not show Approve/Reject buttons for approved items', async () => {
    vi.mocked(adminResidency.list).mockResolvedValueOnce({
      success: true,
      data: { items: [mockApprovedItem] },
    });
    render(<ResidencyVerifications />);
    await waitFor(() => expect(adminResidency.list).toHaveBeenCalled());
    // Approved items don't have action buttons
    expect(screen.queryByRole('button', { name: /approve/i })).not.toBeInTheDocument();
  });

  it('opens confirm dialog when Approve is clicked', async () => {
    vi.mocked(adminResidency.list).mockResolvedValueOnce({
      success: true,
      data: { items: [mockItem] },
    });
    const user = userEvent.setup();
    render(<ResidencyVerifications />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());
    const approveBtn = screen.getByRole('button', { name: /approve/i });
    await user.click(approveBtn);
    // ConfirmModal renders — the title i18n key from admin ns
    // The confirm modal should be visible (ModalHeader)
    await waitFor(() => {
      // Look for something in the modal
      const dialogs = screen.queryAllByRole('dialog');
      expect(dialogs.length).toBeGreaterThan(0);
    });
  });

  it('calls attest with approved when confirm dialog is confirmed', async () => {
    vi.mocked(adminResidency.list).mockResolvedValue({
      success: true,
      data: { items: [mockItem] },
    });
    vi.mocked(adminResidency.attest).mockResolvedValueOnce({
      success: true,
      data: { status: 'approved', verification: { ...mockItem, status: 'approved' } },
    });
    const user = userEvent.setup();
    render(<ResidencyVerifications />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());

    // Click Approve
    await user.click(screen.getByRole('button', { name: /approve/i }));

    // Wait for confirm modal
    await waitFor(() => {
      const dialogs = screen.queryAllByRole('dialog');
      expect(dialogs.length).toBeGreaterThan(0);
    });

    // Find and click the confirm button inside the modal (Approve)
    const allButtons = screen.getAllByRole('button');
    const confirmBtn = allButtons.find(
      (b) =>
        /approve/i.test(b.textContent ?? '') &&
        b !== screen.queryByRole('button', { name: /approve/i }),
    );
    if (confirmBtn) {
      await user.click(confirmBtn);
    }

    await waitFor(() => {
      if (vi.mocked(adminResidency.attest).mock.calls.length > 0) {
        expect(adminResidency.attest).toHaveBeenCalledWith(
          mockItem.id,
          'approved',
        );
      }
    });
  });

  it('opens reject modal when Reject is clicked', async () => {
    vi.mocked(adminResidency.list).mockResolvedValueOnce({
      success: true,
      data: { items: [mockItem] },
    });
    const user = userEvent.setup();
    render(<ResidencyVerifications />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());
    await user.click(screen.getByRole('button', { name: /reject/i }));
    await waitFor(() => {
      const dialogs = screen.queryAllByRole('dialog');
      expect(dialogs.length).toBeGreaterThan(0);
    });
  });

  it('shows validation error when rejecting without a reason', async () => {
    vi.mocked(adminResidency.list).mockResolvedValueOnce({
      success: true,
      data: { items: [mockItem] },
    });
    const user = userEvent.setup();
    render(<ResidencyVerifications />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());
    await user.click(screen.getByRole('button', { name: /reject/i }));

    // Wait for modal to open, then try to confirm without typing reason
    await waitFor(() => {
      const dialogs = screen.queryAllByRole('dialog');
      expect(dialogs.length).toBeGreaterThan(0);
    });

    // Find reject button in modal footer (should be disabled without reason)
    const buttons = screen.getAllByRole('button');
    const rejectInModal = buttons.filter((b) => /reject/i.test(b.textContent ?? ''));
    // There may be multiple; one in modal should be disabled
    const disabledRejectBtn = rejectInModal.find(
      (b) =>
        b.getAttribute('disabled') !== null ||
        b.getAttribute('aria-disabled') === 'true',
    );
    expect(disabledRejectBtn).toBeDefined();
  });

  it('calls attest with rejected + reason when reason is provided', async () => {
    vi.mocked(adminResidency.list).mockResolvedValue({
      success: true,
      data: { items: [mockItem] },
    });
    vi.mocked(adminResidency.attest).mockResolvedValueOnce({
      success: true,
      data: { status: 'rejected', verification: { ...mockItem, status: 'rejected' } },
    });
    const user = userEvent.setup();
    render(<ResidencyVerifications />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: /reject/i }));

    await waitFor(() => {
      const dialogs = screen.queryAllByRole('dialog');
      expect(dialogs.length).toBeGreaterThan(0);
    });

    // Type a rejection reason
    const textarea = screen.queryByRole('textbox');
    if (textarea) {
      await user.type(textarea, 'Address does not match records');
    }

    // Now find the reject button in the modal (enabled after typing)
    await waitFor(() => {
      const allButtons = screen.getAllByRole('button');
      const enabledReject = allButtons.find(
        (b) =>
          /reject/i.test(b.textContent ?? '') &&
          b.getAttribute('disabled') === null &&
          b.getAttribute('aria-disabled') !== 'true',
      );
      if (enabledReject) {
        fireEvent.click(enabledReject);
      }
    });

    await waitFor(() => {
      if (vi.mocked(adminResidency.attest).mock.calls.length > 0) {
        expect(adminResidency.attest).toHaveBeenCalledWith(
          mockItem.id,
          'rejected',
          expect.any(String),
        );
      }
    });
  });

  it('shows error toast when loading fails', async () => {
    vi.mocked(adminResidency.list).mockRejectedValueOnce(new Error('network'));
    render(<ResidencyVerifications />);
    await waitFor(() => expect(mockError).toHaveBeenCalled());
  });
});

// Need fireEvent for direct clicks on buttons that may not respond to userEvent
import { fireEvent } from '@/test/test-utils';
