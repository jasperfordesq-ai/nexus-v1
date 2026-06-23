// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock @/lib/api ──────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockShowToast = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
      showToast: mockShowToast,
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Admin components ─────────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({
    title,
    actions,
  }: {
    title: string;
    actions?: React.ReactNode;
  }) => (
    <div data-testid="page-header">
      <span>{title}</span>
      <div>{actions}</div>
    </div>
  ),
}));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub HeroUI Select to avoid jsdom infinite-loop ─────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Select: ({ label, children, onChange }: {
      label?: string;
      children?: React.ReactNode;
      onChange?: (e: React.ChangeEvent<HTMLSelectElement>) => void;
    }) => (
      <select aria-label={label ?? 'select'} onChange={onChange}>
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children: React.ReactNode; id: string }) => (
      <option value={id}>{children}</option>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeItem = (overrides: Record<string, unknown> = {}) => ({
  id: 'int-001',
  name: 'Bank A',
  category: 'banking',
  owner_name: 'Jane Smith',
  owner_email: 'jane@bank.ie',
  status: 'live',
  interface_spec_url: 'https://spec.bank.ie',
  dsa_status: 'signed',
  sandbox_url: 'https://sandbox.bank.ie',
  notes: 'Primary banking partner',
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-06-01T00:00:00Z',
  ...overrides,
});

const makeListResponse = (items = [makeItem()]) => ({
  success: true,
  data: {
    items,
    last_updated_at: '2025-06-01T10:00:00Z',
  },
});

const makeItemResponse = (item = makeItem()) => ({
  success: true,
  data: { item },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('ExternalIntegrationsAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeListResponse());
    mockApi.post.mockResolvedValue(makeItemResponse());
    mockApi.put.mockResolvedValue(makeItemResponse());
    mockApi.delete.mockResolvedValue({ success: true });
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders integration name after data loads', async () => {
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Bank A')).toBeInTheDocument();
    });
  });

  it('renders owner name in the table', async () => {
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Jane Smith')).toBeInTheDocument();
    });
  });

  it('renders empty state when no integrations', async () => {
    mockApi.get.mockResolvedValue(makeListResponse([]));
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => {
      // Empty state is shown by the PlugZap icon section with the empty title
      const headings = screen.getAllByRole('heading');
      const emptyHeading = headings.find((h) =>
        h.textContent?.toLowerCase().includes('no integrations') ||
        h.textContent?.toLowerCase().includes('external_integrations.empty.title')
      );
      // Fall back to checking for the seed button
      const seedBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('seed') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('seed')
      );
      expect(emptyHeading ?? seedBtn).toBeDefined();
    });
  });

  it('opens create modal when Add button clicked', async () => {
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => screen.getByText('Bank A'));

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('add')
    );
    expect(addBtn).toBeDefined();
    if (addBtn) fireEvent.click(addBtn);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('opens edit modal when edit icon clicked', async () => {
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => screen.getByText('Bank A'));

    const editBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('edit')
    );
    expect(editBtn).toBeDefined();
    if (editBtn) fireEvent.click(editBtn);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('opens delete modal when delete icon clicked', async () => {
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => screen.getByText('Bank A'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('remove')
    );
    expect(deleteBtn).toBeDefined();
    if (deleteBtn) fireEvent.click(deleteBtn);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls PUT when saving an edit', async () => {
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => screen.getByText('Bank A'));

    const editBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('edit')
    );
    if (editBtn) fireEvent.click(editBtn);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Click save / create
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save') || b.textContent?.toLowerCase().includes('create')
    );
    if (saveBtn) fireEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/caring-community/external-integrations/int-001'),
        expect.any(Object)
      );
    });
  });

  it('calls DELETE when confirming removal', async () => {
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => screen.getByText('Bank A'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete') ||
      b.getAttribute('aria-label')?.toLowerCase().includes('remove')
    );
    if (deleteBtn) fireEvent.click(deleteBtn);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    const removeBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('remove') || b.textContent?.toLowerCase().includes('delete')
    );
    if (removeBtn) fireEvent.click(removeBtn);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/caring-community/external-integrations/int-001')
      );
    });
  });

  it('calls seed endpoint and shows success toast', async () => {
    mockApi.get.mockResolvedValue(makeListResponse([]));
    mockApi.post.mockResolvedValue(makeListResponse([makeItem()]));
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => {
      const seedBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('seed') || b.textContent?.toLowerCase().includes('defaults')
      );
      expect(seedBtn).toBeDefined();
    });

    const seedBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('seed') || b.textContent?.toLowerCase().includes('defaults')
    );
    if (seedBtn) fireEvent.click(seedBtn);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/caring-community/external-integrations/seed-defaults',
        {}
      );
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
    });
  });

  it('shows error toast when load fails', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { default: ExternalIntegrationsAdminPage } = await import('./ExternalIntegrationsAdminPage');
    render(<ExternalIntegrationsAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });
});
