// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, waitFor } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'common:errors.alertTitle': 'Error',
        'stripeOnboarding.title': 'Payment setup',
        'stripeOnboarding.eyebrow': 'Stripe Connect',
        'stripeOnboarding.subtitle': 'Connect Stripe so marketplace buyers can pay securely.',
        'stripeOnboarding.incompleteTitle': 'Finish Stripe setup',
        'stripeOnboarding.incompleteSubtitle': 'Your Stripe account exists, but charges or payouts are not enabled yet.',
        'stripeOnboarding.charges': 'Charges',
        'stripeOnboarding.payouts': 'Payouts',
        'stripeOnboarding.details': 'Details',
        'stripeOnboarding.readinessTitle': 'Connect readiness',
        'stripeOnboarding.readinessHint': 'Stripe must enable all three checks before buyers can pay you.',
        'stripeOnboarding.requirementReady': '{{label}} ready',
        'stripeOnboarding.requirementMissing': '{{label}} not ready',
        'stripeOnboarding.needBank': 'Bank details',
        'stripeOnboarding.needBankHint': 'Stripe asks for payout details directly in its secure onboarding flow.',
        'stripeOnboarding.needIdentity': 'Identity checks',
        'stripeOnboarding.needIdentityHint': 'Stripe may ask for identity or business verification depending on seller type.',
        'stripeOnboarding.secure': 'Secure handoff',
        'stripeOnboarding.secureHint': 'The mobile app opens Stripe onboarding in the browser and returns here for status checks.',
        'stripeOnboarding.continue': 'Continue Stripe setup',
        'stripeOnboarding.checkStatus': 'Check status',
        'stripeOnboarding.goListings': 'Back to listings',
        'stripeOnboarding.balanceTitle': 'Seller balance',
        'stripeOnboarding.balanceSubtitle': 'Track marketplace funds from completed Stripe payments.',
        'stripeOnboarding.pending': 'Pending',
        'stripeOnboarding.available': 'Available',
        'stripeOnboarding.totalEarned': 'Total earned',
        'stripeOnboarding.payoutHistory': 'Payout history',
        'stripeOnboarding.payoutHistoryHint': 'Recent marketplace payments and payout status.',
        'stripeOnboarding.noPayouts': 'No payout records yet.',
      };
      return (map[key] ?? key).replace('{{label}}', String(options?.label ?? ''));
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
  useTenant: () => ({ hasFeature: () => true }),
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
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

jest.mock('@/lib/api/marketplace', () => ({
  getMarketplaceSellerBalance: jest.fn(),
  getMarketplaceSellerPayouts: jest.fn(),
  getMarketplaceStripeOnboardingStatus: jest.fn(),
  startMarketplaceStripeOnboarding: jest.fn(),
}));

import MarketplaceStripeOnboardingRoute from './marketplace-stripe-onboarding';
import {
  getMarketplaceSellerBalance,
  getMarketplaceSellerPayouts,
  getMarketplaceStripeOnboardingStatus,
} from '@/lib/api/marketplace';

describe('MarketplaceStripeOnboardingRoute', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    (getMarketplaceStripeOnboardingStatus as jest.Mock).mockResolvedValue({
      data: {
        stripe_account_id: 'acct_123',
        stripe_onboarding_complete: false,
        details_submitted: true,
        charges_enabled: false,
        payouts_enabled: false,
      },
    });
    (getMarketplaceSellerBalance as jest.Mock).mockResolvedValue({
      data: { pending: 0, available: 0, total_earned: 0, currency: 'EUR' },
    });
    (getMarketplaceSellerPayouts as jest.Mock).mockResolvedValue({
      data: [],
      meta: { cursor: null, has_more: false },
    });
  });

  it('shows a resume-onboarding message for incomplete Stripe accounts', async () => {
    const { getByText, queryByText, unmount } = render(<MarketplaceStripeOnboardingRoute />);

    await waitFor(() => {
      expect(getByText('Finish Stripe setup')).toBeTruthy();
    });

    expect(getByText('Your Stripe account exists, but charges or payouts are not enabled yet.')).toBeTruthy();
    expect(getByText('Connect readiness')).toBeTruthy();
    expect(getByText('Details ready')).toBeTruthy();
    expect(getByText('Charges not ready')).toBeTruthy();
    expect(getByText('Payouts not ready')).toBeTruthy();
    expect(getByText('Continue Stripe setup')).toBeTruthy();
    expect(queryByText('Connect Stripe so marketplace buyers can pay securely.')).toBeNull();
    unmount();
  });
});
