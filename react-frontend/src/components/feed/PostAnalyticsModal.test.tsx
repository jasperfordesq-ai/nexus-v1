// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/contexts', () => createMockContexts());

import { api } from '@/lib/api';
import { PostAnalyticsModal } from './PostAnalyticsModal';

const mockGet = vi.mocked(api.get);

const ANALYTICS_DATA = {
  post_id: 99,
  views_count: 1234,
  likes_count: 56,
  comments_count: 12,
  shares_count: 7,
  reactions_breakdown: {
    like: 30,
    love: 15,
    celebrate: 11,
  },
  reach_estimate: 950,
};

describe('PostAnalyticsModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('does not fetch analytics when modal is closed', () => {
    render(
      <PostAnalyticsModal isOpen={false} onClose={vi.fn()} postId={99} />,
    );

    expect(mockGet).not.toHaveBeenCalled();
  });

  it('shows a loading spinner when modal first opens', () => {
    // Hang the promise so loading stays visible
    mockGet.mockReturnValueOnce(new Promise(() => {}));

    render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    // The loading container div has aria-busy="true" — avoids ambiguity with
    // nested Spinner role="status" elements and the ToastProvider's status region
    const busyContainer = document.querySelector('[aria-busy="true"]');
    expect(busyContainer).not.toBeNull();
  });

  it('renders analytics metrics after successful fetch', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: ANALYTICS_DATA });

    render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    await waitFor(() => {
      // views_count = 1234 should appear; toLocaleString() in en test env → "1,234" or "1234"
      expect(screen.getByText(/1[,.]?234/)).toBeInTheDocument();
    });

    // likes, comments, shares, reach
    expect(screen.getByText('56')).toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
    expect(screen.getByText('7')).toBeInTheDocument();
    expect(screen.getByText('950')).toBeInTheDocument();
  });

  it('fetches from the correct endpoint', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: ANALYTICS_DATA });

    render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    await waitFor(() => {
      expect(mockGet).toHaveBeenCalledWith(
        '/v2/feed/posts/99/analytics',
        expect.objectContaining({ signal: expect.any(AbortSignal) }),
      );
    });
  });

  it('shows an error message when the API returns a non-success response', async () => {
    mockGet.mockResolvedValueOnce({ success: false, error: 'Permission denied' });

    render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });

    expect(screen.queryByText(/1[,.]?234/)).not.toBeInTheDocument();
  });

  it('shows an error message when the fetch throws', async () => {
    mockGet.mockRejectedValueOnce(new Error('Network Error'));

    render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });
  });

  it('renders the reactions breakdown section when data is present', async () => {
    mockGet.mockResolvedValueOnce({ success: true, data: ANALYTICS_DATA });

    render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    await waitFor(() => {
      expect(screen.getByText(/1[,.]?234/)).toBeInTheDocument();
    });

    // Reactions breakdown shows reaction type names (capitalised by CSS capitalize)
    expect(screen.getByText('like')).toBeInTheDocument();
    expect(screen.getByText('love')).toBeInTheDocument();
    expect(screen.getByText('celebrate')).toBeInTheDocument();
  });

  it('does not render the reactions section when breakdown is empty', async () => {
    const noReactions = { ...ANALYTICS_DATA, reactions_breakdown: {} };
    mockGet.mockResolvedValueOnce({ success: true, data: noReactions });

    render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    await waitFor(() => {
      expect(screen.getByText(/1[,.]?234/)).toBeInTheDocument();
    });

    expect(screen.queryByText('like')).not.toBeInTheDocument();
  });

  it('re-fetches when postId changes while modal remains open', async () => {
    mockGet
      .mockResolvedValueOnce({ success: true, data: ANALYTICS_DATA })
      .mockResolvedValueOnce({
        success: true,
        data: { ...ANALYTICS_DATA, post_id: 100, views_count: 9999 },
      });

    const { rerender } = render(
      <PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={99} />,
    );

    await waitFor(() => {
      expect(screen.getByText(/1[,.]?234/)).toBeInTheDocument();
    });

    rerender(<PostAnalyticsModal isOpen={true} onClose={vi.fn()} postId={100} />);

    await waitFor(() => {
      expect(screen.getByText(/9[,.]?999/)).toBeInTheDocument();
    });

    expect(mockGet).toHaveBeenCalledTimes(2);
  });
});
