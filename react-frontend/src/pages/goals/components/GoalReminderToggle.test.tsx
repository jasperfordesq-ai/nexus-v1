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

// ─── Toast ────────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice' },
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
  }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeReminder = (overrides = {}) => ({
  id: 10,
  goal_id: 5,
  frequency: 'weekly' as const,
  enabled: true,
  next_reminder_at: '2026-07-01T08:00:00Z',
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('GoalReminderToggle', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders a bell button trigger', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => {
      const btn = screen.getByRole('button');
      expect(btn).toBeInTheDocument();
    });
  });

  it('fetches reminder on mount for given goalId', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: makeReminder() });
    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith('/v2/goals/5/reminder');
    });
  });

  it('shows loading state initially (isLoading=true)', async () => {
    mockApi.get.mockImplementationOnce(() => new Promise(() => {}));
    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);
    // Button is loading — HeroUI renders a spinner inside it
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('shows frequency options in popover when clicked (no existing reminder)', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => {
      const btn = screen.getByRole('button');
      expect(btn).toBeInTheDocument();
    });

    const btn = screen.getByRole('button');
    fireEvent.click(btn);

    // Frequency buttons should appear
    await waitFor(() => {
      // The frequency buttons appear in the popover
      const buttons = screen.getAllByRole('button');
      const freqButtons = buttons.filter((b) =>
        /daily|weekly|biweekly|monthly/i.test(b.textContent ?? ''),
      );
      expect(freqButtons.length).toBeGreaterThan(0);
    });
  });

  it('calls PUT /v2/goals/{id}/reminder when a frequency is selected', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    mockApi.put.mockResolvedValue({ success: true });
    mockApi.get.mockResolvedValueOnce({ success: false });

    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={7} />);

    await waitFor(() => screen.getByRole('button'));
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const weeklyBtn = buttons.find((b) => /weekly/i.test(b.textContent ?? ''));
      expect(weeklyBtn).toBeDefined();
      if (weeklyBtn) fireEvent.click(weeklyBtn);
    });

    await waitFor(() => {
      expect(mockApi.put).toHaveBeenCalledWith(
        '/v2/goals/7/reminder',
        expect.objectContaining({ frequency: 'weekly' }),
      );
    });
  });

  it('shows success toast after setting reminder', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    mockApi.put.mockResolvedValue({ success: true });

    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => screen.getByRole('button'));
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const daily = btns.find((b) => /daily/i.test(b.textContent ?? ''));
      if (daily) fireEvent.click(daily);
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when PUT fails', async () => {
    mockApi.get.mockResolvedValue({ success: false });
    mockApi.put.mockResolvedValue({ success: false });

    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => screen.getByRole('button'));
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const monthly = btns.find((b) => /monthly/i.test(b.textContent ?? ''));
      if (monthly) fireEvent.click(monthly);
    });

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows remove button when reminder is active', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: makeReminder({ enabled: true }) });
    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => screen.getByRole('button'));
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const removeBtn = btns.find((b) => /remove/i.test(b.textContent ?? ''));
      expect(removeBtn).toBeDefined();
    });
  });

  it('calls DELETE /v2/goals/{id}/reminder when remove clicked', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: makeReminder({ enabled: true }) });
    mockApi.delete.mockResolvedValue({ success: true });

    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => screen.getByRole('button'));
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const removeBtn = btns.find((b) => /remove/i.test(b.textContent ?? ''));
      expect(removeBtn).toBeDefined();
      if (removeBtn) fireEvent.click(removeBtn);
    });

    await waitFor(() => {
      expect(mockApi.delete).toHaveBeenCalledWith('/v2/goals/5/reminder');
    });
  });

  it('shows success toast after removing reminder', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: makeReminder({ enabled: true }) });
    mockApi.delete.mockResolvedValue({ success: true });

    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={5} />);

    await waitFor(() => screen.getByRole('button'));
    fireEvent.click(screen.getByRole('button'));

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const removeBtn = btns.find((b) => /remove/i.test(b.textContent ?? ''));
      if (removeBtn) fireEvent.click(removeBtn);
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('handles GET 404/error gracefully without crashing', async () => {
    mockApi.get.mockRejectedValue(new Error('Not found'));
    const { GoalReminderToggle } = await import('./GoalReminderToggle');
    render(<GoalReminderToggle goalId={99} />);

    // Should still render the button without crashing
    await waitFor(() => {
      const btn = screen.getByRole('button');
      expect(btn).toBeInTheDocument();
    });
    // No error toast — the catch block treats missing reminder as OK
    expect(mockToast.error).not.toHaveBeenCalled();
  });
});
