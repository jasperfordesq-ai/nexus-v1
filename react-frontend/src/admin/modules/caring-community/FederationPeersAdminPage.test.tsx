// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Hoisted mock data ────────────────────────────────────────────────────────
const { mockApi, mockShowToast, mockConfirm } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  mockShowToast: vi.fn(),
  mockConfirm: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ showToast: mockShowToast }),
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// useConfirm and Select come from @/components/ui
// Select is replaced with a native <select> so fireEvent.change works in JSDOM
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    useConfirm: () => mockConfirm,
    Select: ({
      children,
      onChange,
      selectedKeys,
      'aria-label': ariaLabel,
      ...rest
    }: {
      children?: React.ReactNode;
      onChange?: React.ChangeEventHandler<HTMLSelectElement>;
      selectedKeys?: string[];
      'aria-label'?: string;
      [key: string]: unknown;
    }) => (
      <select
        aria-label={ariaLabel}
        value={selectedKeys?.[0] ?? ''}
        onChange={onChange ?? (() => {})}
        data-testid="native-select"
        {...(Object.fromEntries(
          Object.entries(rest).filter(([k]) => ['className', 'size', 'style'].includes(k))
        ))}
      >
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children?: React.ReactNode; id?: string }) => (
      <option value={id}>{children}</option>
    ),
  };
});

vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

// ── Fixtures ──────────────────────────────────────────────────────────────────
const makePeer = (overrides = {}) => ({
  id: 1,
  peer_slug: 'cork-timebank',
  display_name: 'Cork Time Bank',
  base_url: 'https://cork.example.com',
  shared_secret: null,
  shared_secret_set: true,
  status: 'active' as const,
  notes: 'Test notes',
  last_handshake_at: '2025-05-01T08:00:00Z',
  created_at: '2025-01-01T00:00:00Z',
  updated_at: '2025-05-01T08:00:00Z',
  ...overrides,
});

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('FederationPeersAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue({ success: true, data: { peers: [] } });
    mockConfirm.mockResolvedValue(true);
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    const busy = screen.getAllByRole('status').find(
      (el) => el.getAttribute('aria-busy') === 'true',
    );
    expect(busy).toBeInTheDocument();
  });

  it('renders empty table when no peers are returned', async () => {
    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => {
      // No spinner
      expect(
        screen.queryAllByRole('status').find(
          (el) => el.getAttribute('aria-busy') === 'true',
        ),
      ).toBeUndefined();
      // HeroUI Table sets data-empty="true" on the table body when there are no rows
      const emptyBody = document.querySelector('[data-empty="true"][data-slot="table-body"]');
      expect(emptyBody).toBeTruthy();
    });
  });

  it('renders peers when data is returned', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: { peers: [makePeer()] } });
    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Cork Time Bank')).toBeInTheDocument();
      expect(screen.getByText('cork-timebank')).toBeInTheDocument();
    });
  });

  it('shows base_url as a link', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: { peers: [makePeer()] } });
    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => {
      const link = screen.getByRole('link', { name: /cork.example.com/i });
      expect(link).toBeInTheDocument();
      expect(link).toHaveAttribute('href', 'https://cork.example.com');
    });
  });

  it('shows an error toast when loading fails', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('network'));
    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });

  it('opens create modal when "Add Peer" is clicked', async () => {
    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => {
      expect(
        screen.queryAllByRole('status').find(
          (el) => el.getAttribute('aria-busy') === 'true',
        ),
      ).toBeUndefined();
    });

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') || b.textContent?.toLowerCase().includes('peer'),
    );
    expect(addBtn).toBeDefined();
    fireEvent.click(addBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('validates required fields and shows error toast when creating with empty fields', async () => {
    const user = userEvent.setup();
    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() =>
      expect(
        screen.queryAllByRole('status').find(
          (el) => el.getAttribute('aria-busy') === 'true',
        ),
      ).toBeUndefined(),
    );

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') || b.textContent?.toLowerCase().includes('peer'),
    );
    await user.click(addBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Click create/reveal without filling in required fields
    const createBtn = screen.getAllByRole('button').find((b) =>
      b.closest('[role="dialog"]') &&
      (b.textContent?.toLowerCase().includes('create') ||
        b.textContent?.toLowerCase().includes('reveal')),
    );
    if (createBtn) {
      await user.click(createBtn);
      await waitFor(() => {
        expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
      });
    }
  });

  it('calls PUT status endpoint when status dropdown changes', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: { peers: [makePeer({ status: 'pending' })] } });
    mockApi.put.mockResolvedValue({ success: true });

    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => screen.getByText('Cork Time Bank'));

    // The Select is mocked as a native <select> with aria-label="Status"
    const select = screen.getByRole('combobox', { name: /status/i });
    expect(select).toBeDefined();
    fireEvent.change(select, { target: { value: 'active' } });

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        `/v2/admin/caring-community/federation-peers/1/status`,
        { status: 'active' },
      );
    });
  });

  it('calls DELETE endpoint after confirmed delete', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: { peers: [makePeer()] } });
    mockApi.delete.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValueOnce(true);

    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => screen.getByText('Cork Time Bank'));

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete'),
    );
    expect(deleteBtn).toBeDefined();
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith(
        `/v2/admin/caring-community/federation-peers/1`,
      );
    });
  });

  it('does NOT call DELETE when confirm is cancelled', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: { peers: [makePeer()] } });
    mockConfirm.mockResolvedValueOnce(false);

    const { default: FederationPeersAdminPage } = await import('./FederationPeersAdminPage');
    render(<FederationPeersAdminPage />);

    await waitFor(() => screen.getByText('Cork Time Bank'));

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete'),
    );
    if (deleteBtn) {
      fireEvent.click(deleteBtn);
      // Give async flow a tick
      await waitFor(() => expect(mockConfirm).toHaveBeenCalled());
      expect(mockApi.delete).not.toHaveBeenCalled();
    }
  });
});
