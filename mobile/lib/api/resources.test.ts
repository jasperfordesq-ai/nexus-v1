// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import {
  getKbArticle,
  getKbArticles,
  getResources,
  getResourceCategories,
  searchKbArticles,
} from './resources';

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn() },
}));

describe('resources API', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads resources with search and category filters', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{ id: 1, title: 'Guide', description: 'Read this', file_url: 'https://example.test/guide.pdf' }],
      meta: { next_cursor: 'next', has_more: true },
    });

    const result = await getResources({ search: 'guide', categoryId: 5, cursor: 'abc', perPage: 10 });

    expect(api.get).toHaveBeenCalledWith('/api/v2/resources', {
      per_page: '10',
      search: 'guide',
      category_id: '5',
      cursor: 'abc',
    });
    expect(result.items).toHaveLength(1);
    expect(result.cursor).toBe('next');
    expect(result.hasMore).toBe(true);
  });

  it('loads resource categories', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [{ id: 2, name: 'Forms', resource_count: 3 }] });

    const result = await getResourceCategories();

    expect(api.get).toHaveBeenCalledWith('/api/v2/resources/categories');
    expect(result[0].name).toBe('Forms');
  });

  it('loads and searches knowledge base articles', async () => {
    (api.get as jest.Mock)
      .mockResolvedValueOnce({ data: [{ id: 7, title: 'Getting started', slug: 'getting-started' }] })
      .mockResolvedValueOnce({ data: [{ id: 8, title: 'Search result', slug: 'result' }] });

    const list = await getKbArticles();
    const search = await searchKbArticles('time credits');

    expect(api.get).toHaveBeenNthCalledWith(1, '/api/v2/kb', { per_page: '100' });
    expect(api.get).toHaveBeenNthCalledWith(2, '/api/v2/kb/search', { q: 'time credits', limit: '20' });
    expect(list.items[0].title).toBe('Getting started');
    expect(search[0].title).toBe('Search result');
  });

  it('loads a knowledge base article detail', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: { id: 7, title: 'Getting started', content: '<p>Hello</p>' } });

    const result = await getKbArticle(7);

    expect(api.get).toHaveBeenCalledWith('/api/v2/kb/7');
    expect(result.title).toBe('Getting started');
  });
});
