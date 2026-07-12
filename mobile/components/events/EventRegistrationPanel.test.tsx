// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { act, fireEvent, render, waitFor } from '@testing-library/react-native';
import EventRegistrationPanel from './EventRegistrationPanel';
import { DARK } from '@/lib/hooks/useTheme';
import {
  acceptRegistrationInvitation,
  cancelRegistrationGuest,
  getAttendeeRegistrationProduct,
  saveRegistrationSubmission,
  submitRegistrationSubmission,
} from '@/lib/api/eventRegistration';

const mockConfirm = jest.fn();

jest.mock('@expo/vector-icons', () => ({ Ionicons: 'View' }));
jest.mock('@/components/ui/AppToast', () => ({ useAppToast: () => ({ show: jest.fn() }) }));
jest.mock('@/components/ui/useConfirm', () => ({
  useConfirm: () => ({ confirm: mockConfirm, confirmDialog: null }),
}));
jest.mock('@/lib/api/eventRegistration', () => ({
  getAttendeeRegistrationProduct: jest.fn(),
  getOwnRegistrationAnswers: jest.fn().mockResolvedValue({}),
  saveRegistrationSubmission: jest.fn(),
  submitRegistrationSubmission: jest.fn(),
  amendRegistrationSubmission: jest.fn(),
  acceptRegistrationInvitation: jest.fn(),
  captureRegistrationGuest: jest.fn(),
  cancelRegistrationGuest: jest.fn(),
}));
jest.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, options?: Record<string, unknown>) => ({
      title: 'Registration and guests',
      description: 'Complete event registration details.',
      'common.refresh': 'Refresh',
      'common.retry': 'Try again',
      'accessible.your_invitations': 'Your invitations',
      'accessible.accept_invitation': 'Accept invitation',
      'accessible.submit_answers': 'Submit answers',
      'accessible.add_guest': 'Add guest',
      'accessible.guest_name': 'Guest name',
      'accessible.guest_email': 'Guest email',
      'accessible.guest_phone': 'Guest phone',
      'accessible.privacy_consent_label': 'Guest consent confirmed',
      'accessible.notification_consent_label': 'Guest notification consent',
      'guests.cancel': 'Cancel guest',
      'guests.cancel_reason_default': 'Cancelled by the registration holder',
      'guests.cancel_confirm_title': 'Cancel this guest place?',
      'guests.cancel_confirm_body': `The place held for ${String(options?.name ?? '')} will be released.`,
      'guests.keep': 'Keep guest place',
      'submissions.save_draft': 'Save draft',
      'statuses.issued': 'Issued',
      'accessible.validation_error': 'Check the information you entered and try again.',
      'answers.validation.required': 'Answer this question.',
      'answers.validation.min_length': 'Enter at least 3 characters.',
    }[key] ?? key),
    i18n: { language: 'en', resolvedLanguage: 'en' },
  }),
}));

const state = {
  settings: {
    id: 1,
    revision: 2,
    status: 'published' as const,
    guests_enabled: true,
    max_guests_per_registration: 2,
    guest_retention_days: 30,
  },
  form: {
    id: 10,
    version_number: 1,
    revision: 1,
    status: 'published' as const,
    name: 'Attendee requirements',
    description: null,
    questions: [{
      id: 11,
      stable_key: 'access_needs',
      position: 1,
      question_type: 'accessibility' as const,
      prompt: 'What support would help?',
      help_text: null,
      is_required: true,
      data_classification: 'sensitive' as const,
      purpose: 'Prepare adjustments',
      retention_days: 30,
      choice_options: null,
      validation_rules: { min_length: 3, max_length: 500 },
      visibility_rules: null,
      displayed_text: null,
      displayed_text_version: null,
    }],
  },
  registrations: [{
    id: 20,
    registration_state: 'confirmed' as const,
    registration_version: 3,
    party_size: 1,
    state_changed_at: '2030-01-01T10:00:00Z',
    invited_at: null,
    pending_at: null,
    confirmed_at: '2030-01-01T10:00:00Z',
    declined_at: null,
    cancelled_at: null,
  }],
  submissions: [],
  guests: [],
  invitations: [{
    id: 40,
    campaign_id: 30,
    status: 'issued' as const,
    invitation_version: 1,
    token_expires_at: '2030-02-01T10:00:00Z',
  }],
};

beforeEach(() => {
  jest.clearAllMocks();
  (getAttendeeRegistrationProduct as jest.Mock).mockResolvedValue({ data: state });
});

