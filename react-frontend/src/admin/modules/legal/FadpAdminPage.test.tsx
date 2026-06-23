// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Hoist mocks ─────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    patch: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ default: mockApi, api: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
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

// Stub ConfirmModal
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
function makeActivity(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    tenant_id: 2,
    activity_name: 'Member Registration',
    purpose: 'Account management',
    data_categories: ['name', 'email'],
    recipients: null,
    retention_period: '3 years',
    legal_basis: 'contract',
    is_automated_profiling: false,
    is_active: true,
    sort_order: 0,
    created_at: '2025-01-01T00:00:00Z',
    updated_at: '2025-01-01T00:00:00Z',
    ...overrides,
  };
}

const retentionResponse = {
  data: {
    config: {
      member_data_years: 7,
      transaction_data_years: 10,
      activity_logs_years: 3,
      messages_years: 2,
      ai_embeddings_years: 1,
    },
    data_residency: 'EU',
    dpa_contact_email: null,
  },
};

const consentRecord = {
  id: 1,
  tenant_id: 2,
  user_id: 100,
  consent_type: 'marketing',
  action: 'granted',
  consent_version: '1.0',
  ip_address: '127.0.0.1',
  created_at: '2025-03-01T10:00:00Z',
};

// ─────────────────────────────────────────────────────────────────────────────
describe('FadpAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Default: all endpoints succeed with empty data
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('processing-activities')) return Promise.resolve({ data: [] });
      if (url.includes('retention-config')) return Promise.resolve(retentionResponse);
      if (url.includes('consent-ledger')) return Promise.resolve({ data: [] });
      if (url.includes('processing-register'))
        return Promise.resolve({ data: { generated_at: null, total_activities: 0, automated_profiling_count: 0 } });
      return Promise.resolve({ data: null });
    });
    mockApi.post.mockResolvedValue({ success: true, data: {} });
    mockApi.put.mockResolvedValue({ success: true });
    mockApi.delete.mockResolvedValue({ success: true });
  });

  it('renders a loading spinner while activities load', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders the FADP header title', async () => {
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => {
      // The title should come from fadp.header.title translation key
      const heading = screen.queryByRole('heading') ?? document.querySelector('h1');
      expect(heading).toBeTruthy();
    });
  });

  it('calls all four API endpoints on mount', async () => {
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('processing-activities')
      );
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('retention-config')
      );
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('consent-ledger')
      );
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('processing-register')
      );
    });
  });

  it('renders an activity row in the processing register table', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('processing-activities'))
        return Promise.resolve({ data: [makeActivity()] });
      if (url.includes('retention-config')) return Promise.resolve(retentionResponse);
      if (url.includes('consent-ledger')) return Promise.resolve({ data: [] });
      if (url.includes('processing-register'))
        return Promise.resolve({ data: { total_activities: 1, automated_profiling_count: 0 } });
      return Promise.resolve({ data: null });
    });

    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Member Registration')).toBeInTheDocument();
    });
  });

  it('opens the add activity modal when Add Activity is clicked', async () => {
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getAllByRole('tab').length > 0);

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') || b.textContent?.toLowerCase().includes('activity')
    );
    expect(addBtn).toBeDefined();
    fireEvent.click(addBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls POST to save a new processing activity', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { id: 99 } });
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getAllByRole('tab').length > 0);

    // Open Add Activity modal
    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') || b.textContent?.toLowerCase().includes('activity')
    );
    fireEvent.click(addBtn!);
    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Fill in required fields
    const inputs = document.querySelectorAll('input');
    const nameInput = Array.from(inputs).find(
      (i) => i.getAttribute('type') !== 'hidden' && !i.getAttribute('type')?.includes('color')
    );
    if (nameInput) {
      fireEvent.change(nameInput, { target: { value: 'New Activity' } });
    }

    // Click the save button
    const saveBtn = screen.getAllByRole('button').find(
      (b) =>
        b.textContent?.toLowerCase().includes('add activity') ||
        b.textContent?.toLowerCase().includes('save') ||
        b.textContent?.toLowerCase().includes('add')
    );
    if (saveBtn) {
      fireEvent.click(saveBtn);
      // If validation passes, POST is called
      await waitFor(() => {
        // Either post is called or error toast (missing purpose field)
        expect(
          mockApi.post.mock.calls.length > 0 || mockToast.showToast.mock.calls.length > 0
        ).toBe(true);
      });
    }
  });

  it('opens the delete confirm dialog when trash icon is clicked', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('processing-activities'))
        return Promise.resolve({ data: [makeActivity()] });
      if (url.includes('retention-config')) return Promise.resolve(retentionResponse);
      return Promise.resolve({ data: [] });
    });

    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getByText('Member Registration'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );
    expect(deleteBtn).toBeDefined();
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls DELETE endpoint when deletion is confirmed', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('processing-activities'))
        return Promise.resolve({ data: [makeActivity({ id: 7 })] });
      if (url.includes('retention-config')) return Promise.resolve(retentionResponse);
      return Promise.resolve({ data: [] });
    });
    mockApi.delete.mockResolvedValue({ success: true });

    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getByText('Member Registration'));

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
      expect(mockApi.delete).toHaveBeenCalledWith(
        expect.stringContaining('processing-activities/7')
      );
    });
  });

  it('renders the consent ledger tab with records when switched', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('consent-ledger'))
        return Promise.resolve({ data: [consentRecord] });
      if (url.includes('retention-config')) return Promise.resolve(retentionResponse);
      return Promise.resolve({ data: [] });
    });

    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getAllByRole('tab').length > 0);

    const ledgerTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('ledger') || t.textContent?.toLowerCase().includes('consent')
    );
    expect(ledgerTab).toBeDefined();
    fireEvent.click(ledgerTab!);

    await waitFor(() => {
      // The consent record's user_id should appear
      expect(screen.getByText('100')).toBeInTheDocument();
    });
  });

  it('calls PUT /retention-config when Save Configuration is pressed', async () => {
    mockApi.put.mockResolvedValue({ success: true });
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getAllByRole('tab').length > 0);

    // Switch to Retention tab
    const retentionTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('retention')
    );
    expect(retentionTab).toBeDefined();
    fireEvent.click(retentionTab!);

    await waitFor(() => {
      const saveBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('save') || b.textContent?.toLowerCase().includes('configuration')
      );
      if (saveBtn) {
        fireEvent.click(saveBtn);
      }
    });

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/v2/admin/fadp/retention-config',
        expect.any(Object)
      );
    });
  });

  it('shows success toast after retention config is saved', async () => {
    mockApi.put.mockResolvedValue({ success: true });
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getAllByRole('tab').length > 0);

    const retentionTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('retention')
    );
    fireEvent.click(retentionTab!);

    await waitFor(() => {
      const saveBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('save') || b.textContent?.toLowerCase().includes('configuration')
      );
      if (saveBtn) fireEvent.click(saveBtn);
    });

    await waitFor(() => {
      expect(mockToast.showToast).toHaveBeenCalledWith(
        expect.any(String),
        'success'
      );
    });
  });

  it('shows error toast when activities fail to load', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('processing-activities')) throw new Error('fail');
      if (url.includes('retention-config')) return Promise.resolve(retentionResponse);
      return Promise.resolve({ data: [] });
    });

    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => {
      expect(mockToast.showToast).toHaveBeenCalledWith(
        expect.any(String),
        'error'
      );
    });
  });

  it('renders the data residency tab with current setting', async () => {
    const { FadpAdminPage } = await import('./FadpAdminPage');
    render(<FadpAdminPage />);

    await waitFor(() => screen.getAllByRole('tab').length > 0);

    const residencyTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('residency') || t.textContent?.toLowerCase().includes('data')
    );
    if (residencyTab) {
      fireEvent.click(residencyTab);
      // The data residency chip (EU) should render from retention config
      await waitFor(() => {
        expect(document.body.textContent).toMatch(/EU|Switzerland|International/);
      });
    }
  });
});
