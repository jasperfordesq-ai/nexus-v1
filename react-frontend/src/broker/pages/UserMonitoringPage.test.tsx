// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Hoist mock objects so factory closures see them ─────────────────────────
const { mockBroker, mockAdminUsers } = vi.hoisted(() => ({
  mockBroker: {
    getMonitoring: vi.fn(),
    setMonitoring: vi.fn(),
  },
  mockAdminUsers: {
    list: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminBroker: mockBroker,
  adminUsers: mockAdminUsers,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Toast + tenant mock ──────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

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

// ─── Stub admin components ────────────────────────────────────────────────────
// The DataTable stub executes each column's render() so cell content
// (avatars, chips, countdowns, row actions) is exercised by the tests.
type StubColumn = { key: string; render?: (item: unknown) => React.ReactNode };

vi.mock('@/admin/components', () => ({
  DataTable: ({
    data,
    columns,
    isLoading,
  }: {
    data: Array<Record<string, unknown>>;
    columns: StubColumn[];
    isLoading: boolean;
  }) => (
    <div data-testid="data-table" data-loading={String(isLoading)}>
      {data.map((item) => (
        <div key={String(item.user_id)} data-testid="data-table-row">
          {columns.map((col) => (
            <div key={col.key} data-testid={`cell-${col.key}`}>
              {col.render ? col.render(item) : String(item[col.key] ?? '')}
            </div>
          ))}
        </div>
      ))}
    </div>
  ),
  ConfirmModal: ({
    isOpen,
    onConfirm,
    onClose,
    title,
  }: {
    isOpen: boolean;
    onConfirm: () => void;
    onClose: () => void;
    title: string;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label={title}>
        <button onClick={onConfirm}>Confirm</button>
        <button onClick={onClose}>Cancel</button>
      </div>
    ) : null,
}));

// ─── Helpers ──────────────────────────────────────────────────────────────────
vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...actual,
    resolveAvatarUrl: (u: string | null) => u ?? '',
  };
});
vi.mock('@/lib/serverTime', () => ({
  parseServerTimestamp: (v: string | null) => (v ? new Date(v) : null),
  formatServerDate: (v: string) => v,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const DAY_MS = 86_400_000;

const makeMonitoredUser = (overrides = {}) => ({
  user_id: 10,
  user_name: 'Bob Suspect',
  under_monitoring: true,
  messaging_disabled: false,
  monitoring_reason: 'Suspicious activity',
  monitoring_started_at: '2025-01-01T00:00:00Z',
  monitoring_expires_at: null,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('UserMonitoring', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockBroker.getMonitoring.mockResolvedValue({ success: true, data: [] });
    mockBroker.setMonitoring.mockResolvedValue({ success: true });
    mockAdminUsers.list.mockResolvedValue({ success: true, data: [] });
  });

  it('shows a loading skeleton while fetching monitored users', async () => {
    mockBroker.getMonitoring.mockImplementationOnce(() => new Promise(() => {}));
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      const statusEls = screen.getAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeTruthy();
    });
    // The table itself only renders once data has arrived
    expect(screen.queryByTestId('data-table')).not.toBeInTheDocument();
  });

  it('renders the page header title', async () => {
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    expect(
      screen.getByRole('heading', { level: 1, name: 'User Monitoring' })
    ).toBeInTheDocument();
  });

  it('shows the reassuring all-clear state when nobody is monitored', async () => {
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      expect(screen.getByText('Nobody is under monitoring')).toBeInTheDocument();
    });
  });

  it('shows error toast when getMonitoring fails', async () => {
    mockBroker.getMonitoring.mockRejectedValue(new Error('network'));
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows an honest error state with a retry button on load failure', async () => {
    mockBroker.getMonitoring.mockRejectedValue(new Error('network'));
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      expect(screen.getByText("Couldn't load monitored members")).toBeInTheDocument();
    });
    // A failed load must never render as an all-clear queue
    expect(screen.queryByText('Nobody is under monitoring')).not.toBeInTheDocument();

    const retryBtn = screen.getByRole('button', { name: 'Try again' });
    fireEvent.click(retryBtn);

    await waitFor(() => {
      expect(mockBroker.getMonitoring).toHaveBeenCalledTimes(2);
    });
  });

  it('renders monitored user rows when data is returned', async () => {
    mockBroker.getMonitoring.mockResolvedValue({
      success: true,
      data: [makeMonitoredUser()],
    });
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      expect(screen.getByText('Bob Suspect')).toBeInTheDocument();
    });
    // Reason is readable in the row, not hidden behind a tooltip
    expect(screen.getByText('Suspicious activity')).toBeInTheDocument();
  });

  it('derives the KPI header from the loaded list', async () => {
    mockBroker.getMonitoring.mockResolvedValue({
      success: true,
      data: [
        makeMonitoredUser({ user_id: 1, user_name: 'User One' }),
        makeMonitoredUser({
          user_id: 2,
          user_name: 'User Two',
          messaging_disabled: true,
        }),
        makeMonitoredUser({
          user_id: 3,
          user_name: 'User Three',
          monitoring_expires_at: new Date(Date.now() + 3 * DAY_MS).toISOString(),
        }),
      ],
    });
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      expect(screen.getByText('User One')).toBeInTheDocument();
    });

    const totalLabel = screen.getByText('Under monitoring');
    expect(totalLabel.parentElement?.textContent).toContain('3');

    // KPI label + the row chip both use the shared "Messaging disabled" string
    const disabledLabels = screen.getAllByText('Messaging disabled');
    expect(disabledLabels.length).toBeGreaterThanOrEqual(2);
    expect(disabledLabels[0].parentElement?.textContent).toContain('1');

    const expiringLabel = screen.getByText('Expiring within 7 days');
    expect(expiringLabel.parentElement?.textContent).toContain('1');
  });

  it('renders expiry countdown chips: expired, days-left, and no expiry', async () => {
    mockBroker.getMonitoring.mockResolvedValue({
      success: true,
      data: [
        makeMonitoredUser({
          user_id: 1,
          user_name: 'Expired Eddie',
          monitoring_expires_at: new Date(Date.now() - DAY_MS).toISOString(),
        }),
        makeMonitoredUser({
          user_id: 2,
          user_name: 'Soon Sally',
          monitoring_expires_at: new Date(Date.now() + 3 * DAY_MS).toISOString(),
        }),
        makeMonitoredUser({
          user_id: 3,
          user_name: 'Open Olive',
          monitoring_expires_at: null,
        }),
      ],
    });
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      expect(screen.getByText('Expired')).toBeInTheDocument();
      expect(screen.getByText('3 days left')).toBeInTheDocument();
      expect(screen.getByText('No expiry')).toBeInTheDocument();
    });
  });

  it('renders Add User button', async () => {
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => screen.getByText('Nobody is under monitoring'));

    // monitoring.add_button resolves to "Add User"
    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Add User')
    );
    expect(addBtn).toBeDefined();
  });

  it('opens the add-monitoring modal when button is clicked', async () => {
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => screen.getByText('Nobody is under monitoring'));

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Add User')
    );
    if (addBtn) fireEvent.click(addBtn);

    await waitFor(() => {
      const dialogs = document.querySelectorAll('[role="dialog"]');
      expect(dialogs.length).toBeGreaterThan(0);
    });
  });

  it('confirm button is disabled when no user is selected in the modal', async () => {
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => screen.getByText('Nobody is under monitoring'));

    // Open modal
    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.includes('Add User')
    );
    if (addBtn) fireEvent.click(addBtn);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // The modal confirm "Add User" button should be data-disabled when no user is selected
    const modalAddBtns = screen.getAllByRole('button').filter((b) =>
      b.textContent?.includes('Add User')
    );
    // There should be at least one Add User button in the modal
    expect(modalAddBtns.length).toBeGreaterThan(0);
    // The modal confirm (last) button is disabled when no user is selected
    const modalConfirm = modalAddBtns[modalAddBtns.length - 1];
    // HeroUI renders isDisabled as data-disabled attribute
    const isDisabled =
      modalConfirm.hasAttribute('disabled') ||
      modalConfirm.getAttribute('data-disabled') === 'true' ||
      modalConfirm.getAttribute('aria-disabled') === 'true';
    expect(isDisabled).toBe(true);
  });

  it('opens the edit modal prefilled from the row action', async () => {
    mockBroker.getMonitoring.mockResolvedValue({
      success: true,
      data: [
        makeMonitoredUser({
          user_id: 42,
          monitoring_expires_at: new Date(Date.now() + 10 * DAY_MS).toISOString(),
        }),
      ],
    });
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => screen.getByText('Bob Suspect'));

    fireEvent.click(screen.getByRole('button', { name: 'Edit monitoring' }));

    await waitFor(() => {
      expect(screen.getByText('Edit Monitoring')).toBeInTheDocument();
      // The record's existing expiry is surfaced so a reason-only edit
      // visibly preserves it
      expect(screen.getByText(/Current expiry:/)).toBeInTheDocument();
    });
  });

  it('calls setMonitoring remove when the row action is confirmed', async () => {
    mockBroker.getMonitoring.mockResolvedValue({
      success: true,
      data: [makeMonitoredUser({ user_id: 42 })],
    });
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => screen.getByText('Bob Suspect'));

    fireEvent.click(screen.getByRole('button', { name: 'Remove from monitoring' }));

    // ConfirmModal stub renders a Confirm button
    const confirmDialog = await screen.findByRole('dialog', { name: 'Remove from Monitoring' });
    expect(confirmDialog).toBeInTheDocument();
    fireEvent.click(screen.getByText('Confirm'));

    await waitFor(() => {
      expect(mockBroker.setMonitoring).toHaveBeenCalledWith(42, { under_monitoring: false });
    });
  });

  it('renders multiple monitored users', async () => {
    mockBroker.getMonitoring.mockResolvedValue({
      success: true,
      data: [
        makeMonitoredUser({ user_id: 1, user_name: 'User One' }),
        makeMonitoredUser({ user_id: 2, user_name: 'User Two' }),
      ],
    });
    const { UserMonitoring } = await import('./UserMonitoringPage');
    render(<UserMonitoring />);

    await waitFor(() => {
      expect(screen.getByText('User One')).toBeInTheDocument();
      expect(screen.getByText('User Two')).toBeInTheDocument();
    });
  });
});
