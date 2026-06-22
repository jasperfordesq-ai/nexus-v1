// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── mock contexts ────────────────────────────────────────────────────────────

const mockSuccess = vi.hoisted(() => vi.fn());
const mockError = vi.hoisted(() => vi.fn());

vi.mock('@/contexts/ToastContext', () => ({
  useToast: () => ({ success: mockSuccess, error: mockError, info: vi.fn(), warning: vi.fn() }),
  ToastProvider: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({ success: mockSuccess, error: mockError, info: vi.fn(), warning: vi.fn() }),
  }),
);

// ── mock adminApi ────────────────────────────────────────────────────────────

const mockGetFeaturedGroups = vi.hoisted(() => vi.fn());
const mockUpdateFeaturedGroups = vi.hoisted(() => vi.fn());
const mockToggleFeatured = vi.hoisted(() => vi.fn());

vi.mock('@/admin/api/adminApi', () => ({
  adminGroups: {
    getFeaturedGroups: mockGetFeaturedGroups,
    updateFeaturedGroups: mockUpdateFeaturedGroups,
    toggleFeatured: mockToggleFeatured,
  },
}));

// ── mock hooks ───────────────────────────────────────────────────────────────

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));

// ── component ────────────────────────────────────────────────────────────────

import React from 'react';
import GroupRanking from './GroupRanking';

const MOCK_GROUPS = vi.hoisted(() => [
  {
    id: 1,
    group_id: 10,
    name: 'Community Gardeners',
    member_count: 45,
    engagement_score: 80,
    geographic_diversity: 60,
    ranking_score: 70,
    is_featured: true,
  },
  {
    id: 2,
    group_id: 11,
    name: 'Tech Helpers',
    member_count: 22,
    engagement_score: 55,
    geographic_diversity: 40,
    ranking_score: 47,
    is_featured: false,
  },
]);

describe('GroupRanking', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders groups in the table after load', async () => {
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: MOCK_GROUPS });
    render(<GroupRanking />);

    await waitFor(() => {
      expect(screen.getByText('Community Gardeners')).toBeInTheDocument();
    });
    expect(screen.getByText('Tech Helpers')).toBeInTheDocument();
  });

  it('shows the empty content text when there are no groups', async () => {
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: [] });
    render(<GroupRanking />);

    await waitFor(() => {
      // Table emptyContent — React Aria renders it as a cell
      expect(screen.getByText(/no groups found/i)).toBeInTheDocument();
    });
  });

  it('calls toast.error when load fails', async () => {
    mockGetFeaturedGroups.mockRejectedValue(new Error('fail'));
    render(<GroupRanking />);

    await waitFor(() => {
      expect(mockError).toHaveBeenCalled();
    });
  });

  it('calls updateFeaturedGroups and reloads on "Auto update rankings" press', async () => {
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: MOCK_GROUPS });
    mockUpdateFeaturedGroups.mockResolvedValue({ success: true });

    render(<GroupRanking />);

    await waitFor(() => {
      expect(screen.getByText('Community Gardeners')).toBeInTheDocument();
    });

    // Second call for the reload after update
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: MOCK_GROUPS });

    const updateBtn = screen.getByRole('button', { name: /auto update rankings/i });
    await userEvent.click(updateBtn);

    await waitFor(() => {
      expect(mockUpdateFeaturedGroups).toHaveBeenCalled();
      expect(mockSuccess).toHaveBeenCalled();
    });
  });

  it('shows error toast when updateFeaturedGroups returns success=false', async () => {
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: MOCK_GROUPS });
    mockUpdateFeaturedGroups.mockResolvedValue({ success: false, error: 'Nope' });

    render(<GroupRanking />);

    await waitFor(() => {
      expect(screen.getByText('Community Gardeners')).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /auto update rankings/i }));

    await waitFor(() => {
      expect(mockError).toHaveBeenCalled();
    });
  });

  it('renders featured toggle switches', async () => {
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: MOCK_GROUPS });
    render(<GroupRanking />);

    await waitFor(() => {
      const switches = screen.getAllByRole('switch');
      expect(switches.length).toBe(MOCK_GROUPS.length);
    });
  });

  it('calls toggleFeatured with correct group id when switch clicked', async () => {
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: MOCK_GROUPS });
    mockToggleFeatured.mockResolvedValue({ success: true });
    // Reload after toggle
    mockGetFeaturedGroups.mockResolvedValueOnce({ success: true, data: MOCK_GROUPS });

    render(<GroupRanking />);

    await waitFor(() => {
      expect(screen.getAllByRole('switch').length).toBe(2);
    });

    const firstSwitch = screen.getAllByRole('switch')[0];
    await userEvent.click(firstSwitch);

    await waitFor(() => {
      expect(mockToggleFeatured).toHaveBeenCalledWith(10);
    });
  });

  it('shows the ranking algorithm explanation card', async () => {
    mockGetFeaturedGroups.mockResolvedValue({ success: true, data: [] });
    render(<GroupRanking />);

    await waitFor(() => {
      expect(screen.getByText(/ranking algorithm/i)).toBeInTheDocument();
    });
  });
});
