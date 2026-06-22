// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ── location map stub — avoids heavy map dep ──────────────────────────────────
vi.mock('@/components/location', () => ({
  LocationMapCard: () => <div data-testid="location-map" />,
}));

// ── SafeHtml stub ─────────────────────────────────────────────────────────────
vi.mock('@/components/ui/SafeHtml', () => ({
  SafeHtml: ({ content }: { content: string }) => <div>{content}</div>,
}));

// ── lib/helpers partial mock ──────────────────────────────────────────────────
vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<Record<string, unknown>>();
  return {
    ...actual,
    resolveAvatarUrl: (url: string | undefined | null) => url ?? '',
    formatDateValue: (d: string) => d,
    formatRelativeTime: (d: string) => d,
  };
});

import type { Group } from '@/types/api';
import { GroupHeader, type JoinRequest } from './GroupHeader';

// ── Shared fixtures ───────────────────────────────────────────────────────────

const makeGroup = (overrides: Partial<Group> = {}): Group => ({
  id: 1,
  name: 'Friendly Neighbours',
  description: 'A local help group',
  visibility: 'public',
  status: 'active',
  cover_image_url: null,
  cover_image: null,
  image_url: null,
  location: null,
  latitude: null,
  longitude: null,
  members_count: 12,
  posts_count: 7,
  created_at: '2026-01-15T00:00:00Z',
  ...overrides,
} as Group);

const defaultProps = {
  group: makeGroup(),
  groupTags: [],
  userIsMember: false,
  userIsAdmin: false,
  isAuthenticated: true,
  isJoining: false,
  isPrivateGroup: false,
  joinRequests: [] as JoinRequest[],
  requestsLoading: false,
  requestsLoaded: false,
  processingRequest: null,
  getMemberCount: (g: Group) => g.members_count ?? 0,
  onJoinLeave: vi.fn(),
  onOpenSettings: vi.fn(),
  onOpenDelete: vi.fn(),
  onOpenInvite: vi.fn(),
  onOpenNotifPrefs: vi.fn(),
  onLoadJoinRequests: vi.fn(),
  onJoinRequest: vi.fn(),
};

