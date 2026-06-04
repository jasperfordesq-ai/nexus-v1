// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';
import { router } from 'expo-router';

jest.mock('expo-router', () => ({
  router: { push: jest.fn() },
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('expo-image', () => ({
  Image: 'View',
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      if (key === 'distanceKilometers') return `${String(opts?.distance ?? '')} km away`;
      if (key === 'detail.hours') return `${String(opts?.count ?? '')} hrs`;
      return key;
    },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    surface: '#ffffff',
    text: '#111827',
    textSecondary: '#4b5563',
    textMuted: '#6b7280',
    border: '#e5e7eb',
  }),
}));

jest.mock('@/components/ui/Avatar', () => {
  const { Text } = require('react-native');
  return ({ name }: { name?: string }) => <Text>{name ?? 'Avatar'}</Text>;
});

jest.mock('@/components/ui/NativePressable', () => {
  const { Pressable } = require('react-native');
  return ({ children, onPress, accessibilityLabel }: { children: React.ReactNode; onPress?: () => void; accessibilityLabel?: string }) => (
    <Pressable onPress={onPress} accessibilityLabel={accessibilityLabel}>
      {children}
    </Pressable>
  );
});

import ExchangeCard from './ExchangeCard';
import type { Exchange } from '@/lib/api/exchanges';

const exchange: Exchange = {
  id: 42,
  title: 'Garden help',
  description: 'I can help tidy a garden.',
  type: 'offer',
  status: 'active',
  hours_estimate: 2,
  category_name: 'Gardening',
  category_color: null,
  image_url: null,
  location: 'Dublin',
  distance_km: 3.4,
  user: { id: 7, name: 'Alice', avatar_url: null },
  created_at: '2026-01-10T09:00:00Z',
  is_favorited: false,
};

describe('ExchangeCard', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('opens the exchange detail route through the native card press target', () => {
    const { getByLabelText, getByText } = render(<ExchangeCard exchange={exchange} />);

    expect(getByText('Garden help')).toBeTruthy();
    fireEvent.press(getByLabelText('Garden help'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/exchange-detail',
      params: { id: '42' },
    });
  });

  it('shows distance returned by nearby listing searches', () => {
    const { getByText } = render(<ExchangeCard exchange={exchange} />);

    expect(getByText('3.4 km away')).toBeTruthy();
  });

  it('exposes a card save action without opening the detail screen', () => {
    const onToggleSave = jest.fn();
    const { getByLabelText } = render(<ExchangeCard exchange={exchange} onToggleSave={onToggleSave} />);

    fireEvent.press(getByLabelText('saveListing'));

    expect(onToggleSave).toHaveBeenCalledWith(42, false);
  });
});
