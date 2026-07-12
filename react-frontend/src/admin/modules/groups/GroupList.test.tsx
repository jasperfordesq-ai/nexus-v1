// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { fireEvent, render, screen, waitFor, within } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';
import type { AdminGroup } from '@/admin/api/types';

// ── Hoist mock data ───────────────────────────────────────────────────────────
const { mockAdminGroups, mockApi } = vi.hoisted(() => ({
  mockAdminGroups: {
    list: vi.fn(),
    delete: vi.fn(),
    updateStatus: vi.fn(),
  },
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

// ── Mock adminApi ─────────────────────────────────────────────────────────────
vi.mock('@/admin/api/adminApi', () => ({
  adminGroups: mockAdminGroups,
  default: { adminGroups: mockAdminGroups },
}));

// ── Mock api ──────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

// ── Contexts ──────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return { ...orig, useNavigate: () => mockNavigate };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── Stub heavy children ───────────────────────────────────────────────────────
vi.mock('../../components/DataTable', () => ({
  DataTable: ({
    columns,
    data,
    isLoading,
    onSearch,
    selectedKeys = new Set<string>(),
    onSelectionChange,
  }: {
    columns: Array<{ key: string; render?: (item: AdminGroup) => React.ReactNode }>;
    data: AdminGroup[];
    isLoading: boolean;
    onSearch?: (q: string) => void;
    selectedKeys?: Set<string>;
    onSelectionChange?: (keys: Set<string>) => void;
  }) => (
    <div data-testid="data-table" data-loading={String(isLoading)}>
      {data.map((group) => (
        <div key={group.id} data-testid="group-row">
          <button
            type="button"
            aria-label={`Select ${group.name}`}
            aria-pressed={selectedKeys.has(String(group.id))}
            onClick={() => {
              const next = new Set(selectedKeys);
              const key = String(group.id);
              if (next.has(key)) next.delete(key);
              else next.add(key);
              onSelectionChange?.(next);
            }}
          >
            Select
          </button>
          {columns.map((column) => (
            <div key={column.key} data-testid={`cell-${group.id}-${column.key}`}>
              {column.render?.(group)}
            </div>
          ))}
        </div>
      ))}
      {onSearch && (
        <input data-testid="search-input" onChange={(e) => onSearch(e.target.value)} />
      )}
    </div>
  ),
}));

vi.mock('../../components/PageHeader', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

vi.mock('../../components/ConfirmModal', () => ({
  ConfirmModal: ({ isOpen, onConfirm, onClose, title }: { isOpen: boolean; onConfirm: () => void; onClose: () => void; title: string }) =>
    isOpen ? (
      <div role="dialog" aria-label={title}>
        <button onClick={onConfirm}>Confirm</button>
        <button onClick={onClose}>Cancel</button>
      </div>
    ) : null,
}));

vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return { ...actual, resolveAssetUrl: (u: string) => u };
});

// ── Fixtures ──────────────────────────────────────────────────────────────────
const makeGroup = (overrides: Partial<AdminGroup> = {}): AdminGroup => ({
  id: 1,
  name: 'Test Group',
  description: 'A description',
  status: 'active' as const,
  visibility: 'public' as const,
  member_count: 10,
  creator_name: 'Alice',
  created_at: '2025-01-01T00:00:00Z',
  image_url: null,
  ...overrides,
});

const makeListResponse = (groups: AdminGroup[] = []) => ({
  success: true,
  data: groups,
  meta: { total: groups.length },
});

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('GroupList', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminGroups.list.mockResolvedValue(makeListResponse());
  });

  it('shows loading state initially', async () => {
    mockAdminGroups.list.mockImplementationOnce(() => new Promise(() => {}));
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);
    const table = screen.getByTestId('data-table');
    expect(table.getAttribute('data-loading')).toBe('true');
  });

  it('renders group rows when data loads', async () => {
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup({ name: 'Alpha Group' })]));
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);
    await waitFor(() => {
      expect(screen.getByText('Alpha Group')).toBeInTheDocument();
    });
  });

  it('renders empty table when no groups returned', async () => {
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);
    await waitFor(() => {
      const table = screen.getByTestId('data-table');
      expect(table.getAttribute('data-loading')).toBe('false');
      expect(screen.queryAllByTestId('group-row')).toHaveLength(0);
    });
  });

  it('shows error toast when list API fails', async () => {
    mockAdminGroups.list.mockRejectedValue(new Error('network error'));
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('requests the first page when the list mounts', async () => {
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup()]));

    const { GroupList } = await import('./GroupList');
    render(<GroupList />);

    await waitFor(() => expect(screen.getByText('Test Group')).toBeInTheDocument());

    expect(mockAdminGroups.list).toHaveBeenCalledWith(
      expect.objectContaining({ page: 1 })
    );
  });

  it('navigates to analytics when Analytics button is clicked', async () => {
    const user = userEvent.setup();
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);
    await waitFor(() => screen.getByTestId('data-table'));

    await user.click(screen.getByRole('button', { name: 'Analytics' }));

    expect(mockNavigate).toHaveBeenCalledWith('/test/admin/groups/analytics');
  });

  it('navigates to approvals when Approvals button is clicked', async () => {
    const user = userEvent.setup();
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);
    await waitFor(() => screen.getByTestId('data-table'));

    await user.click(screen.getByRole('button', { name: 'Approvals' }));

    expect(mockNavigate).toHaveBeenCalledWith('/test/admin/groups/approvals');
  });

  it('reloads list when API returns array format (legacy)', async () => {
    mockAdminGroups.list.mockResolvedValue({
      success: true,
      data: [makeGroup({ name: 'Legacy Group' })],
      meta: { total: 1 },
    });
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);
    await waitFor(() => {
      expect(screen.getByText('Legacy Group')).toBeInTheDocument();
    });
  });

  it('exposes the canonical status tabs and their exact API filters', async () => {
    const user = userEvent.setup();
    const { GroupList, GROUP_STATUS_TABS } = await import('./GroupList');
    render(<GroupList />);

    const expected: Array<[typeof GROUP_STATUS_TABS[number], string]> = [
      ['all', 'All'],
      ['pending_review', 'Pending review'],
      ['active', 'Active'],
      ['dormant', 'Dormant'],
      ['archived', 'Archived'],
      ['rejected', 'Rejected'],
    ];

    expect(GROUP_STATUS_TABS).toEqual(expected.map(([key]) => key));
    for (const [key, label] of expected) {
      const tab = screen.getByRole('tab', { name: label });
      await user.click(tab);
      await waitFor(() => {
        expect(mockAdminGroups.list).toHaveBeenLastCalledWith({
          page: 1,
          search: undefined,
          status: key === 'all' ? undefined : key,
        });
      });
    }
  });

  it('maps every canonical lifecycle status to a valid transition', async () => {
    const { getGroupStatusTransition } = await import('./GroupList');

    expect(getGroupStatusTransition('pending_review')).toEqual({
      target: 'active',
      labelKey: 'groups.transition_pending_review_to_active',
    });
    expect(getGroupStatusTransition('active')).toEqual({
      target: 'dormant',
      labelKey: 'groups.transition_active_to_dormant',
    });
    expect(getGroupStatusTransition('dormant')).toEqual({
      target: 'active',
      labelKey: 'groups.transition_dormant_to_active',
    });
    expect(getGroupStatusTransition('archived')).toEqual({
      target: 'active',
      labelKey: 'groups.transition_archived_to_active',
    });
    expect(getGroupStatusTransition('rejected')).toEqual({
      target: 'pending_review',
      labelKey: 'groups.transition_rejected_to_pending_review',
    });
  });

  it.each([
    ['pending_review', 'Approve and activate', 'active'],
    ['active', 'Set as dormant', 'dormant'],
    ['dormant', 'Reactivate', 'active'],
    ['archived', 'Restore to active', 'active'],
    ['rejected', 'Return to review', 'pending_review'],
  ] as const)(
    'reports a resolved %s transition failure without claiming success',
    async (status, actionLabel, target) => {
    const user = userEvent.setup();
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup({ status })]));
    mockAdminGroups.updateStatus.mockResolvedValue({ success: false, error: 'Transition blocked' });
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);

    await user.click(await screen.findByRole('button', { name: 'Actions for Test Group' }));
    await user.click(await screen.findByRole('menuitem', { name: actionLabel }));

    await waitFor(() => {
      expect(mockAdminGroups.updateStatus).toHaveBeenCalledWith(1, target);
      expect(mockToast.error).toHaveBeenCalledWith('Transition blocked');
    });
    expect(mockToast.success).not.toHaveBeenCalled();
    },
  );

  it('reports a resolved archive failure and never offers archive for terminal statuses', async () => {
    const user = userEvent.setup();
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup()]));
    mockAdminGroups.updateStatus.mockResolvedValue({ success: false, error: 'Archive blocked' });
    const { GroupList } = await import('./GroupList');
    const view = render(<GroupList />);

    await user.click(await screen.findByRole('button', { name: 'Actions for Test Group' }));
    await user.click(await screen.findByRole('menuitem', { name: 'Archive' }));
    await waitFor(() => {
      expect(mockAdminGroups.updateStatus).toHaveBeenCalledWith(1, 'archived');
      expect(mockToast.error).toHaveBeenCalledWith('Archive blocked');
    });
    expect(mockToast.success).not.toHaveBeenCalled();

    view.unmount();
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup({ status: 'archived' })]));
    render(<GroupList />);
    await user.click(await screen.findByRole('button', { name: 'Actions for Test Group' }));
    expect(screen.queryByRole('menuitem', { name: 'Archive' })).not.toBeInTheDocument();
  });

  it('uses the canonical edit route and exposes no clone action', async () => {
    const user = userEvent.setup();
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup()]));
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);

    await user.click(await screen.findByRole('button', { name: 'Actions for Test Group' }));
    expect(screen.queryByRole('menuitem', { name: /clone/i })).not.toBeInTheDocument();
    await user.click(await screen.findByRole('menuitem', { name: 'Edit Group' }));
    expect(mockNavigate).toHaveBeenCalledWith('/test/groups/edit/1');
  });

  it('requires the exact case-sensitive group name and keeps a failed delete dialog open', async () => {
    const user = userEvent.setup();
    let resolveDelete: ((value: { success: false; error: string }) => void) | undefined;
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup()]));
    mockAdminGroups.delete.mockImplementation(() => new Promise((resolve) => {
      resolveDelete = resolve;
    }));
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);

    await user.click(await screen.findByRole('button', { name: 'Actions for Test Group' }));
    await user.click(await screen.findByRole('menuitem', { name: 'Delete' }));

    const dialog = await screen.findByRole('alertdialog', { name: 'Delete Group' });
    const input = within(dialog).getByRole('textbox', { name: 'Group name' });
    const deleteButton = within(dialog).getByRole('button', { name: 'Delete' });
    expect(deleteButton).toBeDisabled();
    fireEvent.change(input, { target: { value: 'test group' } });
    expect(deleteButton).toBeDisabled();
    fireEvent.change(input, { target: { value: 'Test Group' } });
    expect(deleteButton).toBeEnabled();

    await user.click(deleteButton);
    expect(within(dialog).getByRole('button', { name: 'Cancel' })).toBeDisabled();
    expect(input).toBeDisabled();
    resolveDelete?.({ success: false, error: 'Delete blocked' });

    await waitFor(() => expect(mockToast.error).toHaveBeenCalledWith('Delete blocked'));
    expect(screen.getByRole('alertdialog', { name: 'Delete Group' })).toBeInTheDocument();
    expect(mockToast.success).not.toHaveBeenCalled();
  });

  it('keeps only failed ids selected after a partially successful typed bulk delete', async () => {
    const user = userEvent.setup();
    const groups = [
      makeGroup({ id: 1, name: 'Alpha Group' }),
      makeGroup({ id: 2, name: 'Beta Group' }),
    ];
    mockAdminGroups.list.mockResolvedValue(makeListResponse(groups));
    mockAdminGroups.delete.mockImplementation(async (id: number) => (
      id === 1 ? { success: true } : { success: false, error: 'Beta retained' }
    ));
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);

    await user.click(await screen.findByRole('button', { name: 'Select Alpha Group' }));
    await user.click(screen.getByRole('button', { name: 'Select Beta Group' }));
    expect(screen.getByText('2 selected')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Delete' }));

    const dialog = await screen.findByRole('alertdialog', { name: 'Delete selected groups' });
    const input = within(dialog).getByRole('textbox', { name: 'Confirmation phrase' });
    const deleteButton = within(dialog).getByRole('button', { name: 'Delete' });
    expect(deleteButton).toBeDisabled();
    await user.type(input, 'DELETE 2 GROUPS');
    await user.click(deleteButton);

    await waitFor(() => expect(screen.getByText('1 selected')).toBeInTheDocument());
    expect(mockAdminGroups.delete.mock.calls.map(([id]) => id)).toEqual([1, 2]);
    expect(screen.getByRole('button', { name: 'Select Alpha Group' })).toHaveAttribute('aria-pressed', 'false');
    expect(screen.getByRole('button', { name: 'Select Beta Group' })).toHaveAttribute('aria-pressed', 'true');
    expect(mockToast.error).toHaveBeenCalledWith('Beta retained');
    expect(screen.queryByRole('alertdialog', { name: 'Delete selected groups' })).not.toBeInTheDocument();
  });

  it('reports a resolved bulk archive failure without clearing selection', async () => {
    const user = userEvent.setup();
    mockAdminGroups.list.mockResolvedValue(makeListResponse([makeGroup()]));
    mockApi.post.mockResolvedValue({ success: false, error: 'Bulk archive blocked' });
    const { GroupList } = await import('./GroupList');
    render(<GroupList />);

    await user.click(await screen.findByRole('button', { name: 'Select Test Group' }));
    await user.click(screen.getByRole('button', { name: 'Archive' }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/admin/groups/bulk-archive', { group_ids: [1] });
      expect(mockToast.error).toHaveBeenCalledWith('Bulk archive blocked');
    });
    expect(screen.getByText('1 selected')).toBeInTheDocument();
    expect(mockToast.success).not.toHaveBeenCalled();
  });
});
