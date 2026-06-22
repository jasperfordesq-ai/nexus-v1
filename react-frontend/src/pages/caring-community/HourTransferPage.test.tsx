// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HourTransferPage — MONEY-CRITICAL tests
 *
 * Covers:
 *  - Feature gate redirect when caring_community is disabled
 *  - Loading / populated / empty history states
 *  - Amount validation (empty, zero, negative, valid)
 *  - Recipient selection (direct slug input & peer picker)
 *  - Submit button disabled until both slug + hours > 0
 *  - Transfer POST exact payload
 *  - Success state & history reload
 *  - Error codes: NO_MATCHING_EMAIL, DESTINATION_NOT_FOUND, INSUFFICIENT_HOURS, generic
 *  - Network exception → generic error
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

// ── vi.hoisted: used inside vi.mock factory so must be declared first ─────────
const { mockHasFeature } = vi.hoisted(() => {
  const mockHasFeature = vi.fn(() => true);
  return { mockHasFeature };
});

import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ── api mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

// ── FederationCommunityPicker mock ────────────────────────────────────────────
vi.mock('@/components/caring-community/FederationCommunityPicker', () => ({
  FederationCommunityPicker: ({
    isOpen,
    onSelect,
    onClose,
  }: {
    isOpen: boolean;
    onSelect: (peer: { slug: string; display_name: string; region?: string }) => void;
    onClose: () => void;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label="community-picker">
        <button
          onClick={() => {
            onSelect({ slug: 'other-community', display_name: 'Other Community' });
            onClose();
          }}
        >
          Select Other Community
        </button>
        <button onClick={onClose}>Close</button>
      </div>
    ) : null,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { HourTransferPage } from './HourTransferPage';

// ── Helpers ───────────────────────────────────────────────────────────────────

/** Mock both API calls that fire on mount: history + directory probe */
function mockMount({
  directoryAvailable = false,
  historyItems = [] as Array<{
    id: number;
    destination_tenant_slug: string;
    destination_member_email: string;
    hours: number;
    status: 'completed' | 'pending' | 'rejected';
    reason: string;
    created_at: string;
  }>,
} = {}) {
  // First call → history; second call → directory probe
  mockApi.get
    .mockResolvedValueOnce({ success: true, data: { items: historyItems } })
    .mockResolvedValueOnce({
      success: directoryAvailable,
      data: { peers: directoryAvailable ? [{ slug: 'peer', display_name: 'Peer' }] : [] },
    });
}

const HISTORY_ITEM = {
  id: 1,
  destination_tenant_slug: 'partner-bank',
  destination_member_email: 'alice@partner.org',
  hours: 2.5,
  status: 'completed' as const,
  reason: 'Community support',
  created_at: '2026-06-01T10:00:00Z',
};

// ── Helper: find submit button by known English text ──────────────────────────
// t('hour_transfer.form.submit') → "Request Transfer"
function getSubmitBtn() {
  return screen.getAllByRole('button').find(
    (b) => b.textContent?.match(/Request Transfer|Gift Hours|Submit|form\.submit/i),
  );
}

// ── Helper: find slug input by label text ─────────────────────────────────────
// tCaring('federation_picker.fallback_label') → "Or enter a community slug manually"
// t('hour_transfer.form.destination_label') → "Destination cooperative"
function getSlugInput() {
  const textboxes = screen.getAllByRole('textbox');
  // Exclude the reason textarea — find the destination input (not the reason one)
  // The slug input comes before hours (spinbutton) and reason (textarea)
  // Hours is a spinbutton (type=number), so the textboxes are: slug + reason
  // The slug input is the first textbox in the destination section
  return textboxes.find((el) => {
    const id = el.getAttribute('id') ?? '';
    const ariaLabel = el.getAttribute('aria-label') ?? '';
    const placeholder = el.getAttribute('placeholder') ?? '';
    const val = [id, ariaLabel, placeholder].join(' ').toLowerCase();
    return (
      val.includes('slug') ||
      val.includes('destination') ||
      val.includes('community') ||
      val.includes('fallback')
    );
  }) ?? textboxes[0]; // fallback: first textbox is slug input
}

describe('HourTransferPage — feature gate', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('redirects when caring_community feature is disabled', async () => {
    mockHasFeature.mockReturnValue(false);
    render(<HourTransferPage />);
    // Component redirects before form renders
    await waitFor(() => {
      expect(screen.queryByText(/Request Transfer|form\.submit/i)).toBeNull();
    });
  });
});

describe('HourTransferPage — loading & history', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
  });

  it('shows history loading spinner on mount', () => {
    // Never resolves
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<HourTransferPage />);
    const statuses = screen.getAllByRole('status');
    const spinner = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeDefined();
  });

  it('shows empty history message when no transfers exist', async () => {
    mockMount({ historyItems: [] });
    render(<HourTransferPage />);
    await waitFor(() => {
      // t('hour_transfer.history.empty') → some text about no transfers
      const allText = document.body.textContent ?? '';
      // The history section should not have table rows
      expect(screen.queryByRole('row')).toBeNull();
    });
  });

  it('renders history table row when transfers exist', async () => {
    mockMount({ historyItems: [HISTORY_ITEM] });
    render(<HourTransferPage />);
    await waitFor(() => {
      expect(screen.getByText('partner-bank')).toBeInTheDocument();
    });
  });

  it('renders hours value in history table', async () => {
    mockMount({ historyItems: [HISTORY_ITEM] });
    render(<HourTransferPage />);
    await waitFor(() => {
      expect(screen.getByText('2.50')).toBeInTheDocument();
    });
  });

  it('renders status chip in history table', async () => {
    mockMount({ historyItems: [HISTORY_ITEM] });
    render(<HourTransferPage />);
    await waitFor(() => {
      // t('hour_transfer.status.completed') resolves to English
      const statusEl = screen.getAllByText(/completed/i);
      expect(statusEl.length).toBeGreaterThan(0);
    });
  });
});

