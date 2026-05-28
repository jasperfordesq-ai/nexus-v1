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
    t: (key: string, opts?: Record<string, unknown>) => {
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
        'orders.sellerLabel': 'Seller',
        'orders.buyerLabel': 'Buyer',
        'orders.deliveryOffers': 'Delivery offers',
        'orders.waitingShipment': 'Waiting for shipment',
        'orders.status.paid': 'Paid',
        'orders.status.unknown': 'Unknown status',
        'actions.view': 'View',
      };
      if (key === 'orders.number') return `Order ${String(opts?.number ?? '')}`;
      if (key === 'orders.date') return String(opts?.date ?? '');
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

  it('shows the waiting-for-shipment state for paid purchase orders', async () => {
    (getMarketplaceOrders as jest.Mock).mockResolvedValueOnce({
      data: [
        {
          id: 41,
          order_number: 'MKT-000041',
          quantity: 1,
          unit_price: 18,
          total_price: 18,
          currency: 'EUR',
          status: 'paid',
          created_at: '2026-05-22T10:00:00Z',
          listing: {
            id: 71,
            title: 'Paid order lamp',
            image: null,
            delivery_method: 'pickup',
          },
          seller: { id: 2, name: 'Pat Seller', avatar_url: null },
        },
      ],
      meta: { cursor: null, has_more: false },
    });

    const { getByText, unmount } = render(<MarketplaceOrdersRoute />);

    await waitFor(() => {
      expect(getByText('Paid order lamp')).toBeTruthy();
    });

    expect(getByText('Waiting for shipment')).toBeTruthy();
    unmount();
  });

  it('shows delivery-offer actions only for community-delivery orders', async () => {
    (getMarketplaceOrders as jest.Mock).mockResolvedValueOnce({
      data: [
        {
          id: 31,
          order_number: 'MKT-000031',
          quantity: 1,
          unit_price: 25,
          total_price: 25,
          currency: 'EUR',
          status: 'paid',
          created_at: '2026-05-20T10:00:00Z',
          listing: {
            id: 61,
            title: 'Pickup-only lamp',
            image: null,
            delivery_method: 'pickup',
          },
          seller: { id: 2, name: 'Pat Seller', avatar_url: null },
        },
        {
          id: 32,
          order_number: 'MKT-000032',
          quantity: 1,
          unit_price: 30,
          total_price: 30,
          currency: 'EUR',
          status: 'paid',
          created_at: '2026-05-21T10:00:00Z',
          listing: {
            id: 62,
            title: 'Community vase',
            image: null,
            delivery_method: 'community_delivery',
          },
          seller: { id: 3, name: 'Sam Seller', avatar_url: null },
        },
      ],
      meta: { cursor: null, has_more: false },
    });

    const { getByText, getAllByText, unmount } = render(<MarketplaceOrdersRoute />);

    await waitFor(() => {
      expect(getByText('Community vase')).toBeTruthy();
    });

    expect(getAllByText('Delivery offers')).toHaveLength(1);
    unmount();
  });

  it('uses a translated fallback for unknown order statuses', async () => {
    (getMarketplaceOrders as jest.Mock).mockResolvedValueOnce({
      data: [
        {
          id: 51,
          order_number: 'MKT-000051',
          quantity: 1,
          unit_price: 12,
          total_price: 12,
          currency: 'EUR',
          status: 'awaiting_seller_review',
          created_at: '2026-05-24T10:00:00Z',
          listing: {
            id: 81,
            title: 'Review state chair',
            image: null,
            delivery_method: 'pickup',
          },
          seller: { id: 2, name: 'Pat Seller', avatar_url: null },
        },
      ],
      meta: { cursor: null, has_more: false },
    });

    const { getByText, queryByText, unmount } = render(<MarketplaceOrdersRoute />);

    await waitFor(() => {
      expect(getByText('Review state chair')).toBeTruthy();
      expect(getByText('Unknown status')).toBeTruthy();
    });

    expect(queryByText('orders.status.awaiting_seller_review')).toBeNull();
    expect(queryByText('awaiting_seller_review')).toBeNull();
    unmount();
  });
});
