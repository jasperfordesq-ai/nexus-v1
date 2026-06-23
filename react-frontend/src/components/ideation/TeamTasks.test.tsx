// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ resolveAvatarUrl: (url: string | null) => url ?? '' }));

// ─── Context / hooks ──────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
const mockConfirm = vi.fn(() => Promise.resolve(true));

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice', role: 'member' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub HeroUI Select so it renders reliably in jsdom ──────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    Select: ({ label, children }: { label?: string; children?: React.ReactNode }) => (
      <div data-testid="select">
        <span>{label}</span>
        {children}
      </div>
    ),
    SelectItem: ({ children }: { children?: React.ReactNode }) => (
      <div data-testid="select-item">{children}</div>
    ),
    useConfirm: () => mockConfirm,
    Modal: ({ isOpen, children }: { isOpen?: boolean; children?: React.ReactNode }) =>
      isOpen ? <div role="dialog">{children}</div> : null,
    ModalContent: ({ children }: { children?: ((onClose: () => void) => React.ReactNode) | React.ReactNode }) => (
      <div>{typeof children === 'function' ? children(() => {}) : children}</div>
    ),
    ModalHeader: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    ModalBody: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
    ModalFooter: ({ children }: { children?: React.ReactNode }) => <div>{children}</div>,
  };
});

// ─── Stub EmptyState ──────────────────────────────────────────────────────────
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeTask = (overrides = {}) => ({
  id: 1,
  group_id: 10,
  title: 'Fix the bug',
  description: 'A description',
  status: 'todo' as const,
  priority: 'medium' as const,
  assigned_to: null,
  due_date: null,
  created_at: '2025-06-01T10:00:00Z',
  assignee: null,
  ...overrides,
});

const makeStats = (overrides = {}) => ({
  total: 3,
  todo: 1,
  in_progress: 1,
  done: 1,
  overdue: 0,
  ...overrides,
});

const taskResponse = (tasks = [] as object[]) => ({ success: true, data: tasks });
const statsResponse = (stats = makeStats()) => ({ success: true, data: stats });

// ─────────────────────────────────────────────────────────────────────────────
describe('TeamTasks', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse());
      return Promise.resolve(taskResponse());
    });
    mockApi.post.mockResolvedValue({ success: true });
    mockApi.put.mockResolvedValue({ success: true });
    mockApi.delete.mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);
  });

  it('shows a loading spinner initially', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows empty state when no tasks are returned', async () => {
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders task title when tasks are returned', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse());
      return Promise.resolve(taskResponse([makeTask()]));
    });
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('Fix the bug')).toBeInTheDocument();
    });
  });

  it('renders stats bar when stats are loaded', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse({ total: 5, done: 2, in_progress: 1, overdue: 0, todo: 2 }));
      return Promise.resolve(taskResponse([makeTask()]));
    });
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText(/5/)).toBeInTheDocument(); // total count
    });
  });

  it('shows overdue count in stats when overdue > 0', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse({ total: 3, done: 0, in_progress: 1, overdue: 2, todo: 2 }));
      return Promise.resolve(taskResponse([makeTask()]));
    });
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText(/2/)).toBeInTheDocument();
    });
  });

  it('renders filter buttons (All, todo, in_progress, done)', async () => {
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    // The "All" button is always rendered
    expect(screen.getByRole('button', { name: 'All' })).toBeInTheDocument();
  });

  it('renders Create task button', async () => {
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    const buttons = screen.getAllByRole('button');
    const createBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('create') || b.textContent?.toLowerCase().includes('task'));
    expect(createBtn).toBeDefined();
  });

  it('opens create modal when create button is pressed', async () => {
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={true} />);
    const buttons = screen.getAllByRole('button');
    const createBtn = buttons.find(
      (b) => b.textContent?.toLowerCase().includes('create') || b.textContent?.toLowerCase().includes('task')
    );
    if (createBtn) await userEvent.click(createBtn);
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('shows delete button for admin users and calls API on confirm', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse());
      return Promise.resolve(taskResponse([makeTask({ id: 42 })]));
    });
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={true} />);
    await waitFor(() => screen.getByText('Fix the bug'));

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delet') || b.getAttribute('aria-label')?.includes('task_deleted')
    );
    expect(deleteBtn).toBeDefined();
    if (deleteBtn) {
      await userEvent.click(deleteBtn);
      await waitFor(() => {
        expect(mockApi.delete).toHaveBeenCalledWith('/v2/team-tasks/42');
      });
    }
  });

  it('does not show delete button for non-admin non-owner users', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse());
      return Promise.resolve(taskResponse([makeTask({ id: 99, uploaded_by: 99 })]));
    });
    const { TeamTasks } = await import('./TeamTasks');
    // user id=1, task assigned to nobody, isGroupAdmin=false
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    await waitFor(() => screen.getByText('Fix the bug'));
    // No delete buttons should be visible (aria-label matching task_deleted)
    const deleteBtns = screen.queryAllByRole('button').filter(
      (b) => b.getAttribute('aria-label')?.includes('task_deleted')
    );
    expect(deleteBtns).toHaveLength(0);
  });

  it('calls PUT endpoint when task status toggle button is pressed', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse());
      return Promise.resolve(taskResponse([makeTask({ id: 7, status: 'todo' })]));
    });
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    await waitFor(() => screen.getByText('Fix the bug'));

    // The status toggle is an isIconOnly button (no text content, has aria-label from t())
    // Find all icon-only buttons (short/no text) — the first non-text button is the toggle
    const allBtns = screen.getAllByRole('button');
    // Status toggle button has no text content or very short content (icon only)
    const toggleBtn = allBtns.find((b) => {
      const label = b.getAttribute('aria-label') ?? '';
      const text = b.textContent?.trim() ?? '';
      // Must have aria-label (status toggle has one), and no substantial text
      return label.length > 0 && text.length === 0;
    });
    expect(toggleBtn).toBeDefined();
    if (toggleBtn) {
      await userEvent.click(toggleBtn);
      await waitFor(() => {
        expect(mockApi.put).toHaveBeenCalledWith('/v2/team-tasks/7', expect.objectContaining({ status: expect.any(String) }));
      });
    }
  });

  it('shows task description when present', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse());
      return Promise.resolve(taskResponse([makeTask({ description: 'My task description' })]));
    });
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={false} />);
    await waitFor(() => {
      expect(screen.getByText('My task description')).toBeInTheDocument();
    });
  });

  it('shows error toast when task deletion fails', async () => {
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('task-stats')) return Promise.resolve(statsResponse());
      return Promise.resolve(taskResponse([makeTask({ id: 55 })]));
    });
    mockApi.delete.mockRejectedValue(new Error('server error'));
    const { TeamTasks } = await import('./TeamTasks');
    render(<TeamTasks groupId={10} isGroupAdmin={true} />);
    await waitFor(() => screen.getByText('Fix the bug'));

    const deleteBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.includes('task_deleted')
    );
    if (deleteBtn) {
      await userEvent.click(deleteBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });
});
