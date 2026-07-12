// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { axe } from 'vitest-axe';
import {
  eventsApi,
  type EventRecurrenceDefinitionCommit,
  type EventRecurrenceDefinitionHistory,
  type EventRecurrenceDefinitionPreview,
  type EventRecurrenceDefinitionSections,
} from '@/lib/events-api';
import { renderEventComponent } from '@/test/events-test-harness';
import { EventRecurrenceDefinitionBlueprintManager } from './EventRecurrenceDefinitionBlueprintManager';

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('react-i18next', () => {
  const labels: Record<string, string> = {
    title: 'Future occurrence setup',
    description: 'Choose definitions for new occurrences.',
    definition_only_title: 'Definitions only',
    definition_only_description: 'Participant records are never copied.',
    effective_from_label: 'Effective from recurrence identity',
    effective_from_help: 'Stable identity help',
    sections_title: 'Definitions to carry forward',
    sections_description: 'Every section is explicit.',
    'sections.agenda.label': 'Agenda',
    'sections.agenda.description': 'Agenda definitions',
    'sections.ticket_types.label': 'Ticket types',
    'sections.ticket_types.description': 'Ticket definitions',
    'sections.registration.label': 'Registration',
    'sections.registration.description': 'Registration definitions',
    'sections.safety.label': 'Safety requirements',
    'sections.safety.description': 'Safety definitions',
    'sections.staff.label': 'Staff assignments',
    'sections.staff.description': 'High-risk staff opt-in',
    section_not_permitted: 'Not permitted',
    no_sections_title: 'Select at least one section',
    no_sections_description: 'A preview is required.',
    preview_button: 'Preview future setup',
    previewing: 'Preparing preview',
    preview_title: 'Definition preview',
    preview_description: 'Review counts and conflicts.',
    preview_expires: 'Preview expires {{date}}',
    review_button: 'Review and confirm',
    refresh_preview: 'Refresh preview',
    conflicts_title: 'Resolve these conflicts first',
    'counts.none': 'No definitions found',
    'counts.sessions': 'Sessions',
    'counts.speakers': 'Speakers',
    'counts.resources': 'Resources',
    'counts.ticket_types': 'Ticket types',
    'counts.registration_settings': 'Registration settings',
    'counts.published_forms': 'Published forms',
    'counts.form_questions': 'Form questions',
    'counts.safety_requirements': 'Safety requirements',
    'counts.staff_assignments': 'Staff assignments',
    'errors.commit_conflict.title': 'Future setup was not saved',
    'errors.commit_conflict.description': 'Refresh and review again.',
    'errors.preview_expired.title': 'Preview expired',
    'errors.preview_expired.description': 'Refresh before confirming.',
    success_created_title: 'Future setup saved',
    success_created_description: 'Version {{version}} saved.',
    success_replay_title: 'Future setup was already saved',
    success_replay_description: 'Version {{version}} matched this retry.',
    history_title: 'Immutable version history',
    history_description: 'Saved definition versions.',
    history_loading: 'Loading future setup history',
    history_error_title: 'History could not be loaded',
    history_error_description: 'Try again.',
    history_empty_title: 'No future setup versions yet',
    history_empty_description: 'Versions appear after confirmation.',
    history_list_label: 'Future occurrence setup versions',
    history_version: 'Version {{version}}',
    history_sections: 'Included definitions',
    immutable: 'Immutable',
    history_load_more: 'Load more versions',
    history_loading_more: 'Loading more versions',
    load_more_error_title: 'More versions could not be loaded',
    load_more_error_description: 'Try the next page again.',
    retry: 'Try again',
    time_unknown: 'Time not recorded',
    confirm_title: 'Confirm future occurrence setup',
    confirm_scope_title: 'New occurrences only',
    confirm_scope_description: 'Existing occurrences are unchanged.',
    staff_risk_title: 'Staff propagation is selected',
    staff_risk_description: 'Active roles can grant future access.',
    confirm_ack: 'I confirm this future-only definition version',
    confirm_ack_description: 'I reviewed the selected definitions.',
    cancel: 'Cancel',
    commit_button: 'Save immutable version',
    committing: 'Saving version',
  };
  return {
    useTranslation: () => ({
      t: (key: string, values?: Record<string, unknown>) => {
        let value = labels[key] ?? key;
        Object.entries(values ?? {}).forEach(([name, replacement]) => {
          value = value.replace(`{{${name}}}`, String(replacement));
        });
        return value;
      },
      i18n: { language: 'en' },
    }),
    initReactI18next: { type: '3rdParty', init: () => undefined },
  };
});

const allAllowed: EventRecurrenceDefinitionSections = {
  agenda: true,
  ticket_types: true,
  registration: true,
  safety: true,
  staff: true,
};

function emptyHistory(): { success: true; data: EventRecurrenceDefinitionHistory } {
  return { success: true, data: { items: [], next_before_version: null } };
}

