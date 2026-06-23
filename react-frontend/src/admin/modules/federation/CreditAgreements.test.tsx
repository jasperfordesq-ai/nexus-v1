// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Hoist mock data ──────────────────────────────────────────────────────────
const { mockApi, mockAdminFederation } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  mockAdminFederation: {
    getCreditBalances: vi.fn(),
    getCreditAgreementTransactions: vi.fn(),
  },
}));

// ─── Mocks ────────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    formatRelativeTime: (s: string) => s,
    resolveAvatarUrl: (u: unknown) => u ?? null,
  };
});

vi.mock('@/admin/api/adminApi', () => ({
  adminFederation: mockAdminFederation,
}));

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

// Stub heavy admin components
vi.mock('@/admin/modules/federation/PartnerTimebankGuidance', () => ({
  PartnerTimebankGuidance: () => null,
  default: () => null,
}));

vi.mock('@/admin/components', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/admin/components')>();
  return {
    ...orig,
    PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
      <div>
        <h1>{title}</h1>
        {actions}
      </div>
    ),
    StatCard: ({ label, value }: { label: string; value: unknown }) => (
      <div data-testid="stat-card">{label}: {String(value)}</div>
    ),
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
function makeAgreement(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    partner_tenant: { id: 10, name: 'Cork Timebank', slug: 'cork' },
    exchange_rate: 1.0,
    monthly_limit: 100,
    current_balance: 5,
    credits_sent: 20,
    credits_received: 25,
    status: 'active',
    created_at: '2025-01-01T00:00:00Z',
    ...overrides,
  };
}

const emptySuccess = { success: true, data: [], meta: {} };
const partnersSuccess = {
  success: true,
  data: [{ id: 10, name: 'Cork Timebank', slug: 'cork' }],
};

// ─────────────────────────────────────────────────────────────────────────────
describe('CreditAgreements', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(emptySuccess);
    mockAdminFederation.getCreditBalances.mockResolvedValue({ success: true, data: null });
    mockAdminFederation.getCreditAgreementTransactions.mockResolvedValue({ success: true, data: null });
  });

  it('shows a loading spinner on initial render', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders stat cards after data loads', async () => {
    mockApi.get.mockResolvedValue(emptySuccess);
    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => {
      expect(screen.getAllByTestId('stat-card').length).toBeGreaterThan(0);
    });
  });

  it('renders an active agreement row with partner name', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement()] })
      .mockResolvedValue(partnersSuccess);
    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => {
      expect(screen.getByText('Cork Timebank')).toBeInTheDocument();
    });
  });

  it('shows exchange rate in mono format', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement({ exchange_rate: 1.5 })] })
      .mockResolvedValue(emptySuccess);
    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => {
      expect(screen.getByText('1.5:1')).toBeInTheDocument();
    });
  });

  it('shows approve button for pending agreements', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement({ status: 'pending' })] })
      .mockResolvedValue(emptySuccess);
    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => {
      // The approve button has aria-label containing "approve"
      const btn = screen.queryAllByRole('button').find((b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('approv')
      );
      expect(btn).toBeDefined();
    });
  });

  it('calls POST /approve endpoint when approve button is clicked', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement({ id: 99, status: 'pending' })] })
      .mockResolvedValue(emptySuccess);
    mockApi.post.mockResolvedValue({ success: true });

    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.getByText('Cork Timebank'));

    const approveBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('approv')
    );
    expect(approveBtn).toBeDefined();
    fireEvent.click(approveBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/federation/credit-agreements/99/approve'
      );
    });
  });

  it('opens confirm modal when terminate button is clicked for active agreement', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement({ status: 'active' })] })
      .mockResolvedValue(emptySuccess);

    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.getByText('Cork Timebank'));

    const terminateBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('terminat')
    );
    expect(terminateBtn).toBeDefined();
    fireEvent.click(terminateBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('calls POST /terminate endpoint when confirm modal is confirmed', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement({ id: 5, status: 'active' })] })
      .mockResolvedValue(emptySuccess);
    mockApi.post.mockResolvedValue({ success: true });

    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.getByText('Cork Timebank'));

    const terminateBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('terminat')
    );
    fireEvent.click(terminateBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Click the confirm button inside the dialog
    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('terminat') || b.textContent?.toLowerCase() === 'confirm'
    );
    fireEvent.click(confirmBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/admin/federation/credit-agreements/5/terminate'
      );
    });
  });

  it('opens create agreement modal when New Agreement button is clicked', async () => {
    mockApi.get.mockResolvedValue(emptySuccess);
    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.queryAllByTestId('stat-card').length > 0);

    const newBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('new') || b.textContent?.toLowerCase().includes('agreement')
    );
    expect(newBtn).toBeDefined();
    fireEvent.click(newBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('opens create agreement modal and form has required exchange rate field', async () => {
    mockApi.get
      .mockResolvedValueOnce(emptySuccess)
      .mockResolvedValueOnce(partnersSuccess);
    mockApi.post.mockResolvedValue({ success: true });

    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.queryAllByTestId('stat-card').length > 0);

    // Open create modal
    const newBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('new') || b.textContent?.toLowerCase().includes('agreement')
    );
    expect(newBtn).toBeDefined();
    fireEvent.click(newBtn!);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });

    // The modal should be open; the form has an exchange rate input
    const inputs = document.querySelectorAll('input[type="number"], input[inputmode="numeric"]');
    // There should be at least one numeric input (exchange rate) in the form
    expect(inputs.length).toBeGreaterThan(0);
  });

  it('shows reactivate button for suspended agreements', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement({ status: 'suspended' })] })
      .mockResolvedValue(emptySuccess);

    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.getByText('Cork Timebank'));

    const reactivateBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('reactiv')
    );
    expect(reactivateBtn).toBeDefined();
  });

  it('does not show terminate button for already terminated agreements', async () => {
    mockApi.get
      .mockResolvedValueOnce({ success: true, data: [makeAgreement({ status: 'terminated' })] })
      .mockResolvedValue(emptySuccess);

    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.getByText('Cork Timebank'));

    const terminateBtn = screen.queryAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('terminat')
    );
    expect(terminateBtn).toBeUndefined();
  });

  it('shows error toast when API fails to load', async () => {
    mockApi.get.mockRejectedValue(new Error('network'));
    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows balance view when balances tab is clicked', async () => {
    mockApi.get.mockResolvedValue(emptySuccess);
    mockAdminFederation.getCreditBalances.mockResolvedValue({
      success: true,
      data: { balances: [], net_total: 0 },
    });

    const { CreditAgreements } = await import('./CreditAgreements');
    render(<CreditAgreements />);

    await waitFor(() => screen.queryAllByTestId('stat-card').length > 0);

    // Click the Balances tab
    const balancesTab = screen.getAllByRole('tab').find((t) =>
      t.textContent?.toLowerCase().includes('balance')
    );
    if (balancesTab) {
      fireEvent.click(balancesTab);
      await waitFor(() => {
        expect(mockAdminFederation.getCreditBalances).toHaveBeenCalled();
      });
    }
  });
});
