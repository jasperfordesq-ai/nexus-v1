// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Alert } from 'react-native';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockCreateGroup = jest.fn().mockResolvedValue({ data: { id: 484 } });
const mockReplace = jest.fn();

jest.mock('expo-router', () => ({
  router: { replace: (...args: unknown[]) => mockReplace(...args), back: jest.fn() },
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'create.eyebrow': 'New group',
        'create.title': 'Create Group',
        'create.subtitle': 'Start a community space.',
        'create.nameLabel': 'Group name',
        'create.namePlaceholder': 'Name your group',
        'create.descriptionLabel': 'Description',
        'create.descriptionPlaceholder': 'What is this group for?',
        'create.locationLabel': 'Location',
        'create.locationPlaceholder': 'Optional place or area',
        'create.visibilityLabel': 'Visibility',
        'create.federated': 'List in federation',
        'create.reviewTitle': 'Ready to publish?',
        'create.reviewSubtitle': 'Check first.',
        'create.submit': 'Create group',
        'create.validationTitle': 'Check group details',
        'create.validationRequired': 'Add a group name and description before continuing.',
        public: 'Public',
        private: 'Private',
        'common:back': 'Back',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({ usePrimaryColor: () => '#6366f1' }));
jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
  }),
}));
jest.mock('@/lib/api/groups', () => ({
  createGroup: (...args: unknown[]) => mockCreateGroup(...args),
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

import NewGroupRoute from './new-group';

describe('NewGroupRoute', () => {
  beforeEach(() => {
    mockCreateGroup.mockClear();
    mockReplace.mockClear();
    jest.spyOn(Alert, 'alert').mockImplementation(jest.fn());
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('requires a description before creating a group', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(Alert.alert).toHaveBeenCalledWith('Check group details', 'Add a group name and description before continuing.');
    });
    expect(mockCreateGroup).not.toHaveBeenCalled();
  });

  it('submits group visibility and federation settings', async () => {
    const { getByPlaceholderText, getByText } = render(<NewGroupRoute />);

    fireEvent.changeText(getByPlaceholderText('Name your group'), 'Repair club');
    fireEvent.changeText(getByPlaceholderText('What is this group for?'), 'A group for sharing repair skills and local mending sessions.');
    fireEvent.press(getByText('Private'));
    fireEvent.press(getByText('List in federation'));
    fireEvent.press(getByText('Create group'));

    await waitFor(() => {
      expect(mockCreateGroup).toHaveBeenCalledWith(expect.objectContaining({
        name: 'Repair club',
        description: 'A group for sharing repair skills and local mending sessions.',
        visibility: 'private',
        federated_visibility: 'listed',
      }));
    });
    expect(mockReplace).toHaveBeenCalledWith({ pathname: '/(modals)/group-detail', params: { id: '484' } });
  });
});
