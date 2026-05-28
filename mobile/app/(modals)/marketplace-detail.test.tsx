// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Alert } from 'react-native';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

let mockFeatures = new Set(['merchant_coupons']);

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => ({ id: '9' }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'common.free': 'Free',
        'common.seller': 'Seller',
        'common:errors.alertTitle': 'Error',
        'detail.title': 'Listing details',
        'detail.noImages': 'No images yet',
        'detail.seller': 'Seller',
        'detail.video': 'Listing video',
        'detail.communitySeller': 'Community seller',
        'detail.viewSeller': 'View seller profile',
        'detail.description': 'Description',
        'detail.additionalDetails': 'Additional details',
        'detail.quantity': opts ? `${String(opts.count ?? 0)} available` : '0 available',
        'detail.views': opts ? `${String(opts.count ?? 0)} views` : '0 views',
        'priceType.fixed': 'Fixed price',
        'condition.good': 'Good',
        'delivery_method.pickup': 'Local pickup',
        'delivery_method.community_delivery': 'Community delivery',
        'detail.save': 'Save',
        'detail.addToCollection': 'Add to collection',
        'detail.makeOffer': 'Make offer',
        'detail.buyNow': 'Buy now',
        'detail.reportListing': 'Report listing',
        'detail.moreFromSeller': `More from ${String(opts?.name ?? 'this seller')}`,
        'communityDelivery.eyebrow': 'Community-powered delivery',
        'communityDelivery.title': 'Community delivery',
        'communityDelivery.description': 'A trusted member can offer to deliver this order for time credits after checkout.',
        'communityDelivery.step1': 'The buyer creates an order for this community-delivery listing.',
        'communityDelivery.step2': 'Eligible community members can offer delivery time and requested credits.',
        'communityDelivery.step3': 'The buyer or seller accepts an offer, then confirms delivery when the item arrives.',
        'communityDelivery.orderManagedHint': 'Delivery offers are managed from Marketplace orders after checkout.',
        'checkout.title': 'Checkout',
        'checkout.coupon': 'Coupon code',
        'checkout.couponPlaceholder': 'COMMUNITY10',
        'checkout.apply': 'Apply',
        'checkout.paymentRecoveryTitle': 'Checkout paused',
        'checkout.paymentRecoveryHint': `Order ${String(opts?.order ?? '')} was created, but payment could not start. Continue payment from Orders.`,
      };
      if (key === 'detail.templateFieldLabel') return String(opts?.field ?? '');
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
  useTenant: () => ({ hasFeature: (feature: string) => mockFeatures.has(feature) }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#111827',
    textSecondary: '#4b5563',
    textMuted: '#6b7280',
    border: '#d1d5db',
    borderSubtle: '#e5e7eb',
    error: '#dc2626',
    success: '#16a34a',
    warning: '#f59e0b',
  }),
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({ user: { id: 99, name: 'Current User' } }),
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('expo-av', () => {
  const { Text } = require('react-native');
  return {
    ResizeMode: { CONTAIN: 'contain' },
    Video: ({ accessibilityLabel }: { accessibilityLabel?: string }) => <Text>{accessibilityLabel}</Text>,
  };
});
jest.mock('@/lib/haptics', () => ({
  impactAsync: jest.fn(),
  notificationAsync: jest.fn(),
  ImpactFeedbackStyle: { Light: 'light' },
  NotificationFeedbackType: { Success: 'success' },
}));
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/components/marketplace/MarketplaceListingCard', () => ({
  formatMarketplacePrice: () => 'EUR 25',
}));
jest.mock('@/lib/utils/resolveImageUrl', () => ({
  resolveImageUrl: (value?: string | null) => value ?? null,
}));

jest.mock('@/lib/api/marketplace', () => ({
  addMarketplaceCollectionItem: jest.fn(),
  createMarketplaceOrder: jest.fn(),
  createMarketplacePaymentIntent: jest.fn(),
  getMarketplaceListingPickupSlots: jest.fn().mockResolvedValue({ data: [] }),
  getMarketplaceSellerListings: jest.fn().mockResolvedValue({ data: [] }),
  getMarketplaceCollections: jest.fn().mockResolvedValue({ data: [] }),
  getMarketplaceListing: jest.fn(),
  makeMarketplaceOffer: jest.fn(),
  reportMarketplaceListing: jest.fn(),
  reserveMarketplacePickup: jest.fn(),
  saveMarketplaceListing: jest.fn(),
  unsaveMarketplaceListing: jest.fn(),
  validateMarketplaceCoupon: jest.fn(),
}));

import MarketplaceDetailRoute from './marketplace-detail';
import { createMarketplaceOrder, createMarketplacePaymentIntent, getMarketplaceListing, getMarketplaceSellerListings } from '@/lib/api/marketplace';

