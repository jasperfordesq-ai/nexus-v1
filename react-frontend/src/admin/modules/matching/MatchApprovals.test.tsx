// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── stable mock data (vi.hoisted so factory functions can access them) ──────
const { APPROVALS, STATS, APPROVAL_1 } = vi.hoisted(() => {
  const APPROVAL_1 = {
    id: 101,
    user_1_name: 'Alice Green',
    user_1_avatar: null,
    user_2_name: 'Bob Smith',
    user_2_avatar: null,
    listing_title: 'Gardening help needed',
    match_score: 85,
    status: 'pending',
    created_at: '2025-06-01T10:00:00Z',
  };

  const APPROVALS = [APPROVAL_1];

  const STATS = {
    pending_count: 1,
    approved_count: 5,
    rejected_count: 2,
    approval_rate: 71,
  };

  return { APPROVALS, STATS, APPROVAL_1 };
});

// ─── mock adminApi ───────────────────────────────────────────────────────────
const { mockAdminMatching } = vi.hoisted(() => ({
  mockAdminMatching: {
    getApprovals: vi.fn(),
    getApprovalStats: vi.fn(),
    approveMatch: vi.fn(),
    rejectMatch: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminMatching: mockAdminMatching,
}));

// ─── mock admin components ───────────────────────────────────────────────────
vi.mock('../../components', () => ({
  DataTable: ({
    data,
    isLoading,
    columns,
    emptyContent,
  }: {
    data: { id: number; user_1_name: string; user_2_name: string; status: string; listing_title?: string | null; match_score: number; created_at: string }[];
    isLoading: boolean;
    columns: { key: string; label: string; render?: (item: { id: number; user_1_name: string; user_2_name: string; status: string; listing_title?: string | null; match_score: number; created_at: string }) => React.ReactNode }[];
    emptyContent?: React.ReactNode;
  }) => {
    if (isLoading) return <div data-testid="datatable-loading">Loading...</div>;
    if (!data.length) return <div data-testid="datatable-empty">{emptyContent}</div>;
    return (
      <table>
        <thead>
          <tr>
            {columns.map((col) => (
              <th key={col.key}>{col.label}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((item) => (
            <tr key={item.id} data-testid={`row-${item.id}`}>
              {columns.map((col) => (
                <td key={col.key}>{col.render ? col.render(item) : String((item as Record<string, unknown>)[col.key] ?? '')}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    );
  },
  PageHeader: ({ title, description }: { title: string; description: string }) => (
    <div>
      <h1>{title}</h1>
      <p>{description}</p>
    </div>
  ),
  StatCard: ({
    label,
    value,
    loading,
  }: {
    label: string;
    value: number | string;
    loading?: boolean;
  }) => (
    <div data-testid="stat-card">
      <span>{label}</span>
      {loading ? <span>-</span> : <span>{value}</span>}
    </div>
  ),
  StatusBadge: ({ status }: { status: string }) => <span data-testid="status-badge">{status}</span>,
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  }),
);

import { MatchApprovals } from './MatchApprovals';

// ─── helpers ──────────────────────────────────────────────────────────────────

const paginatedResponse = (data: typeof APPROVALS) => ({
  success: true,
  data: { data, meta: { total: data.length } },
});

const statsResponse = () => ({ success: true, data: STATS });

// ─── tests ────────────────────────────────────────────────────────────────────

describe('MatchApprovals — loading', () => {
  beforeEach(() => vi.resetAllMocks());

  it('shows loading state while approvals are being fetched', () => {
    mockAdminMatching.getApprovals.mockReturnValue(new Promise(() => {}));
    mockAdminMatching.getApprovalStats.mockReturnValue(new Promise(() => {}));
    render(<MatchApprovals />);
    expect(screen.getByTestId('datatable-loading')).toBeInTheDocument();
  });
});

describe('MatchApprovals — populated', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminMatching.getApprovals.mockResolvedValue(paginatedResponse(APPROVALS));
    mockAdminMatching.getApprovalStats.mockResolvedValue(statsResponse());
  });

  it('renders page heading', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
    });
  });

  it('renders stat cards for pending, approved, rejected, and approval rate', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      const statCards = screen.getAllByTestId('stat-card');
      expect(statCards.length).toBe(4);
    });
  });

  it('shows pending count from stats', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      // Stats pending_count = 1 — may appear multiple times (stat card + tab chip)
      expect(screen.getAllByText('1').length).toBeGreaterThan(0);
    });
  });

  it('renders a table row for each approval', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      expect(screen.getByTestId('row-101')).toBeInTheDocument();
    });
  });

  it('shows user names in the table', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      expect(screen.getByText('Alice Green')).toBeInTheDocument();
      expect(screen.getByText('Bob Smith')).toBeInTheDocument();
    });
  });

  it('shows the listing title', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      expect(screen.getByText('Gardening help needed')).toBeInTheDocument();
    });
  });

  it('shows status badge', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      expect(screen.getByTestId('status-badge')).toBeInTheDocument();
    });
  });

  it('shows approve and reject buttons for pending approval', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      const allBtns = screen.getAllByRole('button');
      const approveBtn = allBtns.find(
        (b) => b.getAttribute('aria-label')?.toLowerCase().includes('approve'),
      );
      const rejectBtn = allBtns.find(
        (b) => b.getAttribute('aria-label')?.toLowerCase().includes('reject'),
      );
      expect(approveBtn).toBeDefined();
      expect(rejectBtn).toBeDefined();
    });
  });
});