function preview(
  recurrenceId = '20990101T100000Z',
  sections: EventRecurrenceDefinitionSections = { ...allAllowed, staff: true },
): EventRecurrenceDefinitionPreview {
  return {
    preview_token: `signed-token-${recurrenceId}`,
    preview_expires_at: '2099-01-01T11:00:00+00:00',
    schema_version: 1,
    root_event_id: 90,
    source_event_id: 91,
    source_recurrence_id: recurrenceId,
    effective_from_recurrence_id: recurrenceId,
    selected_sections: sections,
    manifest_hash: 'a'.repeat(64),
    blueprint_set_version: 1,
    counts: { sessions: 2, staff_assignments: sections.staff ? 1 : 0 },
    conflicts: [],
    can_commit: true,
  };
}

function commitResult(idempotentReplay = false): EventRecurrenceDefinitionCommit {
  return {
    blueprint_id: 42,
    blueprint_version: 3,
    schema_version: 1,
    root_event_id: 90,
    source_event_id: 91,
    source_recurrence_id: '20990101T100000Z',
    effective_from_recurrence_id: '20990101T100000Z',
    selected_sections: { ...allAllowed, staff: true },
    manifest_hash: 'a'.repeat(64),
    counts: { sessions: 2, staff_assignments: 1 },
    idempotent_replay: idempotentReplay,
    created_at: '2098-12-01T10:00:00+00:00',
  };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((next) => { resolve = next; });
  return { promise, resolve };
}

