// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock adminApi (default+named same object via vi.hoisted) ─────────────────
const { mockAdminBroker } = vi.hoisted(() => ({
  mockAdminBroker: {
    getConfiguration: vi.fn(),
    saveConfiguration: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminBroker: mockAdminBroker,
  default: { adminBroker: mockAdminBroker },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub HeroUI Switch to avoid potential jsdom infinite loops ───────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Switch: ({ isSelected, onValueChange, isDisabled, ...rest }: {
      isSelected?: boolean; onValueChange?: (v: boolean) => void; isDisabled?: boolean; [k: string]: unknown;
    }) => (
      <input
        type="checkbox"
        role="switch"
        checked={!!isSelected}
        disabled={isDisabled}
        onChange={(e) => onValueChange?.(e.target.checked)}
        {...(typeof rest['aria-label'] === 'string' ? { 'aria-label': rest['aria-label'] as string } : {})}
      />
    ),
  };
});

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub admin PageHeader ─────────────────────────────────────────────────────
vi.mock('@/admin/components/PageHeader', () => ({
  PageHeader: ({ title, actions }: { title?: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      <div>{actions}</div>
    </div>
  ),
}));

// ─── Toast and contexts ────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useNavigate: () => mockNavigate,
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => (
      <a href={to}>{children}</a>
    ),
  };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useAuth: () => ({
      user: { id: 1, name: 'Admin User', role: 'admin' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Fixture config ────────────────────────────────────────────────────────────
const defaultConfig = {
  broker_messaging_enabled: true,
  broker_copy_all_messages: false,
  broker_copy_threshold_hours: 5,
  new_member_monitoring_days: 30,
  require_exchange_for_listings: false,
  risk_tagging_enabled: true,
  auto_flag_high_risk: true,
  require_approval_high_risk: false,
  notify_on_high_risk_match: true,
  broker_approval_required: true,
  auto_approve_low_risk: false,
  exchange_timeout_days: 7,
  max_hours_without_approval: 5,
  confirmation_deadline_hours: 48,
  allow_hour_adjustment: false,
  max_hour_variance_percent: 20,
  expiry_hours: 168,
  broker_visible_to_members: false,
  show_broker_name: false,
  broker_contact_email: '',
  copy_first_contact: true,
  copy_new_member_messages: true,
  copy_high_risk_listing_messages: true,
  random_sample_percentage: 0,
  retention_days: 90,
  vetting_enabled: false,
  insurance_enabled: false,
  enforce_vetting_on_exchanges: false,
  enforce_insurance_on_exchanges: false,
  vetting_expiry_warning_days: 30,
  insurance_expiry_warning_days: 30,
};

// ─────────────────────────────────────────────────────────────────────────────
describe('BrokerConfigurationPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminBroker.getConfiguration.mockResolvedValue({ success: true, data: { ...defaultConfig } });
    mockAdminBroker.saveConfiguration.mockResolvedValue({ success: true, data: { ...defaultConfig } });
  });

  it('shows loading spinner initially', async () => {
    mockAdminBroker.getConfiguration.mockImplementationOnce(() => new Promise(() => {}));
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders configuration sections after load', async () => {
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      const saveBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('save')
      );
      expect(saveBtn).toBeDefined();
    });
  });

  it('shows error toast when config load fails', async () => {
    mockAdminBroker.getConfiguration.mockRejectedValue(new Error('network'));
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls saveConfiguration when Save Changes button is clicked', async () => {
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      const saveBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('save')
      );
      expect(saveBtn).toBeDefined();
      if (saveBtn) fireEvent.click(saveBtn);
    });

    await waitFor(() => {
      expect(mockAdminBroker.saveConfiguration).toHaveBeenCalledWith(
        expect.objectContaining({ broker_messaging_enabled: true })
      );
    });
  });

  it('shows success toast after save succeeds', async () => {
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      const saveBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('save')
      );
      if (saveBtn) fireEvent.click(saveBtn);
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when save fails', async () => {
    mockAdminBroker.saveConfiguration.mockResolvedValue({ success: false, error: 'Oops' });
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      const saveBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('save')
      );
      if (saveBtn) fireEvent.click(saveBtn);
    });

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders switch toggles for boolean settings', async () => {
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      const switches = screen.getAllByRole('switch');
      expect(switches.length).toBeGreaterThan(0);
    });
  });

  it('does not show limited access warning for admin user', async () => {
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      // limited_access_title key would appear if user is not admin tier
      const warning = screen.queryByText(/configuration.limited_access_title/);
      expect(warning).toBeNull();
    });
  });

  it('renders numeric input fields for time-based settings', async () => {
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      const numberInputs = screen.getAllByRole('spinbutton');
      expect(numberInputs.length).toBeGreaterThan(0);
    });
  });

  it('shows back button linking to broker dashboard', async () => {
    const { default: BrokerConfigurationPage } = await import('./BrokerConfigurationPage');
    render(<BrokerConfigurationPage />);

    await waitFor(() => {
      const backBtn = screen.getAllByRole('link').find((el) =>
        el.getAttribute('href')?.includes('/broker') || el.textContent?.toLowerCase().includes('back')
      );
      expect(backBtn).toBeDefined();
    });
  });
});
