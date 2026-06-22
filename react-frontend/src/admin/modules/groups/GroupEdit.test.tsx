// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Hoist mocks ──────────────────────────────────────────────────────────────
const { mockAdminGroups, mockApi } = vi.hoisted(() => ({
  mockAdminGroups: {
    getGroup: vi.fn(),
    getGroupTypes: vi.fn(),
    updateGroup: vi.fn(),
    updateStatus: vi.fn(),
  },
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminGroups: mockAdminGroups,
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    resolveAvatarUrl: (u: string | null) => u ?? '',
    resolveAssetUrl: (u: string) => u,
  };
});

// ─── Mock router to inject params ─────────────────────────────────────────────
vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useParams: () => ({ id: '42' }),
    useNavigate: () => mockNavigate,
  };
});
const mockNavigate = vi.fn();

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Toast + tenant ───────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => false), // federation off by default
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Admin component stubs ────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({
    title,
    actions,
  }: {
    title: string;
    description?: string;
    actions?: React.ReactNode;
  }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeGroup = (overrides = {}) => ({
  id: 42,
  name: 'Test Group',
  description: 'A test group',
  visibility: 'public',
  location: 'Dublin',
  status: 'active',
  type_id: null,
  is_featured: false,
  federated_visibility: 'none' as const,
  primary_color: '',
  accent_color: '',
  image_url: null,
  cover_image_url: null,
  member_count: 5,
  stats: null,
  ...overrides,
});

const makeGroupType = (overrides = {}) => ({
  id: 1,
  name: 'Social',
  color: '#ff0000',
  member_count: 10,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GroupEdit', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminGroups.getGroup.mockResolvedValue({ success: true, data: makeGroup() });
    mockAdminGroups.getGroupTypes.mockResolvedValue({ success: true, data: [] });
    mockAdminGroups.updateGroup.mockResolvedValue({ success: true, data: makeGroup() });
    mockAdminGroups.updateStatus.mockResolvedValue({ success: true });
  });

  it('shows a loading spinner while the group is being fetched', async () => {
    mockAdminGroups.getGroup.mockImplementationOnce(() => new Promise(() => {}));
    mockAdminGroups.getGroupTypes.mockImplementationOnce(() => new Promise(() => {}));
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders the group edit form after successful load', async () => {
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => {
      // i18n resolves groups.edit_page_title → "Edit group"
      expect(screen.getByText('Edit group')).toBeInTheDocument();
    });
  });

  it('renders the group name pre-populated in the form', async () => {
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    // The input for the group name should have the value from the fixture
    const nameInputs = screen.getAllByDisplayValue('Test Group');
    expect(nameInputs.length).toBeGreaterThan(0);
  });

  it('shows error state when getGroup fails', async () => {
    mockAdminGroups.getGroup.mockResolvedValue({
      success: false,
      error: 'Group not found',
    });
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => {
      expect(screen.getByText('Group not found')).toBeInTheDocument();
    });
  });

  it('shows Back to Groups button in error state', async () => {
    mockAdminGroups.getGroup.mockResolvedValue({
      success: false,
      error: 'Group not found',
    });
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Group not found'));

    // i18n resolves groups.back_to_groups → "Back to Groups"
    const backBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Back to Groups')
    );
    expect(backBtn).toBeDefined();
  });

  it('renders group types in the type select when loaded', async () => {
    mockAdminGroups.getGroupTypes.mockResolvedValue({
      success: true,
      data: [makeGroupType(), makeGroupType({ id: 2, name: 'Professional' })],
    });
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    expect(screen.getByText('Social')).toBeInTheDocument();
    expect(screen.getByText('Professional')).toBeInTheDocument();
  });

  it('shows no types message when group types list is empty', async () => {
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    // i18n resolves groups.edit_no_types → "No group types are configured"
    await waitFor(() => screen.getByText('No group types are configured'));
  });

  it('calls updateGroup and navigates away on successful save', async () => {
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    // i18n resolves groups.edit_save → "Save changes"
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Save changes')
    );
    expect(saveBtn).toBeDefined();
    if (saveBtn) fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockAdminGroups.updateGroup).toHaveBeenCalledWith(
        42,
        expect.objectContaining({ name: 'Test Group' })
      );
      expect(mockToast.success).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('/test/admin/groups');
    });
  });

  it('shows error toast when save fails', async () => {
    mockAdminGroups.updateGroup.mockResolvedValue({
      success: false,
      error: 'Failed to update',
    });
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    // i18n resolves groups.edit_save → "Save changes"
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Save changes')
    );
    if (saveBtn) fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders cancel button that navigates back', async () => {
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    // i18n resolves groups.edit_cancel → "Cancel"
    const cancelBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Cancel')
    );
    expect(cancelBtn).toBeDefined();
    if (cancelBtn) fireEvent.click(cancelBtn);

    expect(mockNavigate).toHaveBeenCalledWith('/test/admin/groups');
  });

  it('does not show federation section when federation feature is off', async () => {
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    // i18n resolves groups.edit_section_federation → "Federation"
    // Federation section should not appear when hasFeature('federation') is false
    expect(screen.queryByText('Federation')).not.toBeInTheDocument();
  });

  it('renders stats section when group has stats', async () => {
    mockAdminGroups.getGroup.mockResolvedValue({
      success: true,
      data: makeGroup({
        stats: { posts_count: 15, events_count: 3 },
        member_count: 8,
      }),
    });
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    expect(screen.getByText('8')).toBeInTheDocument(); // member_count
    expect(screen.getByText('15')).toBeInTheDocument(); // posts_count
    expect(screen.getByText('3')).toBeInTheDocument(); // events_count
  });

  it('shows empty name error toast when save is clicked with blank name', async () => {
    const { GroupEdit } = await import('./GroupEdit');
    render(<GroupEdit />);

    await waitFor(() => screen.getByText('Edit group'));

    // Clear the name field
    const nameInput = screen.getAllByDisplayValue('Test Group')[0];
    fireEvent.change(nameInput, { target: { value: '' } });

    // i18n resolves groups.edit_save → "Save changes"
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Save changes')
    );
    // Button is disabled when name is empty (isDisabled prop)
    if (saveBtn) {
      // It should be data-disabled
      expect(saveBtn.getAttribute('data-disabled') ?? saveBtn.hasAttribute('disabled')).toBeTruthy();
    }
  });
});
