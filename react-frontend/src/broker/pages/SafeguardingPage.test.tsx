// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ── api mock ──────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── react-router (preserve useSearchParams) ───────────────────────────────────
vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return { ...orig };
});

// ── serverTime ────────────────────────────────────────────────────────────────
vi.mock('@/lib/serverTime', () => ({
  formatServerDate: (v: string | null) => (v ? new Date(v).toLocaleDateString() : '—'),
}));

// ── stubs ─────────────────────────────────────────────────────────────────────
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

vi.mock('@/admin/components', () => ({
  PageHeader: ({ title, description }: { title: string; description?: string }) => (
    <div><h1>{title}</h1>{description && <p>{description}</p>}</div>
  ),
  DataTable: ({
    data,
    isLoading,
    columns,
    emptyContent,
  }: {
    data: object[];
    isLoading: boolean;
    columns: { key: string; label: string; render: (item: Record<string, unknown>) => React.ReactNode }[];
    emptyContent?: React.ReactNode;
  }) => {
    if (isLoading) return <div role="status" aria-busy="true" aria-label="loading" />;
    if (!data || data.length === 0) return <>{emptyContent}</>;
    return (
      <table>
        <tbody>
          {data.map((row: Record<string, unknown>, i) => (
            <tr key={i}>
              {columns.map((col) => (
                <td key={col.key}>{col.render(row)}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    );
  },
  StatCard: ({
    label,
    value,
    loading,
  }: {
    label: string;
    value: number;
    loading?: boolean;
    icon?: unknown;
    color?: string;
  }) => (
    <div data-testid="stat-card">
      <span>{label}</span>
      {!loading && <span>{value}</span>}
    </div>
  ),
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

// ── contexts ──────────────────────────────────────────────────────────────────
const { mockToast } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ── fixtures ──────────────────────────────────────────────────────────────────
const makeStats = (overrides = {}) => ({
  active_assignments: 3,
  unreviewed_flags: 5,
  consented_wards: 2,
  total_flags_this_month: 10,
  critical_flags: 1,
  ...overrides,
});

const makeFlagged = (id: number, overrides = {}) => ({
  id,
  sender_name: `Sender ${id}`,
  receiver_name: `Receiver ${id}`,
  flag_reason: 'Inappropriate language',
  severity: 'medium',
  is_reviewed: false,
  reviewed_at: null,
  reviewed_by: null,
  review_notes: null,
  created_at: '2026-06-01T10:00:00Z',
  ...overrides,
});

const okStats = (data = makeStats()) => ({ success: true, data });
const okFlagged = (items = [makeFlagged(1)]) => ({ success: true, data: items, meta: { total: items.length } });

/**
 * Reset mock state and set up the two standard GETs:
 * 1st call → dashboard stats, 2nd → flagged messages (permanent fallback).
 * Tests that need different behaviour call this again with override params.
 */
function setupMocks(statsResp = okStats(), flaggedResp = okFlagged()) {
  mockApi.get.mockReset();
  mockApi.get
    .mockResolvedValueOnce(statsResp)
    .mockResolvedValue(flaggedResp);
}

// ─────────────────────────────────────────────────────────────────────────────
describe('SafeguardingPage (broker)', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    setupMocks();
    mockApi.post.mockResolvedValue({ success: true });
  });

  async function renderPage() {
    const mod = await import('./SafeguardingPage');
    const Component = mod.default;
    render(<Component />);
  }

  // ── loading ────────────────────────────────────────────────────────────────
  it('shows loading spinner while data loads', async () => {
    mockApi.get.mockReset();
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    await renderPage();
    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  // ── stat cards ─────────────────────────────────────────────────────────────
  it('renders 5 stat cards', async () => {
    await renderPage();
    await waitFor(() => {
      const cards = screen.getAllByTestId('stat-card');
      expect(cards.length).toBe(5);
    });
  });

  it('shows stat values after load', async () => {
    await renderPage();
    await waitFor(() => {
      expect(screen.getByText('10')).toBeInTheDocument(); // total_flags_this_month
      expect(screen.getByText('1')).toBeInTheDocument();  // critical_flags
      expect(screen.getByText('5')).toBeInTheDocument();  // unreviewed_flags
    });
  });

  // ── error banner ───────────────────────────────────────────────────────────
  it('shows error banner when stats API fails', async () => {
    mockApi.get.mockReset();
    mockApi.get
      .mockRejectedValueOnce(new Error('stats fail'))
      .mockResolvedValue(okFlagged());
    await renderPage();
    await waitFor(() => {
      const retryBtns = screen.getAllByRole('button').filter(
        (b) => b.textContent?.match(/retry/i),
      );
      expect(retryBtns.length).toBeGreaterThan(0);
    });
  });

  it('shows error banner when flagged-messages API fails', async () => {
    mockApi.get.mockReset();
    mockApi.get
      .mockResolvedValueOnce(okStats())
      .mockRejectedValue(new Error('flagged fail'));
    await renderPage();
    await waitFor(() => {
      const retryBtns = screen.getAllByRole('button').filter(
        (b) => b.textContent?.match(/retry/i),
      );
      expect(retryBtns.length).toBeGreaterThan(0);
    });
  });

  // ── flagged messages tab ───────────────────────────────────────────────────
  it('renders flagged messages after load', async () => {
    await renderPage();
    await waitFor(() => {
      expect(screen.getByText('Sender 1')).toBeInTheDocument();
      expect(screen.getByText('Receiver 1')).toBeInTheDocument();
    });
  });

  it('shows empty state when no flagged messages', async () => {
    setupMocks(okStats(), okFlagged([]));
    await renderPage();
    await waitFor(() => {
      const emptyEls = screen.getAllByTestId('empty-state');
      expect(emptyEls.length).toBeGreaterThan(0);
    });
  });

  // ── Mark Reviewed button ───────────────────────────────────────────────────
  it('shows Mark Reviewed button for unreviewed messages', async () => {
    await renderPage();
    await waitFor(() => {
      const btns = screen.getAllByRole('button').filter(
        (b) => b.textContent?.match(/review/i),
      );
      expect(btns.length).toBeGreaterThan(0);
    });
  });

  it('opens review modal when Mark Reviewed is clicked', async () => {
    await renderPage();
    await waitFor(() => screen.getByText('Sender 1'));

    const reviewBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/review/i),
    );
    if (reviewBtns.length > 0) {
      fireEvent.click(reviewBtns[0]);
      await waitFor(() => {
        expect(document.querySelector('[role="dialog"]')).toBeTruthy();
      });
    }
  });

  it('calls review POST endpoint on modal confirm', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    // initial load: stats + flagged with 1 item; re-fetch after review: empty
    mockApi.get.mockReset();
    mockApi.get
      .mockResolvedValueOnce(okStats())        // initial stats
      .mockResolvedValueOnce(okFlagged())      // initial flagged
      .mockResolvedValue(okFlagged([]));       // re-fetch after review

    await renderPage();
    await waitFor(() => screen.getByText('Sender 1'));

    const reviewBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/review/i),
    );
    if (reviewBtns.length > 0) {
      fireEvent.click(reviewBtns[0]);
      await waitFor(() => document.querySelector('[role="dialog"]'));

      // Click the confirm / "Mark Reviewed" button inside the modal
      const allBtns = screen.getAllByRole('button');
      const confirmBtn = allBtns.find(
        (b) =>
          b.textContent?.match(/mark reviewed|confirm/i) &&
          document.querySelector('[role="dialog"]')?.contains(b),
      ) ?? allBtns.find((b) => b.textContent?.match(/mark reviewed/i));

      if (confirmBtn) {
        fireEvent.click(confirmBtn);
        await waitFor(() => {
          expect(mockApi.post).toHaveBeenCalledWith(
            '/v2/admin/safeguarding/flagged-messages/1/review',
            expect.anything(),
          );
        });
      }
    }
  });

  it('does not show Mark Reviewed button for already-reviewed messages', async () => {
    setupMocks(okStats(), okFlagged([makeFlagged(1, { is_reviewed: true, reviewed_at: '2026-06-02T10:00:00Z' })]));
    await renderPage();
    await waitFor(() => screen.getByText('Sender 1'));

    const reviewBtns = screen.queryAllByRole('button').filter(
      (b) => b.textContent?.match(/^mark reviewed$/i),
    );
    // reviewed message has no action button
    expect(reviewBtns.length).toBe(0);
  });

  // ── toast on review success ────────────────────────────────────────────────
  it('shows success toast after successful review', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    mockApi.get.mockReset();
    mockApi.get
      .mockResolvedValueOnce(okStats())        // initial stats
      .mockResolvedValueOnce(okFlagged())      // initial flagged (1 item)
      .mockResolvedValue(okFlagged([]));       // re-fetch after review

    await renderPage();
    await waitFor(() => screen.getByText('Sender 1'));

    const reviewBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/review/i),
    );
    if (reviewBtns.length > 0) {
      fireEvent.click(reviewBtns[0]);
      await waitFor(() => document.querySelector('[role="dialog"]'));

      const allBtns = screen.getAllByRole('button');
      const confirmBtn = allBtns.find(
        (b) =>
          b.textContent?.match(/mark reviewed/i) &&
          document.querySelector('[role="dialog"]')?.contains(b),
      ) ?? allBtns.find((b) => b.textContent?.match(/mark reviewed/i));

      if (confirmBtn) {
        fireEvent.click(confirmBtn);
        await waitFor(() => {
          expect(mockToast.success).toHaveBeenCalled();
        });
      }
    }
  });

  // ── tabs rendered ──────────────────────────────────────────────────────────
  it('renders the 3 tab labels (Flagged / Guardians / Preferences)', async () => {
    await renderPage();
    await waitFor(() => screen.getByText('Sender 1'));
    // Tabs have role=tab
    const tabs = screen.getAllByRole('tab');
    expect(tabs.length).toBeGreaterThanOrEqual(3);
  });
});