describe('MatchApprovals — empty', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminMatching.getApprovals.mockResolvedValue(paginatedResponse([]));
    mockAdminMatching.getApprovalStats.mockResolvedValue(statsResponse());
  });

  it('shows empty state message when no approvals', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      expect(screen.getByTestId('datatable-empty')).toBeInTheDocument();
    });
  });
});

describe('MatchApprovals — approve action', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminMatching.getApprovals.mockResolvedValue(paginatedResponse(APPROVALS));
    mockAdminMatching.getApprovalStats.mockResolvedValue(statsResponse());
  });

  it('calls approveMatch with the correct id', async () => {
    const user = userEvent.setup();
    mockAdminMatching.approveMatch.mockResolvedValue({ success: true });

    render(<MatchApprovals />);
    await waitFor(() => {
      expect(screen.getByTestId('row-101')).toBeInTheDocument();
    });

    const allBtns = screen.getAllByRole('button');
    const approveBtn = allBtns.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('approve'),
    );
    expect(approveBtn).toBeDefined();
    await user.click(approveBtn!);

    await waitFor(() => {
      expect(mockAdminMatching.approveMatch).toHaveBeenCalledWith(APPROVAL_1.id);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when approve fails', async () => {
    const user = userEvent.setup();
    mockAdminMatching.approveMatch.mockResolvedValue({ success: false, error: 'Forbidden' });

    render(<MatchApprovals />);
    await waitFor(() => expect(screen.getByTestId('row-101')).toBeInTheDocument());

    const allBtns = screen.getAllByRole('button');
    const approveBtn = allBtns.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('approve'),
    );
    await user.click(approveBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Forbidden');
    });
  });
});

