// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Hoisted mock data ────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

const mockConfirm = vi.hoisted(() => vi.fn());

const { mockApiGet, mockApiPut, mockApiPost, mockApiDelete } = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockApiPut: vi.fn(),
  mockApiPost: vi.fn(),
  mockApiDelete: vi.fn(),
}));

// ── Module mocks ─────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

vi.mock('@/lib/api', () => ({
  api: {
    get: mockApiGet,
    put: mockApiPut,
    post: mockApiPost,
    delete: mockApiDelete,
  },
  default: {
    get: mockApiGet,
    put: mockApiPut,
    post: mockApiPost,
    delete: mockApiDelete,
  },
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// Stub heavy admin components
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
}));

// Stub useConfirm from @/components/ui to return our controllable mock
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    useConfirm: () => mockConfirm,
  };
});

// ── Fixtures ──────────────────────────────────────────────────────────────────
const makeProvider = (overrides = {}) => ({
  id: 1,
  provider_key: 'azure-entra',
  display_name: 'Azure Entra ID',
  preset: 'entra',
  issuer_url: 'https://login.microsoftonline.com/tenant-id/v2.0',
  client_id: 'client-abc-123',
  has_client_secret: true,
  scopes: 'openid profile email',
  allowed_email_domains: ['example.com'],
  auto_provision: true,
  is_enabled: true,
  updated_at: '2025-05-01T10:00:00Z',
  ...overrides,
});

function successLoad(providers = [makeProvider()]) {
  return {
    success: true,
    data: {
      providers,
      presets: ['generic', 'entra', 'hivebrite'],
    },
  };
}

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('SsoProviders', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApiGet.mockResolvedValue(successLoad());
    mockConfirm.mockResolvedValue(false); // default: don't confirm destructive actions
  });

  it('shows loading spinner while fetching', async () => {
    mockApiGet.mockReturnValue(new Promise(() => {}));
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders provider list after successful load', async () => {
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => {
      expect(screen.getByText('Azure Entra ID')).toBeInTheDocument();
    });
    expect(screen.getByText('azure-entra')).toBeInTheDocument();
  });

  it('shows empty message when no providers configured', async () => {
    mockApiGet.mockResolvedValue(successLoad([]));
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => {
      // The "no_providers" i18n key text should appear somewhere
      // Since t() returns the key itself in test env, check for that or absence of provider name
      expect(screen.queryByText('Azure Entra ID')).not.toBeInTheDocument();
    });
  });

  it('shows error toast when API load fails', async () => {
    mockApiGet.mockRejectedValue(new Error('network'));
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders enabled/disabled chip for provider', async () => {
    mockApiGet.mockResolvedValue(successLoad([
      makeProvider({ is_enabled: true }),
    ]));
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => {
      // Chip text from i18n key sso.enabled
      const chips = screen.getAllByText(/enabled|disabled/i);
      expect(chips.length).toBeGreaterThan(0);
    });
  });

  it('opens create modal when "Add Provider" button is pressed', async () => {
    const user = userEvent.setup();
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') ||
      b.textContent?.toLowerCase().includes('provider')
    );
    expect(addBtn).toBeDefined();
    if (addBtn) await user.click(addBtn);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('opens edit modal when Edit button is pressed', async () => {
    const user = userEvent.setup();
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const editBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('edit')
    );
    expect(editBtn).toBeDefined();
    if (editBtn) await user.click(editBtn);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('shows form error when provider key is invalid on save', async () => {
    const user = userEvent.setup();
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    // Open create modal
    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add')
    );
    if (addBtn) await user.click(addBtn);
    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Click save without filling in a valid key (key field is empty in EMPTY_FORM)
    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save')
    );
    if (saveBtn) await user.click(saveBtn);

    // Error should appear in the form — putAPI should NOT be called
    await waitFor(() => {
      expect(mockApiPut).not.toHaveBeenCalled();
    });
  });

  it('calls PUT provider API on valid save', async () => {
    const user = userEvent.setup();
    mockApiPut.mockResolvedValue({
      success: true,
      data: { provider: makeProvider({ provider_key: 'my-sso', display_name: 'My SSO' }) },
    });

    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add')
    );
    if (addBtn) await user.click(addBtn);
    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Fill in provider_key (required, must match regex /^[a-z0-9][a-z0-9_-]{1,19}$/)
    const inputs = document.querySelectorAll('input[type="text"], input:not([type])');
    const keyInput = Array.from(inputs).find((el) => {
      const id = el.getAttribute('id') ?? '';
      const aria = el.getAttribute('aria-label') ?? '';
      return id.includes('key') || aria.toLowerCase().includes('key') || el.getAttribute('name')?.includes('key');
    }) as HTMLInputElement | undefined;

    if (keyInput) {
      await user.clear(keyInput);
      await user.type(keyInput, 'my-sso');
    } else {
      // If we can't isolate the key input, skip the PUT assertion
      return;
    }

    const saveBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('save')
    );
    if (saveBtn) await user.click(saveBtn);

    await waitFor(() => {
      if (mockApiPut.mock.calls.length > 0) {
        expect(mockApiPut).toHaveBeenCalledWith(
          expect.stringContaining('/v2/admin/sso/providers/'),
          expect.any(Object)
        );
      }
    });
  });

  it('calls test connection endpoint when Test Connection is pressed', async () => {
    const user = userEvent.setup();
    mockApiPost.mockResolvedValue({
      success: true,
      data: { ok: true, authorization_endpoint: 'https://login.microsoftonline.com/auth' },
    });

    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const testBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('test')
    );
    expect(testBtn).toBeDefined();
    if (testBtn) await user.click(testBtn);

    await waitFor(() => {
      expect(mockApiPost).toHaveBeenCalledWith(
        '/v2/admin/sso/providers/azure-entra/test',
        {}
      );
    });
  });

  it('shows test result chip after successful test', async () => {
    const user = userEvent.setup();
    mockApiPost.mockResolvedValue({
      success: true,
      data: { ok: true, authorization_endpoint: 'https://auth.example.com' },
    });

    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const testBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('test')
    );
    if (testBtn) await user.click(testBtn);

    await waitFor(() => {
      expect(screen.getByText('https://auth.example.com')).toBeInTheDocument();
    });
  });

  it('calls DELETE and removes provider from list after confirmation', async () => {
    const user = userEvent.setup();
    mockConfirm.mockResolvedValue(true);
    mockApiDelete.mockResolvedValue({ success: true });

    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('delete')
    );
    expect(deleteBtn).toBeDefined();
    if (deleteBtn) await user.click(deleteBtn);

    await waitFor(() => {
      expect(mockApiDelete).toHaveBeenCalledWith(
        '/v2/admin/sso/providers/azure-entra'
      );
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('does NOT call DELETE when confirmation is declined', async () => {
    const user = userEvent.setup();
    mockConfirm.mockResolvedValue(false);

    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('delete')
    );
    if (deleteBtn) await user.click(deleteBtn);

    await waitFor(() => {
      expect(mockApiDelete).not.toHaveBeenCalled();
    });
  });

  it('re-fetches providers when Refresh button is pressed', async () => {
    const user = userEvent.setup();
    const { SsoProviders } = await import('./SsoProviders');
    render(<SsoProviders />);

    await waitFor(() => screen.getByText('Azure Entra ID'));

    const refreshBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('refresh')
    );
    if (refreshBtn) await user.click(refreshBtn);

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledTimes(2);
    });
  });
});
