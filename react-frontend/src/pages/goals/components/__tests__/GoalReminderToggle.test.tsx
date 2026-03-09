// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GoalReminderToggle
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: false, data: null }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { GoalReminderToggle } from '../GoalReminderToggle';
import { api } from '@/lib/api';

describe('GoalReminderToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the bell icon button', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<GoalReminderToggle goalId={10} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Set reminder/i })).toBeInTheDocument();
    });
  });

  it('shows "Reminder active" label when reminder is enabled', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        id: 1,
        goal_id: 10,
        frequency: 'weekly',
        enabled: true,
        next_reminder_at: null,
      },
    });
    render(<GoalReminderToggle goalId={10} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Reminder active/i })).toBeInTheDocument();
    });
  });

  it('calls GET with correct goalId on mount', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<GoalReminderToggle goalId={42} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/goals/42/reminder');
    });
  });

  it('shows frequency buttons in popover after clicking bell', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<GoalReminderToggle goalId={10} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Set reminder/i })).toBeInTheDocument();
    });
    fireEvent.click(screen.getByRole('button', { name: /Set reminder/i }));
    await waitFor(() => {
      expect(screen.getByText('Set Reminder')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Daily/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Weekly/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Every 2 weeks/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /Monthly/i })).toBeInTheDocument();
    });
  });

  it('calls PUT when a frequency is selected', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    vi.mocked(api.put).mockResolvedValue({ success: true });
    render(<GoalReminderToggle goalId={10} />);
    await waitFor(() => screen.getByRole('button', { name: /Set reminder/i }));
    fireEvent.click(screen.getByRole('button', { name: /Set reminder/i }));
    await waitFor(() => screen.getByRole('button', { name: /Weekly/i }));
    fireEvent.click(screen.getByRole('button', { name: /Weekly/i }));
    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith('/v2/goals/10/reminder', { frequency: 'weekly' });
    });
  });

  it('shows Remove Reminder button when reminder is active in popover', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        id: 1,
        goal_id: 10,
        frequency: 'daily',
        enabled: true,
        next_reminder_at: null,
      },
    });
    render(<GoalReminderToggle goalId={10} />);
    await waitFor(() => screen.getByRole('button', { name: /Reminder active/i }));
    fireEvent.click(screen.getByRole('button', { name: /Reminder active/i }));
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Remove Reminder/i })).toBeInTheDocument();
    });
  });

  it('calls DELETE when Remove Reminder is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        id: 1,
        goal_id: 10,
        frequency: 'daily',
        enabled: true,
        next_reminder_at: null,
      },
    });
    vi.mocked(api.delete).mockResolvedValue({ success: true });
    render(<GoalReminderToggle goalId={10} />);
    await waitFor(() => screen.getByRole('button', { name: /Reminder active/i }));
    fireEvent.click(screen.getByRole('button', { name: /Reminder active/i }));
    await waitFor(() => screen.getByRole('button', { name: /Remove Reminder/i }));
    fireEvent.click(screen.getByRole('button', { name: /Remove Reminder/i }));
    await waitFor(() => {
      expect(api.delete).toHaveBeenCalledWith('/v2/goals/10/reminder');
    });
  });
});
