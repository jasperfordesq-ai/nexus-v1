// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

const mockRouterPush = jest.fn();
const mockUsePaginatedApi = jest.fn((_fetchFn?: unknown, _extractor?: unknown, _deps?: unknown, _options?: unknown) => ({
  items: [
    {
      id: 91,
      title: 'Repaired table',
      price: 25,
      price_currency: 'EUR',
      price_type: 'fixed',
      image: null,
    },
  ],
  isLoading: false,
  error: null,
  hasMore: false,
  loadMore: jest.fn(),
}));
let mockParams: Record<string, string> = { id: '5' };
let mockAuthState = {
  isAuthenticated: true,
  user: { id: 42 } as { id: number } | null,
};

jest.mock('expo-router', () => ({
  router: { push: (...args: unknown[]) => mockRouterPush(...args), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => mockParams,
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'seller.eyebrow': 'Seller profile',
        'seller.title': 'Seller profile',
        'seller.notFound': 'Seller not found',
        'seller.notFoundHint': 'This seller profile is not available.',
        'seller.verified': 'Verified',
        'seller.partnerBadge': 'Marketplace Partner',
        'seller.sellerType.business': 'Business seller',
        'seller.location': `Location: ${String(opts?.location ?? '')}`,
        'seller.memberSince': `Member since ${String(opts?.date ?? '')}`,
        'seller.joinedMarketplace': `Selling since ${String(opts?.date ?? '')}`,
        'seller.communityTrust': 'Community trust',
        'seller.trustScore': `${String(opts?.score ?? '')}%`,
        'seller.totalSales': 'Total sales',
        'seller.avgRating': 'Avg rating',
        'seller.responseTime': 'Response time',
        'seller.activeListings': 'Active listings',
        'seller.na': 'N/A',
        'seller.message': 'Message seller',
        'seller.empty': 'No active listings',
        'seller.emptyHint': 'This seller does not have public listings right now.',
        'seller.listingsTab': 'Listings',
        'seller.reviewsTab': 'Reviews',
        'seller.reviewsTitle': 'Reviews are coming soon',
        'seller.reviewsHint': 'Buyer and seller reviews will appear here once rating history is available.',
      };
      return map[key] ?? key;
    },
  }),
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/AppTopBar', () => {
  const { Text } = require('react-native');
  return ({ title }: { title: string }) => <Text>{title}</Text>;
});
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/EmptyState', () => {
  const { Text, View } = require('react-native');
  return ({ title, subtitle }: { title: string; subtitle?: string }) => (
    <View>
      <Text>{title}</Text>
      {subtitle ? <Text>{subtitle}</Text> : null}
    </View>
  );
});
jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/components/marketplace/MarketplaceListingCard', () => {
  const { Text } = require('react-native');
  return ({ item }: { item: { title: string } }) => <Text>{item.title}</Text>;
});
jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));
jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => mockAuthState,
}));
jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#111827',
    textSecondary: '#4b5563',
    textMuted: '#6b7280',
    border: '#d1d5db',
    error: '#dc2626',
    success: '#16a34a',
    warning: '#f59e0b',
  }),
}));
jest.mock('@/lib/api/marketplace', () => ({
  getMarketplaceSeller: jest.fn(),
  getMarketplaceSellerListings: jest.fn(),
  marketplaceHasMore: jest.fn(() => false),
  marketplaceNextCursor: jest.fn(() => null),
}));
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: () => ({
    isLoading: false,
    error: null,
    data: {
      data: {
        id: 5,
        user_id: 8,
        display_name: 'Nexus Goods',
        bio: 'Reuse and repair seller',
        avatar_url: null,
        cover_image_url: null,
        location: 'Dublin',
        marketplace_partner_badge_at: '2026-04-29T10:00:00Z',
        seller_type: 'business',
        business_verified: true,
        is_community_endorsed: false,
        community_trust_score: 80,
        avg_rating: 4.5,
        total_ratings: 3,
        total_sales: 12,
        response_time_avg: '2 hours',
        active_listings: 4,
        member_since: '2024-05-01T00:00:00Z',
        joined_marketplace_at: '2026-04-01T00:00:00Z',
      },
    },
  }),
}));
jest.mock('@/lib/hooks/usePaginatedApi', () => ({
  usePaginatedApi: (fetchFn: unknown, extractor: unknown, deps: unknown, options: unknown) => mockUsePaginatedApi(fetchFn, extractor, deps, options),
}));

import MarketplaceSellerRoute from './marketplace-seller';

describe('MarketplaceSellerRoute', () => {
  beforeEach(() => {
    mockRouterPush.mockClear();
    mockUsePaginatedApi.mockClear();
    mockParams = { id: '5' };
    mockAuthState = {
      isAuthenticated: true,
      user: { id: 42 },
    };
  });

  it('shows listing and reviews tabs on seller profiles', () => {
    const { getAllByText, getByText, queryByText } = render(<MarketplaceSellerRoute />);

    expect(getByText('Nexus Goods')).toBeTruthy();
    expect(getByText('Repaired table')).toBeTruthy();
    expect(getByText('Marketplace Partner')).toBeTruthy();
    expect(getByText('Location: Dublin')).toBeTruthy();
    expect(getByText('Listings')).toBeTruthy();
    expect(getAllByText('4').length).toBeGreaterThanOrEqual(2);
    fireEvent.press(getByText('Reviews'));

    expect(getByText('Reviews are coming soon')).toBeTruthy();
    expect(getByText('Buyer and seller reviews will appear here once rating history is available.')).toBeTruthy();
    expect(queryByText('Repaired table')).toBeNull();
  });

  it('opens a direct message thread for authenticated buyers', () => {
    const { getByText } = render(<MarketplaceSellerRoute />);

    fireEvent.press(getByText('Message seller'));

    expect(mockRouterPush).toHaveBeenCalledWith({
      pathname: '/(modals)/thread',
      params: { recipientId: '8', name: 'Nexus Goods' },
    });
  });

  it('hides the seller message action from guests and the seller themself', () => {
    mockAuthState = {
      isAuthenticated: false,
      user: null,
    };

    const guest = render(<MarketplaceSellerRoute />);
    expect(guest.queryByText('Message seller')).toBeNull();
    guest.unmount();

    mockAuthState = {
      isAuthenticated: true,
      user: { id: 8 },
    };

    const owner = render(<MarketplaceSellerRoute />);
    expect(owner.queryByText('Message seller')).toBeNull();
  });

  it('does not fetch seller listings when the route id is invalid', () => {
    mockParams = { id: 'not-a-number' };

    const { getByText } = render(<MarketplaceSellerRoute />);

    expect(getByText('Seller not found')).toBeTruthy();
    expect(mockUsePaginatedApi).toHaveBeenCalledWith(expect.any(Function), expect.any(Function), [0], { enabled: false });
  });
});
