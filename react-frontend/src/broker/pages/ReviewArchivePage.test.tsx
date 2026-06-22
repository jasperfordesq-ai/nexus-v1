// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Mock adminBroker API ─────────────────────────────────────────────────────
const mockGetArchives = vi.fn();

vi.mock('@/admin/api/adminApi', () => ({
  adminBroker: {
    getArchives: (...args: unknown[]) => mockGetArchives(...args),
  },
}));

// ── Stable context mocks ─────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

// ── DataTable mock (renders a simple table-like div) ─────────────────────────
vi.mock('@/admin/components', () => ({
  DataTable: ({ data, isLoading, emptyContent }: {
    data: unknown[];
    isLoading: boolean;
    emptyContent: string;
  }) => {
    if (isLoading) {
      return <div role="status" aria-busy="true" aria-label="Loading">Loading...</div>;
    }
    if (data.length === 0) {
      return <div data-testid="empty">{emptyContent}</div>;
    }
    return (
      <table role="table">
        <tbody>
          {(data as Array<{ id: number; sender_name: string; receiver_name: string }>).map((row) => (
            <tr key={row.id}>
              <td>{row.sender_name}</td>
              <td>{row.receiver_name}</td>
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
}));

// ── Sample data ───────────────────────────────────────────────────────────────
const ARCHIVE_ROWS = [
  {
    id: 1,
    sender_name: 'Alice Smith',
    receiver_name: 'Bob Jones',
    listing_title: 'Tutoring',
    copy_reason: 'flagged',
    decision: 'approved',
    decided_by_name: 'Admin',
    decided_at: '2026-06-01T10:00:00Z',
  },
  {
    id: 2,
    sender_name: 'Carol White',
    receiver_name: 'Dan Brown',
    listing_title: null,
    copy_reason: 'compliance',
    decision: 'flagged',
    decided_by_name: 'Broker',
    decided_at: '2026-06-02T11:00:00Z',
  },
];

import { ReviewArchive } from './ReviewArchivePage';

describe('ReviewArchivePage — loading', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetArchives.mockReturnValue(new Promise(() => {})); // pending
  });

  it('shows loading spinner while fetching', () => {
    render(<ReviewArchive />);
    const spinner = getAllByRoleStatus();
    expect(spinner).toBeTruthy();
  });

  function getAllByRoleStatus() {
    const items = screen.getAllByRole('status');
    return items.find((el) => el.getAttribute('aria-busy') === 'true');
  }
});

describe('ReviewArchivePage — populated', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetArchives.mockResolvedValue({
      success: true,
      data: ARCHIVE_ROWS,
      meta: { total: 2 },
    });
  });

  it('renders archive rows after load', async () => {
    render(<ReviewArchive />);
    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
      expect(screen.getByText('Carol White')).toBeInTheDocument();
    });
  });

  it('loading spinner gone after data arrives', async () => {
    render(<ReviewArchive />);
    await waitFor(() => {
      const spinner = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(spinner).toBeUndefined();
    });
  });

  it('calls getArchives with page=1 on mount', async () => {
    render(<ReviewArchive />);
    await waitFor(() => {
      expect(mockGetArchives).toHaveBeenCalledWith(
        expect.objectContaining({ page: 1 }),
      );
    });
  });
});

describe('ReviewArchivePage — empty', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetArchives.mockResolvedValue({ success: true, data: [], meta: { total: 0 } });
  });

  it('shows empty content when no archives returned', async () => {
    render(<ReviewArchive />);
    await waitFor(() => {
      expect(screen.getByTestId('empty')).toBeInTheDocument();
    });
  });
});

describe('ReviewArchivePage — error', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetArchives.mockRejectedValue(new Error('Network error'));
  });

  it('shows error toast on load failure', async () => {
    render(<ReviewArchive />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('ReviewArchivePage — filter tabs', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetArchives.mockResolvedValue({ success: true, data: ARCHIVE_ROWS, meta: { total: 2 } });
  });

  it('re-fetches with decision=approved when Approved tab clicked', async () => {
    const user = userEvent.setup();
    render(<ReviewArchive />);
    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());

    // Find the Approved tab (HeroUI Tabs renders tab items as buttons or with role=tab)
    const approvedTab = screen.getByRole('tab', { name: /approved/i });
    await user.click(approvedTab);

    await waitFor(() => {
      expect(mockGetArchives).toHaveBeenCalledWith(
        expect.objectContaining({ decision: 'approved' }),
      );
    });
  });
});
