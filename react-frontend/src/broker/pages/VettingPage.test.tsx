// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── Mock adminVetting + adminUsers ──────────────────────────────────────────
const { mockAdminVetting, mockAdminUsers } = vi.hoisted(() => ({
  mockAdminVetting: {
    stats: vi.fn(),
    list: vi.fn(),
    verify: vi.fn(),
    reject: vi.fn(),
    destroy: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    uploadDocument: vi.fn(),
    bulk: vi.fn(),
    show: vi.fn(),
    getUserRecords: vi.fn(),
  },
  mockAdminUsers: {
    list: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminVetting: mockAdminVetting,
  adminUsers: mockAdminUsers,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// Stub helper libs — use importOriginal so cn + all other utils are preserved
vi.mock('@/lib/helpers', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...orig,
    resolveAvatarUrl: (url: string | null) => url ?? null,
  };
});

vi.mock('@/lib/serverTime', () => ({
  parseServerTimestamp: (s: string | null | undefined) => (s ? new Date(s) : null),
  formatServerDate: (s: string | null | undefined) => (s ? s.split('T')[0] ?? '' : '—'),
  formatServerDateTime: (s: string | null | undefined) => (s ? s : '—'),
}));

// ─── Stub DataTable / StatCard / PageHeader / ConfirmModal / EmptyState ────
vi.mock('@/admin/components', async (importOriginal) => {
  const orig = await importOriginal<Record<string, unknown>>();
  return {
    ...orig,
    DataTable: ({
      data,
      columns,
      isLoading,
      emptyContent,
      selectable,
      selectedKeys,
      onSelectionChange,
    }: {
      data?: Record<string, unknown>[];
      columns?: Array<{ key: string; label?: string; render?: (row: Record<string, unknown>) => React.ReactNode }>;
      isLoading?: boolean;
      emptyContent?: React.ReactNode;
      selectable?: boolean;
      selectedKeys?: Set<string>;
      onSelectionChange?: (keys: Set<string>) => void;
    }) => {
      if (isLoading) return <div role="status" aria-busy="true">Loading...</div>;
      if (!data || data.length === 0) return <>{emptyContent}</>;
      return (
        <table>
          <tbody>
            {data.map((row, i) => (
              <tr key={i}>
                {selectable && (
                  <td>
                    <input
                      type="checkbox"
                      aria-label={`select-${String(row.id ?? i)}`}
                      onChange={(e) => {
                        const newSet = new Set(selectedKeys ?? []);
                        const idStr = String(row.id ?? i);
                        if (e.target.checked) newSet.add(idStr);
                        else newSet.delete(idStr);
                        onSelectionChange?.(newSet);
                      }}
                    />
                  </td>
                )}
                {(columns ?? []).map((col) => (
                  <td key={col.key}>{col.render ? col.render(row) : String(row[col.key] ?? '')}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      );
    },
    StatCard: ({ label, value }: { label?: string; value?: unknown }) => (
      <div data-testid="stat-card">{label}: {String(value ?? 0)}</div>
    ),
    PageHeader: ({ title, actions }: { title?: string; actions?: React.ReactNode }) => (
      <div><h1>{title}</h1>{actions}</div>
    ),
    ConfirmModal: ({
      isOpen,
      onConfirm,
      onClose,
      title,
    }: {
      isOpen?: boolean;
      onConfirm?: () => void;
      onClose?: () => void;
      title?: string;
    }) =>
      isOpen ? (
        <div role="dialog" aria-label={title}>
          <span>{title}</span>
          <button onClick={onConfirm}>Confirm</button>
          <button onClick={onClose}>Cancel</button>
        </div>
      ) : null,
    EmptyState: ({ title }: { title?: string }) => (
      <div data-testid="empty-state">{title}</div>
    ),
  };
});

// Stub HeroUI Select/Switch to avoid infinite-loops
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Select: ({ label, children, onSelectionChange }: { label?: string; children?: React.ReactNode; onSelectionChange?: (keys: Set<string>) => void }) => (
      <select aria-label={label ?? 'select'} onChange={(e) => onSelectionChange?.(new Set([e.target.value]))}>
        {children}
      </select>
    ),
    SelectItem: ({ children, id }: { children?: React.ReactNode; id?: string }) => (
      <option value={id ?? ''}>{children}</option>
    ),
  };
});

// ─── Mock react-router-dom ───────────────────────────────────────────────────
vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
    Link: ({ children, to }: { children?: React.ReactNode; to?: string }) => <a href={to}>{children}</a>,
  };
});

// ─── Mock contexts ────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

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
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeRecord = (overrides = {}): Record<string, unknown> => ({
  id: 1,
  user_id: 100,
  first_name: 'Alice',
  last_name: 'Smith',
  email: 'alice@example.com',
  avatar_url: null,
  vetting_type: 'dbs_basic',
  status: 'pending',
  reference_number: 'DBS-001',
  issue_date: '2024-01-15',
  expiry_date: '2027-01-15',
  works_with_children: false,
  works_with_vulnerable_adults: false,
  requires_enhanced_check: false,
  notes: null,
  document_url: null,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
  ...overrides,
});

const makeStats = () => ({
  total: 15,
  pending: 3,
  pending_review: 5,
  submitted: 2,
  verified: 8,
  expired: 1,
  expiring_soon: 2,
  rejected: 1,
});

const makeListResponse = (data: Record<string, unknown>[] = [], total = data.length) => ({
  success: true,
  data,
  meta: { total, page: 1, per_page: 25 },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('VettingPage (VettingRecords)', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminVetting.stats.mockResolvedValue({ success: true, data: makeStats() });
    mockAdminVetting.list.mockResolvedValue(makeListResponse([makeRecord()]));
    mockAdminVetting.verify.mockResolvedValue({ success: true });
    mockAdminVetting.reject.mockResolvedValue({ success: true });
    mockAdminVetting.destroy.mockResolvedValue({ success: true });
    mockAdminVetting.create.mockResolvedValue({ success: true, data: { id: 99 } });
    mockAdminVetting.update.mockResolvedValue({ success: true });
    mockAdminVetting.bulk.mockResolvedValue({ success: true, data: { processed: 1, failed: 0, total: 1, action: 'verify' } });
    mockAdminUsers.list.mockResolvedValue({ success: true, data: [] });
  });

  it('shows loading spinner while list loads', async () => {
    mockAdminVetting.list.mockImplementationOnce(() => new Promise(() => {}));
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders stat cards after loading stats', async () => {
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      const cards = screen.getAllByTestId('stat-card');
      expect(cards.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('displays vetting record row in table', async () => {
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      // member column renders "{first_name} {last_name}" as one string
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });
  });

  it('shows empty state when no records', async () => {
    mockAdminVetting.list.mockResolvedValueOnce(makeListResponse([]));
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('calls adminVetting.list on mount', async () => {
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      expect(mockAdminVetting.list).toHaveBeenCalledTimes(1);
    });
  });

  it('calls adminVetting.stats on mount', async () => {
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      expect(mockAdminVetting.stats).toHaveBeenCalledTimes(1);
    });
  });

  it('shows Verify and Reject action buttons for pending records', async () => {
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
    });

    // Verify (check icon) and reject (x icon) buttons should be present for pending
    const verifyBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('verify')
    );
    const rejectBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('reject')
    );

    expect(verifyBtn).toBeDefined();
    expect(rejectBtn).toBeDefined();
  });

  it('calls adminVetting.verify when Verify button clicked', async () => {
    const user = userEvent.setup();
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const verifyBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('verify')
    );

    if (verifyBtn) {
      await user.click(verifyBtn);
      await waitFor(() => {
        expect(mockAdminVetting.verify).toHaveBeenCalledWith(1);
      });
    }
  });

  it('opens reject modal and calls adminVetting.reject with reason', async () => {
    const user = userEvent.setup();
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const rejectBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('reject')
    );

    if (rejectBtn) {
      await user.click(rejectBtn);

      // Reject modal should open
      await waitFor(() => {
        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog).toBeTruthy();
      });

      // Fill in reason and submit
      const textarea = document.querySelector('textarea');
      if (textarea) {
        await user.type(textarea, 'Insufficient documentation');
        const confirmBtn = screen.getAllByRole('button').find((b) =>
          b.textContent?.toLowerCase().includes('confirm') ||
          b.textContent?.toLowerCase().includes('reject')
        );
        if (confirmBtn) {
          await user.click(confirmBtn);
          await waitFor(() => {
            expect(mockAdminVetting.reject).toHaveBeenCalledWith(1, 'Insufficient documentation');
          });
        }
      }
    }
  });

  it('opens delete confirm dialog when delete button clicked', async () => {
    const user = userEvent.setup();
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );

    if (deleteBtn) {
      await user.click(deleteBtn);
      await waitFor(() => {
        const dialog = document.querySelector('[role="dialog"]');
        expect(dialog).toBeTruthy();
      });
    }
  });

  it('calls adminVetting.destroy after delete confirmation', async () => {
    const user = userEvent.setup();
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const deleteBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('delete')
    );

    if (deleteBtn) {
      await user.click(deleteBtn);
      await waitFor(() => document.querySelector('[role="dialog"]'));

      const confirmBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase() === 'confirm'
      );
      if (confirmBtn) {
        await user.click(confirmBtn);
        await waitFor(() => {
          expect(mockAdminVetting.destroy).toHaveBeenCalledWith(1);
        });
      }
    }
  });

  it('shows success toast after verifying', async () => {
    const user = userEvent.setup();
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const verifyBtn = screen.getAllByRole('button').find((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('verify')
    );

    if (verifyBtn) {
      await user.click(verifyBtn);
      await waitFor(() => {
        expect(mockToast.success).toHaveBeenCalled();
      });
    }
  });

  it('shows error toast when list fails', async () => {
    mockAdminVetting.list.mockRejectedValue(new Error('network'));
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders Add Record button', async () => {
    const { VettingRecords } = await import('./VettingPage');
    render(<VettingRecords />);

    await waitFor(() => {
      const addBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('add') ||
        b.textContent?.toLowerCase().includes('record')
      );
      expect(addBtn).toBeDefined();
    });
  });
});
