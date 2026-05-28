// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => ({}),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'orders.eyebrow': 'Marketplace orders',
        'orders.title': 'Orders',
        'orders.subtitle': 'Track purchases and sales from marketplace checkout.',
        'orders.purchases': 'Purchases',
        'orders.sales': 'Sales',
        'orders.tabs.all': 'All',
        'orders.tabs.active': 'Active',
        'orders.tabs.completed': 'Completed',
        'orders.tabs.cancelled': 'Closed',
        'orders.empty': 'No orders yet',
        'orders.emptyHint': 'Marketplace purchases and sales will appear here.',
      };
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
    error: '#dc2626',
    success: '#16a34a',
    warning: '#f59e0b',
  }),
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/Avatar', () => 'View');
jest.mock('@/components/ui/LoadingSpinner', () => () => null);
jest.mock('@/lib/utils/resolveImageUrl', () => ({
  resolveImageUrl: (value?: string | null) => value ?? null,
}));

jest.mock('@/lib/api/marketplace', () => ({
  acceptMarketplaceDeliveryOffer: jest.fn(),
  cancelMarketplaceOrder: jest.fn(),
  confirmMarketplaceDeliveryOffer: jest.fn(),
  confirmMarketplaceOrderDelivery: jest.fn(),
  createMarketplacePaymentIntent: jest.fn(),
  disputeMarketplaceOrder: jest.fn(),
  getMarketplaceDeliveryOffers: jest.fn(),
  getMarketplaceOrders: jest.fn(),
  marketplaceHasMore: jest.fn(() => false),
  marketplaceNextCursor: jest.fn(() => null),
  rateMarketplaceOrder: jest.fn(),
  shipMarketplaceOrder: jest.fn(),
}));

import MarketplaceOrdersRoute from './marketplace-orders';
import { getMarketplaceOrders } from '@/lib/api/marketplace';

describe('MarketplaceOrdersRoute', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (getMarketplaceOrders as jest.Mock).mockResolvedValue({
      data: [],
      meta: { cursor: null, has_more: false },
    });
  });

  it('includes pending-payment recovery states in the active orders filter', async () => {
    const { getByText, unmount } = render(<MarketplaceOrdersRoute />);

    await waitFor(() => {
      expect(getMarketplaceOrders).toHaveBeenCalledWith('purchases', null, null);
    });

    fireEvent.press(getByText('Active'));

    await waitFor(() => {
      expect(getMarketplaceOrders).toHaveBeenCalledWith(
        'purchases',
        null,
        'pending_payment,paid,processing,shipped',
      );
    });

    unmount();
  });
});
