// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { getComments, submitComment } from './comments';
import { api } from '@/lib/api/client';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
    post: jest.fn(),
  },
}));

describe('comments api', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads comments for polymorphic feed targets', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: { comments: [], count: 0 } });

    await getComments('poll', 42);

    expect(api.get).toHaveBeenCalledWith('/api/v2/comments', {
      target_type: 'poll',
      target_id: '42',
    });
  });

  it('submits comments for polymorphic feed targets', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { id: 99, content: 'Looks good' } });

    await submitComment('listing', 5, 'Looks good');

    expect(api.post).toHaveBeenCalledWith('/api/v2/comments', {
      target_type: 'listing',
      target_id: 5,
      content: 'Looks good',
    });
  });
});
