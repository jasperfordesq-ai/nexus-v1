// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, waitFor } from '@testing-library/react-native';

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
        'detail.communitySeller': 'Community seller',
        'detail.viewSeller': 'View seller profile',
        'detail.description': 'Description',
        'detail.additionalDetails': 'Additional details',
        'detail.quantity': opts ? `${String(opts.count ?? 0)} available` : '0 available',
        'detail.views': opts ? `${String(opts.count ?? 0)} views` : '0 views',
        'priceType.fixed': 'Fixed price',
        'condition.good': 'Good',
        'delivery_method.pickup': 'Local pickup',
        'detail.save': 'Save',
        'detail.addToCollection': 'Add to collection',
        'detail.makeOffer': 'Make offer',
        'detail.buyNow': 'Buy now',
        'detail.reportListing': 'Report listing',
        'checkout.title': 'Checkout',
        'checkout.coupon': 'Coupon code',
        'checkout.couponPlaceholder': 'COMMUNITY10',
        'checkout.apply': 'Apply',
      };
      if (key === 'detail.templateFieldLabel') return String(opts?.field ?? '');
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
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
import { getMarketplaceListing } from '@/lib/api/marketplace';

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
  beforeEach(() => {
    jest.clearAllMocks();
    (getMarketplaceListing as jest.Mock).mockResolvedValue({ data: mockListing });
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
});