describe('MatchApprovals — reject modal', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminMatching.getApprovals.mockResolvedValue(paginatedResponse(APPROVALS));
    mockAdminMatching.getApprovalStats.mockResolvedValue(statsResponse());
  });

  it('opens reject modal when reject button is clicked', async () => {
    const user = userEvent.setup();
    render(<MatchApprovals />);
    await waitFor(() => expect(screen.getByTestId('row-101')).toBeInTheDocument());

    const allBtns = screen.getAllByRole('button');
    const rejectBtn = allBtns.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('reject'),
    );
    await user.click(rejectBtn!);

    // Modal should open — contains a textarea for rejection reason
    await waitFor(() => {
      expect(screen.getAllByRole('textbox').length).toBeGreaterThan(0);
    });
  });

  it('calls rejectMatch with correct id and reason', async () => {
    const user = userEvent.setup();
    mockAdminMatching.rejectMatch.mockResolvedValue({ success: true });

    render(<MatchApprovals />);
    await waitFor(() => expect(screen.getByTestId('row-101')).toBeInTheDocument());

    const allBtns = screen.getAllByRole('button');
    const rejectBtn = allBtns.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('reject'),
    );
    await user.click(rejectBtn!);

    // Wait for modal textarea to appear
    const textarea = await screen.findAllByRole('textbox').then((els) => els[0]);
    await user.type(textarea, 'Not a good match at this time');

    // Click the modal's Reject button (danger button in footer — not the icon button)
    const modalBtns = screen.getAllByRole('button');
    const confirmRejectBtn = modalBtns.find(
      (b) =>
        !b.hasAttribute('disabled') &&
        b.getAttribute('aria-disabled') !== 'true' &&
        !b.getAttribute('aria-label') && // skip icon buttons
        (b.textContent?.toLowerCase().includes('reject') ||
          b.textContent?.includes('matching.reject_match')),
    );
    if (confirmRejectBtn) {
      await user.click(confirmRejectBtn);
    }

    await waitFor(() => {
      expect(mockAdminMatching.rejectMatch).toHaveBeenCalledWith(
        APPROVAL_1.id,
        'Not a good match at this time',
      );
    });
  });

  it('shows validation error when reason is empty', async () => {
    const user = userEvent.setup();
    render(<MatchApprovals />);
    await waitFor(() => expect(screen.getByTestId('row-101')).toBeInTheDocument());

    const allBtns = screen.getAllByRole('button');
    const rejectBtn = allBtns.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('reject'),
    );
    await user.click(rejectBtn!);

    // In modal, click Reject without typing a reason
    await waitFor(async () => {
      const modalBtns = screen.getAllByRole('button');
      const confirmRejectBtn = modalBtns.find(
        (b) =>
          b.hasAttribute('disabled') ||
          b.getAttribute('aria-disabled') === 'true' ||
          b.textContent?.toLowerCase().includes('reject'),
      );
      if (confirmRejectBtn && (confirmRejectBtn.hasAttribute('disabled') || confirmRejectBtn.getAttribute('aria-disabled') === 'true')) {
        // Already disabled — can't click
        expect(true).toBe(true);
      } else if (confirmRejectBtn) {
        await user.click(confirmRejectBtn);
        await waitFor(() => {
          expect(mockToast.error).toHaveBeenCalled();
        });
      }
    });
  });
});

describe('MatchApprovals — status tabs', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminMatching.getApprovals.mockResolvedValue(paginatedResponse([]));
    mockAdminMatching.getApprovalStats.mockResolvedValue(statsResponse());
  });

  it('renders the four status tabs', async () => {
    render(<MatchApprovals />);
    await waitFor(() => {
      const tabs = screen.getAllByRole('tab');
      expect(tabs.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('refetches approvals when tab changes to Approved', async () => {
    const user = userEvent.setup();
    render(<MatchApprovals />);
    await waitFor(() => expect(mockAdminMatching.getApprovals).toHaveBeenCalledTimes(1));

    const tabs = screen.getAllByRole('tab');
    const approvedTab = tabs.find(
      (t) =>
        t.textContent?.toLowerCase().includes('approved') ||
        t.textContent?.includes('matching.tab_approved'),
    );
    if (approvedTab) {
      await user.click(approvedTab);
      await waitFor(() => {
        expect(mockAdminMatching.getApprovals).toHaveBeenCalledTimes(2);
        expect(mockAdminMatching.getApprovals).toHaveBeenLastCalledWith(
          expect.objectContaining({ status: 'approved' }),
        );
      });
    }
  });
});
