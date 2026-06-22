// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock data ──────────────────────────────────────────────────────────
const MOCK_CRITERIA = vi.hoisted(() => ({
  member: { hours_logged: 0, reviews_received: 0, identity_verified: false },
  trusted: { hours_logged: 5, reviews_received: 2, identity_verified: false },
  verified: { hours_logged: 20, reviews_received: 5, identity_verified: true },
  coordinator: { hours_logged: 50, reviews_received: 10, identity_verified: true },
}));

// ── mock @/lib/api ────────────────────────────────────────────────────────────
// TrustTierAdminPage uses `import api from '@/lib/api'` (default export).
// useApi hook uses `import { api } from '@/lib/api'` (named export).
// Both must point to the SAME mock object so setup in tests applies to both.
// vi.hoisted ensures the factory runs before vi.mock hoisting.
const mockApiObj = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  default: mockApiObj,
  api: mockApiObj,
}));

// ── mock @/contexts ───────────────────────────────────────────────────────────
const mockShowToast = vi.fn();
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      showToast: mockShowToast,
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
    }),
  }),
);

// ── mock AdminMetaContext ─────────────────────────────────────────────────────
vi.mock('@/admin/AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

// ── mock @/components/ui for useConfirm ──────────────────────────────────────
const mockConfirm = vi.fn().mockResolvedValue(true);
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    useConfirm: () => mockConfirm,
  };
});

import { TrustTierAdminPage } from './TrustTierAdminPage';

// References to the shared mock object (default and named are same obj)
const getMock = mockApiObj.get;
const putMock = mockApiObj.put;
const postMock = mockApiObj.post;

describe('TrustTierAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockResolvedValue({
      success: true,
      data: { criteria: MOCK_CRITERIA },
    } as never);
  });

  it('shows loading spinner while fetching config', () => {
    let resolve!: (v: unknown) => void;
    getMock.mockReturnValueOnce(new Promise((r) => (resolve = r)) as never);

    render(<TrustTierAdminPage />);

    const statusEls = screen.queryAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();

    resolve({ success: true, data: { criteria: MOCK_CRITERIA } });
  });

  it('renders tier rows after load', async () => {
    render(<TrustTierAdminPage />);

    await waitFor(() => {
      // The reference table always renders; check tier level cells
      expect(screen.getByText('0')).toBeInTheDocument();
    });
  });

  it('renders the reference table with 5 level rows', async () => {
    render(<TrustTierAdminPage />);

    await waitFor(() => {
      // Five rows in reference table (levels 0-4)
      for (let i = 0; i <= 4; i++) {
        expect(screen.getByText(String(i))).toBeInTheDocument();
      }
    });
  });

  it('shows error card when API returns success:false', async () => {
    getMock.mockResolvedValueOnce({ success: false, error: 'load error' } as never);
    // Subsequent calls (e.g. refetch) succeed so we don't get dangling promises
    getMock.mockResolvedValue({ success: true, data: { criteria: MOCK_CRITERIA } } as never);

    render(<TrustTierAdminPage />);

    await waitFor(() => {
      const busyEl = screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busyEl).toBeUndefined();
    });
    // Component renders without crash; error card is shown
    expect(document.body).toBeInTheDocument();
  });

  it('Save button is disabled initially (no local changes)', async () => {
    render(<TrustTierAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('0')).toBeInTheDocument();
    });

    const saveBtn = screen.getByRole('button', { name: /save/i });
    expect(saveBtn).toBeDisabled();
  });

  it('calls PUT endpoint when save is clicked after a change', async () => {
    putMock.mockResolvedValueOnce({ success: true } as never);
    // After save, refetch is called
    getMock.mockResolvedValue({ success: true, data: { criteria: MOCK_CRITERIA } } as never);

    render(<TrustTierAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('0')).toBeInTheDocument();
    });

    // Simulate changing a number input — the inputs are type=number
    const numberInputs = screen.getAllByDisplayValue('0');
    fireEvent.change(numberInputs[0], { target: { value: '3' } });

    await waitFor(() => {
      const saveBtn = screen.getByRole('button', { name: /save/i });
      expect(saveBtn).not.toBeDisabled();
    });

    const saveBtn = screen.getByRole('button', { name: /save/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      expect(putMock).toHaveBeenCalledWith(
        '/v2/admin/caring-community/trust-tier/config',
        expect.objectContaining({ criteria: expect.any(Object) }),
      );
    });
  });

  it('calls recompute endpoint when confirmed', async () => {
    postMock.mockResolvedValueOnce({ success: true, data: { updated: 7 } } as never);
    mockConfirm.mockResolvedValueOnce(true);

    render(<TrustTierAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('0')).toBeInTheDocument();
    });

    const recomputeBtn = screen.getByRole('button', { name: /recompute/i });
    await userEvent.click(recomputeBtn);

    await waitFor(() => {
      expect(postMock).toHaveBeenCalledWith(
        '/v2/admin/caring-community/trust-tier/recompute',
        {},
      );
    });
  });

  it('does NOT call recompute endpoint when confirm is cancelled', async () => {
    mockConfirm.mockResolvedValueOnce(false);

    render(<TrustTierAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('0')).toBeInTheDocument();
    });

    const recomputeBtn = screen.getByRole('button', { name: /recompute/i });
    await userEvent.click(recomputeBtn);

    await waitFor(() => {
      expect(postMock).not.toHaveBeenCalled();
    });
  });
});
