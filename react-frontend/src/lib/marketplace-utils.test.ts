// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { mapApiToListingItem, type ApiMarketplaceListing } from './marketplace-utils';

function baseRaw(overrides: Partial<ApiMarketplaceListing> = {}): ApiMarketplaceListing {
  return {
    id: 1,
    title: 'Vintage bicycle',
    price: 120,
    price_type: 'fixed',
    currency: 'GBP',
    condition: 'used',
    views_count: 9,
    is_saved: false,
    created_at: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('mapApiToListingItem', () => {
  it('maps a fully populated listing', () => {
    const raw = baseRaw({
      description: 'A nice bike',
      category_id: 4,
      category_name: 'Transport',
      location: 'Cork',
      images: [
        { url: 'a.jpg', thumbnail_url: 'a-thumb.jpg' },
        { url: 'b.jpg', thumbnail_url: 'b-thumb.jpg' },
      ],
      seller: { id: 7, name: 'Aoife', avatar_url: 'av.png' },
      is_featured: true,
    });

    const item = mapApiToListingItem(raw);

    expect(item).toMatchObject({
      id: 1,
      title: 'Vintage bicycle',
      price: 120,
      price_currency: 'GBP',
      price_type: 'fixed',
      condition: 'used',
      location: 'Cork',
      status: 'active',
      image: { url: 'a.jpg', thumbnail_url: 'a-thumb.jpg' },
      image_count: 2,
      category: { id: 4, name: 'Transport', slug: '' },
      user: { id: 7, name: 'Aoife', avatar_url: 'av.png' },
      is_saved: false,
      is_own: false,
      is_promoted: true,
      views_count: 9,
    });
  });

  it('uses an explicit tenant fallback when currency is empty', () => {
    expect(mapApiToListingItem(baseRaw({ currency: '' }), 'JPY').price_currency).toBe('JPY');
  });

  it('defaults the first image to null and image_count to 0 when no images', () => {
    const item = mapApiToListingItem(baseRaw({ images: undefined }));
    expect(item.image).toBeNull();
    expect(item.image_count).toBe(0);
  });

  it('takes only the first image as the primary image', () => {
    const item = mapApiToListingItem(
      baseRaw({ images: [{ url: 'first.jpg', thumbnail_url: 't.jpg' }] }),
    );
    expect(item.image).toEqual({ url: 'first.jpg', thumbnail_url: 't.jpg' });
    expect(item.image_count).toBe(1);
  });

  it('returns a null category when category_id is missing', () => {
    expect(mapApiToListingItem(baseRaw({ category_id: null })).category).toBeNull();
    expect(mapApiToListingItem(baseRaw({ category_id: undefined })).category).toBeNull();
  });

  it('falls back to an empty category name when only the id is provided', () => {
    expect(mapApiToListingItem(baseRaw({ category_id: 5, category_name: null })).category).toEqual({
      id: 5,
      name: '',
      slug: '',
    });
  });

  it('returns a null user when seller is missing', () => {
    expect(mapApiToListingItem(baseRaw({ seller: undefined })).user).toBeNull();
  });

  it('coerces a null seller avatar to undefined', () => {
    const item = mapApiToListingItem(
      baseRaw({ seller: { id: 2, name: 'Bob', avatar_url: null } }),
    );
    expect(item.user).toEqual({ id: 2, name: 'Bob', avatar_url: undefined });
  });

  it('coerces a null location to undefined', () => {
    expect(mapApiToListingItem(baseRaw({ location: null })).location).toBeUndefined();
  });

  it('defaults is_promoted to false when is_featured is absent', () => {
    expect(mapApiToListingItem(baseRaw({ is_featured: undefined })).is_promoted).toBe(false);
  });

  it('preserves a null price', () => {
    expect(mapApiToListingItem(baseRaw({ price: null })).price).toBeNull();
  });
});
