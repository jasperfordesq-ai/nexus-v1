// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render } from '@testing-library/react-native';

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
jest.mock('@/components/ui/ImageCarousel', () => {
  const React = require('react');
  const { Pressable, Text } = require('react-native');

  return function MockImageCarousel({ onImagePress }: { onImagePress?: (index: number) => void }) {
    return (
      <Pressable accessibilityRole="imagebutton" accessibilityLabel="carousel image" onPress={() => onImagePress?.(0)}>
        <Text>carousel image</Text>
      </Pressable>
    );
  };
});
jest.mock('@/components/ui/ActionSheet', () => 'View');
jest.mock('@/components/PollCard', () => 'View');
jest.mock('@/components/comments/CommentSheet', () => {
  const React = require('react');
  const { Text } = require('react-native');

  return function MockCommentSheet({
    visible,
    targetType,
    targetId,
  }: {
    visible: boolean;
    targetType: string;
    targetId: number;
  }) {
    return visible ? <Text>{`comments-${targetType}-${targetId}`}</Text> : null;
  };
});

describe('FeedItem', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

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

  it('opens the native comments sheet instead of navigating when Comment is pressed', () => {
    const item = {
      id: 501,
      type: 'listing',
      title: 'Help with garden planning',
      content: 'Could use some local advice.',
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
    } as FeedItemType;
    const { router } = require('expo-router');

    const { getByText } = render(<FeedItem item={item} />);
    fireEvent.press(getByText('comment'));

    expect(getByText('comments-listing-501')).toBeTruthy();
    expect(router.push).not.toHaveBeenCalled();
  });

  it('opens the native comments sheet when the visible comment count is pressed', () => {
    const item = {
      id: 502,
      type: 'listing',
      title: 'Garden help',
      content: 'There are comments on this listing.',
      image_url: null,
      user_id: 1,
      author_name: 'Alice Smith',
      author_avatar: null,
      is_liked: false,
      likes_count: 0,
      comments_count: 4,
      created_at: '2026-05-30T10:00:00Z',
      location: null,
      rating: null,
      start_date: null,
      job_type: null,
      commitment: null,
      submission_deadline: null,
      receiver: null,
    } as FeedItemType;
    const { router } = require('expo-router');

    const { getAllByText, getByText } = render(<FeedItem item={item} />);
    fireEvent.press(getAllByText('stats.comments')[0]);

    expect(getByText('comments-listing-502')).toBeTruthy();
    expect(router.push).not.toHaveBeenCalled();
  });

  it('opens feed item detail instead of the image viewer when a feed image is pressed', () => {
    const item = {
      id: 503,
      type: 'post',
      title: 'Garden photo',
      content: 'A post with an image.',
      image_url: 'https://example.test/photo.jpg',
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
    } as FeedItemType;
    const { router } = require('expo-router');

    const { getByLabelText } = render(<FeedItem item={item} />);
    fireEvent.press(getByLabelText('feedTypes.post'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/feed-item-detail',
      params: { id: '503', type: 'post' },
    });
  });

  it('opens feed item detail instead of the image viewer when carousel media is pressed', () => {
    const item = {
      id: 504,
      type: 'post',
      title: 'Carousel photo',
      content: 'A post with carousel media.',
      image_url: null,
      media: [{ id: 1, media_type: 'image', file_url: 'https://example.test/photo.jpg', display_order: 0 }],
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
    } as FeedItemType;
    const { router } = require('expo-router');

    const { getByLabelText } = render(<FeedItem item={item} />);
    fireEvent.press(getByLabelText('carousel image'));

    expect(router.push).toHaveBeenCalledWith({
      pathname: '/(modals)/feed-item-detail',
      params: { id: '504', type: 'post' },
    });
  });
});
