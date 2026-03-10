// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { render, fireEvent, waitFor } from '@testing-library/react-native';

// --- Mocks ---

const mockApiRegister = jest.fn();
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const mockExtractToken: jest.Mock<any, any> = jest.fn((r: { access_token?: string }) => r.access_token ?? '');
const mockStorageSet = jest.fn().mockResolvedValue(undefined);
const mockStorageSetJson = jest.fn().mockResolvedValue(undefined);

jest.mock('expo-router', () => {
  const React = require('react');
  const { Text } = require('react-native');
  // Define router inside the factory so it is always initialized when the factory runs
  const router = { push: jest.fn(), replace: jest.fn(), back: jest.fn() };
  return {
    useRouter: () => router,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    Link: ({ children, style }: { children: React.ReactNode; style?: any }) =>
      React.createElement(Text, { style }, children),
    router,
  };
});

// Access the mock router via requireMock so the same object instance is used in assertions
const mockRouter = jest.requireMock('expo-router').router as {
  push: jest.Mock; replace: jest.Mock; back: jest.Mock;
};

jest.mock('@/lib/api/auth', () => ({
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  register: (...args: any[]) => mockApiRegister(...args),
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  extractToken: (...args: any[]) => mockExtractToken(...args),
}));

jest.mock('@/lib/api/client', () => ({
  ApiResponseError: class ApiResponseError extends Error {
    status!: number;
    constructor(status: number, message: string) {
      super(message);
      this.status = status;
      this.name = 'ApiResponseError';
    }
  },
}));

jest.mock('@/lib/constants', () => ({
  STORAGE_KEYS: {
    AUTH_TOKEN: 'auth_token',
    REFRESH_TOKEN: 'refresh_token',
    USER_DATA: 'user_data',
  },
}));

jest.mock('@/lib/storage', () => ({
  storage: {
    set: (...args: unknown[]) => mockStorageSet(...args),
    setJson: (...args: unknown[]) => mockStorageSetJson(...args),
    get: jest.fn().mockResolvedValue(null),
    remove: jest.fn().mockResolvedValue(undefined),
    getJson: jest.fn().mockResolvedValue(null),
  },
}));

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#6366f1',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    surface: '#ffffff',
    text: '#000000',
    textSecondary: '#666666',
    border: '#dddddd',
    error: '#e53e3e',
    errorBg: '#fff5f5',
  }),
}));

// --- Tests ---

import RegisterScreen from './register';
import { ApiResponseError } from '@/lib/api/client';

const mockUser = {
  id: 1, first_name: 'Jane', last_name: 'Smith',
  email: 'jane@example.com', avatar_url: null,
  tenant_id: 1, role: 'member', is_admin: false, onboarding_completed: false,
};

const validAuthResponse = {
  success: true,
  access_token: 'tok_abc',
  refresh_token: 'ref_xyz',
  token_type: 'Bearer' as const,
  expires_in: 3600,
  user: mockUser,
};

// "Create account" appears as both the screen title and the submit button label.
// The button is always the last match — pick it with .at(-1).
function pressSubmit(getAllByText: ReturnType<typeof render>['getAllByText']) {
  fireEvent.press(getAllByText('Create account').at(-1)!);
}

