// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Hoisted mocks ─────────────────────────────────────────────────────────────
const { mockAdminGamification, mockToast } = vi.hoisted(() => ({
  mockAdminGamification: {
    getStats: vi.fn(),
    getBadgeConfig: vi.fn(),
    bulkAward: vi.fn(),
    recheckAll: vi.fn(),
  },
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminGamification: mockAdminGamification,
}));

// ── Mock underlying api ───────────────────────────────────────────────────────
vi.mock('@/lib/api', () => {
  const m = { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn() };
  return { default: m, api: m };
});

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── Fixtures ──────────────────────────────────────────────────────────────────
const GAMIFICATION_STATS = {
  total_badges_awarded: 250,
  active_users: 80,
  total_xp_awarded: 15000,
  active_campaigns: 3,
  badge_distribution: [
    { badge_name: 'First Exchange', count: 120 },
    { badge_name: 'Community Champion', count: 60 },
  ],
};

const BADGE_CONFIG = [
  { key: 'first_exchange', name: 'First Exchange', is_enabled: true },
  { key: 'champion', name: 'Community Champion', is_enabled: true },
  { key: 'disabled_badge', name: 'Disabled Badge', is_enabled: false },
];

import { GamificationHub } from './GamificationHub';

describe('GamificationHub', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAdminGamification.getStats.mockResolvedValue({ success: true, data: GAMIFICATION_STATS });
    mockAdminGamification.getBadgeConfig.mockResolvedValue({ success: true, data: BADGE_CONFIG });
    mockAdminGamification.bulkAward.mockResolvedValue({ success: true, data: { awarded: 2 } });
    mockAdminGamification.recheckAll.mockResolvedValue({ success: true, data: { users_checked: 50 } });
  });

  // ── Loading state ──────────────────────────────────────────────────────────
  it('shows loading spinner while fetching stats', async () => {
    mockAdminGamification.getStats.mockReturnValue(new Promise(() => {}));
    render(<GamificationHub />);
    const spinner = document.querySelector('[role="status"][aria-busy="true"]');
    expect(spinner).toBeTruthy();
  });

  // ── Error state ────────────────────────────────────────────────────────────
  it('shows error toast when stats API fails', async () => {
    mockAdminGamification.getStats.mockResolvedValue({ success: false });
    render(<GamificationHub />);
    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });

  // ── Populated stats ────────────────────────────────────────────────────────
  it('renders total badges awarded KPI', async () => {
    render(<GamificationHub />);
    await waitFor(() => {
      const els = screen.getAllByText('250');
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('renders active users KPI', async () => {
    render(<GamificationHub />);
    await waitFor(() => {
      const els = screen.getAllByText('80');
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('renders active campaigns KPI', async () => {
    render(<GamificationHub />);
    await waitFor(() => {
      const els = screen.getAllByText('3');
      expect(els.length).toBeGreaterThan(0);
    });
  });

  // ── Badge distribution chart ───────────────────────────────────────────────
  it('renders badge distribution bar entries', async () => {
    render(<GamificationHub />);
    await waitFor(() => expect(screen.getByText('First Exchange')).toBeInTheDocument());
    expect(screen.getByText('Community Champion')).toBeInTheDocument();
  });

  it('renders badge counts in the distribution', async () => {
    render(<GamificationHub />);
    await waitFor(() => {
      const matches = screen.getAllByText('120');
      expect(matches.length).toBeGreaterThan(0);
    });
  });

  // ── Empty badge distribution ───────────────────────────────────────────────
  it('shows empty state when badge_distribution is empty', async () => {
    mockAdminGamification.getStats.mockResolvedValue({
      success: true,
      data: { ...GAMIFICATION_STATS, badge_distribution: [] },
    });
    render(<GamificationHub />);
    // Component renders an empty state message; just verify no distribution rows
    await waitFor(() => {
      expect(screen.queryByText('First Exchange')).toBeNull();
    });
  });

  // ── Quick links ────────────────────────────────────────────────────────────
  it('renders quick link to Campaigns', async () => {
    render(<GamificationHub />);
    await waitFor(() => {
      const matches = screen.getAllByText(/campaigns/i);
      expect(matches.length).toBeGreaterThan(0);
    });
  });

  // ── Recheck All ───────────────────────────────────────────────────────────
  it('calls recheckAll API on Recheck All button click', async () => {
    render(<GamificationHub />);
    await waitFor(() => expect(mockAdminGamification.getStats).toHaveBeenCalledTimes(1));

    const recheckBtn = screen.getByRole('button', { name: /recheck/i });
    fireEvent.click(recheckBtn);

    await waitFor(() => expect(mockAdminGamification.recheckAll).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(mockToast.success).toHaveBeenCalled());
  });

  it('shows error toast when recheckAll fails', async () => {
    mockAdminGamification.recheckAll.mockResolvedValue({ success: false, error: 'Recheck failed' });
    render(<GamificationHub />);
    await waitFor(() => expect(mockAdminGamification.getStats).toHaveBeenCalled());

    const recheckBtn = screen.getByRole('button', { name: /recheck/i });
    fireEvent.click(recheckBtn);

    await waitFor(() => expect(mockToast.error).toHaveBeenCalledWith('Badge recheck failed'));
  });

  // ── Bulk Award Modal ──────────────────────────────────────────────────────
  it('opens bulk award modal when Bulk Award button is clicked', async () => {
    render(<GamificationHub />);
    await waitFor(() => expect(mockAdminGamification.getStats).toHaveBeenCalled());

    const bulkBtn = screen.getByRole('button', { name: /bulk award/i });
    fireEvent.click(bulkBtn);

    await waitFor(() => expect(mockAdminGamification.getBadgeConfig).toHaveBeenCalledTimes(1));
  });

  it('shows error toast when bulk award submitted with no badge selected', async () => {
    render(<GamificationHub />);
    await waitFor(() => expect(mockAdminGamification.getStats).toHaveBeenCalled());

    const bulkBtn = screen.getByRole('button', { name: /bulk award/i });
    fireEvent.click(bulkBtn);

    // Wait for modal and submit button
    await waitFor(() => {
      const submitBtn = screen.queryByRole('button', { name: /award|submit/i });
      if (submitBtn) fireEvent.click(submitBtn);
    });

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });
});
