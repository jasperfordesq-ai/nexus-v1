// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

import type { FeedItem as FeedItemType } from '@/lib/api/feed';
import FeedItem from './FeedItem';

jest.mock('expo-router', () => ({
  router: { push: jest.fn() },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) => opts?.defaultValue ? String(opts.defaultValue) : key,
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006FEE',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    surface: '#FFFFFF',
    border: '#E4E4E7',
    borderSubtle: '#F0F0F0',
    text: '#11181C',
    textSecondary: '#687076',
    textMuted: '#9BA1A6',
    onPrimary: '#FFFFFF',
  }),
}));

jest.mock('@/lib/api/feed', () => ({
  getFeedAuthor: () => ({ id: 1, name: 'Alice Smith', avatar: null }),
  toggleBookmark: jest.fn(),
  toggleLike: jest.fn(),
}));

jest.mock('@/lib/haptics', () => ({
  ImpactFeedbackStyle: { Light: 'Light', Medium: 'Medium' },
  NotificationFeedbackType: { Success: 'Success' },
  impactAsync: jest.fn(),
  notificationAsync: jest.fn(),
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
jest.mock('@/components/ui/ImageCarousel', () => 'View');
jest.mock('@/components/ui/ActionSheet', () => 'View');
jest.mock('@/components/PollCard', () => 'View');

describe('FeedItem', () => {
  it('falls back to the default visual config for feed item types not known by the mobile client', () => {
    const item = {
      id: 501,
      type: 'appreciation',
      title: 'Thanks for the lift',
      content: 'A kind note from the community feed.',
      image_url: null,
      user_id: 1,
      author_name: 'Alice Smith',
      author_avatar: null,
      is_liked: false,
      likes_count: 0,
      comments_count: 0,
      created_at: '2026-05-30T10:00:00Z',
      location: null,
      rating: null,
      start_date: null,
      job_type: null,
      commitment: null,
      submission_deadline: null,
      receiver: null,
    } as unknown as FeedItemType;

    const { getByText } = render(<FeedItem item={item} />);

    expect(getByText('Thanks for the lift')).toBeTruthy();
    expect(getByText('A kind note from the community feed.')).toBeTruthy();
  });
});
