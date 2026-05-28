// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, waitFor } from '@testing-library/react-native';

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), replace: jest.fn(), back: jest.fn(), canGoBack: jest.fn(() => false) },
  useLocalSearchParams: () => ({}),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'common:back': 'Back',
        'forms.createTitle': 'Sell an item',
        'forms.eyebrow': 'New marketplace listing',
        'forms.subtitle': 'Use complete details.',
        'forms.title': 'Title',
        'forms.titlePlaceholder': 'What are you selling?',
        'forms.tagline': 'Short tagline',
        'forms.taglinePlaceholder': 'A quick summary',
        'forms.description': 'Description',
        'forms.descriptionPlaceholder': 'Details',
        'forms.priceType': 'Price type',
        'forms.price': 'Price',
        'forms.pricePlaceholder': '0.00',
        'forms.timeCredits': 'Time credits',
        'forms.timeCreditsPlaceholder': 'Optional',
        'forms.condition': 'Condition',
        'forms.category': 'Category',
        'forms.quantity': 'Quantity',
        'forms.quantityPlaceholder': '1',
        'forms.location': 'Location',
        'forms.locationPlaceholder': 'Where can buyers collect it?',
        'forms.coordinates': 'Map coordinates',
        'forms.coordinatesHint': 'Optional coordinates help nearby search place this listing accurately.',
        'forms.latitude': 'Latitude',
        'forms.latitudePlaceholder': '51.5007',
        'forms.longitude': 'Longitude',
        'forms.longitudePlaceholder': '-0.1246',
        'forms.delivery': 'Delivery',
        'forms.sellerType': 'Seller type',
        'forms.media': 'Images',
        'forms.addImages': 'Add images',
        'forms.mediaHint': 'Add up to 8 images.',
        'forms.footerCreateTitle': 'Review listing',
        'forms.footerSubtitle': 'Publish when ready.',
        'forms.publish': 'Publish',
        'filters.noCategory': 'No category',
        'inventory.section_title': 'Inventory',
        'inventory.section_subtitle': 'Track stock.',
        'inventory.unlimited': 'Unlimited stock',
        'inventory.oversold_protected': 'Reject overselling',
        'priceType.fixed': 'Fixed price',
        'priceType.negotiable': 'Negotiable',
        'priceType.free': 'Free',
        'priceType.contact': 'Contact seller',
        'condition.new': 'New',
        'condition.like_new': 'Like new',
        'condition.good': 'Good',
        'condition.fair': 'Fair',
        'condition.poor': 'Poor',
        'delivery_method.pickup': 'Local pickup',
        'delivery_method.shipping': 'Shipping',
        'delivery_method.both': 'Pickup or shipping',
        'delivery_method.community_delivery': 'Community delivery',
        'sellerType.private': 'Private seller',
        'sellerType.business': 'Business',
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
  }),
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('expo-image-picker', () => ({
  requestMediaLibraryPermissionsAsync: jest.fn(),
  launchImageLibraryAsync: jest.fn(),
  MediaTypeOptions: { Images: 'Images' },
}));
jest.mock('@/lib/haptics', () => ({
  notificationAsync: jest.fn(),
  NotificationFeedbackType: { Success: 'success' },
}));
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

jest.mock('@/lib/api/marketplace', () => ({
  createMarketplaceListing: jest.fn(),
  getMarketplaceCategories: jest.fn().mockResolvedValue({ data: [] }),
  getMarketplaceCategoryTemplate: jest.fn().mockResolvedValue({ data: { fields: [] } }),
  getMarketplaceListing: jest.fn(),
  updateMarketplaceListing: jest.fn(),
  uploadMarketplaceImages: jest.fn(),
}));

import NewMarketplaceListingRoute from './new-marketplace-listing';

describe('NewMarketplaceListingRoute', () => {
  it('renders optional coordinate fields for nearby search parity', async () => {
    const { getByText } = render(<NewMarketplaceListingRoute />);

    await waitFor(() => {
      expect(getByText('Map coordinates')).toBeTruthy();
      expect(getByText('Latitude')).toBeTruthy();
      expect(getByText('Longitude')).toBeTruthy();
    });
  });
});