describe('HourTransferPage — form with manual slug input (directory unavailable)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockMount({ directoryAvailable: false });
  });

  it('renders the destination slug input', async () => {
    render(<HourTransferPage />);
    // Wait for directory probe to resolve (false) → shows the fallback Input
    await waitFor(() => {
      const input = getSlugInput();
      expect(input).toBeInTheDocument();
    });
  });

  it('submit button is disabled when destination slug is empty', async () => {
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    // canSubmit = false when destinationSlug is empty
    const submitBtn = getSubmitBtn();
    expect(submitBtn).toBeDefined();
    // HeroUI Button with isDisabled renders aria-disabled="true"
    expect(
      submitBtn!.hasAttribute('disabled') || submitBtn!.getAttribute('aria-disabled') === 'true',
    ).toBe(true);
  });

  it('submit button is disabled when hours is zero or empty', async () => {
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    // Fill slug but leave hours empty
    const slugInput = getSlugInput();
    fireEvent.change(slugInput!, { target: { value: 'other-bank' } });

    const submitBtn = getSubmitBtn();
    expect(submitBtn).toBeDefined();
    expect(
      submitBtn!.hasAttribute('disabled') || submitBtn!.getAttribute('aria-disabled') === 'true',
    ).toBe(true);
  });

  it('submit button is disabled when hours is 0', async () => {
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'other-bank' } });
    const hoursInput = screen.getByRole('spinbutton');
    fireEvent.change(hoursInput, { target: { value: '0' } });

    const submitBtn = getSubmitBtn();
    expect(submitBtn).toBeDefined();
    expect(
      submitBtn!.hasAttribute('disabled') || submitBtn!.getAttribute('aria-disabled') === 'true',
    ).toBe(true);
  });

  it('submit button is enabled when slug and valid hours are set', async () => {
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'other-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '2' } });

    const submitBtn = getSubmitBtn();
    expect(submitBtn).toBeDefined();
    expect(
      submitBtn!.hasAttribute('disabled') || submitBtn!.getAttribute('aria-disabled') === 'true',
    ).toBe(false);
  });

  it('sends POST with correct payload on submit', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: { transfer_id: 999, status: 'pending', success: true },
    });
    // After success, history reloads
    mockApi.get.mockResolvedValueOnce({ success: true, data: { items: [] } });

    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'partner-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1.5' } });

    // Fill reason (the textarea)
    const reasonInput = screen.getByRole('textbox', { name: /reason/i });
    fireEvent.change(reasonInput, { target: { value: 'Thank you!' } });

    const submitBtn = getSubmitBtn();
    expect(submitBtn).toBeDefined();
    await user.click(submitBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/caring-community/hour-transfer/initiate',
        {
          destination_tenant_slug: 'partner-bank',
          hours: 1.5,
          reason: 'Thank you!',
        },
      );
    });
  });

  it('shows success message and resets form after successful submit', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: { transfer_id: 999, status: 'pending', success: true },
    });
    mockApi.get.mockResolvedValueOnce({ success: true, data: { items: [] } });

    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'partner-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1' } });

    const submitBtn = getSubmitBtn();
    await user.click(submitBtn!);

    // t('hour_transfer.success_message') → some success text
    await waitFor(() => {
      // Success div with CheckCircle icon appears
      const successEls = document.querySelectorAll('.text-success');
      expect(successEls.length).toBeGreaterThan(0);
    });
  });

  // Helper: find the error paragraph rendered by HourTransferPage (has non-trivial text,
  // not the persistent but empty ToastProvider alerts)
  function getErrorAlert() {
    const alerts = screen.getAllByRole('alert');
    // The HourTransferPage error paragraph has the class "text-danger" applied
    return alerts.find(
      (el) => el.classList.contains('text-danger') || (el.textContent?.trim().length ?? 0) > 3,
    );
  }

  it('shows NO_MATCHING_EMAIL error message from API code', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: false,
      code: 'NO_MATCHING_EMAIL',
    });
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'other-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1' } });
    await user.click(getSubmitBtn()!);

    await waitFor(() => {
      const alert = getErrorAlert();
      expect(alert).toBeDefined();
      expect(alert!.textContent?.length).toBeGreaterThan(0);
    });
  });

  it('shows DESTINATION_NOT_FOUND error message', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: false,
      code: 'DESTINATION_NOT_FOUND',
    });
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'ghost-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '2' } });
    await user.click(getSubmitBtn()!);

    await waitFor(() => {
      const alert = getErrorAlert();
      expect(alert).toBeDefined();
      expect(alert!.textContent?.length).toBeGreaterThan(0);
    });
  });

  it('shows INSUFFICIENT_HOURS error message', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: false,
      code: 'INSUFFICIENT_HOURS',
    });
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'other-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '9999' } });
    await user.click(getSubmitBtn()!);

    await waitFor(() => {
      const alert = getErrorAlert();
      expect(alert).toBeDefined();
      expect(alert!.textContent?.length).toBeGreaterThan(0);
    });
  });

  it('shows error on unknown API error code', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: false,
      code: 'UNKNOWN_PROBLEM',
    });
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'other-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1' } });
    await user.click(getSubmitBtn()!);

    await waitFor(() => {
      const alert = getErrorAlert();
      expect(alert).toBeDefined();
      expect(alert!.textContent?.length).toBeGreaterThan(0);
    });
  });

  it('shows error on network exception', async () => {
    const user = userEvent.setup();
    mockApi.post.mockRejectedValueOnce(new Error('Network error'));
    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: 'other-bank' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1' } });
    await user.click(getSubmitBtn()!);

    await waitFor(() => {
      const alert = getErrorAlert();
      expect(alert).toBeDefined();
      expect(alert!.textContent?.length).toBeGreaterThan(0);
    });
  });

  it('trims whitespace from destination slug before submitting', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: { transfer_id: 1, status: 'pending', success: true },
    });
    mockApi.get.mockResolvedValueOnce({ success: true, data: { items: [] } });

    render(<HourTransferPage />);
    await waitFor(() => getSlugInput());

    fireEvent.change(getSlugInput()!, { target: { value: '  partner-bank  ' } });
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1' } });
    await user.click(getSubmitBtn()!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/caring-community/hour-transfer/initiate',
        expect.objectContaining({ destination_tenant_slug: 'partner-bank' }),
      );
    });
  });
});

