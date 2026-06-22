// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock refs (vi.hoisted so they're available in vi.mock factories) ───
const { mockAdminBroker } = vi.hoisted(() => ({
  mockAdminBroker: {
    showArchive: vi.fn(),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/admin/api/adminApi', () => ({
  adminBroker: mockAdminBroker,
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/admin/components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

vi.mock('@/lib/serverTime', () => ({
  formatServerDateTime: (dt: string) => dt,
}));

// react-router-dom: mock useParams to inject :id
let mockId = '1';
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: mockId }),
  };
});

import { ArchiveDetail } from './ArchiveDetailPage';

const mockArchive = {
  id: 1,
  decision: 'approved',
  decided_by_name: 'Admin User',
  decided_at: '2025-01-10T12:00:00Z',
  decision_notes: 'Looks fine',
  flag_reason: null,
  flag_severity: null,
  sender_name: 'Alice',
  receiver_name: 'Bob',
  target_message_body: 'Hello there!',
  copy_reason: 'keyword_match',
  target_message_sent_at: '2025-01-09T10:00:00Z',
  listing_title: 'My Listing',
  conversation_snapshot: [
    {
      id: 10,
      sender_name: 'Alice',
      body: 'First message',
      created_at: '2025-01-09T09:00:00Z',
      is_deleted: false,
      is_edited: false,
    },
    {
      id: 11,
      sender_name: 'Bob',
      body: 'Reply here',
      created_at: '2025-01-09T09:05:00Z',
      is_deleted: false,
      is_edited: true,
    },
  ],
};

describe('ArchiveDetail', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockId = '1';
  });

  it('shows loading spinner while fetching', () => {
    mockAdminBroker.showArchive.mockReturnValue(new Promise(() => {}));
    render(<ArchiveDetail />);

    const statusEls = screen.queryAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders archive detail when loaded successfully', async () => {
    mockAdminBroker.showArchive.mockResolvedValue({ success: true, data: mockArchive });

    render(<ArchiveDetail />);

    await waitFor(() => {
      expect(screen.getAllByText('Alice').length).toBeGreaterThan(0);
    });

    expect(screen.getAllByText('Bob').length).toBeGreaterThan(0);
    expect(screen.getByText('Hello there!')).toBeInTheDocument();
    expect(screen.getByText('Admin User')).toBeInTheDocument();
    expect(screen.getByText('Looks fine')).toBeInTheDocument();
  });

  it('renders conversation snapshot messages', async () => {
    mockAdminBroker.showArchive.mockResolvedValue({ success: true, data: mockArchive });

    render(<ArchiveDetail />);

    await waitFor(() => {
      expect(screen.getByText('First message')).toBeInTheDocument();
    });

    expect(screen.getByText('Reply here')).toBeInTheDocument();
  });

  it('renders "edited" label for edited messages', async () => {
    mockAdminBroker.showArchive.mockResolvedValue({ success: true, data: mockArchive });

    render(<ArchiveDetail />);

    await waitFor(() => {
      // t('archives.edited') returns key string in test env
      expect(screen.getByText(/edited/i)).toBeInTheDocument();
    });
  });

  it('renders deleted message placeholder for deleted messages', async () => {
    const archiveWithDeleted = {
      ...mockArchive,
      conversation_snapshot: [
        {
          id: 20,
          sender_name: 'Charlie',
          body: '',
          created_at: '2025-01-08T08:00:00Z',
          is_deleted: true,
          is_edited: false,
        },
      ],
    };
    mockAdminBroker.showArchive.mockResolvedValue({ success: true, data: archiveWithDeleted });

    render(<ArchiveDetail />);

    await waitFor(() => {
      // t('archives.deleted') returns key in test env — look for italic element
      expect(screen.getByText(/deleted/i)).toBeInTheDocument();
    });
  });

  it('shows empty snapshot message when no conversation messages', async () => {
    const archiveEmpty = { ...mockArchive, conversation_snapshot: [] };
    mockAdminBroker.showArchive.mockResolvedValue({ success: true, data: archiveEmpty });

    render(<ArchiveDetail />);

    await waitFor(() => {
      // t('archives.no_snapshot') = "No conversation snapshot available."
      expect(screen.getByText(/No conversation snapshot/i)).toBeInTheDocument();
    });
  });

  it('shows error state when API returns success=false', async () => {
    mockAdminBroker.showArchive.mockResolvedValue({ success: false, data: null });

    render(<ArchiveDetail />);

    await waitFor(() => {
      // back_to_archives button or error message rendered
      expect(screen.getByRole('link', { name: /back/i })).toBeInTheDocument();
    });
  });

  it('shows error state when API call throws', async () => {
    mockAdminBroker.showArchive.mockRejectedValue(new Error('Network error'));

    render(<ArchiveDetail />);

    await waitFor(() => {
      expect(screen.getByRole('link', { name: /back/i })).toBeInTheDocument();
    });
  });

  it('shows invalid_id error for non-numeric id', async () => {
    mockId = 'abc';

    render(<ArchiveDetail />);

    await waitFor(() => {
      // invalid id error renders back link
      expect(screen.getByRole('link', { name: /back/i })).toBeInTheDocument();
    });

    // showArchive must NOT be called for invalid id
    expect(mockAdminBroker.showArchive).not.toHaveBeenCalled();
  });

  it('shows flag_reason section for rejected decisions', async () => {
    const rejectedArchive = {
      ...mockArchive,
      decision: 'rejected',
      flag_reason: 'Inappropriate content',
      flag_severity: 'high',
    };
    mockAdminBroker.showArchive.mockResolvedValue({ success: true, data: rejectedArchive });

    render(<ArchiveDetail />);

    await waitFor(() => {
      expect(screen.getByText('Inappropriate content')).toBeInTheDocument();
    });
  });

  it('shows listing_title when present', async () => {
    mockAdminBroker.showArchive.mockResolvedValue({ success: true, data: mockArchive });

    render(<ArchiveDetail />);

    await waitFor(() => {
      expect(screen.getByText('My Listing')).toBeInTheDocument();
    });
  });
});