describe('EventRegistrationPanel', () => {
  it('accepts a member invitation without exposing its bearer token', async () => {
    (acceptRegistrationInvitation as jest.Mock).mockResolvedValue(undefined);
    const view = render(<EventRegistrationPanel eventId={42} primary="#6366f1" theme={DARK} />);

    fireEvent.press(await view.findByText('Accept invitation'));

    await waitFor(() => expect(acceptRegistrationInvitation).toHaveBeenCalledWith(
      42,
      40,
      expect.stringContaining('event-invitation-accept'),
    ));
    expect(JSON.stringify((acceptRegistrationInvitation as jest.Mock).mock.calls)).not.toContain('token');
  });

  it('saves then submits only the attendee answer values through the versioned contract', async () => {
    const draft = {
      id: 50,
      registration_id: 20,
      form_version_id: 10,
      supersedes_submission_id: null,
      lineage_root_submission_id: null,
      attempt_number: 1,
      effective_slot: 1,
      revision: 1,
      status: 'draft' as const,
      submitted_at: null,
      withdrawn_at: null,
      anonymised_at: null,
      superseded_at: null,
      created_at: '2030-01-01T10:00:00Z',
      updated_at: '2030-01-01T10:00:00Z',
    };
    (saveRegistrationSubmission as jest.Mock).mockResolvedValue({
      data: { submission: draft, changed: true, idempotent_replay: false },
    });
    (submitRegistrationSubmission as jest.Mock).mockResolvedValue({
      data: { submission: { ...draft, status: 'submitted', revision: 2 }, changed: true, idempotent_replay: false },
    });
    const view = render(<EventRegistrationPanel eventId={42} primary="#6366f1" theme={DARK} />);

    fireEvent.changeText(await view.findByLabelText('What support would help?'), 'A quiet space');
    fireEvent.press(view.getByText('Submit answers'));

    await waitFor(() => expect(saveRegistrationSubmission).toHaveBeenCalledWith(
      42,
      {
        registrationId: 20,
        formVersionId: 10,
        expectedRevision: null,
        answers: { access_needs: 'A quiet space' },
      },
      expect.stringContaining('event-registration-save'),
    ));
    expect(submitRegistrationSubmission).toHaveBeenCalledWith(
      42,
      50,
      1,
      expect.stringContaining('event-registration-submit'),
    );
  });

  it('blocks submit until visible required and range-constrained answers are valid', async () => {
    const view = render(<EventRegistrationPanel eventId={42} primary="#6366f1" theme={DARK} />);

    fireEvent.press(await view.findByText('Submit answers'));
    expect(await view.findByText('Answer this question.')).toBeTruthy();
    expect(saveRegistrationSubmission).not.toHaveBeenCalled();

    fireEvent.changeText(view.getByLabelText('What support would help?'), 'No');
    fireEvent.press(view.getByText('Submit answers'));
    expect(await view.findByText('Enter at least 3 characters.')).toBeTruthy();
    expect(saveRegistrationSubmission).not.toHaveBeenCalled();
  });

  it('keeps a guest place until destructive cancellation is confirmed', async () => {
    const withGuest = {
      ...state,
      guests: [{
        id: 81,
        registration_id: 20,
        guest_number: 1,
        revision: 2,
        status: 'captured' as const,
        display_name: 'Sam Guest',
        notification_consent: false,
      }],
    };
    (getAttendeeRegistrationProduct as jest.Mock).mockResolvedValue({ data: withGuest });
    (cancelRegistrationGuest as jest.Mock).mockResolvedValue({
      data: { guest: { ...withGuest.guests[0], status: 'withdrawn', revision: 3 } },
    });
    const view = render(<EventRegistrationPanel eventId={42} primary="#6366f1" theme={DARK} />);

    fireEvent.press(await view.findByText('Cancel guest'));
    expect(cancelRegistrationGuest).not.toHaveBeenCalled();
    expect(mockConfirm).toHaveBeenCalledWith(expect.objectContaining({
      title: 'Cancel this guest place?',
      message: 'The place held for Sam Guest will be released.',
      variant: 'danger',
    }));

    await act(async () => {
      await mockConfirm.mock.calls[0][0].onConfirm();
    });
    expect(cancelRegistrationGuest).toHaveBeenCalledWith(
      42,
      81,
      2,
      'Cancelled by the registration holder',
    );
  });
});
