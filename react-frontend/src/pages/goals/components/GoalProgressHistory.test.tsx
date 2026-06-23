// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: (s: string) => `rel:${s}`,
  resolveAvatarUrl: (url?: string) => url ?? '/default.png',
  cn: (...classes: unknown[]) => classes.filter(Boolean).join(' '),
}));

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeEvent = (overrides: Partial<{
  id: number;
  type: string;
  description: string;
  data: Record<string, unknown>;
  created_at: string;
}> = {}) => ({
  id: 1,
  type: 'progress_update',
  description: 'Made good progress today',
  data: { progress_value: 60 },
  created_at: '2026-06-01T12:00:00Z',
  ...overrides,
});

const makeResponse = (events: object[] = []) => ({
  success: true,
  data: events,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GoalProgressHistory', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockApi.get.mockResolvedValue(makeResponse());
  });

  it('shows loading spinner initially', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    const spinner = document.querySelector('[aria-busy="true"]');
    expect(spinner).toBeTruthy();
  });

  it('calls GET /v2/goals/:id/history with correct goalId', async () => {
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={7} />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalledWith('/v2/goals/7/history'));
  });

  it('renders event description after load', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent()]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      expect(screen.getByText('Made good progress today')).toBeInTheDocument();
    });
  });

  it('renders relative timestamp for each event', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent()]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      expect(screen.getByText(/rel:2026-06-01/)).toBeInTheDocument();
    });
  });

  it('renders progress percentage from data.progress_value', async () => {
    mockApi.get.mockResolvedValue(makeResponse([makeEvent({ data: { progress_value: 75 } })]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      expect(screen.getByText('75%')).toBeInTheDocument();
    });
  });

  it('renders note text for checkin events', async () => {
    mockApi.get.mockResolvedValue(makeResponse([
      makeEvent({ type: 'checkin', description: 'Daily check-in', data: { note: 'Feeling motivated!' } }),
    ]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      expect(screen.getByText(/Feeling motivated!/)).toBeInTheDocument();
    });
  });

  it('renders empty state when no history events', async () => {
    mockApi.get.mockResolvedValue(makeResponse([]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      // Empty state renders a Clock icon and a "no activity" message
      // The container renders without event items
      const eventList = document.querySelector('.border-l-2');
      expect(eventList).toBeNull();
    });
  });

  it('shows error state and retry button when API throws', async () => {
    mockApi.get.mockRejectedValue(new Error('network error'));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      const retryBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('retry') || b.textContent?.toLowerCase().includes('again')
      );
      expect(retryBtn).toBeDefined();
    });
  });

  it('retries load when retry button is clicked', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('network'));
    mockApi.get.mockResolvedValueOnce(makeResponse([makeEvent()]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);

    // Wait for error state
    await waitFor(() => {
      const retryBtn = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('retry') || b.textContent?.toLowerCase().includes('again')
      );
      expect(retryBtn).toBeDefined();
    });

    const retryBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('retry') || b.textContent?.toLowerCase().includes('again')
    );
    fireEvent.click(retryBtn!);

    await waitFor(() => expect(mockApi.get).toHaveBeenCalledTimes(2));
  });

  it('renders multiple events in order', async () => {
    mockApi.get.mockResolvedValue(makeResponse([
      makeEvent({ id: 1, description: 'First event', type: 'created' }),
      makeEvent({ id: 2, description: 'Second event', type: 'milestone' }),
      makeEvent({ id: 3, description: 'Third event', type: 'completed' }),
    ]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      expect(screen.getByText('First event')).toBeInTheDocument();
      expect(screen.getByText('Second event')).toBeInTheDocument();
      expect(screen.getByText('Third event')).toBeInTheDocument();
    });
  });

  it('renders buddy_joined event without crashing', async () => {
    mockApi.get.mockResolvedValue(makeResponse([
      makeEvent({ type: 'buddy_joined', description: 'Sam joined as your buddy', data: {} }),
    ]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} />);
    await waitFor(() => {
      expect(screen.getByText('Sam joined as your buddy')).toBeInTheDocument();
    });
  });

  it('accepts className prop without crashing', async () => {
    mockApi.get.mockResolvedValue(makeResponse([]));
    const { GoalProgressHistory } = await import('./GoalProgressHistory');
    render(<GoalProgressHistory goalId={5} className="custom-class" />);
    await waitFor(() => {
      // No spinner means loading complete
      expect(document.querySelector('[aria-busy="true"]')).toBeNull();
    });
  });
});
