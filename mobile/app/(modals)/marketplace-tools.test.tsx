// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

let mockParams: Record<string, string> = {};
const mockHasFeature = jest.fn(() => true);
let mockAuthState: {
  isAuthenticated: boolean;
  isLoading: boolean;
  user: { id: number } | null;
} = {
  isAuthenticated: true,
  isLoading: false,
  user: { id: 9 },
};
const mockT = (key: string, opts?: Record<string, unknown>) => {
  const map: Record<string, string> = {
    'common:back': 'Back',
    'common:cancel': 'Cancel',
    'common:errors.alertTitle': 'Error',
    'tools.eyebrow': 'Seller and discovery tools',
    'tools.title': 'Marketplace tools',
    'tools.subtitle': 'Manage saved searches, collections, promotions, pickup slots, and seller coupons.',
    'tools.onboarding': 'Seller setup',
    'tools.payments': 'Payments',
    'tools.shipping': 'Shipping options',
    'tools.delete': 'Delete',
    'tools.signInTitle': 'Sign in to use marketplace tools',
    'tools.signInHint': 'Saved searches, collections, promotions, pickup slots, and seller coupons are available after you sign in.',
    'tools.tabs.collections': 'Collections',
    'tools.tabs.savedSearches': 'Saved searches',
    'tools.tabs.promotions': 'Promotions',
    'tools.tabs.pickups': 'Pickups',
    'tools.tabs.coupons': 'Coupons',
    'tools.pickups.title': 'Pickups',
    'tools.pickups.subtitle': 'Create click-and-collect slots, scan pickup QR codes, and review reservations.',
    'tools.pickups.start': 'Slot start',
    'tools.pickups.startPlaceholder': '2026-06-01 10:00',
    'tools.pickups.end': 'Slot end',
    'tools.pickups.endPlaceholder': '2026-06-01 12:00',
    'tools.pickups.capacityLabel': 'Capacity',
    'tools.pickups.capacityPlaceholder': '4',
    'tools.pickups.recurringWeekly': 'Repeat weekly',
    'tools.pickups.createSlot': 'Create pickup slot',
    'tools.pickups.qr': 'QR code',
    'tools.pickups.qrPlaceholder': 'Paste or scan pickup code',
    'tools.pickups.scan': 'Mark pickup complete',
    'tools.pickups.emptySlots': 'No pickup slots yet',
    'tools.pickups.emptyReservations': 'No pickup reservations yet',
    'tools.savedSearches.title': 'Saved searches',
    'tools.savedSearches.subtitle': 'Save a marketplace search and keep alerts close to the mobile app.',
    'tools.savedSearches.name': 'Name',
    'tools.savedSearches.namePlaceholder': 'Bikes under 100',
    'tools.savedSearches.query': 'Search query',
    'tools.savedSearches.queryPlaceholder': 'bike, tools, sofa...',
    'tools.savedSearches.alertFrequency': 'Alert frequency',
    'tools.savedSearches.alertChannel': 'Alert channel',
    'tools.savedSearches.create': 'Save search',
    'tools.savedSearches.empty': 'No saved searches yet',
    'tools.savedSearches.frequency.instant': 'Instant alerts',
    'tools.savedSearches.frequency.daily': 'Daily alerts',
    'tools.savedSearches.frequency.weekly': 'Weekly alerts',
    'tools.savedSearches.channel.email': 'Email',
    'tools.savedSearches.channel.push': 'Push',
    'tools.savedSearches.channel.both': 'Email and push',
    'tools.coupons.title': 'Seller coupons',
    'tools.coupons.subtitle': 'Create and manage merchant coupons for marketplace checkout.',
    'tools.coupons.code': 'Coupon code',
    'tools.coupons.codePlaceholder': 'COMMUNITY10',
    'tools.coupons.name': 'Coupon title',
    'tools.coupons.namePlaceholder': 'June community discount',
    'tools.coupons.description': 'Description',
    'tools.coupons.descriptionPlaceholder': 'Optional customer-facing note',
    'tools.coupons.discountType': 'Discount type',
    'tools.coupons.value': 'Discount percent',
    'tools.coupons.valuePlaceholder': '10',
    'tools.coupons.valueFixed': 'Discount amount (cents)',
    'tools.coupons.valueFixedPlaceholder': '500',
    'tools.coupons.minOrder': 'Minimum order (cents)',
    'tools.coupons.minOrderPlaceholder': '2500',
    'tools.coupons.maxUses': 'Max uses',
    'tools.coupons.maxUsesPlaceholder': '100',
    'tools.coupons.perMember': 'Per member',
    'tools.coupons.perMemberPlaceholder': '1',
    'tools.coupons.validFrom': 'Valid from',
    'tools.coupons.validUntil': 'Valid until',
    'tools.coupons.datePlaceholder': '2026-06-01 10:00',
    'tools.coupons.status': 'Status',
    'tools.coupons.appliesTo': 'Applies to',
    'tools.coupons.create': 'Create coupon',
    'tools.coupons.update': 'Update coupon',
    'tools.coupons.edit': 'Edit',
    'tools.coupons.editingTitle': 'Editing coupon',
    'tools.coupons.cancelEdit': 'Cancel editing',
    'tools.coupons.redemptions': 'Redemptions',
    'tools.coupons.redemptionsTitle': 'Coupon redemptions',
    'tools.coupons.noRedemptions': 'No redemptions yet',
    'tools.coupons.qrRedeemTitle': 'Redeem customer QR',
    'tools.coupons.qrRedeemHint': 'Paste the token shown on a member coupon QR to mark it redeemed.',
    'tools.coupons.qrToken': 'QR token',
    'tools.coupons.qrTokenPlaceholder': 'Paste coupon token',
    'tools.coupons.redeemQr': 'Redeem coupon QR',
    'tools.coupons.redeemingQr': 'Redeeming QR',
    'tools.coupons.qrRedeemed': 'Coupon QR redeemed',
    'tools.coupons.qrRedeemedDetail': `Coupon #${String(opts?.coupon ?? '')} was redeemed at ${String(opts?.date ?? '')}.`,
    'tools.coupons.empty': 'No seller coupons yet',
    'tools.coupons.usageCount': `${String(opts?.count ?? 0)} uses`,
    'tools.coupons.validUntilShort': `Until ${String(opts?.date ?? '')}`,
    'tools.coupons.noExpiry': 'No expiry',
    'tools.coupons.appliesLabel': `Applies: ${String(opts?.scope ?? '')}`,
    'tools.coupons.percentValue': `${String(opts?.value ?? 0)}%`,
    'tools.coupons.redemptionOrder': `Order ${String(opts?.order ?? '')}`,
    'tools.coupons.redemptionValue': `Discount ${String(opts?.value ?? '')} - ${String(opts?.date ?? '')}`,
    'tools.coupons.redemptionMember': `Member #${String(opts?.member ?? '')}`,
    'tools.coupons.redemptionMethod': `Method: ${String(opts?.method ?? '')}`,
    'tools.coupons.dateUnknown': 'Date unavailable',
    'publicCoupons.fixedValue': `EUR ${String(opts?.value ?? '')} off`,
    'publicCoupons.bogo': 'BOGO',
    'tools.coupons.discountTypes.percent': 'Percent',
    'tools.coupons.discountTypes.fixed': 'Fixed',
    'tools.coupons.discountTypes.bogo': 'BOGO',
    'tools.coupons.statuses.draft': 'Draft',
    'tools.coupons.statuses.active': 'Active',
    'tools.coupons.statuses.paused': 'Paused',
    'tools.coupons.statuses.expired': 'Expired',
    'tools.coupons.applies.all_listings': 'All listings',
    'tools.coupons.applies.listing_ids': 'Specific listings',
    'tools.coupons.applies.category_ids': 'Specific categories',
    'auth:login.submit': 'Sign in',
  };
  return map[key] ?? key;
};

