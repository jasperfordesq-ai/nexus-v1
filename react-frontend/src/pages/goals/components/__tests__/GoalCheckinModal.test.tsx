// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GoalCheckinModal
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { framerMotionMock } from '@/test/mocks';

vi.mock('framer-motion', () => framerMotionMock);

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
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

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: (date: string) => date,
}));

import { GoalCheckinModal } from '../GoalCheckinModal';
import { api } from '@/lib/api';

const defaultProps = {
  isOpen: true,
  onClose: vi.fn(),
  goalId: 42,
  goalTitle: 'Learn Spanish',
  currentProgress: 30,
  onCheckinCreated: vi.fn(),
};

describe('GoalCheckinModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the modal with title and goal name when open', () => {
    render(<GoalCheckinModal {...defaultProps} />);
    expect(screen.getByText('Check In')).toBeInTheDocument();
    expect(screen.getByText('Learn Spanish')).toBeInTheDocument();
  });

  it('renders the New Check-in and History tab buttons', () => {
    render(<GoalCheckinModal {...defaultProps} />);
    expect(screen.getByRole('button', { name: /New Check-in/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /History/i })).toBeInTheDocument();
  });

  it('shows progress label and mood selector on new check-in view', () => {
    render(<GoalCheckinModal {...defaultProps} />);
    expect(screen.getByText('Progress')).toBeInTheDocument();
    expect(screen.getByText('How are you feeling?')).toBeInTheDocument();
  });

  it('shows all mood buttons', () => {
    render(<GoalCheckinModal {...defaultProps} />);
    expect(screen.getByRole('button', { name: /Great/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Good/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Okay/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Struggling/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Motivated/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Grateful/i })).toBeInTheDocument();
  });

  it('renders Record Check-in and Cancel buttons', () => {
    render(<GoalCheckinModal {...defaultProps} />);
    expect(screen.getByRole('button', { name: /Record Check-in/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Cancel/i })).toBeInTheDocument();
  });

  it('switches to history view when History tab is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GoalCheckinModal {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: /History/i }));
    await waitFor(() => {
      expect(screen.getByText('No check-ins yet. Record your first one!')).toBeInTheDocument();
    });
  });

  it('does not render Record Check-in button in history view', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<GoalCheckinModal {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: /History/i }));
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /Record Check-in/i })).not.toBeInTheDocument();
    });
  });

  it('renders history checkins when API returns them', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          goal_id: 42,
          progress_value: 50,
          note: 'Making good progress!',
          mood: 'good',
          created_at: '2026-03-01T10:00:00Z',
        },
      ],
    });
    render(<GoalCheckinModal {...defaultProps} />);
    fireEvent.click(screen.getByRole('button', { name: /History/i }));
    await waitFor(() => {
      expect(screen.getByText('50%')).toBeInTheDocument();
      expect(screen.getByText('Making good progress!')).toBeInTheDocument();
    });
  });

  it('calls POST and triggers onCheckinCreated on successful submit', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true });
    const onCheckinCreated = vi.fn();
    const onClose = vi.fn();
    render(<GoalCheckinModal {...defaultProps} onCheckinCreated={onCheckinCreated} onClose={onClose} />);

    fireEvent.click(screen.getByRole('button', { name: /Record Check-in/i }));
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/goals/42/checkins', expect.objectContaining({
        progress_value: 30,
      }));
      expect(onCheckinCreated).toHaveBeenCalled();
      expect(onClose).toHaveBeenCalled();
    });
  });

  it('does not render when isOpen is false', () => {
    render(<GoalCheckinModal {...defaultProps} isOpen={false} />);
    expect(screen.queryByText('Check In')).not.toBeInTheDocument();
  });
});