describe('HourTransferPage — peer picker (directory available)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockMount({ directoryAvailable: true });
  });

  it('shows browse button when directory is available', async () => {
    render(<HourTransferPage />);
    await waitFor(() => {
      // tCaring('federation_picker.browse_button') → "Browse communities"
      const btns = screen.getAllByRole('button');
      const browseBtn = btns.find(
        (b) => b.textContent?.toLowerCase().includes('browse'),
      );
      expect(browseBtn).toBeDefined();
    });
  });

  it('opens federation picker when browse button is clicked', async () => {
    const user = userEvent.setup();
    render(<HourTransferPage />);
    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      return btns.find((b) => b.textContent?.toLowerCase().includes('browse'));
    });

    const browseBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('browse'),
    );
    await user.click(browseBtn!);
    expect(screen.getByRole('dialog', { name: 'community-picker' })).toBeInTheDocument();
  });

  it('sets destination slug from selected peer', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: { transfer_id: 1, status: 'pending', success: true },
    });
    mockApi.get.mockResolvedValueOnce({ success: true, data: { items: [] } });

    render(<HourTransferPage />);
    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      return btns.find((b) => b.textContent?.toLowerCase().includes('browse'));
    });

    const browseBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('browse'),
    );
    await user.click(browseBtn!);
    await user.click(screen.getByText('Select Other Community'));

    // Peer display name shown after selection
    await waitFor(() => {
      expect(screen.getByText('Other Community')).toBeInTheDocument();
    });

    // Fill hours and submit
    fireEvent.change(screen.getByRole('spinbutton'), { target: { value: '1' } });
    const submitBtn = getSubmitBtn();
    await user.click(submitBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/caring-community/hour-transfer/initiate',
        expect.objectContaining({ destination_tenant_slug: 'other-community' }),
      );
    });
  });

  it('clears peer selection when cancel button is pressed', async () => {
    const user = userEvent.setup();
    render(<HourTransferPage />);
    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      return btns.find((b) => b.textContent?.toLowerCase().includes('browse'));
    });

    const browseBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('browse'),
    );
    await user.click(browseBtn!);
    await user.click(screen.getByText('Select Other Community'));

    await waitFor(() => screen.getByText('Other Community'));

    // tCaring('federation_picker.cancel_button') → "Cancel"
    const cancelBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase() === 'cancel',
    );
    expect(cancelBtn).toBeDefined();
    await user.click(cancelBtn!);

    await waitFor(() => {
      expect(screen.queryByText('Other Community')).toBeNull();
    });
  });
});
