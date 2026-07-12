// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

const { mockAdminGroups, mockToastSuccess, mockToastError, mockNavigate } = vi.hoisted(() => ({
  mockAdminGroups: {
    getGroup: vi.fn(),
    getMembers: vi.fn(),
    geocodeGroup: vi.fn(),
    promoteMember: vi.fn(),
    demoteMember: vi.fn(),
    kickMember: vi.fn(),
  },
  mockToastSuccess: vi.fn(),
  mockToastError: vi.fn(),
  mockNavigate: vi.fn(),
}));

vi.mock('@/admin/api/adminApi', () => ({ adminGroups: mockAdminGroups }));

// GroupDetail imports useToast from '@/contexts/ToastContext' and destructures { success, error }
vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => ({
    success: mockToastSuccess,
    error: mockToastError,
    info: vi.fn(),
    warning: vi.fn(),
  }),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      success: mockToastSuccess,
      error: mockToastError,
      info: vi.fn(),
      warning: vi.fn(),
    }),
  })
);

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useParams: () => ({ id: '42' }),
    useNavigate: () => mockNavigate,
  };
});

vi.mock('./GroupAuditLog', () => ({
  GroupAuditLog: ({ groupId }: { groupId: number }) => (
    <div data-testid="group-audit-log">Audit group {groupId}</div>
  ),
}));

const GROUP = {
  id: 42,
  name: 'Test Group',
  description: 'A test group description',
  location: 'Dublin',
  visibility: 'public',
  member_count: 3,
  created_at: '2025-01-01T00:00:00Z',
  status: 'active',
  stats: {
    total_exchanges: 0,
    total_hours: 0,
    active_members: 2,
    posts_count: 7,
    events_count: 2,
    activity_score: 85,
  },
  latitude: 53.3498,
  longitude: -6.2603,
};

const MEMBERS = [
  {
    user_id: 10,
    user_name: 'Alice',
    user_avatar: null,
    role: 'owner',
    joined_at: '2025-01-01T00:00:00Z',
  },
  {
    user_id: 11,
    user_name: 'Bob',
    user_avatar: null,
    role: 'member',
    joined_at: '2025-02-01T00:00:00Z',
  },
  {
    user_id: 12,
    user_name: 'Carol',
    user_avatar: null,
    role: 'admin',
    joined_at: '2025-03-01T00:00:00Z',
  },
];

import GroupDetail from './GroupDetail';

