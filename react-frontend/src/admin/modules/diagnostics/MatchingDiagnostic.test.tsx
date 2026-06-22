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
const mockGetMatchingStats = vi.hoisted(() => vi.fn());
const mockDiagnoseUser = vi.hoisted(() => vi.fn());
const mockDiagnoseListing = vi.hoisted(() => vi.fn());
const mockToastError = vi.hoisted(() => vi.fn());

vi.mock('../../api/adminApi', () => ({
  adminDiagnostics: {
    getMatchingStats: mockGetMatchingStats,
    diagnoseUser: mockDiagnoseUser,
    diagnoseListing: mockDiagnoseListing,
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

// MatchingDiagnostic uses useAdminPageMeta which reads AdminMetaContext.
// The context is optional (hook gracefully handles null context).
vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { MatchingDiagnostic } from './MatchingDiagnostic';

const ENGINE_STATUS = {
  overview: {
    total_matches_today: 120,
    cache_entries: 500,
    avg_match_score: 73.4,
  },
};

const USER_DIAG_RESULT = {
  user_id: 42,
  top_matches: [{ listing_id: 1, score: 0.9 }],
};

const LISTING_DIAG_RESULT = {
  listing_id: 105,
  matched_users: [{ user_id: 7, score: 0.85 }],
};

describe('MatchingDiagnostic — engine status', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner for engine status on mount', () => {
    mockGetMatchingStats.mockReturnValue(new Promise(() => {}));
    render(<MatchingDiagnostic />);

    const statusEls = screen.getAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  it('renders engine overview stats after successful load', async () => {
    mockGetMatchingStats.mockResolvedValue({ success: true, data: ENGINE_STATUS });
    render(<MatchingDiagnostic />);

    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    expect(screen.getByText('120')).toBeInTheDocument();
    expect(screen.getByText('500')).toBeInTheDocument();
    expect(screen.getByText(/73\.4%/)).toBeInTheDocument();
  });

  it('shows "--" placeholders when overview is undefined', async () => {
    mockGetMatchingStats.mockResolvedValue({ success: true, data: {} });
    render(<MatchingDiagnostic />);

    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    const dashes = screen.getAllByText('--');
    expect(dashes.length).toBeGreaterThan(0);
  });

  it('calls toast.error when engine status fetch fails', async () => {
    mockGetMatchingStats.mockRejectedValue(new Error('500'));
    render(<MatchingDiagnostic />);

    await waitFor(() => {
      expect(mockToastError).toHaveBeenCalled();
    });
  });
});

describe('MatchingDiagnostic — diagnose user', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetMatchingStats.mockResolvedValue({ success: true, data: ENGINE_STATUS });
  });

  it('Diagnose button is disabled when user ID field is empty', async () => {
    render(<MatchingDiagnostic />);
    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    const diagnoseButtons = screen.getAllByRole('button', { name: /diagnose/i });
    // First Diagnose button belongs to the user section
    expect(diagnoseButtons[0]).toBeDisabled();
  });

  it('calls diagnoseUser with numeric ID and renders JSON result', async () => {
    const user = userEvent.setup();
    mockDiagnoseUser.mockResolvedValue({ success: true, data: USER_DIAG_RESULT });
    render(<MatchingDiagnostic />);

    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    const userIdInput = screen.getByLabelText(/user id/i);
    await user.click(userIdInput);
    await user.type(userIdInput, '42');

    const diagnoseButtons = screen.getAllByRole('button', { name: /diagnose/i });
    await user.click(diagnoseButtons[0]);

    await waitFor(() => {
      expect(mockDiagnoseUser).toHaveBeenCalledWith(42);
    });

    // JSON result rendered in a <pre>
    await waitFor(() => {
      expect(screen.getByText(/user_id/)).toBeInTheDocument();
    });
  });

  it('shows error toast when diagnoseUser fails', async () => {
    const user = userEvent.setup();
    mockDiagnoseUser.mockRejectedValue(new Error('not found'));
    render(<MatchingDiagnostic />);

    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    const userIdInput = screen.getByLabelText(/user id/i);
    await user.click(userIdInput);
    await user.type(userIdInput, '99');

    const diagnoseButtons = screen.getAllByRole('button', { name: /diagnose/i });
    await user.click(diagnoseButtons[0]);

    await waitFor(() => expect(mockToastError).toHaveBeenCalled());
  });

  it('shows toast.error when diagnoseUser returns success=false', async () => {
    const user = userEvent.setup();
    mockDiagnoseUser.mockResolvedValue({ success: false, data: null });
    render(<MatchingDiagnostic />);

    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    const userIdInput = screen.getByLabelText(/user id/i);
    await user.click(userIdInput);
    await user.type(userIdInput, '1');

    const diagnoseButtons = screen.getAllByRole('button', { name: /diagnose/i });
    await user.click(diagnoseButtons[0]);

    await waitFor(() => expect(mockToastError).toHaveBeenCalled());
  });
});

describe('MatchingDiagnostic — diagnose listing', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetMatchingStats.mockResolvedValue({ success: true, data: ENGINE_STATUS });
  });

  it('Diagnose button for listing is disabled when listing ID is empty', async () => {
    render(<MatchingDiagnostic />);
    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    const diagnoseButtons = screen.getAllByRole('button', { name: /diagnose/i });
    // Second button belongs to listing section
    expect(diagnoseButtons[1]).toBeDisabled();
  });

  it('calls diagnoseListing and renders JSON result', async () => {
    const user = userEvent.setup();
    mockDiagnoseListing.mockResolvedValue({ success: true, data: LISTING_DIAG_RESULT });
    render(<MatchingDiagnostic />);

    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
      ).toBeUndefined();
    });

    const listingIdInput = screen.getByLabelText(/listing id/i);
    await user.click(listingIdInput);
    await user.type(listingIdInput, '105');

    const diagnoseButtons = screen.getAllByRole('button', { name: /diagnose/i });
    await user.click(diagnoseButtons[1]);

    await waitFor(() => {
      expect(mockDiagnoseListing).toHaveBeenCalledWith(105);
    });

    await waitFor(() => {
      expect(screen.getByText(/listing_id/)).toBeInTheDocument();
    });
  });
});
