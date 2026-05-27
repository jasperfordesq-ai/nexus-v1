// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockCreateEvent = jest.fn().mockResolvedValue({ data: { id: 6 } });
const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  router: { replace: (...args: unknown[]) => mockReplace(...args), back: jest.fn() },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'create.eyebrow': 'New event',
        'create.title': 'Create Event',
        'create.subtitle': 'Add a gathering.',
        'create.titleLabel': 'Title',
        'create.titlePlaceholder': 'What is happening?',
        'create.descriptionLabel': 'Description',
        'create.descriptionPlaceholder': 'Tell members what to expect.',
        'create.categoryLabel': 'Category',
        'create.startLabel': 'Start',
        'create.endLabel': 'End',
        'create.datePlaceholder': 'YYYY-MM-DDTHH:mm',
        'create.optionalDatePlaceholder': 'Optional end time',
        'create.locationLabel': 'Location',
        'create.locationPlaceholder': 'Venue, town, or meeting place',
        'create.remoteAttendance': 'Allow remote attendance',
        'create.videoUrlLabel': 'Video link',
        'create.videoUrlPlaceholder': 'https://...',
        'create.maxAttendeesLabel': 'Capacity',
        'create.maxAttendeesPlaceholder': 'Optional attendee limit',
        'create.federated': 'Share with federation',
        'create.reviewTitle': 'Ready to publish?',
        'create.reviewSubtitle': 'Review first.',
        'create.submit': 'Create event',
        'category.workshop': 'Workshop',
        'category.social': 'Social',
        'category.outdoor': 'Outdoor',
        'category.online': 'Online',
        'category.meeting': 'Meeting',
        'category.training': 'Training',
        'category.other': 'Other',
        'common:back': 'Back',
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
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
    error: '#e53e3e',
  }),
}));

jest.mock('@/lib/api/events', () => ({
  createEvent: (...args: unknown[]) => mockCreateEvent(...args),
}));

jest.mock('@/lib/haptics', () => ({
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  NotificationFeedbackType: { Success: 'success' },
}));

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/AppTopBar', () => 'View');

jest.mock('@/components/ui/FormActionFooter', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');
  return function MockFormActionFooter({ submitLabel, onSubmit }: { submitLabel: string; onSubmit: () => void }) {
    return (
      <View>
        <Pressable accessibilityRole="button" onPress={onSubmit}>
          <Text>{submitLabel}</Text>
        </Pressable>
      </View>
    );
  };
});

jest.mock('heroui-native', () => {
  const React = require('react');
  const { Pressable, Text, View } = require('react-native');
  const Button = ({ children, onPress }: { children: React.ReactNode; onPress?: () => void }) => (
    <Pressable onPress={onPress}>
      <View>{children}</View>
    </Pressable>
  );
  Button.Label = ({ children }: { children: React.ReactNode }) => <Text>{children}</Text>;
  const Card = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  Card.Body = ({ children }: { children: React.ReactNode }) => <View>{children}</View>;
  return { Button, Card, Text };
});

import NewEventRoute from './new-event';

describe('NewEventRoute', () => {
  beforeEach(() => {
    mockCreateEvent.mockClear();
    mockReplace.mockClear();
  });

  it('submits category and remote attendance fields using the event API contract', async () => {
    const { getByPlaceholderText, getByText } = render(<NewEventRoute />);

    fireEvent.changeText(getByPlaceholderText('What is happening?'), 'Repair workshop');
    fireEvent.changeText(getByPlaceholderText('Tell members what to expect.'), 'Bring something small to mend.');
    fireEvent.press(getByText('Workshop'));
    fireEvent.press(getByText('Allow remote attendance'));
    fireEvent.changeText(getByPlaceholderText('https://...'), 'https://meet.example/workshop');
    fireEvent.press(getByText('Create event'));

    await waitFor(() => {
      expect(mockCreateEvent).toHaveBeenCalledWith(expect.objectContaining({
        category_name: 'workshop',
        is_online: true,
        online_link: 'https://meet.example/workshop',
      }));
    });
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/event-detail', params: { id: '6' } });
  });
});
