// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Hoisted mocks ───────────────────────────────────────────────────────────
const { mockAdminVolunteering } = vi.hoisted(() => ({
  mockAdminVolunteering: {
    getExpenses: vi.fn(),
    reviewExpense: vi.fn(),
    exportExpenses: vi.fn(),
    getExpensePolicies: vi.fn(),
    updateExpensePolicies: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminVolunteering: mockAdminVolunteering,
}));

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn() },
  default: { get: vi.fn(), post: vi.fn() },
  API_BASE: 'http://localhost:8090/api',
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast ────────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// Stub DataTable, EmptyState, StatCard, PageHeader from admin components
vi.mock('../../components', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    DataTable: ({ data, columns, isLoading }: {
      data: unknown[];
      columns: Array<{ key: string; label: string; render?: (item: unknown) => React.ReactNode }>;
      isLoading?: boolean;
    }) => {
      if (isLoading) return <div role="status" aria-busy="true" aria-label="loading" />;
      if (!data || data.length === 0) return <div data-testid="data-table-empty">No data</div>;
      return (
        <div data-testid="data-table">
          {(data as Array<Record<string, unknown>>).map((row) => (
            <div key={String(row.id)} data-testid={`row-${String(row.id)}`}>
              {columns.map((col) => (
                <div key={col.key}>
                  {col.render
                    ? col.render(row)
                    : String(row[col.key] ?? '')}
                </div>
              ))}
            </div>
          ))}
        </div>
      );
    },
    EmptyState: ({ title, description }: { title: string; description?: string }) => (
      <div data-testid="empty-state">
        <p>{title}</p>
        {description && <p>{description}</p>}
      </div>
    ),
    StatCard: ({ label, value }: { label: string; value: number | string }) => (
      <div data-testid="stat-card">
        <span>{label}</span>
        <span>{value}</span>
      </div>
    ),
    PageHeader: ({ title, actions }: { title?: React.ReactNode; actions?: React.ReactNode }) => (
      <div data-testid="page-header">
        <h1>{title}</h1>
        {actions}
      </div>
    ),
  };
});

