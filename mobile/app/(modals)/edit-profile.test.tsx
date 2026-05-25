// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render } from '@testing-library/react-native';

// --- Mocks ---

jest.mock('expo-router', () => ({
  router: { push: jest.fn(), back: jest.fn() },
  useNavigation: () => ({
    setOptions: jest.fn(),
    addListener: jest.fn(() => jest.fn()),
    dispatch: jest.fn(),
  }),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => {
      const map: Record<string, string> = {
        'edit.title': 'Edit Profile',
        'edit.firstName': 'First Name',
        'edit.lastName': 'Last Name',
        'edit.aboutYou': 'About You',
        'edit.aboutPlaceholder': 'Tell us about yourself...',
        'edit.location': 'Location',
        'edit.locationPlaceholder': 'e.g. Dublin, Ireland',
        'edit.phoneOptional': 'Phone (Optional)',
        'edit.phonePlaceholder': '+1 555 123 4567',
        'edit.saveChanges': 'Save Changes',
        'edit.saved': 'Saved',
        'edit.savedMessage': 'Your profile has been updated.',
        'edit.saveError': 'Failed to save profile.',
        'edit.firstNameRequired': 'First name is required.',
        'edit.phoneInvalid': 'Enter a valid phone number.',
        'edit.unsavedTitle': 'Unsaved Changes',
        'edit.unsavedMessage': 'You have unsaved changes. Discard them?',
        'edit.discard': 'Discard',
        'common:buttons.cancel': 'Cancel',
        'common:buttons.done': 'Done',
        'common:errors.generic': 'Error',
      };
      return map[key] ?? key;
    },
    i18n: { language: 'en' },
  }),
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#ffffff',
    surface: '#f8f9fa',
    text: '#000000',
    textSecondary: '#666666',
    textMuted: '#999999',
    border: '#dddddd',
    borderSubtle: '#eeeeee',
    error: '#e53e3e',
  }),
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));

jest.mock('@/lib/hooks/useAuth', () => ({
  useAuth: () => ({
    user: {
      id: 1,
      first_name: 'Jane',
      last_name: 'Doe',
      bio: 'Community builder',
      location: 'Dublin',
      phone: '+353 87 123 4567',
    },
    refreshUser: jest.fn(),
  }),
}));

jest.mock('@/lib/storage', () => ({
  storage: { setJson: jest.fn().mockResolvedValue(undefined) },
}));

jest.mock('@/lib/constants', () => ({
  STORAGE_KEYS: { USER_DATA: 'user_data' },
}));

jest.mock('@/lib/api/profile', () => ({
  updateProfile: jest.fn().mockResolvedValue({ data: {} }),
}));

jest.mock('expo-haptics', () => ({
  notificationAsync: jest.fn().mockResolvedValue(undefined),
  NotificationFeedbackType: { Success: 'success', Error: 'error' },
}));

jest.mock('@/components/OfflineBanner', () => () => null);
jest.mock('@/components/ui/Input', () => {
  const { View, Text, TextInput: RNTextInput } = require('react-native');
  return function MockInput(props: { value?: string; placeholder?: string; onChangeText?: (t: string) => void; error?: string }) {
    return (
      <View>
        <RNTextInput
          value={props.value}
          placeholder={props.placeholder}
          onChangeText={props.onChangeText}
          testID={props.placeholder}
        />
        {props.error ? <Text>{props.error}</Text> : null}
      </View>
    );
  };
});
jest.mock('@/components/ui/Button', () => {
  const { TouchableOpacity, Text } = require('react-native');
  return function MockButton(props: { children?: React.ReactNode; onPress?: () => void; disabled?: boolean; accessibilityLabel?: string }) {
    return (
      <TouchableOpacity onPress={props.onPress} disabled={props.disabled} accessibilityLabel={props.accessibilityLabel}>
        <Text>{props.children}</Text>
      </TouchableOpacity>
    );
  };
});

// --- Tests ---

import EditProfileScreen from './edit-profile';

describe('EditProfileScreen', () => {
  it('renders without crashing', () => {
    const { toJSON } = render(<EditProfileScreen />);
    expect(toJSON()).toBeTruthy();
  });

  it('renders the field labels', () => {
    const { getByText } = render(<EditProfileScreen />);
    expect(getByText('First Name')).toBeTruthy();
    expect(getByText('Last Name')).toBeTruthy();
    expect(getByText('About You')).toBeTruthy();
    expect(getByText('Location')).toBeTruthy();
    expect(getByText('Phone (Optional)')).toBeTruthy();
  });

  it('renders the Save Changes button', () => {
    const { getByText } = render(<EditProfileScreen />);
    expect(getByText('Save Changes')).toBeTruthy();
  });

  it('pre-fills form fields with user data', () => {
    const { getByDisplayValue } = render(<EditProfileScreen />);
    expect(getByDisplayValue('Jane')).toBeTruthy();
    expect(getByDisplayValue('Doe')).toBeTruthy();
    expect(getByDisplayValue('Community builder')).toBeTruthy();
    expect(getByDisplayValue('Dublin')).toBeTruthy();
  });

  it('renders placeholders on inputs', () => {
    const { getByPlaceholderText } = render(<EditProfileScreen />);
    expect(getByPlaceholderText('Tell us about yourself...')).toBeTruthy();
    expect(getByPlaceholderText('e.g. Dublin, Ireland')).toBeTruthy();
  });
});
