// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { post: jest.fn() },
}));

jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
}));

import { api } from '@/lib/api/client';
import { createPoll } from './polls';

describe('createPoll', () => {
  beforeEach(() => { jest.clearAllMocks(); });

  it('posts a standard poll payload to the V2 polls endpoint', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { id: 8 } });

    await createPoll({
      question: 'Which lunch should we host?',
      options: ['Soup', 'Sandwiches'],
      description: 'Choose one',
    });

    expect(api.post).toHaveBeenCalledWith('/api/v2/polls', {
      poll_type: 'standard',
      is_anonymous: false,
      question: 'Which lunch should we host?',
      options: ['Soup', 'Sandwiches'],
      description: 'Choose one',
    });
  });
});
