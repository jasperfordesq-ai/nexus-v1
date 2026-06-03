// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { fireEvent, render, waitFor } from '@testing-library/react-native';

const mockGetIdentityStatus = jest.fn();
const mockCreateIdentityVerificationPayment = jest.fn();
const mockOpenURL = jest.fn();

jest.mock('react-native/Libraries/Linking/Linking', () => ({
  openURL: mockOpenURL,
}));

jest.mock('expo-linking', () => ({
  createURL: jest.fn((path: string) => `nexus://${path}`),
}));

jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, params?: Record<string, unknown>) => {
      const map: Record<string, string> = {
        'identity.page_title': 'Verify Identity',
        'identity.eyebrow': 'Trust and safety',
        'identity.fee_title': 'Verification fee',
        'identity.mobile_payment_body': `Identity verification costs ${params?.fee ?? ''}.`,
        'identity.fee_one_time_label': 'One-time verification fee',
        'identity.pay_button': `Pay ${params?.fee ?? ''} securely`,
        'identity.open_web_flow': 'Open web verification flow',
        'identity.refresh_status': 'Refresh status',
        'identity.error_title': 'Something went wrong',
        'identity.error_create_payment': 'Unable to create payment.',
        'identity.error_missing_publishable_key': 'Missing publishable key.',
        'identity.payment_success_title': 'Payment complete',
        'identity.payment_success_body': 'Payment has been paid.',
        'identity.verified_badge_label': 'ID verified',
        'common:buttons.back': 'Back',
        'common:verification.not_id_verified': 'Not ID verified',
      };
      return map[key] ?? key;
    },
  }),
}));

jest.mock('@expo/vector-icons', () => ({
  Ionicons: 'View',
}));

jest.mock('@/components/ui/AppTopBar', () => 'View');
jest.mock('@/components/ModalErrorBoundary', () => ({ children }: { children: React.ReactNode }) => <>{children}</>);

jest.mock('@/components/ui/AppToast', () => {
  const show = jest.fn();
  const hide = jest.fn();
  return { useAppToast: () => ({ show, hide, isToastVisible: false }) };
});

jest.mock('@/lib/hooks/useTenant', () => ({
  usePrimaryColor: () => '#006fee',
}));

jest.mock('@/lib/hooks/useTheme', () => ({
  useTheme: () => ({
    bg: '#000000',
    surface: '#111111',
    text: '#ffffff',
    textSecondary: '#d1d5db',
    textMuted: '#9ca3af',
    border: '#333333',
    borderSubtle: '#222222',
    error: '#ef4444',
    success: '#22c55e',
  }),
}));

jest.mock('@/lib/utils/color', () => ({
  withAlpha: (color: string) => color,
}));

jest.mock('@/lib/constants', () => ({
  APP_URL: 'https://app.project-nexus.ie',
}));

jest.mock('@/lib/api/verification', () => ({
  createIdentityVerificationPayment: (...args: unknown[]) => mockCreateIdentityVerificationPayment(...args),
  getIdentityStatus: (...args: unknown[]) => mockGetIdentityStatus(...args),
  saveIdentityDateOfBirth: jest.fn(),
  startIdentityVerification: jest.fn(),
}));

jest.mock('@/lib/payments/identityPayment', () => ({
  presentIdentityPayment: jest.fn().mockResolvedValue({ status: 'redirected' }),
}));

import VerifyIdentityScreen from './verify-identity';

describe('VerifyIdentityScreen', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockGetIdentityStatus.mockResolvedValue({
      data: {
        has_id_verified_badge: false,
        user_has_dob: true,
        fee_cents: 500,
        fee_currency: 'EUR',
        payment_completed: false,
        verification_status: null,
        latest_session: null,
      },
    });
    mockCreateIdentityVerificationPayment.mockResolvedValue({
      data: {
        client_secret: 'pi_test_secret',
        publishable_key: 'pk_test_123',
        fee_cents: 500,
        fee_currency: 'EUR',
      },
    });
  });

  it('renders the native payment step when a verification fee is required', async () => {
    const { getAllByText, getByText } = render(<VerifyIdentityScreen />);

    await waitFor(() => expect(getAllByText('Verification fee').length).toBeGreaterThan(0));
    expect(getByText('Pay €5.00 securely')).toBeTruthy();
    expect(getByText('One-time verification fee')).toBeTruthy();
  });

  it('uses the mobile payment endpoint from the payment button', async () => {
    const { getByTestId, getByText } = render(<VerifyIdentityScreen />);

    await waitFor(() => expect(getByText('Pay €5.00 securely')).toBeTruthy());
    fireEvent.press(getByTestId('identity-pay-button'));

    await waitFor(() => {
      expect(mockCreateIdentityVerificationPayment).toHaveBeenCalled();
    });
  });
});
