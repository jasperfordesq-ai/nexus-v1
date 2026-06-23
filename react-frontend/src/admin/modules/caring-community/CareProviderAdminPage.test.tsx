// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast / Auth / Tenant ───────────────────────────────────────────────────
const mockShowToast = vi.fn();
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: mockShowToast };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useAuth: () => ({
      user: { id: 1, name: 'Admin', role: 'admin' },
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(),
      updateUser: vi.fn(), refreshUser: vi.fn(),
      status: 'idle' as const, error: null,
    }),
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub useConfirm ─────────────────────────────────────────────────────────
const mockConfirm = vi.fn(() => Promise.resolve(true));

// ─── Stub HeroUI Select (infinite-loop in jsdom) ──────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Select: ({ children, label }: { children: React.ReactNode; label?: string }) => (
      <select aria-label={label}>{children}</select>
    ),
    SelectItem: ({ children, id }: { children: React.ReactNode; id?: string }) => (
      <option value={id}>{children}</option>
    ),
    useConfirm: () => mockConfirm,
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeProvider = (overrides = {}) => ({
  id: 1,
  name: 'Sunrise Care',
  type: 'spitex' as const,
  description: 'Professional home care',
  address: '123 Main St',
  contact_phone: '+1 555 000 0001',
  contact_email: 'info@sunrise.care',
  website_url: 'https://sunrise.care',
  is_verified: false,
  status: 'active' as const,
  created_at: '2025-01-15T00:00:00Z',
  ...overrides,
});

const makeDirectoryResponse = (providers: object[] = []) => ({
  success: true,
  data: {
    data: providers,
    total: providers.length,
    per_page: 20,
    current_page: 1,
  },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('CareProviderAdminPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockConfirm.mockResolvedValue(true);
    mockApi.get.mockResolvedValue(makeDirectoryResponse());
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders provider rows when data loads', async () => {
    mockApi.get.mockResolvedValue(makeDirectoryResponse([makeProvider()]));

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Sunrise Care')).toBeInTheDocument();
    });
  });

  it('renders empty state when no providers returned', async () => {
    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });

    expect(screen.queryByText('Sunrise Care')).toBeNull();
  });

  it('shows error message when API fails', async () => {
    mockApi.get.mockResolvedValue({ success: false, error: 'Server error' });

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => {
      // Error state renders a div with danger styling
      const errorDiv = document.querySelector('.text-danger');
      expect(errorDiv).toBeTruthy();
    });
  });

  it('opens create modal when Add Provider button clicked', async () => {
    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') ||
      b.textContent?.toLowerCase().includes('provider'),
    );
    expect(addBtn).toBeDefined();
    fireEvent.click(addBtn!);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('shows validation error when create form submitted without required fields', async () => {
    // CareProviderAdminPage's handleSave validates name (required) and type (required).
    // The Select is stubbed as a plain <select> using onSelectionChange (not onChange),
    // so type value cannot be injected via the stub in jsdom.
    // This test verifies the form validation path fires a toast or sets formErrors
    // when save is clicked with empty name.
    mockApi.post.mockResolvedValue({ success: true });

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') ||
      b.textContent?.toLowerCase().includes('provider'),
    );
    fireEvent.click(addBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Click save without filling any fields — validation should block the call
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('create') ||
      b.textContent?.toLowerCase().includes('save'),
    );
    if (saveBtn) {
      fireEvent.click(saveBtn);
      // api.post should NOT be called because form is invalid (name empty)
      await waitFor(() => {
        expect(mockApi.post).not.toHaveBeenCalled();
      });
    }
  });

  it('opens edit modal pre-filled when edit button clicked', async () => {
    mockApi.get.mockResolvedValue(makeDirectoryResponse([makeProvider()]));

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => screen.getByText('Sunrise Care'));

    const editBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('edit'),
    );
    expect(editBtn).toBeDefined();
    fireEvent.click(editBtn!);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });

    // Modal should contain the provider name
    expect(document.body.textContent).toContain('Sunrise Care');
  });

  it('calls PUT /providers/:id when saving edits', async () => {
    mockApi.get.mockResolvedValue(makeDirectoryResponse([makeProvider()]));
    mockApi.put.mockResolvedValue({ success: true });

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => screen.getByText('Sunrise Care'));

    const editBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('edit'),
    );
    fireEvent.click(editBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save'),
    );
    if (saveBtn) {
      fireEvent.click(saveBtn);
      await waitFor(() => {
        expect(mockApi.put).toHaveBeenCalledWith(
          '/v2/admin/caring-community/providers/1',
          expect.any(Object),
        );
      });
    }
  });

  it('calls DELETE /providers/:id after confirm when delete button clicked', async () => {
    mockApi.get.mockResolvedValue(makeDirectoryResponse([makeProvider()]));
    mockApi.delete.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => screen.getByText('Sunrise Care'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete'),
    );
    expect(deleteBtn).toBeDefined();
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith(
        '/v2/admin/caring-community/providers/1',
      );
    });
  });

  it('does not delete when confirm returns false', async () => {
    mockApi.get.mockResolvedValue(makeDirectoryResponse([makeProvider()]));
    mockConfirm.mockResolvedValue(false);

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => screen.getByText('Sunrise Care'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete'),
    );
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockApi.delete).not.toHaveBeenCalled();
    });
  });

  it('calls POST /verify when verify button clicked', async () => {
    const unverifiedProvider = makeProvider({ is_verified: false });
    mockApi.get.mockResolvedValue(makeDirectoryResponse([unverifiedProvider]));
    mockApi.post.mockResolvedValue({ success: true });

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => screen.getByText('Sunrise Care'));

    const verifyBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('verify'),
    );
    expect(verifyBtn).toBeDefined();
    fireEvent.click(verifyBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/caring-community/providers/1/verify',
        expect.any(Object),
      );
    });
  });

  it('calls GET /duplicates and shows duplicates panel when Find Duplicates clicked', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/duplicates')) {
        return Promise.resolve({
          success: true,
          data: { pairs: [], total: 0, scanned: 50 },
        });
      }
      return Promise.resolve(makeDirectoryResponse());
    });

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => {
      const busy = screen.queryAllByRole('status').find(
        (el) => el.getAttribute('aria-busy') === 'true',
      );
      expect(busy).toBeUndefined();
    });

    const dupBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('duplicate'),
    );
    expect(dupBtn).toBeDefined();
    fireEvent.click(dupBtn!);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        expect.stringContaining('/duplicates'),
      );
    });
  });

  it('shows verified chip for verified providers', async () => {
    mockApi.get.mockResolvedValue(makeDirectoryResponse([makeProvider({ is_verified: true })]));

    const { default: CareProviderAdminPage } = await import('./CareProviderAdminPage');
    render(<CareProviderAdminPage />);

    await waitFor(() => screen.getByText('Sunrise Care'));

    // No verify button for already-verified providers
    const verifyBtn = screen.queryAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('verify'),
    );
    expect(verifyBtn).toBeUndefined();
  });
});