jest.mock('expo-router', () => ({
  router: { push: jest.fn() },
  useLocalSearchParams: () => mockParams,
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: mockT,
  }),
}));

jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);
jest.mock('@/components/ui/AppTopBar', () => {
  const { Text } = require('react-native');
  return ({ title }: { title: string }) => <Text>{title}</Text>;
});
jest.mock('@/components/ui/EmptyState', () => {
  const { Text } = require('react-native');
  return ({ title }: { title: string }) => <Text>{title}</Text>;
});
jest.mock('@/components/ui/LoadingSpinner', () => {
  const { Text } = require('react-native');
  return () => <Text>Loading</Text>;
});

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => mockAuthState,
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
  useTenant: () => ({ hasFeature: mockHasFeature }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
    error: '#dc2626',
    success: '#16a34a',
    warning: '#f59e0b',
  }),
}));

jest.mock('@/lib/api/marketplace', () => ({
  createMarketplaceCollection: jest.fn(),
  createMarketplacePickupSlot: jest.fn(),
  createMarketplaceSavedSearch: jest.fn(),
  createMerchantCoupon: jest.fn(),
  deleteMarketplaceCollection: jest.fn(),
  deleteMarketplacePickupSlot: jest.fn(),
  deleteMarketplaceSavedSearch: jest.fn(),
  deleteMerchantCoupon: jest.fn(),
  getMarketplaceCollections: jest.fn(),
  getMarketplacePickupSlots: jest.fn(),
  getMarketplacePromotionProducts: jest.fn(),
  getMarketplaceSavedSearches: jest.fn(),
  getMerchantCoupons: jest.fn(),
  getMerchantCouponRedemptions: jest.fn(),
  getMyMarketplaceListings: jest.fn(),
  getMyMarketplacePickups: jest.fn(),
  getMyMarketplacePromotions: jest.fn(),
  marketplaceHasMore: jest.fn(() => false),
  marketplaceNextCursor: jest.fn(() => null),
  promoteMarketplaceListing: jest.fn(),
  redeemPublicMerchantCouponQr: jest.fn(),
  scanMarketplacePickup: jest.fn(),
  updateMerchantCoupon: jest.fn(),
}));

