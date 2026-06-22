// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─────────────────────────────────────────────────────────────────────────────
// Stable mock data
// ─────────────────────────────────────────────────────────────────────────────
const { mockGetSendTimeData } = vi.hoisted(() => ({
  mockGetSendTimeData: vi.fn(),
}));

vi.mock('@/contexts', () => createMockContexts());

vi.mock('../../api/adminApi', () => ({
  adminNewsletters: {
    getSendTimeData: mockGetSendTimeData,
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { NewsletterSendTimeOptimizer } from './NewsletterSendTimeOptimizer';

// ─────────────────────────────────────────────────────────────────────────────
// Fixtures
// ─────────────────────────────────────────────────────────────────────────────
const SAMPLE_HEATMAP = [
  { day_of_week: 1, hour: 9, engagement_score: 12 },
  { day_of_week: 3, hour: 14, engagement_score: 20 },
  { day_of_week: 5, hour: 10, engagement_score: 8 },
];

const SAMPLE_RECOMMENDATIONS = [
  { description: 'Wednesday 2PM', score: 20 },
  { description: 'Monday 9AM', score: 12 },
];

const FULL_DATA = {
  heatmap: SAMPLE_HEATMAP,
  recommendations: SAMPLE_RECOMMENDATIONS,
  insights: 'Best engagement on Wednesdays.',
};

// ─────────────────────────────────────────────────────────────────────────────
// Tests
// ─────────────────────────────────────────────────────────────────────────────
describe('NewsletterSendTimeOptimizer', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders heatmap grid after data loads', async () => {
    mockGetSendTimeData.mockResolvedValue({ success: true, data: FULL_DATA });

    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      // Heatmap grid is rendered with role="grid"
      expect(screen.getByRole('grid')).toBeInTheDocument();
    });

    // Cells with scores > 0 render their score as text (may appear in multiple nodes)
    expect(screen.getAllByText('12').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('20').length).toBeGreaterThanOrEqual(1);
  });

  it('renders recommendations when present', async () => {
    mockGetSendTimeData.mockResolvedValue({ success: true, data: FULL_DATA });

    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      expect(screen.getByText('Wednesday 2PM')).toBeInTheDocument();
    });
    expect(screen.getByText('Monday 9AM')).toBeInTheDocument();
  });

  it('renders insights text when present', async () => {
    mockGetSendTimeData.mockResolvedValue({ success: true, data: FULL_DATA });

    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      expect(screen.getByText('Best engagement on Wednesdays.')).toBeInTheDocument();
    });
  });

  it('shows empty/no-data state when heatmap array is empty', async () => {
    mockGetSendTimeData.mockResolvedValue({
      success: true,
      data: { heatmap: [], recommendations: [], insights: null },
    });

    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      // Empty state message (not_enough_data key)
      expect(screen.queryByRole('grid')).not.toBeInTheDocument();
    });
  });

  it('shows no recommendations section when empty', async () => {
    mockGetSendTimeData.mockResolvedValue({
      success: true,
      data: { heatmap: [], recommendations: [], insights: null },
    });

    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      expect(screen.queryByText('Wednesday 2PM')).not.toBeInTheDocument();
    });
  });

  it('falls back to null data on API error without crashing', async () => {
    mockGetSendTimeData.mockRejectedValue(new Error('Server error'));

    // Should not throw; component handles catch silently
    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      expect(screen.queryByRole('grid')).not.toBeInTheDocument();
    });
  });

  it('refetches data when Refresh button is pressed', async () => {
    mockGetSendTimeData
      .mockResolvedValueOnce({ success: true, data: FULL_DATA })
      .mockResolvedValueOnce({
        success: true,
        data: { ...FULL_DATA, insights: 'Updated insight.' },
      });

    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      expect(screen.getByRole('grid')).toBeInTheDocument();
    });

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await userEvent.click(refreshBtn);

    await waitFor(() => {
      expect(mockGetSendTimeData).toHaveBeenCalledTimes(2);
    });
  });

  it('calls getSendTimeData with days=30 on initial load', async () => {
    mockGetSendTimeData.mockResolvedValue({ success: true, data: FULL_DATA });

    render(<NewsletterSendTimeOptimizer />);

    await waitFor(() => {
      expect(mockGetSendTimeData).toHaveBeenCalledWith({ days: 30 });
    });
  });

  it('does not show recommendations section when no data loaded yet', async () => {
    // Never resolves so we stay in loading state
    mockGetSendTimeData.mockReturnValue(new Promise(() => {}));

    render(<NewsletterSendTimeOptimizer />);

    // Loading text is shown, no recommendations
    expect(screen.queryByText('Wednesday 2PM')).not.toBeInTheDocument();
    expect(screen.queryByRole('grid')).not.toBeInTheDocument();
  });
});
