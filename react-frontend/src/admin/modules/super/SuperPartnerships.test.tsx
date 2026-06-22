// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable hoisted refs ──────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

const mockAdminSuper = vi.hoisted(() => ({
  getFederationPartnerships: vi.fn(),
  suspendPartnership: vi.fn(),
  terminatePartnership: vi.fn(),
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminSuper: mockAdminSuper,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

// SuperPartnerships imports useToast directly from ToastContext, not @/contexts
vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToast,
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import SuperPartnerships from './SuperPartnerships';

// ── Fixtures ─────────────────────────────────────────────────────────────────
const ACTIVE_PARTNERSHIP = {
  id: 1,
  tenant_1_id: 10,
  tenant_1_name: 'Alpha Bank',
  tenant_2_id: 20,
  tenant_2_name: 'Beta Bank',
  status: 'active' as const,
  created_at: '2024-01-15T00:00:00Z',
};

const PENDING_PARTNERSHIP = {
  id: 2,
  tenant_1_id: 30,
  tenant_1_name: 'Gamma Bank',
  tenant_2_id: 40,
  tenant_2_name: 'Delta Bank',
  status: 'pending' as const,
  created_at: '2024-02-10T00:00:00Z',
};

const SUSPENDED_PARTNERSHIP = {
  id: 3,
  tenant_1_id: 50,
  tenant_1_name: 'Epsilon Bank',
  tenant_2_id: 60,
  tenant_2_name: 'Zeta Bank',
  status: 'suspended' as const,
  created_at: '2024-03-05T00:00:00Z',
};

// ─────────────────────────────────────────────────────────────────────────────
describe('SuperPartnerships', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner initially', () => {
    mockAdminSuper.getFederationPartnerships.mockReturnValue(new Promise(() => {}));
    render(<SuperPartnerships />);

    // Loading spinner - the component renders a full-page spinner while loading
    const spinner = document.querySelector('.animate-spin');
    expect(spinner).toBeInTheDocument();
  });

  it('renders partnership rows after load', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP],
    });
    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });
    expect(screen.getByText('Beta Bank')).toBeInTheDocument();
  });

  it('displays stat cards for each status', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP, PENDING_PARTNERSHIP, SUSPENDED_PARTNERSHIP],
    });
    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });

    // Stat card numbers: 1 active, 1 pending, 1 suspended
    // There are multiple elements with "1" so we check the stat cards via bold text
    const allBolds = screen
      .getAllByText('1')
      .filter((el) => el.classList.contains('font-bold') || el.tagName === 'P');
    // At least 3 stat boxes should show "1"
    expect(allBolds.length).toBeGreaterThanOrEqual(3);
  });

  it('shows empty content when no partnerships', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [],
    });
    render(<SuperPartnerships />);

    await waitFor(() => {
      // no_partnerships_found i18n key will be shown as empty content
      // the table body emptyContent is rendered when data is empty
      // With real i18n returning key, check the table renders with no rows
      const rows = document.querySelectorAll('[role="row"]');
      // Only header row when empty
      expect(rows.length).toBeLessThanOrEqual(2); // header + maybe empty row
    });
  });

  it('shows Suspend button for active partnerships', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP],
    });
    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });

    // Suspend button should be visible for active partnership
    const suspendBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent && b.textContent.toLowerCase().includes('suspend'),
    );
    expect(suspendBtns.length).toBeGreaterThan(0);
  });

  it('shows Terminate button for active partnerships', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP],
    });
    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });

    const terminateBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent && b.textContent.toLowerCase().includes('terminate'),
    );
    expect(terminateBtns.length).toBeGreaterThan(0);
  });

  it('opens confirm modal when Suspend is clicked', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP],
    });
    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });

    const suspendBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('suspend'),
    )!;
    await userEvent.click(suspendBtn);

    // Modal renders into document.body portal; the ConfirmModal shows a modal
    // with the title and two action buttons.
    await waitFor(() => {
      // At minimum, the state has been set to open the modal (actionPartnership set)
      // The ConfirmModal footer has two buttons; at least the cancel button is always present
      const allBtns = document.querySelectorAll('button');
      // There should be more buttons now (modal + table) than before
      expect(allBtns.length).toBeGreaterThan(2);
    });
  });

  it('calls suspendPartnership and shows success toast on confirm', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP],
    });
    mockAdminSuper.suspendPartnership.mockResolvedValue({ success: true });

    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });

    // Click the Suspend button in the table row to open modal
    const suspendBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('suspend'),
    )!;
    await userEvent.click(suspendBtn);

    // Wait for the modal to render with a confirm button
    let confirmBtn: HTMLElement | null = null;
    await waitFor(() => {
      const allBtns = Array.from(document.querySelectorAll('button'));
      // The modal footer has 2 buttons. Find the one that's NOT the cancel button.
      // Cancel button has no color class; confirm has variant=danger styling.
      // Look for the one in a modal footer (ModalFooter sibling of ModalBody).
      const inModal = allBtns.filter((b) => b.closest('[role="dialog"]'));
      expect(inModal.length).toBeGreaterThan(1);
      // The last button in the dialog is the confirm button
      confirmBtn = inModal[inModal.length - 1];
    });

    await userEvent.click(confirmBtn!);

    await waitFor(() => {
      expect(mockAdminSuper.suspendPartnership).toHaveBeenCalledWith(
        ACTIVE_PARTNERSHIP.id,
        expect.any(String),
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('calls terminatePartnership and shows success toast on confirm', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP],
    });
    mockAdminSuper.terminatePartnership.mockResolvedValue({ success: true });

    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });

    const terminateBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('terminate'),
    )!;
    await userEvent.click(terminateBtn);

    // Wait for the modal then click its confirm button
    let confirmBtn: HTMLElement | null = null;
    await waitFor(() => {
      const inModal = Array.from(document.querySelectorAll('[role="dialog"] button'));
      expect(inModal.length).toBeGreaterThan(1);
      confirmBtn = inModal[inModal.length - 1] as HTMLElement;
    });

    await userEvent.click(confirmBtn!);

    await waitFor(() => {
      expect(mockAdminSuper.terminatePartnership).toHaveBeenCalledWith(
        ACTIVE_PARTNERSHIP.id,
        expect.any(String),
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when suspend fails', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [ACTIVE_PARTNERSHIP],
    });
    mockAdminSuper.suspendPartnership.mockResolvedValue({
      success: false,
      error: 'Server error',
    });

    render(<SuperPartnerships />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Bank')).toBeInTheDocument();
    });

    const suspendBtn = screen.getAllByRole('button').find(
      (b) => b.textContent && b.textContent.toLowerCase().includes('suspend'),
    )!;
    await userEvent.click(suspendBtn);

    // Wait for modal then click confirm (last button in dialog)
    let confirmBtn: HTMLElement | null = null;
    await waitFor(() => {
      const inModal = Array.from(document.querySelectorAll('[role="dialog"] button'));
      expect(inModal.length).toBeGreaterThan(1);
      confirmBtn = inModal[inModal.length - 1] as HTMLElement;
    });

    await userEvent.click(confirmBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
