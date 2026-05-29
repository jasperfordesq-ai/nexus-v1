// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { createReview, deleteReview, getPendingReviews, getUserReviews } from './reviews';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
    post: jest.fn(),
    delete: jest.fn(),
  },
}));

describe('reviews API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads received reviews for a user with cursor pagination', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: {
        items: [{ id: 1, rating: 5, reviewer: { id: 2, name: 'Niamh' }, created_at: '2026-05-29T10:00:00Z' }],
        cursor: 'next',
        has_more: true,
      },
    });

    const result = await getUserReviews(7, { cursor: 'abc', perPage: 10 });

    expect(api.get).toHaveBeenCalledWith('/api/v2/reviews/user/7', { per_page: '10', cursor: 'abc' });
    expect(result.items).toHaveLength(1);
    expect(result.cursor).toBe('next');
    expect(result.hasMore).toBe(true);
  });

  it('loads pending reviews from the same endpoint as web', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{ exchange_id: 22, receiver_id: 8, receiver_name: 'Sam', exchange_title: 'Garden help' }],
    });

    const result = await getPendingReviews();

    expect(api.get).toHaveBeenCalledWith('/api/v2/reviews/pending');
    expect(result).toHaveLength(1);
  });

  it('creates and deletes reviews', async () => {
    (api.post as jest.Mock).mockResolvedValue({ success: true });
    (api.delete as jest.Mock).mockResolvedValue({ success: true });

    await createReview({ receiver_id: 8, rating: 4, comment: 'Great exchange', transaction_id: 99 });
    await deleteReview(11);

    expect(api.post).toHaveBeenCalledWith('/api/v2/reviews', {
      receiver_id: 8,
      rating: 4,
      comment: 'Great exchange',
      transaction_id: 99,
    });
    expect(api.delete).toHaveBeenCalledWith('/api/v2/reviews/11');
  });
});
