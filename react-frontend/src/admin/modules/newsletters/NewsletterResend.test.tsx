// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable toast ─────────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

// ── Mock adminNewsletters ─────────────────────────────────────────────────────
const mockGetResendInfo = vi.fn();
const mockResend = vi.fn();

vi.mock('../../api/adminApi', () => ({
  adminNewsletters: {
    getResendInfo: (...args: unknown[]) => mockGetResendInfo(...args),
    resend: (...args: unknown[]) => mockResend(...args),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

// ── Sample ResendInfo ─────────────────────────────────────────────────────────
const RESEND_INFO = {
  total_sent: 1000,
  total_opened: 400,
  total_clicked: 150,
  non_openers_count: 600,
  non_clickers_count: 850,
};

import { NewsletterResend } from './NewsletterResend';

const DEFAULT_PROPS = {
  isOpen: true,
  onClose: vi.fn(),
  newsletterId: 42,
  onSuccess: vi.fn(),
};

describe('NewsletterResend — loading', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetResendInfo.mockReturnValue(new Promise(() => {})); // pending
  });

  it('shows loading text while fetching resend info', () => {
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });

  it('Resend button is disabled while loading', () => {
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    // Queue Resend button should be disabled
    const btn = screen.getByRole('button', { name: /queue.+resend/i });
    expect(btn).toBeDisabled();
  });
});

describe('NewsletterResend — populated', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetResendInfo.mockResolvedValue({ success: true, data: RESEND_INFO });
    mockResend.mockResolvedValue({ success: true, data: { queued_count: 600 } });
  });

  it('renders stats from resend info', async () => {
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByText('1,000')).toBeInTheDocument(); // total_sent
      expect(screen.getByText('400')).toBeInTheDocument();  // total_opened
      expect(screen.getByText('150')).toBeInTheDocument();  // total_clicked
    });
  });

  it('renders both radio options', async () => {
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(screen.getByRole('radio', { name: /non.openers/i })).toBeInTheDocument();
      expect(screen.getByRole('radio', { name: /non.clickers/i })).toBeInTheDocument();
    });
  });

  it('calls resend API when Queue Resend button is pressed', async () => {
    const user = userEvent.setup();
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    await waitFor(() => expect(screen.getByText('1,000')).toBeInTheDocument());

    const sendBtn = screen.getByRole('button', { name: /queue.+resend/i });
    await user.click(sendBtn);

    await waitFor(() => {
      expect(mockResend).toHaveBeenCalledWith(
        42,
        expect.objectContaining({ target: 'non_openers' }),
      );
    });
  });

  it('shows success toast and calls onSuccess after resend', async () => {
    const user = userEvent.setup();
    const onSuccess = vi.fn();
    render(<NewsletterResend {...DEFAULT_PROPS} onSuccess={onSuccess} />);
    await waitFor(() => expect(screen.getByText('1,000')).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: /queue.+resend/i }));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
      expect(onSuccess).toHaveBeenCalled();
    });
  });

  it('shows error toast when resend API fails', async () => {
    mockResend.mockResolvedValueOnce({ success: false });
    const user = userEvent.setup();
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    await waitFor(() => expect(screen.getByText('1,000')).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: /queue.+resend/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls onClose when Cancel button pressed', async () => {
    const onClose = vi.fn();
    const user = userEvent.setup();
    render(<NewsletterResend {...DEFAULT_PROPS} onClose={onClose} />);
    await waitFor(() => expect(screen.getByText('1,000')).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: /cancel/i }));

    expect(onClose).toHaveBeenCalled();
  });
});

describe('NewsletterResend — zero recipients warning', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetResendInfo.mockResolvedValue({
      success: true,
      data: { ...RESEND_INFO, non_openers_count: 0 },
    });
  });

  it('shows no-recipients warning when count is zero', async () => {
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    await waitFor(() => {
      // Two elements match the warning: the heading and the description.
      const matches = screen.getAllByText(/no.+recipients/i);
      expect(matches.length).toBeGreaterThan(0);
    });
  });

  it('Queue Resend button is disabled when zero recipients', async () => {
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /queue.+resend/i });
      expect(btn).toBeDisabled();
    });
  });
});

describe('NewsletterResend — load error', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetResendInfo.mockRejectedValue(new Error('Server error'));
  });

  it('shows error toast on load failure', async () => {
    render(<NewsletterResend {...DEFAULT_PROPS} />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('NewsletterResend — closed modal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not fetch resend info when isOpen is false', () => {
    render(<NewsletterResend {...DEFAULT_PROPS} isOpen={false} />);
    expect(mockGetResendInfo).not.toHaveBeenCalled();
  });
});