import MarketplaceToolsRoute from './marketplace-tools';
import {
  createMarketplacePickupSlot,
  createMarketplaceSavedSearch,
  getMarketplacePickupSlots,
  getMarketplaceCollections,
  getMarketplaceSavedSearches,
  getMerchantCoupons,
  getMerchantCouponRedemptions,
  getMyMarketplacePickups,
  redeemPublicMerchantCouponQr,
} from '@/lib/api/marketplace';

const coupon = {
  id: 7,
  code: 'SAVE10',
  title: 'June seller discount',
  description: 'Ten percent for community members',
  discount_type: 'percent',
  discount_value: 10,
  min_order_cents: 2500,
  max_uses: 100,
  max_uses_per_member: 1,
  used_count: 3,
  usage_count: 3,
  valid_from: '2026-06-01T10:00:00Z',
  valid_until: '2026-06-30T22:00:00Z',
  status: 'active',
  applies_to: 'all_listings',
};

describe('MarketplaceToolsRoute', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockParams = {};
    mockHasFeature.mockReturnValue(true);
    mockAuthState = {
      isAuthenticated: true,
      isLoading: false,
      user: { id: 9 },
    };
    jest.mocked(getMarketplaceCollections).mockResolvedValue({ data: [] } as never);
    jest.mocked(getMarketplacePickupSlots).mockResolvedValue({ data: [] } as never);
    jest.mocked(getMyMarketplacePickups).mockResolvedValue({ data: [] } as never);
    jest.mocked(createMarketplacePickupSlot).mockResolvedValue({ data: { id: 32 } } as never);
    jest.mocked(getMarketplaceSavedSearches).mockResolvedValue({ data: [] } as never);
    jest.mocked(createMarketplaceSavedSearch).mockResolvedValue({ data: { id: 21 } } as never);
    jest.mocked(getMerchantCoupons).mockResolvedValue({ data: { items: [coupon] } } as never);
    jest.mocked(getMerchantCouponRedemptions).mockResolvedValue({
      data: {
        items: [
          {
            id: 31,
            coupon_id: 7,
            user_id: 42,
            order_id: 44,
            discount_applied_cents: 500,
            redeemed_at: '2026-06-05T12:30:00Z',
            redemption_method: 'qr',
          },
        ],
      },
    } as never);
    jest.mocked(redeemPublicMerchantCouponQr).mockResolvedValue({
      data: {
        redemption_id: 91,
        coupon_id: 7,
        redeemed_at: '2026-06-05T13:00:00Z',
      },
    } as never);
  });

  it('opens a seller coupon in edit mode from route params', async () => {
    mockParams = { tab: 'coupons', couponId: '7', couponMode: 'edit' };

    const { findByText } = render(<MarketplaceToolsRoute />);

    expect(await findByText('Editing coupon')).toBeTruthy();
    expect(await findByText('Update coupon')).toBeTruthy();
  });

  it('shows the sign-in state without calling protected seller tool APIs when unauthenticated', () => {
    mockAuthState = { isAuthenticated: false, isLoading: false, user: null };

    const { getByText } = render(<MarketplaceToolsRoute />);

    expect(getByText('Sign in to use marketplace tools')).toBeTruthy();
    expect(getMarketplaceCollections).not.toHaveBeenCalled();
    expect(getMarketplaceSavedSearches).not.toHaveBeenCalled();
    expect(getMarketplacePickupSlots).not.toHaveBeenCalled();
    expect(getMerchantCoupons).not.toHaveBeenCalled();
  });

  it('creates saved searches with selected alert preferences', async () => {
    mockParams = { tab: 'savedSearches' };

    const { getByPlaceholderText, getByText } = render(<MarketplaceToolsRoute />);

    fireEvent.changeText(getByPlaceholderText('Bikes under 100'), 'Weekend bikes');
    fireEvent.changeText(getByPlaceholderText('bike, tools, sofa...'), 'bicycle');
    fireEvent.press(getByText('Weekly alerts'));
    fireEvent.press(getByText('Email and push'));
    fireEvent.press(getByText('Save search'));

    await waitFor(() => {
      expect(createMarketplaceSavedSearch).toHaveBeenCalledWith({
        name: 'Weekend bikes',
        search_query: 'bicycle',
        alert_frequency: 'weekly',
        alert_channel: 'both',
      });
    });
  });

  it('creates pickup slots with selected capacity and recurrence', async () => {
    mockParams = { tab: 'pickups' };

    const { getByPlaceholderText, getByText } = render(<MarketplaceToolsRoute />);

    fireEvent.changeText(getByPlaceholderText('2026-06-01 10:00'), '2026-06-01 10:00');
    fireEvent.changeText(getByPlaceholderText('2026-06-01 12:00'), '2026-06-01 12:00');
    fireEvent.changeText(getByPlaceholderText('4'), '8');
    fireEvent.press(getByText('Repeat weekly'));
    fireEvent.press(getByText('Create pickup slot'));

    await waitFor(() => {
      expect(createMarketplacePickupSlot).toHaveBeenCalledWith({
        slot_start: '2026-06-01 10:00',
        slot_end: '2026-06-01 12:00',
        capacity: 8,
        is_recurring: true,
        recurring_pattern: 'weekly',
        is_active: true,
      });
    });
  });

  it('opens a seller coupon redemptions sheet from route params', async () => {
    mockParams = { tab: 'coupons', couponId: '7', couponMode: 'redemptions' };

    const { findByText } = render(<MarketplaceToolsRoute />);

    expect(await findByText('Coupon redemptions')).toBeTruthy();
    await waitFor(() => {
      expect(getMerchantCouponRedemptions).toHaveBeenCalledWith(7);
    });
    expect(await findByText('Order 44')).toBeTruthy();
  });

  it('redeems a customer coupon QR token from seller tools', async () => {
    mockParams = { tab: 'coupons' };

    const { getByPlaceholderText, getByText, findByText } = render(<MarketplaceToolsRoute />);

    fireEvent.changeText(getByPlaceholderText('Paste coupon token'), 'qr-token-123');
    fireEvent.press(getByText('Redeem coupon QR'));

    await waitFor(() => {
      expect(redeemPublicMerchantCouponQr).toHaveBeenCalledWith('qr-token-123');
    });
    expect(await findByText('Coupon QR redeemed')).toBeTruthy();
    expect(getMerchantCoupons).toHaveBeenCalledTimes(2);
  });
});
