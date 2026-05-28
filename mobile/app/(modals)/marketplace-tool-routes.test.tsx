// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, waitFor } from '@testing-library/react-native';

const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  router: { replace: (...args: unknown[]) => mockReplace(...args), back: jest.fn(), canGoBack: jest.fn(() => false) },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'collections.savedTab': 'Saved searches',
        'merchantOnboarding.title': 'Seller setup',
        'orders.sales': 'Sales',
        'tools.tabs.promotions': 'Promotions',
        'tools.pickups.title': 'Pickups',
        'tools.pickups.scan': 'Mark pickup complete',
      };
      return map[key] ?? key;
    },
  }),
}));

jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => children);
jest.mock('@/components/ui/AppTopBar', () => {
  const { Text } = require('react-native');
  return ({ title }: { title: string }) => <Text>{title}</Text>;
});
jest.mock('@/components/ui/LoadingSpinner', () => {
  const { Text } = require('react-native');
  return () => <Text>Loading</Text>;
});

import MarketplaceSavedSearchesRoute from './marketplace-saved-searches';
import MarketplacePromotionsRoute from './marketplace-promotions';
import MarketplacePickupSlotsRoute from './marketplace-pickup-slots';
import MarketplacePickupScanRoute from './marketplace-pickup-scan';
import MarketplaceSellerOnboardingRoute from './marketplace-seller-onboarding';
import MarketplaceBecomePartnerRoute from './marketplace-become-partner';
import MarketplaceSalesOrdersRoute from './marketplace-sales-orders';

describe('marketplace seller tool action routes', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('redirects saved searches to the saved marketplace collection tab', async () => {
    const { getByText } = render(<MarketplaceSavedSearchesRoute />);

    expect(getByText('Saved searches')).toBeTruthy();
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith({
        pathname: '/(modals)/marketplace-collections',
        params: { tab: 'saved' },
      });
    });
  });

  it('redirects promotions to the seller tools promotions tab', async () => {
    const { getByText } = render(<MarketplacePromotionsRoute />);

    expect(getByText('Promotions')).toBeTruthy();
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith({
        pathname: '/(modals)/marketplace-tools',
        params: { tab: 'promotions' },
      });
    });
  });

  it('redirects pickup slots to the seller tools pickups tab', async () => {
    const { getByText } = render(<MarketplacePickupSlotsRoute />);

    expect(getByText('Pickups')).toBeTruthy();
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith({
        pathname: '/(modals)/marketplace-tools',
        params: { tab: 'pickups' },
      });
    });
  });

  it('redirects pickup scanning to the seller tools pickups tab', async () => {
    const { getByText } = render(<MarketplacePickupScanRoute />);

    expect(getByText('Mark pickup complete')).toBeTruthy();
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith({
        pathname: '/(modals)/marketplace-tools',
        params: { tab: 'pickups' },
      });
    });
  });

  it('redirects seller onboarding aliases to merchant onboarding', async () => {
    const first = render(<MarketplaceSellerOnboardingRoute />);

    expect(first.getByText('Seller setup')).toBeTruthy();
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith('/(modals)/marketplace-merchant-onboarding');
    });
    first.unmount();
    mockReplace.mockClear();

    const second = render(<MarketplaceBecomePartnerRoute />);
    expect(second.getByText('Seller setup')).toBeTruthy();
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith('/(modals)/marketplace-merchant-onboarding');
    });
    second.unmount();
  });

  it('redirects seller order deep links to the sales order mode', async () => {
    const { getByText } = render(<MarketplaceSalesOrdersRoute />);

    expect(getByText('Sales')).toBeTruthy();
    await waitFor(() => {
      expect(mockReplace).toHaveBeenCalledWith({
        pathname: '/(modals)/marketplace-orders',
        params: { mode: 'sales' },
      });
    });
  });
});
