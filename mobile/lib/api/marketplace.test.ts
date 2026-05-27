// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn(), put: jest.fn(), patch: jest.fn(), delete: jest.fn(), upload: jest.fn() },
}));

jest.mock('@/lib/constants', () => ({
  API_V2: '/api/v2',
}));

import { api } from '@/lib/api/client';
import {
  createMarketplaceListing,
  getMarketplaceCategories,
  getMarketplaceListing,
  getMarketplaceListings,
  getMarketplaceOffers,
  getMyMarketplaceListings,
  makeMarketplaceOffer,
  marketplaceHasMore,
  marketplaceNextCursor,
  updateMarketplaceListing,
  uploadMarketplaceImages,
} from './marketplace';

describe('marketplace api', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('loads marketplace listings with web parity filters', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [], meta: { cursor: null, has_more: false } });

    await getMarketplaceListings({
      q: 'bike',
      category_id: 3,
      price_type: 'free',
      condition: 'good',
      delivery_method: 'pickup',
      sort: 'newest',
      cursor: 'abc',
      limit: 24,
    });

    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/listings', {
      q: 'bike',
      category_id: '3',
      price_type: 'free',
      condition: 'good',
      delivery_method: 'pickup',
      sort: 'newest',
      cursor: 'abc',
      limit: '24',
    });
  });

  it('loads current user listings with user_id scope', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });
    await getMyMarketplaceListings(null, 42);
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/listings', {
      limit: '20',
      sort: 'newest',
      user_id: '42',
    });
  });

  it('uses the backend category and detail endpoints', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });
    await getMarketplaceCategories();
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/categories');

    await getMarketplaceListing(9);
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/listings/9');
  });

  it('creates and updates listings with marketplace payloads', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { id: 1 } });
    (api.put as jest.Mock).mockResolvedValue({ data: { id: 1 } });
    const payload = {
      title: 'Garden table',
      description: 'Solid table',
      price_type: 'fixed' as const,
      price: 25,
      condition: 'good' as const,
      delivery_method: 'pickup' as const,
    };

    await createMarketplaceListing(payload);
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/listings', payload);

    await updateMarketplaceListing(1, { title: 'Updated' });
    expect(api.put).toHaveBeenCalledWith('/api/v2/marketplace/listings/1', { title: 'Updated' });
  });

  it('creates and loads offers', async () => {
    (api.post as jest.Mock).mockResolvedValue({ data: { id: 1 } });
    (api.get as jest.Mock).mockResolvedValue({ data: [], meta: { cursor: null, has_more: false } });

    await makeMarketplaceOffer(8, { amount: 12, message: 'Can collect today' });
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/listings/8/offers', {
      amount: 12,
      message: 'Can collect today',
    });

    await getMarketplaceOffers('received', 'next');
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/my-offers/received', {
      cursor: 'next',
      per_page: '20',
    });
  });

  it('normalises pagination metadata variants', () => {
    expect(marketplaceNextCursor({ data: [], meta: { next_cursor: 'next', has_more: true } })).toBe('next');
    expect(marketplaceHasMore({ data: [], meta: { next_cursor: 'next', has_more: true } })).toBe(true);
  });

  it('uploads listing images to the marketplace images endpoint', async () => {
    (api.upload as jest.Mock).mockResolvedValue({ data: [{ id: 1, url: '/uploads/marketplace/a.jpg' }] });

    await uploadMarketplaceImages(8, ['file:///tmp/a.jpg']);

    expect(api.upload).toHaveBeenCalledWith('/api/v2/marketplace/listings/8/images', expect.any(FormData));
  });
});
