// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';

// ---------------------------------------------------------------------------
// Module mocks
// ---------------------------------------------------------------------------

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

import GuardianConsentModal from './GuardianConsentModal';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function renderModal(overrides: Partial<{
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  onClose: () => void;
  opportunityId?: number;
}> = {}) {
  const props = {
    isOpen: true,
    onOpenChange: vi.fn(),
    onClose: vi.fn(),
    ...overrides,
  };
  return { ...render(<GuardianConsentModal {...props} />), ...props };
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

describe('GuardianConsentModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not render when isOpen=false', () => {
    // No api.get call expected; modal is closed
    renderModal({ isOpen: false });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('shows a spinner on initial load while checking consent records', async () => {
    // Keep api.get pending to stay in loading stage
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    renderModal();
    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());
    // In the 'loading' stage the component renders a Spinner inside the modal body.
    // The Spinner renders as a div with a data-slot attribute. The form inputs are NOT
    // visible yet (those appear in the 'form' stage).
    expect(screen.queryAllByRole('textbox').length).toBe(0);
  });

  it('shows the consent form when no pending consent record exists', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    renderModal();

    await waitFor(() => {
      // Form inputs should be visible in the 'form' stage
      expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(2);
    });
  });

  it('shows the pending stage when a pending consent record exists', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: [{
        id: 5,
        guardian_email: 'parent@example.com',
        status: 'pending',
        expires_at: null,
      }],
    });
    renderModal();

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
    // The pending email should appear somewhere in the modal body
    await waitFor(() => {
      expect(screen.getByText(/parent@example\.com/)).toBeInTheDocument();
    });
  });

  it('falls back to the form stage when API get throws', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('network'));
    renderModal();

    await waitFor(() => {
      // Form stage: at least 2 text inputs (guardian name + email)
      expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(2);
    });
  });

  it('calls GET /v2/volunteering/guardian-consents on open', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    renderModal();

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/volunteering/guardian-consents');
    });
  });

  it('calls POST with guardian details when form is submitted', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    vi.mocked(api.post).mockResolvedValueOnce({ success: true, data: {} });

    renderModal();

    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(2);
    });

    // Fill in required fields
    const textInputs = screen.getAllByRole('textbox');
    // First labelled input for guardian name
    fireEvent.change(textInputs[0], { target: { value: 'Jane Doe' } });
    // Second labelled input for guardian email
    fireEvent.change(textInputs[1], { target: { value: 'jane@example.com' } });

    // Click send/submit button
    const submitBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/send|request/i),
    );
    if (submitBtn) fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/volunteering/guardian-consents',
        expect.objectContaining({
          guardian_name: 'Jane Doe',
          guardian_email: 'jane@example.com',
        }),
      );
    });
  });

  it('transitions to sent stage after successful submission', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    vi.mocked(api.post).mockResolvedValueOnce({ success: true, data: {} });

    renderModal();

    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(2);
    });

    const textInputs = screen.getAllByRole('textbox');
    fireEvent.change(textInputs[0], { target: { value: 'Jane Doe' } });
    fireEvent.change(textInputs[1], { target: { value: 'jane@example.com' } });

    const submitBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/send|request/i),
    );
    if (submitBtn) fireEvent.click(submitBtn);

    await waitFor(() => {
      // After successful post the stage becomes 'sent'; email appears in the body
      expect(screen.getByText(/jane@example\.com/)).toBeInTheDocument();
    });
  });

  it('shows error toast when POST fails', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Email invalid' });

    renderModal();

    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(2);
    });

    const textInputs = screen.getAllByRole('textbox');
    fireEvent.change(textInputs[0], { target: { value: 'Jane Doe' } });
    fireEvent.change(textInputs[1], { target: { value: 'jane@example.com' } });

    const submitBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/send|request/i),
    );
    if (submitBtn) fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when POST throws', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    vi.mocked(api.post).mockRejectedValueOnce(new Error('network error'));

    renderModal();

    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(2);
    });

    const textInputs = screen.getAllByRole('textbox');
    fireEvent.change(textInputs[0], { target: { value: 'Jane Doe' } });
    fireEvent.change(textInputs[1], { target: { value: 'jane@example.com' } });

    const submitBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/send|request/i),
    );
    if (submitBtn) fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('passes opportunityId in the POST payload when provided', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    vi.mocked(api.post).mockResolvedValueOnce({ success: true, data: {} });

    renderModal({ opportunityId: 77 });

    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThanOrEqual(2);
    });

    const textInputs = screen.getAllByRole('textbox');
    fireEvent.change(textInputs[0], { target: { value: 'Dad Smith' } });
    fireEvent.change(textInputs[1], { target: { value: 'dad@smith.com' } });

    const submitBtn = screen.getAllByRole('button').find(
      (b) => !b.hasAttribute('disabled') && b.textContent?.match(/send|request/i),
    );
    if (submitBtn) fireEvent.click(submitBtn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/volunteering/guardian-consents',
        expect.objectContaining({ opportunity_id: 77 }),
      );
    });
  });

  it('calls onClose when the close button is pressed', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: [] });
    const onClose = vi.fn();
    renderModal({ onClose });

    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    const closeBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.match(/close/i),
    );
    if (closeBtn) fireEvent.click(closeBtn);

    await waitFor(() => {
      expect(onClose).toHaveBeenCalled();
    });
  });
});
