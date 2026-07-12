// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── No API calls ─────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── helpers stub ─────────────────────────────────────────────────────────────
vi.mock('@/lib/helpers', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...orig,
    resolveAvatarUrl: (url: string | null) => url ?? '',
    formatDateValue: (d: string) => d,
  };
});

// ─── Stub HeroUI components that misbehave in jsdom ──────────────────────────
vi.mock('@/components/ui/GlassCard', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/components/ui/Dropdown', () => ({
  Dropdown: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="member-dropdown">{children}</div>
  ),
  DropdownTrigger: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="dropdown-trigger">{children}</div>
  ),
  DropdownMenu: ({ children }: { children: React.ReactNode; 'aria-label'?: string }) => (
    <div data-testid="member-dropdown-menu">{children}</div>
  ),
  DropdownItem: ({
    children,
    onPress,
    id,
  }: {
    children: React.ReactNode;
    onPress?: () => void;
    id?: string;
    key?: string;
    className?: string;
    color?: string;
    startContent?: React.ReactNode;
  }) => (
    <button data-testid={`member-action-${id}`} onClick={onPress}>
      {children}
    </button>
  ),
}));

vi.mock('@/components/ui/Button', () => ({
  Button: ({
    children,
    isIconOnly: _isIconOnly,
    isLoading,
    isDisabled,
    onPress,
    'aria-label': ariaLabel,
    ...rest
  }: React.ButtonHTMLAttributes<HTMLButtonElement> & {
    isIconOnly?: boolean;
    isLoading?: boolean;
    isDisabled?: boolean;
    onPress?: () => void;
    variant?: string;
    size?: string;
  }) => (
    <button
      {...rest}
      aria-label={ariaLabel}
      disabled={isDisabled || isLoading}
      data-loading={isLoading ? 'true' : undefined}
      onClick={onPress}
    >
      {isLoading ? <span>Loading...</span> : children}
    </button>
  ),
}));

vi.mock('@/components/ui/Chip', () => ({
  Chip: ({ children, className }: { children: React.ReactNode; className?: string; size?: string; variant?: string; startContent?: React.ReactNode }) => (
    <span data-testid="chip" className={className}>{children}</span>
  ),
}));

vi.mock('@/components/ui/Spinner', () => ({
  Spinner: ({ size }: { size?: string }) => (
    <div role="status" aria-busy="true" data-testid="spinner" data-size={size} />
  ),
}));

vi.mock('@/components/ui/Avatar', () => ({
  Avatar: ({ name, src }: { name?: string; src?: string; size?: string; className?: string }) => (
    <img data-testid="member-avatar" alt={name ?? ''} src={src || undefined} />
  ),
}));

