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

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        offering: 'Offering',
        requesting: 'Requesting',
        viewDetails: 'View details',
        'detail.hours': `${String(opts?.count ?? 0)} hours`,
      };
      return map[key] ?? key;
    },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    text: '#111111',
    textSecondary: '#555555',
    textMuted: '#777777',
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('expo-image', () => ({
  Image: 'View',
}));

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');

  const Button = ({ children, onPress, accessibilityLabel }: { children?: React.ReactNode; onPress?: () => void; accessibilityLabel?: string }) => (
    <Pressable accessibilityRole="button" accessibilityLabel={accessibilityLabel} onPress={onPress}>
      {children}
    </Pressable>
  );
  Button.Label = ({ children }: { children?: React.ReactNode }) => <Text>{children}</Text>;

  const Card = ({ children }: { children?: React.ReactNode }) => <View>{children}</View>;
  Card.Header = ({ children }: { children?: React.ReactNode }) => <View>{children}</View>;
  Card.Body = ({ children }: { children?: React.ReactNode }) => <View>{children}</View>;
  Card.Footer = ({ children }: { children?: React.ReactNode }) => <View>{children}</View>;

  const Chip = ({ children }: { children?: React.ReactNode }) => <View>{children}</View>;
  Chip.Label = ({ children }: { children?: React.ReactNode }) => <Text>{children}</Text>;

  return {
    Button,
    Card,
    Chip,
    Separator: () => <View />,
    Surface: ({ children }: { children?: React.ReactNode }) => <View>{children}</View>,
  };
});

jest.mock('@/components/ui/Avatar', () => 'View');

import ExchangeCard from './ExchangeCard';

describe('ExchangeCard', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('opens the exchange detail route through the HeroUI Native-backed card button', () => {
    const { getByLabelText, getByText } = render(
      <ExchangeCard
        exchange={{
          id: 42,
          type: 'offer',
          title: 'Garden help',
          description: 'I can help clear raised beds.',
          image_url: null,
          hours_estimate: 2,
          location: 'Local park',
          category_name: 'Gardening',
          created_at: '2026-05-01T10:00:00Z',
          user: { id: 8, name: 'Alice Smith', avatar_url: null },
        } as never}
      />,
    );

    expect(getByText('Garden help')).toBeTruthy();
    fireEvent.press(getByLabelText('Garden help'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/exchange-detail',
      params: { id: '42' },
    });
  });
});
