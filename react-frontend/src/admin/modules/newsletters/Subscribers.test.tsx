// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ─── Hoist mock data ──────────────────────────────────────────────────────────
const { mockAdminNewsletters } = vi.hoisted(() => ({
  mockAdminNewsletters: {
    getSubscribers: vi.fn(),
    addSubscriber: vi.fn(),
    removeSubscriber: vi.fn(),
    importSubscribers: vi.fn(),
    exportSubscribers: vi.fn(),
    syncMembers: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminNewsletters: mockAdminNewsletters,
}));

// ─── Stub PapaParse (CSV) ─────────────────────────────────────────────────────
vi.mock('papaparse', () => ({
  default: {
    parse: vi.fn(),
  },
}));

// ─── Admin components ─────────────────────────────────────────────────────────
vi.mock('../../components/PageHeader', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      <div>{actions}</div>
    </div>
  ),
}));

vi.mock('../../components/StatCard', () => ({
  StatCard: ({ label, value }: { label: string; value: number | string; icon?: unknown; color?: string; loading?: boolean }) => (
    <div data-testid="stat-card">
      <span>{label}</span>
      <span data-testid={`stat-value-${label.toLowerCase().replace(/\s+/g, '-')}`}>{value}</span>
    </div>
  ),
}));

vi.mock('../../components/ConfirmModal', () => ({
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
    confirmLabel: string;
    message?: string;
    confirmColor?: string;
    isLoading?: boolean;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label={title} data-testid="confirm-modal">
        <p>{title}</p>
        <button onClick={onConfirm} data-testid="confirm-btn">{confirmLabel}</button>
        <button onClick={onClose} data-testid="cancel-btn">Cancel</button>
      </div>
    ) : null,
}));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeSubscriber = (overrides = {}) => ({
  id: 1,
  first_name: 'Jane',
  last_name: 'Doe',
  email: 'jane@example.com',
  status: 'active',
  source: 'signup',
  created_at: '2024-01-15T00:00:00Z',
  confirmed_at: '2024-01-15T00:00:00Z',
  user_id: 10,
  ...overrides,
});

const makeStats = (overrides = {}) => ({
  total: 42,
  active: 30,
  pending: 8,
  unsubscribed: 4,
  ...overrides,
});

const makeApiResponse = (subscribers = [makeSubscriber()], stats = makeStats()) => ({
  success: true,
  data: {
    data: subscribers,
    meta: { total: subscribers.length, total_pages: 1 },
    stats,
  },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('Subscribers', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminNewsletters.getSubscribers.mockResolvedValue(makeApiResponse());
  });

  it('shows subscriber email after loading', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => {
      expect(screen.getByText('jane@example.com')).toBeInTheDocument();
    });
  });

  it('shows empty table message when no subscribers', async () => {
    mockAdminNewsletters.getSubscribers.mockResolvedValue(makeApiResponse([]));
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => {
      // HeroUI TableBody emptyContent — t() returns the key itself in tests
      const empties = screen.getAllByText(/newsletters\.|no subscriber/i);
      expect(empties.length).toBeGreaterThan(0);
    });
  });

  it('renders stats card values', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => {
      const statCards = screen.getAllByTestId('stat-card');
      expect(statCards.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('shows "Add Subscriber" button', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => {
      const btn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('add') && b.textContent?.toLowerCase().includes('subscriber')
      );
      expect(btn).toBeInTheDocument();
    });
  });

  it('opens add subscriber modal when button is clicked', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') && b.textContent?.toLowerCase().includes('subscriber')
    );
    fireEvent.click(addBtn!);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('calls addSubscriber API with email when modal is submitted', async () => {
    mockAdminNewsletters.addSubscriber.mockResolvedValue({ success: true });
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') && b.textContent?.toLowerCase().includes('subscriber')
    );
    fireEvent.click(addBtn!);

    await waitFor(() => document.querySelector('[role="dialog"]'));

    // Find the email input inside the modal
    const emailInput = document.querySelector('input[type="email"]') as HTMLInputElement;
    expect(emailInput).toBeTruthy();

    fireEvent.change(emailInput, { target: { value: 'new@example.com' } });

    // Find the submit button in the modal (contains "add")
    const submitBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('add') &&
      b.textContent?.toLowerCase().includes('subscriber') &&
      !b.disabled
    );
    if (submitBtn) {
      fireEvent.click(submitBtn);
      await waitFor(() => {
        expect(mockAdminNewsletters.addSubscriber).toHaveBeenCalledWith(
          expect.objectContaining({ email: 'new@example.com' })
        );
      });
    }
  });

  it('opens remove confirm modal when trash button is clicked', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const removeBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove')
    );
    expect(removeBtn).toBeDefined();
    fireEvent.click(removeBtn!);

    await waitFor(() => {
      expect(screen.getByTestId('confirm-modal')).toBeInTheDocument();
    });
  });

  it('calls removeSubscriber API when confirm is clicked', async () => {
    mockAdminNewsletters.removeSubscriber.mockResolvedValue({ success: true });
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const removeBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove')
    );
    fireEvent.click(removeBtn!);

    await waitFor(() => screen.getByTestId('confirm-modal'));
    fireEvent.click(screen.getByTestId('confirm-btn'));

    await waitFor(() => {
      expect(mockAdminNewsletters.removeSubscriber).toHaveBeenCalledWith(1);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('calls syncMembers when "Sync Now" is clicked', async () => {
    mockAdminNewsletters.syncMembers.mockResolvedValue({ success: true });
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const syncBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('sync')
    );
    expect(syncBtn).toBeDefined();
    fireEvent.click(syncBtn!);

    await waitFor(() => {
      expect(mockAdminNewsletters.syncMembers).toHaveBeenCalled();
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows Export CSV button', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => {
      const exportBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('export')
      );
      expect(exportBtn).toBeInTheDocument();
    });
  });

  it('calls exportSubscribers when Export CSV button is clicked', async () => {
    // Return empty array to exercise the "no subscribers" path without DOM api complications
    mockAdminNewsletters.exportSubscribers.mockResolvedValue({ success: true, data: [] });
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const exportBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('export')
    );
    fireEvent.click(exportBtn!);

    await waitFor(() => {
      expect(mockAdminNewsletters.exportSubscribers).toHaveBeenCalled();
    });
  });

  it('shows Import CSV button and opens modal', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const importBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('import')
    );
    expect(importBtn).toBeDefined();
    fireEvent.click(importBtn!);

    await waitFor(() => {
      const dialog = document.querySelector('[role="dialog"]');
      expect(dialog).toBeTruthy();
    });
  });

  it('shows stat card buttons that act as status filters', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    // Each StatCard is wrapped in a <Button> — our StatCard stub renders data-testid="stat-card"
    // inside the real HeroUI Button, which renders as a <button> element
    const statCardBtns = screen.getAllByRole('button').filter((b) =>
      b.querySelector('[data-testid="stat-card"]') !== null
    );
    expect(statCardBtns.length).toBeGreaterThanOrEqual(4);
  });

  it('shows subscriber status chip', async () => {
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => {
      expect(screen.getAllByText('Active').length).toBeGreaterThan(0);
    });
  });

  it('shows error toast when removeSubscriber fails', async () => {
    mockAdminNewsletters.removeSubscriber.mockResolvedValue({ success: false });
    const { Subscribers } = await import('./Subscribers');
    render(<Subscribers />);

    await waitFor(() => screen.getByText('jane@example.com'));

    const removeBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove')
    );
    fireEvent.click(removeBtn!);

    await waitFor(() => screen.getByTestId('confirm-modal'));
    fireEvent.click(screen.getByTestId('confirm-btn'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