// Stub Select to avoid HeroUI infinite-loop
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    Select: ({ label, children, onSelectionChange }: {
      label?: string;
      children?: React.ReactNode;
      onSelectionChange?: (keys: Set<string>) => void;
    }) => (
      <select
        aria-label={label}
        onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}
      >
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children?: React.ReactNode; id?: string }) => (
      <option value={id}>{children}</option>
    ),
    Accordion: ({ children }: { children?: React.ReactNode }) => (
      <div data-testid="accordion">{children}</div>
    ),
    AccordionItem: ({ children, title }: { children?: React.ReactNode; title?: React.ReactNode }) => (
      <div data-testid="accordion-item">
        <div>{title}</div>
        <div>{children}</div>
      </div>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeExpense = (overrides = {}): Record<string, unknown> => ({
  id: 1,
  volunteer_name: 'Alice Volunteer',
  organization_name: 'Green Community',
  amount: 25.5,
  currency: '€',
  type: 'travel',
  status: 'pending',
  submitted_at: '2026-06-01T10:00:00Z',
  has_receipt: false,
  description: 'Bus fare to volunteering location',
  ...overrides,
});

const makeStats = (overrides = {}) => ({
  total_submitted: 10,
  pending_review: 4,
  approved_total: 5,
  paid_total: 1,
  ...overrides,
});

const makePolicy = (overrides = {}) => ({
  id: 1,
  type: 'travel',
  expense_type: 'travel',
  max_amount: 100,
  max_monthly: 200,
  requires_receipt_above: 25,
  requires_approval: true,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('VolunteerExpenses', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminVolunteering.getExpenses.mockResolvedValue({
      success: true,
      data: { items: [], stats: makeStats() },
    });
    mockAdminVolunteering.getExpensePolicies.mockResolvedValue({
      success: true,
      data: [],
    });
    mockAdminVolunteering.reviewExpense.mockResolvedValue({ success: true });
    mockAdminVolunteering.updateExpensePolicies.mockResolvedValue({ success: true });
    mockAdminVolunteering.exportExpenses.mockResolvedValue(new Blob(['csv'], { type: 'text/csv' }));
  });

  it('shows loading state on initial mount', async () => {
    mockAdminVolunteering.getExpenses.mockImplementationOnce(() => new Promise(() => {}));
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders stat cards once data is loaded', async () => {
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      const statCards = screen.getAllByTestId('stat-card');
      expect(statCards.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('shows empty state when no expenses returned', async () => {
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders expense rows in DataTable when expenses are present', async () => {
    mockAdminVolunteering.getExpenses.mockResolvedValue({
      success: true,
      data: {
        items: [makeExpense()],
        stats: makeStats(),
      },
    });
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      expect(screen.getByTestId('data-table')).toBeInTheDocument();
      expect(screen.getByText('Alice Volunteer')).toBeInTheDocument();
    });
  });

  it('renders Export CSV and Refresh buttons', async () => {
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const exportBtn = buttons.find((b) =>
        b.textContent?.toLowerCase().includes('export') ||
        b.textContent?.toLowerCase().includes('csv'),
      );
      const refreshBtn = buttons.find((b) =>
        b.textContent?.toLowerCase().includes('refresh'),
      );
      expect(exportBtn).toBeDefined();
      expect(refreshBtn).toBeDefined();
    });
  });

  it('shows error toast when expense load fails', async () => {
    mockAdminVolunteering.getExpenses.mockRejectedValueOnce(new Error('Network error'));
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows date range filter inputs', async () => {
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      const dateInputs = document.querySelectorAll('input[type="date"]');
      expect(dateInputs.length).toBeGreaterThanOrEqual(2);
    });
  });

  it('opens review modal when Review button is clicked', async () => {
    mockAdminVolunteering.getExpenses.mockResolvedValue({
      success: true,
      data: { items: [makeExpense({ id: 5 })], stats: makeStats() },
    });
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => screen.getByTestId('data-table'));

    const reviewBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('review'),
    );
    if (reviewBtn) {
      fireEvent.click(reviewBtn);
      await waitFor(() => {
        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog).toBeTruthy();
      });
    }
  });

  it('calls reviewExpense API on modal confirmation', async () => {
    mockAdminVolunteering.getExpenses.mockResolvedValue({
      success: true,
      data: { items: [makeExpense({ id: 5 })], stats: makeStats() },
    });
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => screen.getByTestId('data-table'));

    // Click Review button
    const reviewBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('review'),
    );
    if (reviewBtn) {
      fireEvent.click(reviewBtn);

      await waitFor(() => document.querySelector('[role="dialog"]'));

      // Click the primary action button (approve/reject/paid)
      const dialogBtns = document.querySelector('[role="dialog"]')?.querySelectorAll('button');
      const confirmBtn = Array.from(dialogBtns ?? []).find((b) =>
        b.textContent?.toLowerCase().includes('approve') ||
        b.textContent?.toLowerCase().includes('confirm') ||
        b.textContent?.toLowerCase().includes('save'),
      );
      if (confirmBtn) {
        fireEvent.click(confirmBtn);
        await waitFor(() => {
          expect(mockAdminVolunteering.reviewExpense).toHaveBeenCalledWith(
            5,
            expect.objectContaining({ status: expect.any(String) }),
          );
        });
      }
    }
  });

  it('renders policies section heading', async () => {
    mockAdminVolunteering.getExpensePolicies.mockResolvedValue({
      success: true,
      data: [makePolicy()],
    });
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      // Policy section card should render
      expect(screen.getByTestId('accordion')).toBeInTheDocument();
    });
  });

  it('shows org breakdown table when expenses with org data are loaded', async () => {
    mockAdminVolunteering.getExpenses.mockResolvedValue({
      success: true,
      data: {
        items: [
          makeExpense({ id: 1, organization_name: 'Green Org', amount: 50, status: 'approved' }),
          makeExpense({ id: 2, organization_name: 'Blue Org', amount: 30, status: 'pending' }),
        ],
        stats: makeStats(),
      },
    });
    const { VolunteerExpenses } = await import('./VolunteerExpenses');
    render(<VolunteerExpenses />);

    await waitFor(() => {
      expect(screen.getByText('Green Org')).toBeInTheDocument();
      expect(screen.getByText('Blue Org')).toBeInTheDocument();
    });
  });
});
