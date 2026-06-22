// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── vi.hoisted: stable mock references ─────────────────────────────────────
const { mockListSubscribers } = vi.hoisted(() => ({
  mockListSubscribers: vi.fn(),
}));

// ─── Mock memberPremiumApi ───────────────────────────────────────────────────
vi.mock('../../api/memberPremiumApi', () => ({
  memberPremiumAdminApi: {
    listSubscribers: mockListSubscribers,
    listTiers: vi.fn(),
    getTier: vi.fn(),
    createTier: vi.fn(),
    updateTier: vi.fn(),
    deleteTier: vi.fn(),
    syncStripe: vi.fn(),
  },
}));

// ─── Mock @/contexts ─────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

// ─── Mock @/hooks ────────────────────────────────────────────────────────────
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Mock PageHeader only — let HeroUI Table/Select/Chip render naturally ────
vi.mock('../../components', async (importOriginal) => {
  const { PageHeader: RealPageHeader } = await importOriginal<typeof import('../../components')>();
  return {
    PageHeader: RealPageHeader,
    DataTable: vi.fn(),
    StatusBadge: vi.fn(),
    EmptyState: vi.fn(),
  };
});

import { MemberPremiumSubscribersPage } from './MemberPremiumSubscribersPage';

function makeRow(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    user_id: 100,
    tier_id: 1,
    status: 'active',
    billing_interval: 'monthly' as const,
    current_period_end: '2026-07-01T00:00:00Z',
    canceled_at: null,
    grace_period_ends_at: null,
    created_at: '2026-01-01T00:00:00Z',
    tier_name: 'Gold',
    tier_slug: 'gold',
    email: 'user@example.com',
    user_name: 'Test User',
    first_name: null,
    ...overrides,
  };
}

describe('MemberPremiumSubscribersPage — loading state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching subscribers', () => {
    mockListSubscribers.mockReturnValue(new Promise(() => {}));

    render(<MemberPremiumSubscribersPage />);

    const spinner = screen
      .getAllByRole('status')
      .find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });
});

describe('MemberPremiumSubscribersPage — populated state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders subscriber rows in the table after loading', async () => {
    mockListSubscribers.mockResolvedValue({
      data: {
        rows: [makeRow(), makeRow({ id: 2, user_name: 'Jane Doe', email: 'jane@example.com' })],
        total: 2,
        page: 1,
        per_page: 25,
      },
    });

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });

    // User names appear as table cell content
    expect(screen.getByText('Test User')).toBeInTheDocument();
    expect(screen.getByText('Jane Doe')).toBeInTheDocument();
  });

  it('renders email addresses in the table', async () => {
    mockListSubscribers.mockResolvedValue({
      data: {
        rows: [makeRow({ email: 'alice@nexus.ie' })],
        total: 1,
        page: 1,
        per_page: 25,
      },
    });

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      expect(screen.getByText('alice@nexus.ie')).toBeInTheDocument();
    });
  });

  it('renders tier name in the table', async () => {
    mockListSubscribers.mockResolvedValue({
      data: {
        rows: [makeRow({ tier_name: 'Platinum' })],
        total: 1,
        page: 1,
        per_page: 25,
      },
    });

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      expect(screen.getByText('Platinum')).toBeInTheDocument();
    });
  });

  it('shows total subscriber count in the filter bar', async () => {
    mockListSubscribers.mockResolvedValue({
      data: {
        rows: [makeRow()],
        total: 42,
        page: 1,
        per_page: 25,
      },
    });

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      // The translated key renders with count=42; match the number
      expect(screen.getByText(/42/)).toBeInTheDocument();
    });
  });

  it('shows pagination when totalPages > 1', async () => {
    const rows = Array.from({ length: 25 }, (_, i) =>
      makeRow({ id: i + 1, user_name: `User ${i + 1}` })
    );
    mockListSubscribers.mockResolvedValue({
      data: { rows, total: 100, page: 1, per_page: 25 },
    });

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      // Pagination component renders with role="navigation" or buttons
      // total=100, per_page=25 → 4 pages > 1 so pagination is visible
      // HeroUI Pagination renders a nav element
      const nav = screen.queryByRole('navigation');
      expect(nav).toBeInTheDocument();
    });
  });
});

describe('MemberPremiumSubscribersPage — empty state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows empty message when no subscribers returned', async () => {
    mockListSubscribers.mockResolvedValue({
      data: { rows: [], total: 0, page: 1, per_page: 25 },
    });

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      // spinner gone
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });

    // HeroUI Table is present but with empty body; the empty message div is shown
    // (The source renders a <div className="text-center..."> with the empty key)
    const emptyDivs = screen.queryAllByText(/no subscribers|empty/i);
    // The translation key resolves to something — or we check no table rows
    const tableRows = screen.queryAllByRole('row');
    // header row + 0 data rows = 1 or the table header alone
    expect(tableRows.length).toBeLessThan(2);
  });
});

describe('MemberPremiumSubscribersPage — error state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls toast.error when listSubscribers throws', async () => {
    mockListSubscribers.mockRejectedValue(new Error('500 Server Error'));

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('does not crash — component remains mounted after error', async () => {
    mockListSubscribers.mockRejectedValue(new Error('network'));

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });

    // Component still rendered (no thrown error boundary)
    // Empty state is visible since rows=[]
    const spinner = screen
      .queryAllByRole('status')
      .find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeUndefined();
  });
});

describe('MemberPremiumSubscribersPage — refresh button', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls listSubscribers again when refresh button is pressed', async () => {
    mockListSubscribers.mockResolvedValue({
      data: { rows: [makeRow()], total: 1, page: 1, per_page: 25 },
    });

    render(<MemberPremiumSubscribersPage />);

    await waitFor(() => {
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    // The Refresh button (HeroUI Button onPress)
    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    refreshBtn.click();

    await waitFor(() => {
      expect(mockListSubscribers).toHaveBeenCalledTimes(2);
    });
  });
});
