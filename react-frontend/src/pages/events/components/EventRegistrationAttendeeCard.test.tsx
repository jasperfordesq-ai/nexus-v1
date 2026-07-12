// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  eventRegistrationApi,
  type AttendeeRegistrationState,
  type RegistrationSubmission,
} from '@/lib/event-registration-api';
import { renderEventRoute } from '@/test/events-test-harness';
import EventRegistrationAttendeeCard from './EventRegistrationAttendeeCard';

const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts/ToastContext', () => ({ useToast: () => mockToast }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

function submissionFixture(status: RegistrationSubmission['status'] = 'draft'): RegistrationSubmission {
  return {
    id: 71,
    registration_id: 61,
    form_version_id: 11,
    revision: status === 'draft' ? 1 : 2,
    status,
    attempt_number: 1,
    effective_slot: 1,
  };
}

function stateFixture(): AttendeeRegistrationState {
  return {
    settings: {
      id: 5,
      revision: 2,
      status: 'published',
      approval_mode: 'auto',
      form_state: 'published',
      published_form_version: 1,
      per_member_limit: 1,
      guests_enabled: true,
      max_guests_per_registration: 2,
      guest_retention_days: 30,
    },
    form: {
      id: 11,
      version_number: 1,
      revision: 2,
      status: 'published',
      name: 'Your event details',
      description: 'Tell the organiser what is needed.',
      questions: [
        {
          id: 21,
          stable_key: 'display_name',
          question_type: 'short_text',
          prompt: 'Preferred name',
          is_required: true,
          data_classification: 'internal',
          purpose: 'Event administration',
          retention_days: 30,
        },
        {
          id: 22,
          stable_key: 'meal_choice',
          question_type: 'single_choice',
          prompt: 'Meal choice',
          is_required: true,
          data_classification: 'internal',
          purpose: 'Catering',
          retention_days: 30,
          choice_options: ['Plant-based', 'Standard'],
        },
        {
          id: 23,
          stable_key: 'waiver',
          question_type: 'waiver',
          prompt: 'Safety waiver',
          displayed_text: 'I accept the safety terms.',
          displayed_text_version: 'v1',
          is_required: true,
          data_classification: 'confidential',
          purpose: 'Participation consent',
          retention_days: 30,
        },
      ],
    },
    registrations: [{
      id: 61,
      registration_state: 'confirmed',
      registration_version: 1,
      party_size: 1,
    }],
    submissions: [],
    guests: [],
    invitations: [{
      id: 91,
      campaign_id: 31,
      status: 'issued',
      invitation_version: 1,
      token_expires_at: '2030-01-01T10:00:00Z',
    }],
  };
}

describe('EventRegistrationAttendeeCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.spyOn(eventRegistrationApi, 'attendeeState').mockResolvedValue({
      success: true,
      data: stateFixture(),
    });
  });

  it('accepts a member-bound invitation through the canonical participation API', async () => {
    const user = userEvent.setup();
    const accept = vi.spyOn(eventRegistrationApi, 'acceptMemberInvitation').mockResolvedValue({
      success: true,
      data: { changed: true },
    });
    renderEventRoute(<EventRegistrationAttendeeCard eventId={42} />, {
      path: '/events/42',
      route: '/events/42',
    });

    await user.click(await screen.findByRole('button', { name: 'Accept invitation' }));
    await waitFor(() => expect(accept).toHaveBeenCalledWith(42, 91));
    expect(mockToast.success).toHaveBeenCalledWith('Invitation accepted.');
  });

  it('saves visible answers then submits the returned optimistic revision', async () => {
    const user = userEvent.setup();
    const save = vi.spyOn(eventRegistrationApi, 'saveSubmission').mockResolvedValue({
      success: true,
      data: { value: submissionFixture('draft'), changed: true, idempotent_replay: false },
    });
    const submit = vi.spyOn(eventRegistrationApi, 'submit').mockResolvedValue({
      success: true,
      data: { value: submissionFixture('submitted'), changed: true, idempotent_replay: false },
    });
    renderEventRoute(<EventRegistrationAttendeeCard eventId={42} />, {
      path: '/events/42',
      route: '/events/42',
    });

    await user.type(await screen.findByRole('textbox', { name: 'Preferred name' }), 'Alex');
    await user.click(screen.getByRole('radio', { name: 'Plant-based' }));
    await user.click(screen.getByRole('checkbox', { name: 'I accept the safety terms.' }));
    await user.click(screen.getByRole('button', { name: 'Save and submit answers' }));

    await waitFor(() => expect(save).toHaveBeenCalledWith(42, {
      registration_id: 61,
      form_version_id: 11,
      expected_revision: null,
      answers: {
        display_name: 'Alex',
        meal_choice: 'Plant-based',
        waiver: true,
      },
    }));
    expect(submit).toHaveBeenCalledWith(42, 71, 1);
    expect(mockToast.success).toHaveBeenCalledWith('Registration answers submitted.');
  });

  it('captures explicit guest privacy and notification consent', async () => {
    const user = userEvent.setup();
    const capture = vi.spyOn(eventRegistrationApi, 'captureGuest').mockResolvedValue({
      success: true,
      data: {
        party_size: 1,
        guest: {
          id: 81,
          registration_id: 61,
          guest_number: 1,
          revision: 1,
          status: 'captured',
          notification_consent: true,
        },
      },
    });
    renderEventRoute(<EventRegistrationAttendeeCard eventId={42} />, {
      path: '/events/42',
      route: '/events/42',
    });

    await user.type(await screen.findByRole('textbox', { name: 'Guest name' }), 'Sam Guest');
    await user.type(screen.getByRole('textbox', { name: 'Guest email address (optional)' }), 'sam@example.test');
    await user.click(screen.getByRole('checkbox', { name: "I confirm that I have the guest's permission to provide these details." }));
    await user.click(screen.getByRole('checkbox', { name: 'The guest agrees to receive messages about this event.' }));
    await user.click(screen.getByRole('button', { name: 'Add a guest' }));

    await waitFor(() => expect(capture).toHaveBeenCalledWith(42, 61, expect.objectContaining({
      display_name: 'Sam Guest',
      email: 'sam@example.test',
      consent_accepted: true,
      notification_consent: true,
      notification_consent_text: 'The guest agreed to receive necessary event updates at the email address provided.',
    })));
  });
});