describe('GroupDetail', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    window.history.replaceState({}, '', '/admin/groups/42/detail');
    mockAdminGroups.getGroup.mockResolvedValue({ success: true, data: GROUP });
    mockAdminGroups.getMembers.mockResolvedValue({ success: true, data: MEMBERS });
    mockAdminGroups.geocodeGroup.mockResolvedValue({ success: true });
    mockAdminGroups.promoteMember.mockResolvedValue({ success: true });
    mockAdminGroups.demoteMember.mockResolvedValue({ success: true });
    mockAdminGroups.kickMember.mockResolvedValue({ success: true });
  });

  it('shows loading text while data is fetching', () => {
    mockAdminGroups.getGroup.mockReturnValue(new Promise(() => {}));
    mockAdminGroups.getMembers.mockReturnValue(new Promise(() => {}));
    render(<GroupDetail />);
    expect(screen.getByText(/loading/i)).toBeInTheDocument();
  });

  it('renders group name after data loads', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('Test Group')).toBeInTheDocument();
    });
  });

  it('renders stat cards with correct values', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('3')).toBeInTheDocument(); // member_count
      expect(screen.getByText('85')).toBeInTheDocument(); // activity_score
    });
  });

  it('renders Overview tab content by default', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('A test group description')).toBeInTheDocument();
    });
  });

  it('routes Edit to the canonical tenant group form and keeps the overview read-only', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /edit/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /edit/i }));
    expect(mockNavigate).toHaveBeenCalledWith('/test/groups/edit/42');
    expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
  });

  it('renders members in Members tab', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('Test Group')).toBeInTheDocument();
    });

    await userEvent.click(await screen.findByRole('tab', { name: /members/i }));

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });
  });

  it('shows kick button for non-owner members', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('Test Group')).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('tab', { name: /members/i }));

    await waitFor(() => {
      expect(screen.getByText('Bob')).toBeInTheDocument();
    });

    expect(screen.getAllByRole('button', { name: /kick/i }).length).toBeGreaterThan(0);
  });

  it('does not show kick button for owner', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('Test Group')).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('tab', { name: /members/i }));
    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
    });

    // Owner row (Alice) should not have promote button (only kick for non-owners)
    // Alice is owner so she has no kick button — count should match Bob's row only
    const kickBtns = screen.getAllByRole('button', { name: /kick/i });
    // Bob and Carol can be removed; the owner cannot.
    expect(kickBtns).toHaveLength(2);
  });

  it('never offers the unfinished Make Owner action and retains admin demotion', async () => {
    render(<GroupDetail />);
    await screen.findByText('Test Group');
    await userEvent.click(screen.getByRole('tab', { name: /members/i }));
    await screen.findByText('Carol');

    expect(screen.queryByRole('button', { name: /make owner/i })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /demote/i })).toBeInTheDocument();
    expect(screen.getByText('Administrator')).toBeInTheDocument();
  });

  it('opens confirm modal when kick is clicked', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('Test Group')).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('tab', { name: /members/i }));
    await waitFor(() => {
      expect(screen.getAllByRole('button', { name: /kick/i }).length).toBeGreaterThan(0);
    });

    await userEvent.click(screen.getAllByRole('button', { name: /kick/i })[0]);

    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('geocode button is disabled when location is empty', async () => {
    mockAdminGroups.getGroup.mockResolvedValue({
      success: true,
      data: { ...GROUP, location: '' },
    });

    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('Test Group')).toBeInTheDocument();
    });

    await userEvent.click(await screen.findByRole('tab', { name: /location/i }));

    await waitFor(() => {
      const geocodeBtn = screen.getByRole('button', { name: /geocode/i });
      expect(geocodeBtn).toBeDisabled();
    });
  });

  it('shows location address in Location tab', async () => {
    render(<GroupDetail />);
    await waitFor(() => {
      expect(screen.getByText('Test Group')).toBeInTheDocument();
    });

    await userEvent.click(await screen.findByRole('tab', { name: /location/i }));

    await waitFor(() => {
      expect(screen.getByText('Dublin')).toBeInTheDocument();
    });
  });

  it('uses a translated fallback instead of rendering an unknown visibility code', async () => {
    mockAdminGroups.getGroup.mockResolvedValue({
      success: true,
      data: { ...GROUP, visibility: 'future_visibility' },
    });
    render(<GroupDetail />);

    expect(await screen.findByText('Unknown visibility')).toBeInTheDocument();
    expect(screen.queryByText('future_visibility')).not.toBeInTheDocument();
  });

  it('mounts the addressable audit tab for ?tab=audit', async () => {
    window.history.replaceState({}, '', '/admin/groups/42/detail?tab=audit');
    render(<GroupDetail />);

    await waitFor(() => {
      expect(screen.getByTestId('group-audit-log')).toHaveTextContent('Audit group 42');
    });
    expect(screen.getByRole('tab', { name: /audit log/i })).toHaveAttribute('aria-selected', 'true');
  });

  it('replaces an invalid detail tab with the canonical overview URL', async () => {
    window.history.replaceState({}, '', '/admin/groups/42/detail?tab=unknown');
    render(<GroupDetail />);

    await waitFor(() => expect(window.location.search).toBe(''));
    expect(screen.getByRole('tab', { name: /overview/i })).toHaveAttribute('aria-selected', 'true');
  });
});
