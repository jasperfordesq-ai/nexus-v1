// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn().mockResolvedValue({ success: true, data: {} }),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useAuth: () => ({ user: { id: 1 }, isAuthenticated: true }),
  useTenant: () => ({ tenantPath: (p: string) => p, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ formatRelativeTime: vi.fn(() => 'today') }));

import { api } from '@/lib/api';
import { GoalInsightsPanel } from '../GoalInsightsPanel';

const mockInsights = {
  checkin_count: 3,
  last_checkin_at: '2026-05-17T10:00:00Z',
  checkin_frequency: 'weekly',
  next_checkin_due_at: '2026-05-24T10:00:00Z',
  is_checkin_due: false,
  streak_count: 2,
  best_streak_count: 4,
  completed_milestones: 1,
  milestone_count: 4,
  milestones: [
    { id: 1, title: 'First quarter', target_percent: 25, target_value: 25, completed_at: '2026-05-17T10:00:00Z' },
    { id: 2, title: 'Halfway there', target_percent: 50, target_value: 50, completed_at: null },
  ],
  buddy_notes: [
    { id: 1, type: 'encouragement', message: 'Keep going', created_at: '2026-05-17T10:00:00Z', buddy_name: 'Buddy User' },
  ],
};

describe('GoalInsightsPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: mockInsights });
  });

  it('renders streaks, check-ins, milestones, and buddy support', async () => {
    render(<GoalInsightsPanel goalId={42} />);

    await waitFor(() => {
      expect(screen.getByText('Current streak')).toBeInTheDocument();
    });

    expect(screen.getByText('2 check-ins')).toBeInTheDocument();
    expect(screen.getByText('3 recorded')).toBeInTheDocument();
    expect(screen.getByText('1 of 4')).toBeInTheDocument();
    expect(screen.getByText('Keep going')).toBeInTheDocument();
  });

  it('lets a buddy send a nudge', async () => {
    const user = userEvent.setup();
    render(<GoalInsightsPanel goalId={42} canNudge />);

    await waitFor(() => {
      expect(screen.getByText('Send nudge')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Send nudge'));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/goals/42/buddy/nudge', { type: 'nudge' });
    });
  });
});
