// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { getUserAppreciations, reactToAppreciation } from './appreciations';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
    post: jest.fn(),
  },
}));

const mockGet = api.get as jest.Mock;
const mockPost = api.post as jest.Mock;

beforeEach(() => {
  jest.clearAllMocks();
});

describe('appreciations API', () => {
  it('loads public appreciations for a user with pagination params', async () => {
    mockGet.mockResolvedValueOnce({ data: [] });

    await getUserAppreciations(7, 2, 10);

    expect(mockGet).toHaveBeenCalledWith('/api/v2/users/7/appreciations', {
      page: '2',
      per_page: '10',
    });
  });

  it('posts appreciation reactions to the v2 endpoint', async () => {
    mockPost.mockResolvedValueOnce({ data: { reacted: true, reaction_type: 'heart' } });

    await reactToAppreciation(12, 'heart');

    expect(mockPost).toHaveBeenCalledWith('/api/v2/appreciations/12/react', {
      reaction_type: 'heart',
    });
  });
});