const mockListing = {
  id: 9,
  title: 'Oak dining table',
  tagline: 'Seats six comfortably',
  description: 'Solid oak table in good condition.',
  price: 25,
  price_currency: 'EUR',
  price_type: 'fixed' as const,
  time_credit_price: null,
  condition: 'good' as const,
  quantity: 1,
  location: 'Community hall',
  delivery_method: 'pickup',
  shipping_available: false,
  local_pickup: true,
  seller_type: 'private',
  status: 'active',
  image: null,
  image_count: 0,
  images: [],
  category: null,
  user: { id: 22, name: 'Sam Seller', avatar_url: null, is_verified: false },
  is_saved: false,
  is_own: false,
  is_promoted: false,
  views_count: 4,
  saves_count: 0,
  created_at: '2026-05-01T10:00:00Z',
  template_data: {
    frame_size: 'Medium',
    material: 'Oak',
  },
};

describe('MarketplaceDetailRoute', () => {
  let alertSpy: jest.SpyInstance;

  beforeEach(() => {
    jest.clearAllMocks();
    mockFeatures = new Set(['merchant_coupons']);
    alertSpy = jest.spyOn(Alert, 'alert').mockImplementation(jest.fn());
    (getMarketplaceListing as jest.Mock).mockResolvedValue({ data: mockListing });
    (getMarketplaceSellerListings as jest.Mock).mockResolvedValue({ data: [] });
  });

  afterEach(() => {
    alertSpy.mockRestore();
  });

  it('renders category-specific template details from the listing payload', async () => {
    const { getByText } = render(<MarketplaceDetailRoute />);

    await waitFor(() => {
      expect(getByText('Additional details')).toBeTruthy();
      expect(getByText('Frame size')).toBeTruthy();
      expect(getByText('Medium')).toBeTruthy();
      expect(getByText('Material')).toBeTruthy();
      expect(getByText('Oak')).toBeTruthy();
    });
  });

  it('loads more listings from the same seller and excludes the current listing', async () => {
    (getMarketplaceSellerListings as jest.Mock).mockResolvedValueOnce({
      data: [
        { ...mockListing, id: 9, title: 'Oak dining table' },
        { ...mockListing, id: 10, title: 'Matching chair' },
      ],
    });

    const { getByText, queryAllByText } = render(<MarketplaceDetailRoute />);

    await waitFor(() => {
      expect(getMarketplaceSellerListings).toHaveBeenCalledWith(22, null, 4);
      expect(getByText('More from Sam Seller')).toBeTruthy();
      expect(getByText('Matching chair')).toBeTruthy();
    });
    expect(queryAllByText('Oak dining table')).toHaveLength(1);
  });

  it('renders listing video media without showing the empty image state', async () => {
    (getMarketplaceListing as jest.Mock).mockResolvedValueOnce({
      data: {
        ...mockListing,
        images: [],
        image: null,
        video_url: '/uploads/marketplace/demo.mp4',
      },
    });

    const { getByText, queryByText } = render(<MarketplaceDetailRoute />);

    await waitFor(() => {
      expect(getByText('Listing video')).toBeTruthy();
    });
    expect(queryByText('No images yet')).toBeNull();
  });

  it('shows pending-payment recovery when checkout fails after order creation', async () => {
    (createMarketplaceOrder as jest.Mock).mockResolvedValue({
      data: { id: 44, order_number: 'MKT-000044', status: 'pending_payment' },
    });
    (createMarketplacePaymentIntent as jest.Mock).mockRejectedValue(new Error('Seller payments are not ready'));

    const { getByText } = render(<MarketplaceDetailRoute />);

    await waitFor(() => {
      expect(getByText('Buy now')).toBeTruthy();
    });

    fireEvent.press(getByText('Buy now'));

    await waitFor(() => {
      expect(createMarketplacePaymentIntent).toHaveBeenCalledWith(44);
      expect(Alert.alert).toHaveBeenCalledWith(
        'Checkout paused',
        'Order MKT-000044 was created, but payment could not start. Continue payment from Orders.',
      );
    });
  });

  it('hides coupon checkout controls when merchant coupons are disabled', async () => {
    mockFeatures = new Set();

    const { getByText, queryByText } = render(<MarketplaceDetailRoute />);

    await waitFor(() => {
      expect(getByText('Checkout')).toBeTruthy();
    });

    expect(queryByText('Coupon code')).toBeNull();
    expect(queryByText('Apply')).toBeNull();
  });

  it('shows only the valid order-based community delivery surface on listing detail', async () => {
    (getMarketplaceListing as jest.Mock).mockResolvedValueOnce({
      data: {
        ...mockListing,
        delivery_method: 'community_delivery',
      },
    });

    const { getAllByText, getByText, queryByText } = render(<MarketplaceDetailRoute />);

    await waitFor(() => {
      expect(getAllByText('Community delivery').length).toBeGreaterThan(0);
      expect(getByText('Delivery offers are managed from Marketplace orders after checkout.')).toBeTruthy();
    });

    expect(queryByText(/Mobile can manage/i)).toBeNull();
    expect(queryByText('Offer to deliver')).toBeNull();
  });
});