describe('EventRecurrenceDefinitionBlueprintManager', () => {
  beforeEach(() => {
    vi.spyOn(eventsApi, 'recurrenceDefinitionHistory').mockResolvedValue(emptyHistory());
  });

  afterEach(() => vi.restoreAllMocks());

  it('requires explicit staff opt-in and confirmation, then reuses one idempotency key', async () => {
    const previewSpy = vi.spyOn(eventsApi, 'previewRecurrenceDefinitions')
      .mockResolvedValue({ success: true, data: preview() });
    const commitSpy = vi.spyOn(eventsApi, 'commitRecurrenceDefinitions')
      .mockResolvedValueOnce({ success: false, code: 'EVENT_RECURRENCE_DEFINITION_CONFLICT' })
      .mockResolvedValueOnce({ success: true, data: commitResult(true) });
    const user = userEvent.setup();

    renderEventComponent(
      <EventRecurrenceDefinitionBlueprintManager
        eventId={91}
        recurrenceId="20990101T100000Z"
        allowedSections={allAllowed}
        onUnavailable={vi.fn()}
      />,
    );

    expect(await screen.findByText('No future setup versions yet')).toBeInTheDocument();
    const staff = screen.getByRole('checkbox', { name: /^Staff assignments/ });
    expect(staff).not.toBeChecked();
    await user.click(staff);
    await user.click(screen.getByRole('button', { name: 'Preview future setup' }));

    expect(await screen.findByRole('heading', { name: 'Definition preview' })).toBeInTheDocument();
    expect(previewSpy).toHaveBeenCalledWith(
      91,
      '20990101T100000Z',
      expect.objectContaining({ staff: true }),
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
    await user.click(screen.getByRole('button', { name: 'Review and confirm' }));
    expect(await screen.findByRole('dialog')).toHaveTextContent('Staff propagation is selected');
    const save = screen.getByRole('button', { name: 'Save immutable version' });
    expect(save).toBeDisabled();
    await user.click(screen.getByRole('checkbox', { name: /^I confirm this future-only definition version/ }));
    await user.click(save);

    expect(await screen.findByText('Future setup was not saved')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Save immutable version' }));
    expect(await screen.findByText('Future setup was already saved')).toBeInTheDocument();
    expect(commitSpy).toHaveBeenCalledTimes(2);
    expect(commitSpy.mock.calls[0]?.[4]).toBeTruthy();
    expect(commitSpy.mock.calls[1]?.[4]).toBe(commitSpy.mock.calls[0]?.[4]);
  });

  it('paginates immutable history without rendering manifests or staff identities', async () => {
    const history = vi.mocked(eventsApi.recurrenceDefinitionHistory);
    history
      .mockResolvedValueOnce({
        success: true,
        data: {
          items: [{
            blueprint_id: 2,
            blueprint_version: 2,
            schema_version: 1,
            effective_from_recurrence_id: '20990108T100000Z',
            source_event_id: 91,
            source_recurrence_id: '20990101T100000Z',
            selected_sections: { ...allAllowed, staff: false },
            counts: { sessions: 2 },
            manifest_hash: 'b'.repeat(64),
            captured_by_user_id: 7,
            created_at: '2098-12-02T10:00:00+00:00',
            manifest: 'secret-definition',
            staff_user_ids: ['Private Person'],
          } as never],
          next_before_version: 2,
        },
      })
      .mockResolvedValueOnce({
        success: true,
        data: {
          items: [{
            blueprint_id: 1,
            blueprint_version: 1,
            schema_version: 1,
            effective_from_recurrence_id: '20990101T100000Z',
            source_event_id: 91,
            source_recurrence_id: '20990101T100000Z',
            selected_sections: { ...allAllowed, staff: false },
            counts: { sessions: 1 },
            manifest_hash: 'a'.repeat(64),
            captured_by_user_id: null,
            created_at: '2098-12-01T10:00:00+00:00',
          }],
          next_before_version: null,
        },
      });
    const user = userEvent.setup();

    renderEventComponent(
      <EventRecurrenceDefinitionBlueprintManager
        eventId={91}
        recurrenceId="20990101T100000Z"
        allowedSections={allAllowed}
        onUnavailable={vi.fn()}
      />,
    );

    expect(await screen.findByRole('heading', { name: 'Version 2' })).toBeInTheDocument();
    expect(screen.queryByText('secret-definition')).not.toBeInTheDocument();
    expect(screen.queryByText('Private Person')).not.toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Load more versions' }));
    expect(await screen.findByRole('heading', { name: 'Version 1' })).toBeInTheDocument();
    expect(screen.getAllByText('Immutable')).toHaveLength(2);
    expect(history).toHaveBeenNthCalledWith(
      2,
      91,
      10,
      2,
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
  });

  it('resets revoked section authority before previewing a different event', async () => {
    const previewSpy = vi.spyOn(eventsApi, 'previewRecurrenceDefinitions')
      .mockResolvedValue({
        success: true,
        data: preview('20990108T100000Z', { ...allAllowed, agenda: false, staff: false }),
      });
    const user = userEvent.setup();
    const view = renderEventComponent(
      <EventRecurrenceDefinitionBlueprintManager
        eventId={91}
        recurrenceId="20990101T100000Z"
        allowedSections={allAllowed}
        onUnavailable={vi.fn()}
      />,
    );
    await screen.findByText('No future setup versions yet');

    view.rerender(
      <EventRecurrenceDefinitionBlueprintManager
        eventId={92}
        recurrenceId="20990108T100000Z"
        allowedSections={{ ...allAllowed, agenda: false }}
        onUnavailable={vi.fn()}
      />,
    );
    await waitFor(() => expect(screen.getByRole('checkbox', { name: /^Agenda/ })).not.toBeChecked());
    expect(screen.getByRole('checkbox', { name: /^Agenda/ })).toBeDisabled();
    await user.click(screen.getByRole('button', { name: 'Preview future setup' }));

    await screen.findByRole('heading', { name: 'Definition preview' });
    expect(previewSpy).toHaveBeenCalledWith(
      92,
      '20990108T100000Z',
      expect.objectContaining({ agenda: false }),
      expect.any(Object),
    );
  });

  it('ignores a deferred preview after the event identity changes', async () => {
    const stale = deferred<Awaited<ReturnType<typeof eventsApi.previewRecurrenceDefinitions>>>();
    const previewSpy = vi.spyOn(eventsApi, 'previewRecurrenceDefinitions')
      .mockReturnValueOnce(stale.promise)
      .mockResolvedValueOnce({ success: true, data: preview('20990108T100000Z') });
    const user = userEvent.setup();
    const view = renderEventComponent(
      <EventRecurrenceDefinitionBlueprintManager
        eventId={91}
        recurrenceId="20990101T100000Z"
        allowedSections={allAllowed}
        onUnavailable={vi.fn()}
      />,
    );
    await screen.findByText('No future setup versions yet');
    await user.click(screen.getByRole('button', { name: 'Preview future setup' }));

    view.rerender(
      <EventRecurrenceDefinitionBlueprintManager
        eventId={92}
        recurrenceId="20990108T100000Z"
        allowedSections={allAllowed}
        onUnavailable={vi.fn()}
      />,
    );
    await act(async () => stale.resolve({ success: true, data: preview('20990101T100000Z') }));
    expect(screen.queryByRole('heading', { name: 'Definition preview' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Preview future setup' }));
    expect(await screen.findByRole('heading', { name: 'Definition preview' })).toBeInTheDocument();
    expect(previewSpy).toHaveBeenLastCalledWith(
      92,
      '20990108T100000Z',
      expect.any(Object),
      expect.any(Object),
    );
  });

  it('has no automated accessibility violations in the bounded manager surface', async () => {
    const view = renderEventComponent(
      <EventRecurrenceDefinitionBlueprintManager
        eventId={91}
        recurrenceId="20990101T100000Z"
        allowedSections={allAllowed}
        onUnavailable={vi.fn()}
      />,
    );
    await screen.findByText('No future setup versions yet');

    expect(await axe(view.container)).toHaveNoViolations();
  });
});
