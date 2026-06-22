// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock adminApi ────────────────────────────────────────────────────────────
const mockAdminSuper = vi.hoisted(() => ({
  getWhitelist: vi.fn(),
  listTenants: vi.fn(),
  addToWhitelist: vi.fn(),
  removeFromWhitelist: vi.fn(),
}));

vi.mock('../../api/adminApi', () => ({
  adminSuper: mockAdminSuper,
}));

// ─── Mock contexts ───────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 1, name: 'Platform', slug: 'platform' },
      tenantPath: (p: string) => `/platform${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Sample data ─────────────────────────────────────────────────────────────
const WHITELIST_ENTRIES = [
  {
    tenant_id: 10,
    tenant_name: 'Alpha Community',
    tenant_domain: 'alpha.example.com',
    added_by: 'jasper@example.com',
    added_at: '2026-06-01T10:00:00Z',
    notes: 'Trusted partner',
  },
];

const AVAILABLE_TENANTS = [
  { id: 20, name: 'Beta Community', domain: 'beta.example.com' },
  { id: 30, name: 'Gamma Network', domain: 'gamma.example.com' },
];

function setupSuccess() {
  mockAdminSuper.getWhitelist.mockResolvedValue({ success: true, data: WHITELIST_ENTRIES });
  mockAdminSuper.listTenants.mockResolvedValue({ success: true, data: AVAILABLE_TENANTS });
}

import FederationWhitelist from './FederationWhitelist';

describe('FederationWhitelist', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state while fetching data (no table yet)', () => {
    mockAdminSuper.getWhitelist.mockReturnValue(new Promise(() => {}));
    mockAdminSuper.listTenants.mockReturnValue(new Promise(() => {}));

    render(<FederationWhitelist />);

    // FederationWhitelist uses a custom CSS spinner; the table is not mounted yet
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('renders whitelisted tenant rows after loading', async () => {
    setupSuccess();
    render(<FederationWhitelist />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Community')).toBeInTheDocument();
      expect(screen.getByText('alpha.example.com')).toBeInTheDocument();
    });
  });

  it('shows empty table message when no entries', async () => {
    mockAdminSuper.getWhitelist.mockResolvedValue({ success: true, data: [] });
    mockAdminSuper.listTenants.mockResolvedValue({ success: true, data: AVAILABLE_TENANTS });

    render(<FederationWhitelist />);

    // i18n: federation_whitelist.no_tenants_whitelisted → "No tenants are currently whitelisted."
    await waitFor(() => {
      expect(screen.getByText('No tenants are currently whitelisted.')).toBeInTheDocument();
    });
  });

  it('shows error toast when no tenant selected and Add is clicked', async () => {
    setupSuccess();
    const user = userEvent.setup();
    render(<FederationWhitelist />);

    await waitFor(() => expect(screen.getByText('Alpha Community')).toBeInTheDocument());

    // i18n: federation_whitelist.add_to_whitelist → "Add to Whitelist"
    const addBtn = screen.getByRole('button', { name: /Add to Whitelist/i });
    await user.click(addBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
      expect(mockAdminSuper.addToWhitelist).not.toHaveBeenCalled();
    });
  });

  it('renders available tenants in the Select dropdown', async () => {
    setupSuccess();
    const user = userEvent.setup();
    render(<FederationWhitelist />);

    await waitFor(() => expect(screen.getByText('Alpha Community')).toBeInTheDocument());

    // Open the Select — i18n: col_tenant → "Tenant"
    const selectBtn = screen.getByRole('button', { name: /Tenant/i });
    await user.click(selectBtn);

    await waitFor(() => {
      expect(screen.getByText('Beta Community (beta.example.com)')).toBeInTheDocument();
      expect(screen.getByText('Gamma Network (gamma.example.com)')).toBeInTheDocument();
    });
  });

  it('calls addToWhitelist and shows success toast after selection + submit', async () => {
    setupSuccess();
    mockAdminSuper.addToWhitelist.mockResolvedValue({ success: true });
    // After add, reload returns empty so we just check the call happened
    mockAdminSuper.getWhitelist
      .mockResolvedValueOnce({ success: true, data: WHITELIST_ENTRIES })
      .mockResolvedValue({ success: true, data: [] });
    mockAdminSuper.listTenants.mockResolvedValue({ success: true, data: AVAILABLE_TENANTS });

    const user = userEvent.setup();
    render(<FederationWhitelist />);

    await waitFor(() => expect(screen.getByText('Alpha Community')).toBeInTheDocument());

    // Open select and pick "Beta Community"
    const selectBtn = screen.getByRole('button', { name: /Tenant/i });
    await user.click(selectBtn);
    await waitFor(() => expect(screen.getByText('Beta Community (beta.example.com)')).toBeInTheDocument());
    await user.click(screen.getByText('Beta Community (beta.example.com)'));

    const addBtn = screen.getByRole('button', { name: /Add to Whitelist/i });
    await user.click(addBtn);

    await waitFor(() => {
      expect(mockAdminSuper.addToWhitelist).toHaveBeenCalledWith(20, undefined);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when addToWhitelist fails', async () => {
    setupSuccess();
    mockAdminSuper.addToWhitelist.mockResolvedValue({ success: false, error: 'Already whitelisted' });

    const user = userEvent.setup();
    render(<FederationWhitelist />);

    await waitFor(() => expect(screen.getByText('Alpha Community')).toBeInTheDocument());

    // Pick a tenant
    const selectBtn = screen.getByRole('button', { name: /Tenant/i });
    await user.click(selectBtn);
    await waitFor(() => expect(screen.getByText('Beta Community (beta.example.com)')).toBeInTheDocument());
    await user.click(screen.getByText('Beta Community (beta.example.com)'));

    await user.click(screen.getByRole('button', { name: /Add to Whitelist/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('opens confirm modal when Remove is clicked', async () => {
    setupSuccess();
    const user = userEvent.setup();
    render(<FederationWhitelist />);

    await waitFor(() => expect(screen.getByText('Alpha Community')).toBeInTheDocument());

    // i18n: federation_whitelist.remove → "Remove"
    const removeBtn = screen.getByRole('button', { name: /^Remove$/i });
    await user.click(removeBtn);

    // ConfirmModal uses HeroUI Modal which renders as role="dialog"
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('calls removeFromWhitelist and removes entry when confirmed', async () => {
    setupSuccess();
    mockAdminSuper.removeFromWhitelist.mockResolvedValue({ success: true });

    const user = userEvent.setup();
    render(<FederationWhitelist />);

    await waitFor(() => expect(screen.getByText('Alpha Community')).toBeInTheDocument());

    // Click remove to open modal
    const removeBtn = screen.getByRole('button', { name: /^Remove$/i });
    await user.click(removeBtn);

    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    // The confirm button inside the modal also says "Remove"
    const allRemoveBtns = screen.getAllByRole('button', { name: /^Remove$/i });
    // Click the last one (inside the dialog)
    await user.click(allRemoveBtns[allRemoveBtns.length - 1]);

    await waitFor(() => {
      expect(mockAdminSuper.removeFromWhitelist).toHaveBeenCalledWith(10);
      expect(mockToast.success).toHaveBeenCalled();
    });

    // Row should be removed from state
    await waitFor(() => {
      expect(screen.queryByText('Alpha Community')).not.toBeInTheDocument();
    });
  });

  it('renders View link and Remove button for each row', async () => {
    setupSuccess();
    render(<FederationWhitelist />);

    await waitFor(() => expect(screen.getByText('Alpha Community')).toBeInTheDocument());

    // i18n: federation_whitelist.view → "View"
    // <Button as={Link}> renders with role="link"
    expect(screen.getByRole('link', { name: /^View$/i })).toBeInTheDocument();
    // i18n: federation_whitelist.remove → "Remove" — rendered as a plain button
    expect(screen.getByRole('button', { name: /^Remove$/i })).toBeInTheDocument();
  });
});