// ─── Stub EmptyState ──────────────────────────────────────────────────────────
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string; icon?: React.ReactNode }) => (
    <div data-testid="empty-state">
      <p>{title}</p>
      {description && <p>{description}</p>}
    </div>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeMember = (overrides: Record<string, unknown> = {}) => ({
  id: 10,
  name: 'Alice Green',
  avatar_url: null,
  avatar: null,
  tagline: 'Gardening enthusiast',
  role: 'member' as const,
  joined_at: '2025-01-15',
  ...overrides,
});

const defaultProps = {
  members: [makeMember()],
  membersLoading: false,
  userIsAdmin: false,
  currentUserId: 99,
  groupOwnerId: 1,
  groupAdminIds: [],
  updatingMember: null,
  membersHasMore: false,
  membersLoadingMore: false,
  onUpdateMemberRole: vi.fn(),
  onRemoveMember: vi.fn(),
  onSearchMembers: vi.fn(),
  onLoadMoreMembers: vi.fn(),
};

describe('GroupMembersTab', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('shows a loading spinner when membersLoading is true', async () => {
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} members={[]} membersLoading={true} />);
    // Multiple role=status elements exist (ToastProvider adds one); find ours by testid
    expect(screen.getByTestId('spinner')).toBeInTheDocument();
    expect(screen.getByTestId('spinner').getAttribute('aria-busy')).toBe('true');
  });

  it('shows the EmptyState when members array is empty', async () => {
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} members={[]} />);
    expect(screen.getByTestId('empty-state')).toBeInTheDocument();
  });

  it('renders member name when members are provided', async () => {
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} />);
    expect(screen.getByText('Alice Green')).toBeInTheDocument();
  });

  it('renders member tagline when present', async () => {
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} />);
    expect(screen.getByText('Gardening enthusiast')).toBeInTheDocument();
  });

  it('renders an avatar for each member', async () => {
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} />);
    expect(screen.getByTestId('member-avatar')).toBeInTheDocument();
  });

  it('shows Owner chip for the group owner', async () => {
    const owner = makeMember({ id: 1, name: 'Group Owner', role: 'member' as const });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} members={[owner]} groupOwnerId={1} />);
    // i18n key 'detail.member_owner' resolves to "Owner" from en/groups.json
    const chips = screen.getAllByTestId('chip');
    const ownerChip = chips.find((c) => c.textContent?.includes('Owner'));
    expect(ownerChip).toBeDefined();
  });

  it('shows Admin chip for a non-owner admin member', async () => {
    const admin = makeMember({ id: 5, name: 'Bob Admin', role: 'admin' as const });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} members={[admin]} groupOwnerId={1} />);
    // i18n key 'detail.member_admin' resolves to "Admin" from en/groups.json
    const chips = screen.getAllByTestId('chip');
    const adminChip = chips.find((c) => c.textContent?.includes('Admin'));
    expect(adminChip).toBeDefined();
  });

  it('does NOT show admin dropdown when userIsAdmin is false', async () => {
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} userIsAdmin={false} />);
    expect(screen.queryByTestId('member-dropdown')).toBeNull();
  });

  it('shows admin dropdown for manageable members when userIsAdmin is true', async () => {
    // member id=10, currentUserId=99, groupOwnerId=1 → canManage=true
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} userIsAdmin={true} />);
    expect(screen.getByTestId('member-dropdown')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Manage Alice Green' })).toHaveClass('min-h-11', 'min-w-11');
  });

  it('uses server capabilities to hide forbidden role actions', async () => {
    const member = makeMember({
      capabilities: { can_change_role: false, can_remove: true },
    });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} members={[member]} userIsAdmin />);

    expect(screen.queryByTestId('member-action-promote')).not.toBeInTheDocument();
    expect(screen.getByTestId('member-action-remove')).toBeInTheDocument();
  });

  it('hides the action menu when the server grants no member-management capability', async () => {
    const admin = makeMember({
      id: 5,
      role: 'admin' as const,
      capabilities: { can_change_role: false, can_remove: false },
    });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} members={[admin]} userIsAdmin />);

    expect(screen.queryByTestId('member-dropdown')).not.toBeInTheDocument();
  });

  it('does not show dropdown for the current user themselves', async () => {
    const self = makeMember({ id: 99, name: 'Self User' });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(
      <GroupMembersTab
        {...defaultProps}
        members={[self]}
        currentUserId={99}
        userIsAdmin={true}
      />
    );
    expect(screen.queryByTestId('member-dropdown')).toBeNull();
  });

  it('does not show dropdown for the group owner', async () => {
    const owner = makeMember({ id: 1, name: 'Owner' });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(
      <GroupMembersTab
        {...defaultProps}
        members={[owner]}
        groupOwnerId={1}
        currentUserId={99}
        userIsAdmin={true}
      />
    );
    expect(screen.queryByTestId('member-dropdown')).toBeNull();
  });

  it('calls onUpdateMemberRole with "admin" when promote action is clicked', async () => {
    const onUpdateMemberRole = vi.fn();
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(
      <GroupMembersTab
        {...defaultProps}
        userIsAdmin={true}
        onUpdateMemberRole={onUpdateMemberRole}
      />
    );

    const promoteBtn = screen.getByTestId('member-action-promote');
    await userEvent.click(promoteBtn);

    await waitFor(() => {
      expect(onUpdateMemberRole).toHaveBeenCalledWith(10, 'admin');
    });
  });

  it('calls onRemoveMember when remove action is clicked', async () => {
    const onRemoveMember = vi.fn();
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(
      <GroupMembersTab
        {...defaultProps}
        userIsAdmin={true}
        onRemoveMember={onRemoveMember}
      />
    );

    const removeBtn = screen.getByTestId('member-action-remove');
    await userEvent.click(removeBtn);

    await waitFor(() => {
      expect(onRemoveMember).toHaveBeenCalledWith(10);
    });
  });

  it('shows demote action for an admin member', async () => {
    const admin = makeMember({ id: 5, name: 'Admin Member', role: 'admin' as const });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(
      <GroupMembersTab
        {...defaultProps}
        members={[admin]}
        userIsAdmin={true}
        groupOwnerId={1}
        currentUserId={99}
      />
    );
    expect(screen.getByTestId('member-action-demote')).toBeInTheDocument();
  });

  it('calls onUpdateMemberRole with "member" when demote action is clicked', async () => {
    const onUpdateMemberRole = vi.fn();
    const admin = makeMember({ id: 5, name: 'Admin Member', role: 'admin' as const });
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(
      <GroupMembersTab
        {...defaultProps}
        members={[admin]}
        userIsAdmin={true}
        groupOwnerId={1}
        currentUserId={99}
        onUpdateMemberRole={onUpdateMemberRole}
      />
    );

    await userEvent.click(screen.getByTestId('member-action-demote'));

    await waitFor(() => {
      expect(onUpdateMemberRole).toHaveBeenCalledWith(5, 'member');
    });
  });

  it('renders multiple members correctly', async () => {
    const members = [
      makeMember({ id: 10, name: 'Alice Green' }),
      makeMember({ id: 11, name: 'Bob Smith', tagline: 'Coder' }),
      makeMember({ id: 12, name: 'Carol Day', tagline: '' }),
    ];
    const { GroupMembersTab } = await import('./GroupMembersTab');
    render(<GroupMembersTab {...defaultProps} members={members} />);
    expect(screen.getByText('Alice Green')).toBeInTheDocument();
    expect(screen.getByText('Bob Smith')).toBeInTheDocument();
    expect(screen.getByText('Carol Day')).toBeInTheDocument();
    expect(screen.getAllByTestId('member-avatar').length).toBe(3);
  });

  it('debounces server-backed search and never filters the current page as if it were complete', async () => {
    const members = [
      makeMember({ id: 10, name: 'Alice Green', tagline: 'Gardener' }),
      makeMember({ id: 11, name: 'Bob Smith', tagline: 'Software developer' }),
    ];
    const onSearchMembers = vi.fn();
    const { GroupMembersTab } = await import('./GroupMembersTab');
    const { rerender } = render(
      <GroupMembersTab {...defaultProps} members={members} onSearchMembers={onSearchMembers} />,
    );

    const search = screen.getByRole('searchbox', { name: 'Search group members' });
    await userEvent.type(search, 'developer');

    expect(screen.getByText('Alice Green')).toBeInTheDocument();
    expect(screen.getByText('Bob Smith')).toBeInTheDocument();
    await waitFor(() => expect(onSearchMembers).toHaveBeenCalledWith('developer'));

    rerender(
      <GroupMembersTab {...defaultProps} members={[]} onSearchMembers={onSearchMembers} />,
    );
    expect(screen.getByText('No matching members')).toBeInTheDocument();
    expect(screen.getByText('No members match “developer”.')).toBeInTheDocument();
  });

  it('exposes cursor-backed load more and renders member 21 after an append', async () => {
    const members = Array.from({ length: 20 }, (_, index) => makeMember({
      id: index + 1,
      name: `Member ${index + 1}`,
      tagline: '',
    }));
    const onLoadMoreMembers = vi.fn();
    const { GroupMembersTab } = await import('./GroupMembersTab');
    const { rerender } = render(
      <GroupMembersTab
        {...defaultProps}
        members={members}
        membersHasMore
        onLoadMoreMembers={onLoadMoreMembers}
      />,
    );

    await userEvent.click(screen.getByRole('button', { name: 'Load more members' }));
    expect(onLoadMoreMembers).toHaveBeenCalledOnce();

    rerender(
      <GroupMembersTab
        {...defaultProps}
        members={[...members, makeMember({ id: 21, name: 'Member 21', tagline: '' })]}
        membersHasMore={false}
        onLoadMoreMembers={onLoadMoreMembers}
      />,
    );
    expect(screen.getByText('Member 21')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Load more members' })).not.toBeInTheDocument();
  });
});
