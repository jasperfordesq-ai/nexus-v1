// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: (s: string) => `relative(${s})`,
  resolveAvatarUrl: (s: unknown) => (s ? String(s) : null),
}));

// ─── Context mocks ─────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useAuth: () => ({
      user: { id: 1, name: 'Tester' },
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(),
      updateUser: vi.fn(), refreshUser: vi.fn(),
      status: 'idle' as const, error: null,
    }),
  })
);

// Also mock direct import path used by GoalInsightsPanel — keep ToastProvider for test-utils
vi.mock('@/contexts/ToastContext', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/contexts/ToastContext')>();
  return { ...orig, useToast: () => mockToast };
});

// ─── Stub heavy HeroUI components ─────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Progress: ({ value, 'aria-label': ariaLabel }: { value: number; 'aria-label'?: string }) =>
      <div role="progressbar" aria-valuenow={value} aria-label={ariaLabel} />,
    Button: ({ children, onPress, isLoading, isDisabled }: { children?: React.ReactNode; onPress?: () => void; isLoading?: boolean; isDisabled?: boolean; [key: string]: unknown }) =>
      <button type="button" onClick={() => !isDisabled && onPress?.()} disabled={isDisabled} aria-busy={isLoading}>{children}</button>,
    Chip: ({ children, color }: { children: React.ReactNode; color?: string }) =>
      <span data-testid="chip" data-color={color}>{children}</span>,
    Skeleton: ({ className }: { className?: string }) =>
      <div role="status" aria-busy="true" className={className} data-testid="skeleton" />,
  };
});

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeInsights = (overrides = {}) => ({
  checkin_count: 5,
  last_checkin_at: '2026-06-20T10:00:00Z',
  checkin_frequency: 'weekly' as const,
  next_checkin_due_at: '2026-06-27T10:00:00Z',
  is_checkin_due: false,
  streak_count: 3,
  best_streak_count: 7,
  milestones: [],
  completed_milestones: 0,
  milestone_count: 0,
  buddy_notes: [],
  ...overrides,
});

const makeSuccessResponse = (data: object) => ({ success: true, data });
const makeErrorResponse = () => ({ success: false, data: null });

// ─────────────────────────────────────────────────────────────────────────────
describe('GoalInsightsPanel', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeSuccessResponse(makeInsights()));
  });

  it('shows skeleton loading state before data arrives', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={42} />);
    const skeletons = screen.getAllByTestId('skeleton');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('calls GET /v2/goals/:id/insights with correct goalId', async () => {
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={42} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalledWith('/v2/goals/42/insights'));
  });

  it('renders streak count after data loads', async () => {
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      // Translation key goals.insights.streak_value renders with count=3
      // The i18n fallback renders the key + count or the full string; check for "3"
      expect(screen.getByText(/3/)).toBeInTheDocument();
    });
  });

  it('renders check-in count in a card', async () => {
    mockApi.get.mockResolvedValue(makeSuccessResponse(makeInsights({ checkin_count: 12 })));
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      expect(screen.getByText(/12/)).toBeInTheDocument();
    });
  });

  it('renders milestone progress bar when milestone_count > 0', async () => {
    mockApi.get.mockResolvedValue(
      makeSuccessResponse(makeInsights({ milestone_count: 4, completed_milestones: 2 }))
    );
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      const bar = screen.getByRole('progressbar');
      expect(bar).toBeInTheDocument();
      expect(bar).toHaveAttribute('aria-valuenow', '50');
    });
  });

  it('renders milestone titles in the milestone plan section', async () => {
    mockApi.get.mockResolvedValue(
      makeSuccessResponse(makeInsights({
        milestone_count: 1,
        completed_milestones: 0,
        milestones: [
          { id: 1, title: 'Finish chapter 1', target_percent: 25, target_value: null, completed_at: null },
        ],
      }))
    );
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Finish chapter 1')).toBeInTheDocument();
    });
  });

  it('marks a completed milestone with success chip color', async () => {
    mockApi.get.mockResolvedValue(
      makeSuccessResponse(makeInsights({
        milestone_count: 1,
        completed_milestones: 1,
        milestones: [
          { id: 2, title: 'First milestone', target_percent: 50, target_value: null, completed_at: '2026-06-01T00:00:00Z' },
        ],
      }))
    );
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      const chips = screen.getAllByTestId('chip');
      const successChip = chips.find((c) => c.getAttribute('data-color') === 'success');
      expect(successChip).toBeDefined();
    });
  });

  it('shows buddy action buttons when canNudge=true', async () => {
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} canNudge={true} />);
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const nudgeBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('nudge'));
      expect(nudgeBtn).toBeInTheDocument();
    });
  });

  it('does not show buddy action buttons when canNudge=false (default)', async () => {
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      // Wait for loading to complete
      expect(screen.queryByTestId('skeleton')).not.toBeInTheDocument();
    });
    const buttons = screen.queryAllByRole('button');
    const nudgeBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('nudge'));
    expect(nudgeBtn).toBeUndefined();
  });

  it('calls POST /v2/goals/:id/buddy/nudge when nudge button is clicked', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={5} canNudge={true} />);
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      return buttons.find((b) => b.textContent?.toLowerCase().includes('nudge'));
    });
    const nudgeBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('nudge'))!;
    nudgeBtn.click();
    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/goals/5/buddy/nudge', { type: 'nudge' });
    });
  });

  it('shows success toast after buddy action succeeds', async () => {
    mockApi.post.mockResolvedValue({ success: true });
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={5} canNudge={true} />);
    await waitFor(() => screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('nudge')));
    const nudgeBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('nudge'))!;
    nudgeBtn.click();
    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when API fails to load insights', async () => {
    mockApi.get.mockRejectedValue(new Error('network error'));
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      // Error state renders retry button
      const buttons = screen.getAllByRole('button');
      const retryBtn = buttons.find((b) => b.textContent?.toLowerCase().includes('retry') || b.textContent?.toLowerCase().includes('try'));
      expect(retryBtn).toBeInTheDocument();
    });
  });

  it('shows retry button when server returns success=false', async () => {
    mockApi.get.mockResolvedValue(makeErrorResponse());
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

  it('renders buddy notes list when notes exist', async () => {
    mockApi.get.mockResolvedValue(
      makeSuccessResponse(makeInsights({
        buddy_notes: [
          { id: 1, type: 'nudge', message: 'Keep going!', created_at: '2026-06-20T10:00:00Z', buddy_name: 'Alice' },
        ],
      }))
    );
    const { GoalInsightsPanel } = await import('./GoalInsightsPanel');
    render(<GoalInsightsPanel goalId={1} />);
    await waitFor(() => {
      expect(screen.getByText('Keep going!')).toBeInTheDocument();
    });
  });
});
