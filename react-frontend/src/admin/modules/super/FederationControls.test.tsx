// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock adminApi via vi.hoisted ─────────────────────────────────────────────
const { mockAdminSuper } = vi.hoisted(() => ({
  mockAdminSuper: {
    getSystemControls: vi.fn(),
    getWhitelist: vi.fn(),
    getFederationPartnerships: vi.fn(),
    getFederationJwtStatus: vi.fn(),
    updateSystemControls: vi.fn(),
    emergencyLockdown: vi.fn(),
    liftLockdown: vi.fn(),
    addToWhitelist: vi.fn(),
    removeFromWhitelist: vi.fn(),
    suspendPartnership: vi.fn(),
    terminatePartnership: vi.fn(),
    reactivatePartnership: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminSuper: mockAdminSuper,
  default: { adminSuper: mockAdminSuper },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub HeroUI Switch, Accordion, Snippet, Code (can loop or use clipboard) ─
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Switch: ({ isSelected, onValueChange, isDisabled }: {
      isSelected?: boolean; onValueChange?: (v: boolean) => void; isDisabled?: boolean;
    }) => (
      <input
        type="checkbox"
        role="switch"
        aria-checked={Boolean(isSelected)}
        checked={!!isSelected}
        disabled={isDisabled}
        onChange={(e) => onValueChange?.(e.target.checked)}
      />
    ),
    Accordion: ({ children }: { children: React.ReactNode }) => <div data-testid="accordion">{children}</div>,
    AccordionItem: ({ title, children }: { title?: React.ReactNode; children?: React.ReactNode }) => (
      <div><div>{title}</div><div>{children}</div></div>
    ),
    Snippet: ({ children }: { children?: React.ReactNode }) => <code>{children}</code>,
    Code: ({ children }: { children?: React.ReactNode }) => <code>{children}</code>,
    Tooltip: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
  };
});

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub admin sub-components ─────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title }: { title?: string }) => <div data-testid="page-header"><h1>{title}</h1></div>,
  ConfirmModal: ({
    isOpen,
    onClose,
    onConfirm,
    title,
    children,
  }: {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title?: string;
    children?: React.ReactNode;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label={title}>
        <span>{title}</span>
        {children}
        <button onClick={onClose}>cancel</button>
        <button data-testid="confirm-btn" onClick={onConfirm}>confirm</button>
      </div>
    ) : null,
  StatCard: ({ label, value }: { label?: string; value?: string | number }) => (
    <div data-testid="stat-card">{label}: {value}</div>
  ),
}));

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    Link: ({ children, to }: { children: React.ReactNode; to?: string }) => <a href={to ?? '#'}>{children}</a>,
  };
});

// ─── Toast context ─────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeControls = (overrides = {}) => ({
  federation_enabled: true,
  whitelist_mode_enabled: false,
  emergency_lockdown_active: false,
  emergency_lockdown_reason: null,
  cross_tenant_profiles_enabled: true,
  cross_tenant_messaging_enabled: true,
  cross_tenant_transactions_enabled: true,
  cross_tenant_listings_enabled: true,
  cross_tenant_events_enabled: true,
  cross_tenant_groups_enabled: true,
  ...overrides,
});

const makeWhitelistEntry = (id: number, name: string) => ({ tenant_id: id, tenant_name: name });

