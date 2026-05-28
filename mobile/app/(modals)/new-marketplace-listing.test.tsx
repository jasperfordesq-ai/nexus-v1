// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';
import * as ImagePicker from 'expo-image-picker';

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
        'forms.generateDescription': 'Generate with AI',
        'forms.generatingDescription': 'Generating description',
        'forms.generateTitleRequired': 'Add a title before generating a description.',
        'forms.generateDescriptionFailed': 'Could not generate a description.',
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
        'forms.media': 'Media',
        'forms.addImages': 'Add images',
        'forms.video': 'Video',
        'forms.addVideo': 'Add video',
        'forms.removeVideo': 'Remove video',
        'forms.videoSelected': 'Selected video',
        'forms.currentVideo': 'Current video',
        'forms.mediaHint': 'Add up to 8 images and one optional video.',
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
  MediaTypeOptions: { Images: 'Images', Videos: 'Videos' },
}));
jest.mock('@/lib/haptics', () => ({
  notificationAsync: jest.fn(),
  NotificationFeedbackType: { Success: 'success' },
}));
jest.mock('@/components/ui/LoadingSpinner', () => () => null);

jest.mock('@/lib/api/marketplace', () => ({
  createMarketplaceListing: jest.fn(),
  generateMarketplaceDescription: jest.fn(),
  getMarketplaceCategories: jest.fn().mockResolvedValue({ data: [] }),
  getMarketplaceCategoryTemplate: jest.fn().mockResolvedValue({ data: { fields: [] } }),
  getMarketplaceListing: jest.fn(),
  updateMarketplaceListing: jest.fn(),
  uploadMarketplaceImages: jest.fn(),
  uploadMarketplaceVideo: jest.fn(),
}));

import NewMarketplaceListingRoute from './new-marketplace-listing';
import { createMarketplaceListing, generateMarketplaceDescription, uploadMarketplaceVideo } from '@/lib/api/marketplace';

describe('NewMarketplaceListingRoute', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('renders optional coordinate fields for nearby search parity', async () => {
    const { getByText } = render(<NewMarketplaceListingRoute />);

    await waitFor(() => {
      expect(getByText('Map coordinates')).toBeTruthy();
      expect(getByText('Latitude')).toBeTruthy();
      expect(getByText('Longitude')).toBeTruthy();
    });
  });

  it('generates a marketplace description from the backend AI endpoint', async () => {
    jest.mocked(generateMarketplaceDescription).mockResolvedValue({
      data: { description: 'Generated mobile marketplace description.' },
    } as never);

    const { findByDisplayValue, getByPlaceholderText, getByText } = render(<NewMarketplaceListingRoute />);

    fireEvent.changeText(getByPlaceholderText('What are you selling?'), 'Garden shears');
    fireEvent.press(getByText('Generate with AI'));

    await waitFor(() => {
      expect(generateMarketplaceDescription).toHaveBeenCalledWith({
        title: 'Garden shears',
        category: undefined,
        condition: 'good',
      });
    });
    expect(await findByDisplayValue('Generated mobile marketplace description.')).toBeTruthy();
  });

  it('uploads a selected listing video after creating the listing', async () => {
    jest.mocked(ImagePicker.requestMediaLibraryPermissionsAsync).mockResolvedValue({ granted: true } as never);
    jest.mocked(ImagePicker.launchImageLibraryAsync).mockResolvedValue({
      canceled: false,
      assets: [{ uri: 'file:///tmp/demo.mp4', fileName: 'demo.mp4', mimeType: 'video/mp4', fileSize: 1024 }],
    } as never);
    jest.mocked(createMarketplaceListing).mockResolvedValue({ data: { id: 77 } } as never);
    jest.mocked(uploadMarketplaceVideo).mockResolvedValue({ data: { video_url: '/uploads/marketplace/demo.mp4' } } as never);

    const { getByPlaceholderText, getByText } = render(<NewMarketplaceListingRoute />);

    fireEvent.changeText(getByPlaceholderText('What are you selling?'), 'Garden shears');
    fireEvent.changeText(getByPlaceholderText('Details'), 'Lightly used shears with clean blades.');
    fireEvent.press(getByText('Add video'));

    await waitFor(() => {
      expect(ImagePicker.launchImageLibraryAsync).toHaveBeenCalledWith(expect.objectContaining({
        mediaTypes: ImagePicker.MediaTypeOptions.Videos,
      }));
    });
    expect(getByText('demo.mp4')).toBeTruthy();

    fireEvent.press(getByText('Publish'));

    await waitFor(() => {
      expect(uploadMarketplaceVideo).toHaveBeenCalledWith(77, expect.objectContaining({
        uri: 'file:///tmp/demo.mp4',
        fileName: 'demo.mp4',
        mimeType: 'video/mp4',
      }));
    });
  });
});