describe('RegisterScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockStorageSet.mockResolvedValue(undefined);
    mockStorageSetJson.mockResolvedValue(undefined);
  });

  it('renders key form elements', () => {
    const { getAllByText, getByPlaceholderText } = render(<RegisterScreen />);
    // Both the heading and the button have this text; at least one must exist
    expect(getAllByText('Create account').length).toBeGreaterThanOrEqual(1);
    expect(getByPlaceholderText('Jane')).toBeTruthy();       // first name
    expect(getByPlaceholderText('you@example.com')).toBeTruthy(); // email
  });

  it('shows first name validation error when first name is empty', async () => {
    const { getAllByText, getByText, getByPlaceholderText } = render(<RegisterScreen />);

    fireEvent.changeText(getByPlaceholderText('you@example.com'), 'user@example.com');
    fireEvent.changeText(getByPlaceholderText('Min. 8 characters'), 'password123');
    fireEvent.changeText(getByPlaceholderText('Re-enter password'), 'password123');
    // Leave first name empty
    pressSubmit(getAllByText);

    await waitFor(() => expect(getByText('First name is required')).toBeTruthy());
    expect(mockApiRegister).not.toHaveBeenCalled();
  });

  it('shows email validation error for invalid email', async () => {
    const { getAllByText, getByText, getByPlaceholderText } = render(<RegisterScreen />);

    fireEvent.changeText(getByPlaceholderText('Jane'), 'Jane');
    fireEvent.changeText(getByPlaceholderText('you@example.com'), 'not-an-email');
    pressSubmit(getAllByText);

    await waitFor(() => expect(getByText('Please enter a valid email address')).toBeTruthy());
    expect(mockApiRegister).not.toHaveBeenCalled();
  });

  it('shows password-too-short error', async () => {
    const { getAllByText, getByText, getByPlaceholderText } = render(<RegisterScreen />);

    fireEvent.changeText(getByPlaceholderText('Jane'), 'Jane');
    fireEvent.changeText(getByPlaceholderText('you@example.com'), 'user@example.com');
    fireEvent.changeText(getByPlaceholderText('Min. 8 characters'), 'short');
    pressSubmit(getAllByText);

    await waitFor(() => expect(getByText('Password must be at least 8 characters')).toBeTruthy());
    expect(mockApiRegister).not.toHaveBeenCalled();
  });

  it('shows passwords-do-not-match error', async () => {
    const { getAllByText, getByText, getByPlaceholderText } = render(<RegisterScreen />);

    fireEvent.changeText(getByPlaceholderText('Jane'), 'Jane');
    fireEvent.changeText(getByPlaceholderText('you@example.com'), 'user@example.com');
    fireEvent.changeText(getByPlaceholderText('Min. 8 characters'), 'password123');
    fireEvent.changeText(getByPlaceholderText('Re-enter password'), 'different123');
    pressSubmit(getAllByText);

    await waitFor(() => expect(getByText('Passwords do not match')).toBeTruthy());
    expect(mockApiRegister).not.toHaveBeenCalled();
  });

  it('calls apiRegister and navigates to home on success', async () => {
    mockApiRegister.mockResolvedValue(validAuthResponse);
    mockExtractToken.mockReturnValue('tok_abc');

    const { getAllByText, getByPlaceholderText } = render(<RegisterScreen />);

    fireEvent.changeText(getByPlaceholderText('Jane'), 'Jane');
    fireEvent.changeText(getByPlaceholderText('Smith'), 'Smith');
    fireEvent.changeText(getByPlaceholderText('you@example.com'), 'jane@example.com');
    fireEvent.changeText(getByPlaceholderText('Min. 8 characters'), 'password123');
    fireEvent.changeText(getByPlaceholderText('Re-enter password'), 'password123');
    pressSubmit(getAllByText);

    await waitFor(() => expect(mockApiRegister).toHaveBeenCalledTimes(1));
    expect(mockApiRegister).toHaveBeenCalledWith(expect.objectContaining({
      first_name: 'Jane',
      last_name: 'Smith',
      email: 'jane@example.com',
      password: 'password123',
      password_confirmation: 'password123',
    }));
    await waitFor(() => expect(mockRouter.replace).toHaveBeenCalledWith('/(tabs)/home'));
  });

  it('shows API error message in the error banner', async () => {
    mockApiRegister.mockRejectedValue(new ApiResponseError(422, 'Email already in use'));
    const { getAllByText, getByPlaceholderText, findByText } = render(<RegisterScreen />);

    fireEvent.changeText(getByPlaceholderText('Jane'), 'Jane');
    fireEvent.changeText(getByPlaceholderText('you@example.com'), 'taken@example.com');
    fireEvent.changeText(getByPlaceholderText('Min. 8 characters'), 'password123');
    fireEvent.changeText(getByPlaceholderText('Re-enter password'), 'password123');
    pressSubmit(getAllByText);

    expect(await findByText('Email already in use')).toBeTruthy();
  });

  it('shows generic error for non-API failures', async () => {
    mockApiRegister.mockRejectedValue(new Error('Network failure'));
    const { getAllByText, getByPlaceholderText, findByText } = render(<RegisterScreen />);

    fireEvent.changeText(getByPlaceholderText('Jane'), 'Jane');
    fireEvent.changeText(getByPlaceholderText('you@example.com'), 'user@example.com');
    fireEvent.changeText(getByPlaceholderText('Min. 8 characters'), 'password123');
    fireEvent.changeText(getByPlaceholderText('Re-enter password'), 'password123');
    pressSubmit(getAllByText);

    expect(await findByText('Unable to register. Please try again.')).toBeTruthy();
  });
});