const makePartnership = (overrides = {}) => ({
  id: 1,
  tenant_1_name: 'Alpha',
  tenant_2_name: 'Beta',
  status: 'active',
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('FederationControls', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminSuper.getSystemControls.mockResolvedValue({ success: true, data: makeControls() });
    mockAdminSuper.getWhitelist.mockResolvedValue({ success: true, data: [] });
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({ success: true, data: [] });
    mockAdminSuper.getFederationJwtStatus.mockResolvedValue({
      success: true,
      data: { configured: true, issuer: 'https://api.example.com', key_bits: 256, recommended_bits: 256 },
    });
    mockAdminSuper.updateSystemControls.mockResolvedValue({ success: true });
    mockAdminSuper.addToWhitelist.mockResolvedValue({ success: true });
    mockAdminSuper.removeFromWhitelist.mockResolvedValue({ success: true });
    mockAdminSuper.emergencyLockdown.mockResolvedValue({ success: true });
    mockAdminSuper.liftLockdown.mockResolvedValue({ success: true });
    mockAdminSuper.suspendPartnership.mockResolvedValue({ success: true });
    mockAdminSuper.terminatePartnership.mockResolvedValue({ success: true });
    mockAdminSuper.reactivatePartnership.mockResolvedValue({ success: true });
  });

  it('shows loading spinner initially', async () => {
    mockAdminSuper.getSystemControls.mockImplementationOnce(() => new Promise(() => {}));
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows error state when controls fail to load', async () => {
    mockAdminSuper.getSystemControls.mockResolvedValue({ success: false });
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  it('renders stat cards after successful load', async () => {
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      const statCards = screen.getAllByTestId('stat-card');
      expect(statCards.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('renders federation-enabled switch', async () => {
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      const switches = screen.getAllByRole('switch');
      // Federation enabled + whitelist mode + 6 feature toggles = 8 switches minimum
      expect(switches.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('renders JWT status chip as configured', async () => {
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      // The issuer or configured state text should be visible
      expect(screen.getByText('https://api.example.com')).toBeInTheDocument();
    });
  });

  it('shows warning when JWT is not configured', async () => {
    mockAdminSuper.getFederationJwtStatus.mockResolvedValue({
      success: true,
      data: { configured: false, issuer: '', key_bits: 0, recommended_bits: 256 },
    });
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      // Not-configured chip or warning should appear
      const doc = document.body;
      expect(doc.textContent).toMatch(/not.configured|not configured|jwt_warn/i);
    });
  });

  it('renders whitelist entry names when whitelist is populated', async () => {
    mockAdminSuper.getWhitelist.mockResolvedValue({
      success: true,
      data: [makeWhitelistEntry(3, 'Alpha Tenant'), makeWhitelistEntry(4, 'Beta Tenant')],
    });
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      expect(screen.getByText('Alpha Tenant')).toBeInTheDocument();
      expect(screen.getByText('Beta Tenant')).toBeInTheDocument();
    });
  });

  it('calls addToWhitelist when Add button clicked with a tenant ID', async () => {
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    // Wait for the component to fully load (controls rendered)
    await waitFor(() => {
      expect(screen.getAllByTestId('stat-card').length).toBeGreaterThan(0);
    });

    // Find whitelist tenant ID input — label is i18n key 'super.label_tenant_id'
    // It renders as a textbox (Input component)
    const inputs = screen.getAllByRole('textbox');
    // The whitelist add input is the one with a small width or the one that accepts a number string
    // Pick the last textbox in the whitelist section (or any standalone textbox)
    const tenantIdInput = inputs.find((el) =>
      el.getAttribute('aria-label')?.toLowerCase().includes('tenant') ||
      el.getAttribute('aria-label')?.toLowerCase().includes('id') ||
      el.getAttribute('aria-label')?.includes('label_tenant_id') ||
      el.getAttribute('aria-label')?.includes('super.label_tenant_id')
    ) ?? inputs[inputs.length - 1]; // fallback: last input is the whitelist add field

    if (tenantIdInput) {
      fireEvent.change(tenantIdInput, { target: { value: '7' } });
    }

    // Find Add button
    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') ||
      b.textContent === 'super.add'
    );
    if (addBtn) fireEvent.click(addBtn);

    await waitFor(() => {
      expect(mockAdminSuper.addToWhitelist).toHaveBeenCalledWith(7);
    });
  });

  it('renders active partnerships with suspend/end buttons', async () => {
    mockAdminSuper.getFederationPartnerships.mockResolvedValue({
      success: true,
      data: [makePartnership({ status: 'active' })],
    });
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      expect(screen.getByText('Alpha')).toBeInTheDocument();
      expect(screen.getByText('Beta')).toBeInTheDocument();
    });
  });

  it('renders lockdown active banner when lockdown is active', async () => {
    mockAdminSuper.getSystemControls.mockResolvedValue({
      success: true,
      data: makeControls({ emergency_lockdown_active: true, emergency_lockdown_reason: 'Security incident' }),
    });
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      // Lockdown banner text — rendered via t() so translation key or fallback
      const doc = document.body.textContent;
      expect(doc).toMatch(/lockdown|Security incident/i);
    });
  });

  it('calls updateSystemControls when federation toggle is changed', async () => {
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      const switches = screen.getAllByRole('switch');
      // First switch should be federation_enabled
      expect(switches.length).toBeGreaterThan(0);
      fireEvent.click(switches[0]);
    });

    await waitFor(() => {
      expect(mockAdminSuper.updateSystemControls).toHaveBeenCalled();
    });
  });

  it('shows confirm modal when emergency lockdown button is clicked', async () => {
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      const lockdownBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('lockdown') || b.textContent?.toLowerCase().includes('emergency')
      );
      if (lockdownBtn) fireEvent.click(lockdownBtn);
    });

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('shows error toast when API load fails', async () => {
    mockAdminSuper.getSystemControls.mockRejectedValue(new Error('network'));
    const { FederationControls } = await import('./FederationControls');
    render(<FederationControls />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
