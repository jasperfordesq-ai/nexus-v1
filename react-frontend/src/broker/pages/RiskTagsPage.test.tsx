// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock adminApi ─────────────────────────────────────────────────────────────
const { mockAdminBroker, mockAdminListings } = vi.hoisted(() => ({
  mockAdminBroker: {
    getRiskTags: vi.fn(),
    saveRiskTag: vi.fn(),
    removeRiskTag: vi.fn(),
  },
  mockAdminListings: {
    list: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminBroker: mockAdminBroker,
  adminListings: mockAdminListings,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
    useToast: () => mockToast,
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub admin/components ────────────────────────────────────────────────────
vi.mock('@/admin/components', () => ({
  DataTable: ({
    data,
    columns,
    isLoading,
  }: {
    data: Record<string, unknown>[];
    columns: { key: string; label: string; render?: (item: Record<string, unknown>) => React.ReactNode }[];
    isLoading?: boolean;
  }) => {
    if (isLoading) {
      return <div role="status" aria-busy="true" data-testid="datatable-loading">Loading…</div>;
    }
    return (
      <table>
        <thead>
          <tr>
            {columns.map((c) => (
              <th key={c.key}>{c.label}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {data.map((row, ri) => (
            <tr key={ri}>
              {columns.map((c) => (
                <td key={c.key}>
                  {c.render ? c.render(row) : String(row[c.key] ?? '')}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    );
  },
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <span>{title}</span>
      <div>{actions}</div>
    </div>
  ),
  ConfirmModal: ({
    isOpen,
    onClose,
    onConfirm,
    title,
  }: {
    isOpen: boolean;
    onClose: () => void;
    onConfirm: () => void;
    title: string;
    message?: string;
    confirmLabel?: string;
    confirmColor?: string;
    isLoading?: boolean;
  }) =>
    isOpen ? (
      <div role="dialog" aria-label="Dialog" data-testid="confirm-modal">
        <span>{title}</span>
        <button onClick={onConfirm}>Confirm</button>
        <button onClick={onClose}>Cancel</button>
      </div>
    ) : null,
  StatusBadge: ({ status }: { status: string }) => <span>{status}</span>,
}));

vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// Stub formatServerDate
vi.mock('@/lib/serverTime', () => ({
  formatServerDate: (d: string) => d,
}));

// ─── Stub HeroUI Select / Switch to prevent jsdom hangs ───────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Select: ({ label, children, onSelectionChange }: {
      label?: string;
      children?: React.ReactNode;
      onSelectionChange?: (keys: Set<string>) => void;
      selectedKeys?: Set<string> | string[];
      isRequired?: boolean;
    }) => (
      <select
        aria-label={label ?? 'select'}
        onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}
      >
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children: React.ReactNode; id: string }) => (
      <option value={id}>{children}</option>
    ),
    Switch: ({ isSelected, onValueChange, size: _size }: {
      isSelected: boolean;
      onValueChange?: (v: boolean) => void;
      size?: string;
    }) => (
      <input
        type="checkbox"
        data-testid="switch"
        checked={isSelected}
        onChange={(e) => onValueChange?.(e.target.checked)}
      />
    ),
    Tabs: ({ children, onSelectionChange, selectedKey, 'aria-label': al }: {
      children: React.ReactNode;
      onSelectionChange?: (key: React.Key) => void;
      selectedKey?: string;
      'aria-label'?: string;
      variant?: string;
      size?: string;
    }) => (
      <div role="tablist" aria-label={al}>
        {children}
      </div>
    ),
    Tab: ({ key: _key, title }: { key?: string; title: string }) => (
      <div role="tab">{title}</div>
    ),
  };
});

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeTag = (overrides: Record<string, unknown> = {}) => ({
  id: 1,
  listing_id: 10,
  listing_title: 'Dog Walking Service',
  owner_name: 'Alice',
  risk_level: 'high' as const,
  risk_category: 'health_safety',
  risk_notes: 'Requires training',
  member_visible_notes: 'Please note risks',
  requires_approval: true,
  insurance_required: true,
  dbs_required: false,
  tagged_by_name: 'Admin Bob',
  created_at: '2025-01-01T00:00:00Z',
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('RiskTagsPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminBroker.getRiskTags.mockResolvedValue({
      success: true,
      data: [makeTag()],
    });
    mockAdminBroker.saveRiskTag.mockResolvedValue({ success: true });
    mockAdminBroker.removeRiskTag.mockResolvedValue({ success: true });
    mockAdminListings.list.mockResolvedValue({ success: true, data: [] });
  });

  it('shows loading state initially', async () => {
    mockAdminBroker.getRiskTags.mockImplementationOnce(() => new Promise(() => {}));
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    expect(screen.getByTestId('datatable-loading')).toBeInTheDocument();
  });

  it('renders listing title after data loads', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => {
      expect(screen.getByText('Dog Walking Service')).toBeInTheDocument();
    });
  });

  it('renders owner name in the table', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => {
      expect(screen.getByText('Alice')).toBeInTheDocument();
    });
  });

  it('renders empty table when no tags returned', async () => {
    mockAdminBroker.getRiskTags.mockResolvedValue({ success: true, data: [] });
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => {
      expect(screen.queryByText('Dog Walking Service')).not.toBeInTheDocument();
    });
  });

  it('shows error toast when API fails', async () => {
    mockAdminBroker.getRiskTags.mockRejectedValue(new Error('server error'));
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('opens create modal when Tag Listing button pressed', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => screen.getByText('Dog Walking Service'));

    const addBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('tag') || b.textContent?.toLowerCase().includes('listing')
    );
    expect(addBtn).toBeDefined();
    if (addBtn) fireEvent.click(addBtn);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('opens edit modal when edit icon button clicked', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => screen.getByText('Dog Walking Service'));

    const editBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('edit')
    );
    expect(editBtn).toBeDefined();
    if (editBtn) fireEvent.click(editBtn);

    await waitFor(() => {
      expect(document.querySelector('[role="dialog"]')).toBeTruthy();
    });
  });

  it('opens confirm modal when remove icon button clicked', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => screen.getByText('Dog Walking Service'));

    const removeBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove')
    );
    expect(removeBtn).toBeDefined();
    if (removeBtn) fireEvent.click(removeBtn);

    await waitFor(() => {
      expect(screen.getByTestId('confirm-modal')).toBeInTheDocument();
    });
  });

  it('calls removeRiskTag and shows success toast after confirm', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => screen.getByText('Dog Walking Service'));

    const removeBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('remove')
    );
    if (removeBtn) fireEvent.click(removeBtn);

    await waitFor(() => screen.getByTestId('confirm-modal'));

    const confirmBtn = screen.getByText('Confirm');
    fireEvent.click(confirmBtn);

    await waitFor(() => {
      expect(mockAdminBroker.removeRiskTag).toHaveBeenCalledWith(10);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('renders tab filters (all / critical / high / medium / low)', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => screen.getByText('Dog Walking Service'));

    const tabs = screen.getAllByRole('tab');
    expect(tabs.length).toBeGreaterThanOrEqual(5);
  });

  it('renders tagged_by_name in table', async () => {
    const { RiskTagsPage } = await import('./RiskTagsPage');
    render(<RiskTagsPage />);

    await waitFor(() => {
      expect(screen.getByText('Admin Bob')).toBeInTheDocument();
    });
  });
});
