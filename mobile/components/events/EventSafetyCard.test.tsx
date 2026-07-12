// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import EventSafetyCard from './EventSafetyCard';
import { DARK } from '@/lib/hooks/useTheme';
import { acknowledgeEventCode, requestEventGuardianConsent, withdrawEventCode } from '@/lib/api/eventSafety';

const mockSafetyFixture = require('../../../contracts/events/v2/event-safety.json');
const mockRefresh = jest.fn();
const mockShow = jest.fn();
const mockConfirm = jest.fn();

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/lib/hooks/useApi', () => ({
  useApi: () => ({ data: { data: mockSafetyFixture }, isLoading: false, error: null, refresh: mockRefresh }),
}));
jest.mock('@/components/ui/AppToast', () => ({ useAppToast: () => ({ show: mockShow }) }));
jest.mock('@/components/ui/useConfirm', () => ({
  useConfirm: () => ({ confirm: mockConfirm, confirmDialog: null }),
}));
jest.mock('@/lib/api/eventSafety', () => ({
  acknowledgeEventCode: jest.fn(async () => ({
    data: {
      ...mockSafetyFixture,
      eligibility: { ...mockSafetyFixture.eligibility, status: 'allow', reason_codes: [] },
      evidence: {
        ...mockSafetyFixture.evidence,
        code_of_conduct: { ...mockSafetyFixture.evidence.code_of_conduct, status: 'acknowledged', acknowledgement_id: 91 },
      },
      permissions: { ...mockSafetyFixture.permissions, acknowledge_code_of_conduct: false, withdraw_code_of_conduct: true },
    },
  })),
  requestEventGuardianConsent: jest.fn(),
  withdrawEventCode: jest.fn(),
  withdrawEventGuardianConsent: jest.fn(),
  getEventSafety: jest.fn(),
}));
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => ({
      'safety.attendee.title': 'Participation requirements',
      'safety.attendee.description': 'Complete safety steps.',
      'safety.attendee.attention_title': 'Action is required',
      'safety.eligibility.deny': 'Participation blocked',
      'safety.reasons.event_safety_code_of_conduct_acknowledgement_required': 'Acknowledge the current code.',
      'safety.code.title': 'Code of conduct',
      'safety.code.status.required': 'Acknowledgement required',
      'safety.code.status.acknowledged': 'Acknowledged',
      'safety.code.confirm_read': 'I have read the code.',
      'safety.actions.acknowledge': 'Acknowledge the code',
      'safety.actions.withdraw_acknowledgement': 'Withdraw acknowledgement',
      'safety.confirmations.withdraw_code_title': 'Withdraw acknowledgement?',
      'safety.confirmations.withdraw_code_body': 'Participation may be blocked.',
      'common:buttons.cancel': 'Cancel',
      'safety.attendee.success.acknowledge': 'Code acknowledged.',
    } as Record<string, string>)[key] ?? key,
    i18n: { language: 'en', resolvedLanguage: 'en' },
  }),
}));

describe('EventSafetyCard', () => {
  beforeEach(() => jest.clearAllMocks());

  it('shows the exact published code and submits its version/hash only after confirmation', async () => {
    const { getByTestId, getByText } = render(
      <EventSafetyCard eventId={101} primary="#6366f1" theme={DARK} />,
    );

    expect(getByTestId('event-safety-code-scroll')).toBeTruthy();
    expect(getByText('Treat everyone with respect and follow the event safety guidance.')).toBeTruthy();
    fireEvent.press(getByText('I have read the code.'));
    fireEvent.press(getByText('Acknowledge the code'));

    await waitFor(() => expect(acknowledgeEventCode).toHaveBeenCalledWith(
      101,
      'conduct-2026-07',
      '426bb49f31b7c15dfd91b62db039e1247633019cc53a970926f4bff91f549296',
      expect.stringContaining('event-safety-code-'),
    ));
    expect(requestEventGuardianConsent).not.toHaveBeenCalled();
  });

  it('requires branded confirmation before withdrawing an acknowledgement', async () => {
    const { getByText } = render(
      <EventSafetyCard eventId={101} primary="#6366f1" theme={DARK} />,
    );

    fireEvent.press(getByText('I have read the code.'));
    fireEvent.press(getByText('Acknowledge the code'));
    await waitFor(() => expect(getByText('Withdraw acknowledgement')).toBeTruthy());

    fireEvent.press(getByText('Withdraw acknowledgement'));
    expect(withdrawEventCode).not.toHaveBeenCalled();
    expect(mockConfirm).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Withdraw acknowledgement?',
      variant: 'danger',
    }));

    await act(async () => {
      await mockConfirm.mock.calls[0][0].onConfirm();
    });
    expect(withdrawEventCode).toHaveBeenCalledWith(
      101,
      91,
      expect.stringContaining('event-safety-code-withdraw-'),
    );
  });
});