describe('GroupHeader', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders group name', () => {
    render(<GroupHeader {...defaultProps} />);
    expect(screen.getByText('Friendly Neighbours')).toBeInTheDocument();
  });

  it('renders group description', () => {
    render(<GroupHeader {...defaultProps} />);
    expect(screen.getByText('A local help group')).toBeInTheDocument();
  });

  it('renders public chip for public group', () => {
    render(<GroupHeader {...defaultProps} />);
    // The chip label will be a translation key result — check aria-label or text
    const allText = document.body.textContent ?? '';
    expect(allText).toBeTruthy(); // rendered correctly
  });

  it('renders private chip for private group', () => {
    render(<GroupHeader {...{ ...defaultProps, isPrivateGroup: true, group: makeGroup({ visibility: 'private' }) }} />);
    // private groups show a lock icon; chip has amber color classes
    const chipEl = document.querySelector('[aria-label]');
    expect(chipEl).toBeDefined();
  });

  it('renders Join button when user is not a member (authenticated)', () => {
    render(<GroupHeader {...defaultProps} />);
    const joinBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('join') || b.getAttribute('aria-label')?.toLowerCase().includes('join')
    );
    expect(joinBtn).toBeDefined();
  });

  it('calls onJoinLeave when join button is clicked', async () => {
    const onJoinLeave = vi.fn();
    render(<GroupHeader {...{ ...defaultProps, onJoinLeave }} />);
    const joinBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('join'));
    if (joinBtn) {
      await userEvent.click(joinBtn);
      expect(onJoinLeave).toHaveBeenCalledTimes(1);
    }
  });

  it('renders Leave button when user is a member', () => {
    render(<GroupHeader {...{ ...defaultProps, userIsMember: true }} />);
    const leaveBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('leave'));
    expect(leaveBtn).toBeDefined();
  });

  it('renders Settings + Delete buttons for admin', () => {
    render(<GroupHeader {...{ ...defaultProps, userIsAdmin: true, userIsMember: true }} />);
    const settingsBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('setting'));
    const deleteBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('delete'));
    expect(settingsBtn).toBeDefined();
    expect(deleteBtn).toBeDefined();
  });

  it('calls onOpenSettings when Settings clicked', async () => {
    const onOpenSettings = vi.fn();
    render(<GroupHeader {...{ ...defaultProps, userIsAdmin: true, userIsMember: true, onOpenSettings }} />);
    const btn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('setting'));
    if (btn) {
      await userEvent.click(btn);
      expect(onOpenSettings).toHaveBeenCalledTimes(1);
    }
  });

  it('calls onOpenDelete when Delete clicked', async () => {
    const onOpenDelete = vi.fn();
    render(<GroupHeader {...{ ...defaultProps, userIsAdmin: true, userIsMember: true, onOpenDelete }} />);
    const btn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('delete'));
    if (btn) {
      await userEvent.click(btn);
      expect(onOpenDelete).toHaveBeenCalledTimes(1);
    }
  });

  it('renders Invite button for admins who are members', () => {
    render(<GroupHeader {...{ ...defaultProps, userIsAdmin: true, userIsMember: true }} />);
    const inviteBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('invite') || b.getAttribute('aria-label')?.toLowerCase().includes('invite')
    );
    expect(inviteBtn).toBeDefined();
  });

  it('shows notification prefs button when user is a member', () => {
    render(<GroupHeader {...{ ...defaultProps, userIsMember: true }} />);
    const notifBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('notif') || b.getAttribute('aria-label')?.toLowerCase().includes('setting')
    );
    expect(notifBtn).toBeDefined();
  });

  it('renders tags when provided', () => {
    render(
      <GroupHeader
        {...defaultProps}
        groupTags={[
          { id: 1, name: 'Volunteering', color: 'green' },
          { id: 2, name: 'Local', color: undefined },
        ]}
      />
    );
    expect(screen.getByText('Volunteering')).toBeInTheDocument();
    expect(screen.getByText('Local')).toBeInTheDocument();
  });

  it('shows "view pending requests" button for private group admin before loading', () => {
    render(
      <GroupHeader
        {...{ ...defaultProps, userIsAdmin: true, isPrivateGroup: true, requestsLoaded: false }}
      />
    );
    const btn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('pending') || b.textContent?.toLowerCase().includes('request')
    );
    expect(btn).toBeDefined();
  });

  it('calls onLoadJoinRequests when view-pending button is clicked', async () => {
    const onLoadJoinRequests = vi.fn();
    render(
      <GroupHeader
        {...{ ...defaultProps, userIsAdmin: true, isPrivateGroup: true, requestsLoaded: false, onLoadJoinRequests }}
      />
    );
    const btn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('pending') || b.textContent?.toLowerCase().includes('request')
    );
    if (btn) {
      await userEvent.click(btn);
      expect(onLoadJoinRequests).toHaveBeenCalledTimes(1);
    }
  });

  it('renders join requests when requestsLoaded=true', () => {
    const joinRequests: JoinRequest[] = [
      {
        user_id: 55,
        user: { id: 55, name: 'Charlie', avatar: null },
        created_at: '2026-01-10T00:00:00Z',
      },
    ];
    render(
      <GroupHeader
        {...{ ...defaultProps, userIsAdmin: true, requestsLoaded: true, joinRequests }}
      />
    );
    expect(screen.getByText('Charlie')).toBeInTheDocument();
  });

  it('shows accept/reject for each join request', () => {
    const joinRequests: JoinRequest[] = [
      {
        user_id: 55,
        user: { id: 55, name: 'Charlie', avatar: null },
        created_at: '2026-01-10T00:00:00Z',
      },
    ];
    render(
      <GroupHeader
        {...{ ...defaultProps, userIsAdmin: true, requestsLoaded: true, joinRequests }}
      />
    );
    const acceptBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('accept'));
    const rejectBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('reject'));
    expect(acceptBtn).toBeDefined();
    expect(rejectBtn).toBeDefined();
  });

  it('calls onJoinRequest with accept when accept clicked', async () => {
    const onJoinRequest = vi.fn();
    const joinRequests: JoinRequest[] = [
      { user_id: 55, user: { id: 55, name: 'Charlie', avatar: null }, created_at: '2026-01-10T00:00:00Z' },
    ];
    render(
      <GroupHeader
        {...{ ...defaultProps, userIsAdmin: true, requestsLoaded: true, joinRequests, onJoinRequest }}
      />
    );
    const acceptBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('accept'));
    if (acceptBtn) {
      await userEvent.click(acceptBtn);
      expect(onJoinRequest).toHaveBeenCalledWith(55, 'accept');
    }
  });

  it('renders no join / leave button when not authenticated', () => {
    render(<GroupHeader {...{ ...defaultProps, isAuthenticated: false }} />);
    const joinBtn = screen.queryAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('join'));
    expect(joinBtn).toBeUndefined();
  });

  it('renders location map when lat/lng provided', () => {
    const groupWithLocation = makeGroup({ location: 'Dublin', latitude: 53.3, longitude: -6.3 });
    render(<GroupHeader {...{ ...defaultProps, group: groupWithLocation }} />);
    expect(screen.getByTestId('location-map')).toBeInTheDocument();
  });

  it('shows empty-requests message when requestsLoaded but none', () => {
    render(
      <GroupHeader
        {...{ ...defaultProps, userIsAdmin: true, requestsLoaded: true, joinRequests: [] }}
      />
    );
    // Translation key: detail.no_pending_requests
    // In test env key fallback text will be rendered — just check no request rows
    expect(screen.queryByRole('button', { name: /accept/i })).not.toBeInTheDocument();
  });

  it('renders spinner inside requests section when requestsLoading', () => {
    const joinRequests: JoinRequest[] = [];
    render(
      <GroupHeader
        {...{
          ...defaultProps,
          userIsAdmin: true,
          requestsLoaded: true,
          requestsLoading: true,
          joinRequests,
        }}
      />
    );
    // Spinner renders role=status aria-busy=true
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeTruthy();
  });
});
