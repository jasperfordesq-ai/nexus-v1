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
  createMarketplaceCollection,
  createMarketplacePaymentIntent,
  createMarketplacePickupSlot,
  createMarketplaceSavedSearch,
  createMerchantCoupon,
  completeMerchantOnboarding,
  getMarketplaceCategories,
  getMarketplaceCollectionItems,
  getMarketplaceCollections,
  getMarketplaceListing,
  getMarketplaceListingPickupSlots,
  getMarketplaceListings,
  getMarketplaceOffers,
  getMarketplacePickupSlots,
  getMarketplaceSavedSearches,
  getMarketplaceStripeOnboardingStatus,
  getMerchantOnboardingStatus,
  getMyMarketplaceListings,
  getMyMarketplacePickups,
  getMyMarketplacePromotions,
  makeMarketplaceOffer,
  marketplaceHasMore,
  marketplaceNextCursor,
  promoteMarketplaceListing,
  removeMarketplaceCollectionItem,
  reserveMarketplacePickup,
  scanMarketplacePickup,
  saveMerchantOnboardingStep1,
  saveMerchantOnboardingStep2,
  saveMerchantOnboardingStep3,
  startMarketplaceStripeOnboarding,
  updateMarketplaceListing,
  uploadMarketplaceImages,
  validateMarketplaceCoupon,
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

  it('wires marketplace discovery collections and saved searches', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });
    (api.post as jest.Mock).mockResolvedValue({ data: { id: 1 } });

    await getMarketplaceCollections();
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/collections');

    await getMarketplaceCollectionItems(7, null, 50);
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/collections/7/items', {
      limit: '50',
    });

    await removeMarketplaceCollectionItem(7, 99);
    expect(api.delete).toHaveBeenCalledWith('/api/v2/marketplace/collections/7/items/99');

    await createMarketplaceCollection({ name: 'Garden kit', description: 'Useful things', is_public: false });
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/collections', {
      name: 'Garden kit',
      description: 'Useful things',
      is_public: false,
    });

    await getMarketplaceSavedSearches();
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/saved-searches');

    await createMarketplaceSavedSearch({ name: 'Bikes', search_query: 'bike', alert_frequency: 'daily', alert_channel: 'push' });
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/saved-searches', {
      name: 'Bikes',
      search_query: 'bike',
      alert_frequency: 'daily',
      alert_channel: 'push',
    });
  });

  it('wires promotions, pickups, and seller coupons', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });
    (api.post as jest.Mock).mockResolvedValue({ data: { id: 1 } });

    await getMyMarketplacePromotions();
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/promotions/mine');

    await promoteMarketplaceListing(9, 'featured');
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/listings/9/promote', { promotion_type: 'featured' });

    await getMarketplacePickupSlots();
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/seller/pickup-slots');

    await createMarketplacePickupSlot({ slot_start: '2026-06-01 10:00', slot_end: '2026-06-01 12:00', capacity: 4 });
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/seller/pickup-slots', {
      slot_start: '2026-06-01 10:00',
      slot_end: '2026-06-01 12:00',
      capacity: 4,
    });

    await getMyMarketplacePickups();
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/me/pickups');

    await scanMarketplacePickup('abc123');
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/seller/pickup-scan', { qr_code: 'abc123' });

    await createMerchantCoupon({ title: 'June discount', discount_type: 'percent', discount_value: 10, status: 'active' });
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/seller/coupons', {
      title: 'June discount',
      discount_type: 'percent',
      discount_value: 10,
      status: 'active',
    });
  });

  it('wires checkout payment, pickup reservation, and coupon validation', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: [] });
    (api.post as jest.Mock).mockResolvedValue({ data: { checkout_url: 'https://checkout.test' } });

    await getMarketplaceListingPickupSlots(9);
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/listings/9/pickup-slots');

    await reserveMarketplacePickup(14, 3);
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/orders/14/pickup-reservation', {
      slot_id: 3,
    });

    await createMarketplacePaymentIntent(14);
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/payments/create-intent', {
      order_id: 14,
    });

    await validateMarketplaceCoupon({ code: 'SAVE10', order_total_cents: 2500, listing_id: 9 });
    expect(api.post).toHaveBeenCalledWith('/api/v2/coupons/validate', {
      code: 'SAVE10',
      order_total_cents: 2500,
      listing_id: 9,
    });
  });

  it('wires merchant and Stripe onboarding endpoints', async () => {
    (api.get as jest.Mock).mockResolvedValue({ data: { onboarding_completed: false, profile: null } });
    (api.post as jest.Mock).mockResolvedValue({ data: { profile: { id: 1 } } });

    await getMerchantOnboardingStatus();
    expect(api.get).toHaveBeenCalledWith('/api/v2/merchant-onboarding/status');

    await saveMerchantOnboardingStep1({
      seller_type: 'business',
      business_name: 'Nexus Shop',
      display_name: 'Nexus Shop',
      bio: 'Community seller',
      business_registration: null,
    });
    expect(api.post).toHaveBeenCalledWith('/api/v2/merchant-onboarding/step-1', {
      seller_type: 'business',
      business_name: 'Nexus Shop',
      display_name: 'Nexus Shop',
      bio: 'Community seller',
      business_registration: null,
    });

    await saveMerchantOnboardingStep2({ business_address: { city: 'Cork' } });
    expect(api.post).toHaveBeenCalledWith('/api/v2/merchant-onboarding/step-2', {
      business_address: { city: 'Cork' },
    });

    await saveMerchantOnboardingStep3({ avatar_url: '/uploads/avatar.jpg', cover_image_url: null });
    expect(api.post).toHaveBeenCalledWith('/api/v2/merchant-onboarding/step-3', {
      avatar_url: '/uploads/avatar.jpg',
      cover_image_url: null,
    });

    await completeMerchantOnboarding();
    expect(api.post).toHaveBeenCalledWith('/api/v2/merchant-onboarding/complete', {});

    await getMarketplaceStripeOnboardingStatus();
    expect(api.get).toHaveBeenCalledWith('/api/v2/marketplace/seller/onboard/status');

    await startMarketplaceStripeOnboarding();
    expect(api.post).toHaveBeenCalledWith('/api/v2/marketplace/seller/onboard', {});
  });
});
