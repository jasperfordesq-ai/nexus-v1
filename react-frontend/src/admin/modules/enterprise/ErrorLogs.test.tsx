// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable hoisted mock data ──────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));

const mockGetLogs = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

vi.mock('../../api/adminApi', () => ({
  adminEnterprise: {
    getLogs: mockGetLogs,
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// usePageTitle
vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

import { ErrorLogs } from './ErrorLogs';

// ── Test helpers ──────────────────────────────────────────────────────────────
const SAMPLE_LOGS = [
  {
    id: 10,
    action: 'auth.failed',
    description: 'Login failed for user test@test.com',
    user_name: 'Alice',
    ip_address: '192.168.1.1',
    created_at: '2024-03-01T12:00:00Z',
  },
  {
    id: 11,
    action: 'permission.denied',
    description: 'Unauthorized access attempt',
    user_name: null,
    ip_address: null,
    created_at: '2024-03-02T08:30:00Z',
  },
];

const paginated = (data: typeof SAMPLE_LOGS) => ({
  success: true,
  data: { data, meta: { total: data.length } },
});

describe('ErrorLogs', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetLogs.mockResolvedValue(paginated([]));
  });

  // ── Loading state ─────────────────────────────────────────────────────────
  it('shows a loading spinner while fetching', () => {
    mockGetLogs.mockReturnValue(new Promise(() => {}));
    render(<ErrorLogs />);

    const spinners = screen.getAllByRole('status');
    const busyEl = spinners.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  // ── Populated state ───────────────────────────────────────────────────────
  it('renders log rows after successful fetch (array format)', async () => {
    mockGetLogs.mockResolvedValue({ success: true, data: SAMPLE_LOGS });
    render(<ErrorLogs />);

    await waitFor(() => {
      expect(screen.getByText('auth.failed')).toBeInTheDocument();
    });
    expect(screen.getByText('permission.denied')).toBeInTheDocument();
  });

  it('renders log rows from paginated response (data.data format)', async () => {
    mockGetLogs.mockResolvedValue(paginated(SAMPLE_LOGS));
    render(<ErrorLogs />);

    await waitFor(() => {
      expect(screen.getByText('auth.failed')).toBeInTheDocument();
    });
  });

  it('renders ip address in monospace when present', async () => {
    mockGetLogs.mockResolvedValue({ success: true, data: SAMPLE_LOGS });
    render(<ErrorLogs />);

    await waitFor(() => {
      expect(screen.getByText('192.168.1.1')).toBeInTheDocument();
    });
  });

  it('renders --- placeholder for null user_name', async () => {
    mockGetLogs.mockResolvedValue({ success: true, data: SAMPLE_LOGS });
    render(<ErrorLogs />);

    await waitFor(() => {
      const dashes = screen.getAllByText('---');
      expect(dashes.length).toBeGreaterThan(0);
    });
  });

  // ── Empty state ───────────────────────────────────────────────────────────
  it('removes loading indicator when empty list is returned', async () => {
    mockGetLogs.mockResolvedValue(paginated([]));
    render(<ErrorLogs />);

    await waitFor(() => {
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });

    expect(screen.queryByText('auth.failed')).not.toBeInTheDocument();
  });

  // ── Error state ───────────────────────────────────────────────────────────
  it('calls toast.error when API throws', async () => {
    mockGetLogs.mockRejectedValue(new Error('500 Server Error'));
    render(<ErrorLogs />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Refresh action ────────────────────────────────────────────────────────
  it('re-fetches when Refresh button is clicked', async () => {
    const user = userEvent.setup();
    mockGetLogs.mockResolvedValue(paginated([]));
    render(<ErrorLogs />);

    await waitFor(() => {
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });

    const btn = screen.getByRole('button', { name: /refresh/i });
    await user.click(btn);

    await waitFor(() => {
      expect(mockGetLogs).toHaveBeenCalledTimes(2);
    });
  });
});
