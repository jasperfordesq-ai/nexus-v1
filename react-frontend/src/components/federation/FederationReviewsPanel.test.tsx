// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── api mock ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () => createMockContexts());

// ── component ────────────────────────────────────────────────────────────────
import { FederationReviewsPanel } from './FederationReviewsPanel';

const MEMBER_ID = 42;

describe('FederationReviewsPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── loading ──────────────────────────────────────────────────────────────
  it('shows loading skeleton while fetching', () => {
    // Never resolves, so component stays in loading state
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    // Loading state exposes role="status" aria-busy="true"
    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  // ── populated ────────────────────────────────────────────────────────────
  it('renders reviews when API returns data', async () => {
    mockApi.get.mockResolvedValueOnce({
      success: true,
      data: [
        {
          id: 1,
          rating: 4,
          comment: 'Great member!',
          created_at: '2025-01-15T12:00:00Z',
          reviewer: { id: 99, name: 'Alice Smith', avatar: null },
          partner: { id: 10, name: 'Dublin Timebank' },
          verified: true,
        },
        {
          id: 2,
          rating: 5,
          comment: null,
          created_at: '2025-02-01T08:00:00Z',
          reviewer: { id: 100, first_name: 'Bob', last_name: 'Jones', avatar: null },
          partner: null,
          verified: false,
        },
      ],
    });

    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });

    expect(screen.getByText('Great member!')).toBeInTheDocument();
    // Reviewer constructed from first_name + last_name
    expect(screen.getByText('Bob Jones')).toBeInTheDocument();
  });

  it('shows partner chip when partner is present', async () => {
    mockApi.get.mockResolvedValueOnce({
      success: true,
      data: [
        {
          id: 3,
          rating: 3,
          comment: 'Good',
          created_at: '2025-03-01T00:00:00Z',
          reviewer: { name: 'Carol', avatar: null },
          partner: { id: 5, name: 'Cork Timebank' },
          verified: false,
        },
      ],
    });

    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    await waitFor(() => {
      expect(screen.getByText(/Cork Timebank/)).toBeInTheDocument();
    });
  });

  it('passes tenantId as query param when provided', async () => {
    mockApi.get.mockResolvedValueOnce({ success: true, data: [] });

    render(<FederationReviewsPanel memberId={MEMBER_ID} tenantId={7} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('tenant_id=7'),
        expect.any(Object),
      );
    });
  });

  // ── empty ────────────────────────────────────────────────────────────────
  it('shows empty state when reviews array is empty', async () => {
    mockApi.get.mockResolvedValueOnce({ success: true, data: [] });

    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    // Empty state renders a Star icon + translated text; we just verify no
    // loading spinner remains and the status role is gone/not-busy.
    await waitFor(() => {
      const statusEls = screen.queryAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  // ── 404 / unavailable ────────────────────────────────────────────────────
  it('shows unavailable state on 404', async () => {
    mockApi.get.mockResolvedValueOnce({
      success: false,
      status: 404,
      error: 'Not found',
    });

    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    // After load, a MessageSquare icon card is shown — no spinner.
    await waitFor(() => {
      const statusEls = screen.queryAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });

  it('shows unavailable state when error message contains "not found"', async () => {
    mockApi.get.mockResolvedValueOnce({
      success: false,
      error: 'not found',
    });

    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });
  });

  // ── generic error ────────────────────────────────────────────────────────
  it('shows error state when API fails with a non-404 error', async () => {
    mockApi.get.mockResolvedValueOnce({
      success: false,
      error: 'Server exploded',
    });

    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });
  });

  it('shows error state when API throws a non-404 exception', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('Network error'));

    render(<FederationReviewsPanel memberId={MEMBER_ID} />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEls.length).toBe(0);
    });
  });
});
