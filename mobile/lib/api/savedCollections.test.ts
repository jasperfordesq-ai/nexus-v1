// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import {
  createSavedCollection,
  getMySavedCollections,
  getPublicSavedCollections,
  getSavedCollectionItems,
  removeSavedItem,
} from './savedCollections';

jest.mock('@/lib/api/client', () => ({
  api: {
    get: jest.fn(),
    post: jest.fn(),
    delete: jest.fn(),
  },
}));

const mockGet = api.get as jest.Mock;
const mockPost = api.post as jest.Mock;
const mockDelete = api.delete as jest.Mock;

beforeEach(() => {
  jest.clearAllMocks();
});

describe('saved collections API', () => {
  it('loads my saved collections', async () => {
    mockGet.mockResolvedValueOnce({ data: [] });

    await getMySavedCollections();

    expect(mockGet).toHaveBeenCalledWith('/api/v2/me/collections');
  });

  it('loads public collections for a member', async () => {
    mockGet.mockResolvedValueOnce({ data: [] });

    await getPublicSavedCollections(7);

    expect(mockGet).toHaveBeenCalledWith('/api/v2/users/7/public-collections');
  });

  it('creates a collection', async () => {
    const payload = { name: 'Weekend', description: null, is_public: true };
    mockPost.mockResolvedValueOnce({ data: { id: 1, name: 'Weekend' } });

    await createSavedCollection(payload);

    expect(mockPost).toHaveBeenCalledWith('/api/v2/me/collections', payload);
  });

  it('loads collection items with pagination params', async () => {
    mockGet.mockResolvedValueOnce({ data: { items: [], collection: { id: 9 } } });

    await getSavedCollectionItems(9, 2, 10);

    expect(mockGet).toHaveBeenCalledWith('/api/v2/me/collections/9/items', {
      page: '2',
      per_page: '10',
    });
  });

  it('removes saved items by id', async () => {
    mockDelete.mockResolvedValueOnce(undefined);

    await removeSavedItem(44);

    expect(mockDelete).toHaveBeenCalledWith('/api/v2/me/saved-items/44');
  });
});
