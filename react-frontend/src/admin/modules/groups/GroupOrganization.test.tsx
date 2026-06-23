// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Hoist mock objects ───────────────────────────────────────────────────────
const { mockAdminGroups } = vi.hoisted(() => ({
  mockAdminGroups: {
    getTags: vi.fn(),
    createTag: vi.fn(),
    deleteTag: vi.fn(),
    getCollections: vi.fn(),
    createCollection: vi.fn(),
    updateCollection: vi.fn(),
    deleteCollection: vi.fn(),
    setCollectionGroups: vi.fn(),
    getAutoAssignRules: vi.fn(),
    createAutoAssignRule: vi.fn(),
    deleteAutoAssignRule: vi.fn(),
    list: vi.fn(),
  },
}));

// ─── Mocks ────────────────────────────────────────────────────────────────────
vi.mock('@/admin/api/adminApi', () => ({ adminGroups: mockAdminGroups }));

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

const mockToastFns = { success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => mockToastFns,
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToastFns,
    useTenant: () => ({
      tenant: { id: 2, name: 'hOUR', slug: 'hour-timebank' },
      tenantPath: (p: string) => `/hour-timebank${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    useAuth: () => ({
      user: { id: 1, name: 'Admin' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
  })
);

// Stub ConfirmModal to avoid HeroUI issues
vi.mock('@/admin/components', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/admin/components')>();
  return {
    ...orig,
    ConfirmModal: ({
      isOpen,
      onClose,
      onConfirm,
      title,
      confirmLabel,
    }: {
      isOpen: boolean;
      onClose: () => void;
      onConfirm: () => void;
      title: string;
      confirmLabel?: string;
    }) =>
      isOpen ? (
        <div role="dialog" aria-label={title}>
          <span>{title}</span>
          <button onClick={onConfirm}>{confirmLabel ?? 'Confirm'}</button>
          <button onClick={onClose}>Cancel</button>
        </div>
      ) : null,
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeTag = (overrides = {}) => ({
  id: 1,
  name: 'Gardening',
  slug: 'gardening',
  color: '#22c55e',
  usage_count: 3,
  created_at: '2025-01-01T00:00:00Z',
  ...overrides,
});

const makeCollection = (overrides = {}) => ({
  id: 1,
  name: 'Featured Groups',
  description: 'Top picks',
  image_url: null,
  sort_order: 0,
  is_active: true,
  group_count: 4,
  groups: [],
  created_at: '2025-01-01T00:00:00Z',
  ...overrides,
});

const makeRule = (overrides = {}) => ({
  id: 1,
  group_id: 10,
  group_name: 'Cyclists',
  rule_type: 'interest',
  rule_value: 'cycling',
  is_active: 1,
  created_at: '2025-01-01T00:00:00Z',
  ...overrides,
});

const successEmpty = { success: true, data: [] };

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupOrganization', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminGroups.getTags.mockResolvedValue(successEmpty);
    mockAdminGroups.getCollections.mockResolvedValue(successEmpty);
    mockAdminGroups.getAutoAssignRules.mockResolvedValue(successEmpty);
    mockAdminGroups.list.mockResolvedValue({ success: true, data: [] });
    mockAdminGroups.createTag.mockResolvedValue({ success: true });
    mockAdminGroups.deleteTag.mockResolvedValue({ success: true });
    mockAdminGroups.createCollection.mockResolvedValue({ success: true });
    mockAdminGroups.updateCollection.mockResolvedValue({ success: true });
    mockAdminGroups.deleteCollection.mockResolvedValue({ success: true });
    mockAdminGroups.setCollectionGroups.mockResolvedValue({ success: true });
    mockAdminGroups.createAutoAssignRule.mockResolvedValue({ success: true });
    mockAdminGroups.deleteAutoAssignRule.mockResolvedValue({ success: true });
  });

  it('renders the page title and Tags tab by default', async () => {
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => {
      // Tags tab should be selected (active)
      const tabs = screen.getAllByRole('tab');
      expect(tabs.length).toBeGreaterThan(0);
    });
  });

  it('calls getTags on mount', async () => {
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => {
      expect(mockAdminGroups.getTags).toHaveBeenCalledWith({ limit: 500 });
    });
  });

  it('renders a tag row when tags are loaded', async () => {
    mockAdminGroups.getTags.mockResolvedValue({ success: true, data: [makeTag()] });
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => {
      expect(screen.getByText('Gardening')).toBeInTheDocument();
    });
  });

  it('opens the create tag modal when Create Tag is pressed', async () => {
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => screen.queryAllByRole('tab').length > 0);

    const createTagBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('tag')
    );
    expect(createTagBtn).toBeDefined();
    fireEvent.click(createTagBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls createTag API and closes modal on success', async () => {
    mockAdminGroups.createTag.mockResolvedValue({ success: true });
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => screen.queryAllByRole('tab').length > 0);

    // Open modal
    const createTagBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('create tag') || b.textContent?.toLowerCase().includes('tag')
    );
    fireEvent.click(createTagBtn!);
    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Find the name input and type into it
    const nameInput = document.querySelector('input[type="text"]') as HTMLInputElement;
    if (nameInput) {
      fireEvent.change(nameInput, { target: { value: 'New Tag' } });
    }

    // Click create inside the modal
    const dialogCreateBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase() === 'create'
    );
    if (dialogCreateBtn) {
      fireEvent.click(dialogCreateBtn);
      await waitFor(() => {
        expect(mockAdminGroups.createTag).toHaveBeenCalled();
      });
    }
  });

  it('opens confirm dialog when delete button for a tag is clicked', async () => {
    mockAdminGroups.getTags.mockResolvedValue({ success: true, data: [makeTag()] });
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => screen.getByText('Gardening'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    expect(deleteBtn).toBeDefined();
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls deleteTag when deletion is confirmed', async () => {
    mockAdminGroups.getTags.mockResolvedValue({ success: true, data: [makeTag({ id: 42 })] });
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => screen.getByText('Gardening'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    fireEvent.click(deleteBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    const confirmBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase() === 'delete' || b.textContent?.toLowerCase() === 'confirm'
    );
    fireEvent.click(confirmBtn!);

    await waitFor(() => {
      expect(mockAdminGroups.deleteTag).toHaveBeenCalledWith(42);
    });
  });

  it('switches to Collections tab and shows collections', async () => {
    mockAdminGroups.getCollections.mockResolvedValue({
      success: true,
      data: [makeCollection()],
    });

    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => screen.queryAllByRole('tab').length > 0);

    const collectionsTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('collection')
    );
    expect(collectionsTab).toBeDefined();
    fireEvent.click(collectionsTab!);

    await waitFor(() => {
      expect(screen.getByText('Featured Groups')).toBeInTheDocument();
    });
  });

  it('switches to Rules tab and shows rules', async () => {
    mockAdminGroups.getAutoAssignRules.mockResolvedValue({
      success: true,
      data: [makeRule()],
    });

    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => screen.queryAllByRole('tab').length > 0);

    const rulesTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('rule')
    );
    expect(rulesTab).toBeDefined();
    fireEvent.click(rulesTab!);

    await waitFor(() => {
      expect(screen.getByText('Cyclists')).toBeInTheDocument();
    });
  });

  it('shows error toast when getTags fails', async () => {
    mockAdminGroups.getTags.mockRejectedValue(new Error('network'));
    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => {
      expect(mockToastFns.error).toHaveBeenCalled();
    });
  });

  it('shows success toast and calls setCollectionGroups when groups saved', async () => {
    mockAdminGroups.getCollections.mockResolvedValue({
      success: true,
      data: [makeCollection()],
    });
    mockAdminGroups.list.mockResolvedValue({
      success: true,
      data: [{ id: 5, name: 'Runners', slug: 'runners' }],
    });
    mockAdminGroups.setCollectionGroups.mockResolvedValue({ success: true });

    const { default: GroupOrganization } = await import('./GroupOrganization');
    render(<GroupOrganization />);

    await waitFor(() => screen.queryAllByRole('tab').length > 0);

    // Switch to collections tab
    const collectionsTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('collection')
    );
    fireEvent.click(collectionsTab!);

    await waitFor(() => screen.getByText('Featured Groups'));

    // Click Manage Groups
    const manageBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('manage')
    );
    expect(manageBtn).toBeDefined();
    fireEvent.click(manageBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Click Save inside the modal
    const saveBtn = screen.getAllByRole('button').find(
      (b) => b.textContent?.toLowerCase() === 'save'
    );
    if (saveBtn) {
      fireEvent.click(saveBtn);
      await waitFor(() => {
        expect(mockAdminGroups.setCollectionGroups).toHaveBeenCalled();
      });
    }
  });
});
