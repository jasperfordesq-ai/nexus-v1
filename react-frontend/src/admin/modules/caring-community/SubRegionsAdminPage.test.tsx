// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── api mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── useConfirm mock from @/components/ui ─────────────────────────────────────
const { mockConfirm } = vi.hoisted(() => ({ mockConfirm: vi.fn() }));

vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return { ...orig, useConfirm: () => mockConfirm };
});

// ─── contexts ─────────────────────────────────────────────────────────────────
const mockShowToast = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      showToast: mockShowToast,
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
    }),
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── fixtures ─────────────────────────────────────────────────────────────────
const makeRegion = (overrides = {}) => ({
  id: 1,
  name: 'Altstadt',
  slug: 'altstadt',
  type: 'quartier',
  description: 'Historic district',
  postal_codes: ['4051', '4052'],
  center_latitude: 47.5596,
  center_longitude: 7.5886,
  status: 'active',
  created_at: '2025-01-01T00:00:00Z',
  ...overrides,
});

const makeListResp = (data = [] as object[], total = 0) => ({
  success: true,
  data: { data, total, per_page: 25, current_page: 1 },
});

// ─── tests ────────────────────────────────────────────────────────────────────
describe('SubRegionsAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeListResp());
    mockConfirm.mockResolvedValue(true);
  });

  it('shows a spinner while loading', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows empty table when no regions are returned', async () => {
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    expect(screen.queryByText('Altstadt')).not.toBeInTheDocument();
  });

  it('renders sub-region rows when data is returned', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);
    await waitFor(() => {
      expect(screen.getByText('Altstadt')).toBeInTheDocument();
    });
    expect(screen.getByText('altstadt')).toBeInTheDocument();
  });

  it('shows postal codes preview', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);
    await waitFor(() => {
      expect(screen.getByText('4051, 4052')).toBeInTheDocument();
    });
  });

  it('shows coordinates for regions with lat/lon', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);
    await waitFor(() => {
      expect(screen.getByText(/47\.5596.*7\.5886/)).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);
    await waitFor(() => {
      // error container renders
      const danger = document.querySelector('.text-danger');
      expect(danger).toBeTruthy();
    });
  });

  it('opens create modal when Add button is clicked', async () => {
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const addBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('add') ||
             b.textContent?.toLowerCase().includes('+') ||
             b.getAttribute('aria-label')?.toLowerCase().includes('add'),
    );
    expect(addBtn).toBeDefined();
    await userEvent.click(addBtn!);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('opens edit modal when edit icon button is clicked', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Altstadt')).toBeInTheDocument());

    const editBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('edit'),
    );
    expect(editBtn).toBeDefined();
    await userEvent.click(editBtn!);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });

    // Edit modal pre-fills the region name
    await waitFor(() => {
      const nameInput = document.querySelector('input[value="Altstadt"]');
      expect(nameInput).toBeTruthy();
    });
  });

  it('does not call POST when modal is opened and closed without saving', async () => {
    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);

    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    const addBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('add') ||
             b.textContent?.toLowerCase().includes('+'),
    );
    expect(addBtn).toBeDefined();
    await userEvent.click(addBtn!);

    // Modal is now open — verify it opened (we tested this above already)
    // Close via Escape key
    await userEvent.keyboard('{Escape}');

    // POST was never called because we never submitted
    expect(mockApi.post).not.toHaveBeenCalled();
  });

  it('calls PUT when edit form is saved', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    mockApi.put.mockResolvedValue({ success: true });

    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Altstadt')).toBeInTheDocument());

    const editBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('edit'),
    );
    await userEvent.click(editBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Click Save Changes
    const saveBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('save') ||
             b.textContent?.toLowerCase().includes('changes'),
    );
    expect(saveBtn).toBeDefined();
    await userEvent.click(saveBtn!);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/v2/admin/caring-community/sub-regions/1',
        expect.objectContaining({ name: 'Altstadt' }),
      );
    });
  });

  it('calls DELETE when mark-inactive button is clicked and confirm is accepted', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    mockApi.delete.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);

    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Altstadt')).toBeInTheDocument());

    const deleteBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('inactive') ||
             b.getAttribute('aria-label')?.toLowerCase().includes('delete'),
    );
    expect(deleteBtn).toBeDefined();
    await userEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/v2/admin/caring-community/sub-regions/1');
    });
  });

  it('does NOT call DELETE when confirm is cancelled', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    mockConfirm.mockResolvedValue(false);

    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Altstadt')).toBeInTheDocument());

    const deleteBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('inactive') ||
             b.getAttribute('aria-label')?.toLowerCase().includes('delete'),
    );
    expect(deleteBtn).toBeDefined();
    await userEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalled();
    });
    expect(mockApi.delete).not.toHaveBeenCalled();
  });

  it('shows success toast after mark-inactive', async () => {
    mockApi.get.mockResolvedValue(makeListResp([makeRegion()]));
    mockApi.delete.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);

    const { default: Page } = await import('./SubRegionsAdminPage');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Altstadt')).toBeInTheDocument());

    const deleteBtn = screen.queryAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('inactive') ||
             b.getAttribute('aria-label')?.toLowerCase().includes('delete'),
    );
    await userEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
    });
  });
});
