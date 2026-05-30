// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Platform } from 'react-native';

const mockPost = jest.fn();
const mockGetPermissionsAsync = jest.fn();
const mockRequestPermissionsAsync = jest.fn();
const mockGetExpoPushTokenAsync = jest.fn();
const mockSetNotificationChannelAsync = jest.fn();

jest.mock('expo-device', () => ({
  isDevice: true,
}));

jest.mock('expo-constants', () => ({
  expoConfig: { extra: { eas: { projectId: 'project-123' } } },
}));

jest.mock('expo-notifications', () => ({
  AndroidImportance: { MAX: 'max' },
  getExpoPushTokenAsync: (...args: unknown[]) => mockGetExpoPushTokenAsync(...args),
  getPermissionsAsync: (...args: unknown[]) => mockGetPermissionsAsync(...args),
  requestPermissionsAsync: (...args: unknown[]) => mockRequestPermissionsAsync(...args),
  setNotificationChannelAsync: (...args: unknown[]) => mockSetNotificationChannelAsync(...args),
  setNotificationHandler: jest.fn(),
}));

jest.mock('@sentry/react-native', () => ({
  captureException: jest.fn(),
  captureMessage: jest.fn(),
}));

jest.mock('@/lib/api/client', () => ({
  api: {
    post: (...args: unknown[]) => mockPost(...args),
  },
}));

import { registerForPushNotifications, unregisterPushNotifications } from './notifications';

describe('push notification registration', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetPermissionsAsync.mockResolvedValue({ status: 'granted' });
    mockRequestPermissionsAsync.mockResolvedValue({ status: 'granted' });
    mockGetExpoPushTokenAsync.mockResolvedValue({ data: 'ExponentPushToken[abc123]' });
    mockPost.mockResolvedValue(undefined);
  });

  it('registers Expo push tokens with an explicit token type for backend routing', async () => {
    await registerForPushNotifications();

    expect(mockGetExpoPushTokenAsync).toHaveBeenCalledWith({ projectId: 'project-123' });
    expect(mockPost).toHaveBeenCalledWith('/api/push/register-device', {
      token: 'ExponentPushToken[abc123]',
      token_type: 'expo',
      platform: Platform.OS === 'ios' ? 'ios' : 'android',
    });
  });

  it('unregisters the same Expo push token on logout', async () => {
    await unregisterPushNotifications();

    expect(mockPost).toHaveBeenCalledWith('/api/push/unregister-device', {
      token: 'ExponentPushToken[abc123]',
      token_type: 'expo',
    });
  });
});
