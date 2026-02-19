// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AchievementsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({
      success: true,
      data: [],
      meta: { available_types: [] },
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const { variants, initial, animate, layout, whileHover, whileTap, transition, ...rest } = props;
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { AchievementsPage } from './AchievementsPage';
import { api } from '@/lib/api';

describe('AchievementsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Default mocks for profile and badges
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/v2/gamification/profile')) {
        return Promise.resolve({
          success: true,
          data: {
            user: { id: 1, name: 'Test User', avatar_url: null },
            xp: 1200,
            level: 5,
            level_progress: {
              current_xp: 200,
              xp_for_current_level: 1000,
              xp_for_next_level: 1500,
              progress_percentage: 40,
            },
            badges_count: 3,
            showcased_badges: [],
            is_own_profile: true,
          },
        });
      }
      if (url.includes('/v2/gamification/badges')) {
        return Promise.resolve({
          success: true,
          data: [
            {
              badge_key: 'first_post',
              name: 'First Post',
              description: 'Made your first post',
              icon: '',
              type: 'social',
              created_at: '2026-01-01',
              is_showcased: false,
              earned: true,
              earned_at: '2026-01-01',
            },
            {
              badge_key: 'helper',
              name: 'Helper',
              description: 'Helped 5 people',
              icon: '',
              type: 'community',
              created_at: '2026-01-05',
              is_showcased: true,
              earned: true,
              earned_at: '2026-01-05',
            },
            {
              badge_key: 'locked_badge',
              name: 'Locked Badge',
              description: 'This is locked',
              icon: '',
              type: 'special',
              created_at: '2026-01-10',
              is_showcased: false,
              earned: false,
              earned_at: null,
            },
          ],
          meta: { total: 3, available_types: ['social', 'community', 'special'] },
        });
      }
      if (url.includes('/v2/gamification/daily-reward')) {
        return Promise.resolve({
          success: true,
          data: {
            claimed_today: false,
            current_streak: 3,
            reward_xp: 25,
            next_reward_xp: 30,
            next_claim_at: null,
          },
        });
      }
      if (url.includes('/v2/gamification/challenges')) {
        return Promise.resolve({ success: true, data: [] });
      }
      if (url.includes('/v2/gamification/collections')) {
        return Promise.resolve({ success: true, data: [] });
      }
      if (url.includes('/v2/gamification/shop')) {
        return Promise.resolve({ success: true, data: [] });
      }
      return Promise.resolve({ success: true, data: [], meta: {} });
    });
  });

  it('renders the page heading and description', async () => {
    render(<AchievementsPage />);
    expect(screen.getByText('Achievements')).toBeInTheDocument();
    expect(screen.getByText('Track your badges, XP, and progress')).toBeInTheDocument();
  });

  it('displays badges after loading', async () => {
    render(<AchievementsPage />);
    await waitFor(() => {
      expect(screen.getByText('First Post')).toBeInTheDocument();
    });
    expect(screen.getByText('Helper')).toBeInTheDocument();
    expect(screen.getByText('Locked Badge')).toBeInTheDocument();
  });

  it('shows XP profile card with level and XP info', async () => {
    render(<AchievementsPage />);
    await waitFor(() => {
      expect(screen.getByText('Level 5')).toBeInTheDocument();
    });
    expect(screen.getByText('1,200 XP total')).toBeInTheDocument();
  });

  it('shows badge type filter when types are available', async () => {
    render(<AchievementsPage />);
    await waitFor(() => {
      expect(screen.getByLabelText('Filter badges by type')).toBeInTheDocument();
    });
  });

  it('shows Manage Showcase button', async () => {
    render(<AchievementsPage />);
    await waitFor(() => {
      expect(screen.getByText('Manage Showcase')).toBeInTheDocument();
    });
  });

  it('shows tab navigation with Badges, Challenges, Collections, XP Shop', async () => {
    render(<AchievementsPage />);
    await waitFor(() => {
      // "Badges" appears in profile card ("3 Badges") and tab, use getAllByText
      expect(screen.getAllByText('Badges').length).toBeGreaterThanOrEqual(1);
    });
    expect(screen.getByText('Challenges')).toBeInTheDocument();
    expect(screen.getByText('Collections')).toBeInTheDocument();
    expect(screen.getByText('XP Shop')).toBeInTheDocument();
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<AchievementsPage />);
    await waitFor(() => {
      expect(screen.getByText('Unable to Load Achievements')).toBeInTheDocument();
    });
    expect(screen.getByText('Try Again')).toBeInTheDocument();
  });

  it('shows showcased chip on showcased badges', async () => {
    render(<AchievementsPage />);
    await waitFor(() => {
      expect(screen.getByText('Showcased')).toBeInTheDocument();
    });
  });
});
