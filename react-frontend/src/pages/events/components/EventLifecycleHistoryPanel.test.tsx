// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  eventLifecycleHistoryApi,
  type EventLifecycleHistoryEntry,
  type EventLifecycleHistoryResponse,
} from '@/lib/event-lifecycle-history-api';
import { renderEventComponent } from '@/test/events-test-harness';
import { EventLifecycleHistoryPanel } from './EventLifecycleHistoryPanel';

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('react-i18next', () => {
  const labels: Record<string, string> = {
    title: 'Lifecycle history',
    description: 'An immutable record of publication and operational changes.',
    loading: 'Loading lifecycle history',
    load_error_title: 'Lifecycle history could not be loaded',
    load_error_description: 'Try again to retrieve the immutable history.',
    retry: 'Try again',
    empty_title: 'No lifecycle changes yet',
    empty_description: 'Changes will appear here after the event lifecycle is updated.',
    list_label: 'Event lifecycle changes',
    version: 'Version {{version}}',
    immutable: 'Immutable',
    publication_label: 'Publication',
    operational_label: 'Operational status',
    transition: '{{from}} to {{to}}',
    actor_label: 'Changed by',
    reason_label: 'Reason',
    unknown_actor: 'Member {{id}}',
    evidence_title: 'Operational evidence',
    notifications_suppressed: 'Duplicate notifications were suppressed.',
    load_more: 'Load more history',
    loading_more: 'Loading more history',
    load_more_error_title: 'More history could not be loaded',
    load_more_error_description: 'Try loading the next page again.',
    timestamp_unknown: 'Time not recorded',
    'states.publication.draft': 'Draft',
    'states.publication.pending_review': 'Pending review',
    'states.publication.published': 'Published',
    'states.publication.archived': 'Archived',
    'states.operational.scheduled': 'Scheduled',
    'states.operational.postponed': 'Postponed',
    'states.operational.cancelled': 'Cancelled',
    'states.operational.completed': 'Completed',
    'cascade.reminders_cancelled': '{{count}} reminder schedules cancelled',
    'series.template': 'Recurring template {{id}}',
    'series.occurrence': 'Occurrence of recurring template {{id}}',
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

function entry(id: number, version = id): EventLifecycleHistoryEntry {
  return {
    id,
    lifecycle_version: version,
    publication: { from: 'pending_review', to: 'published' },
    operational: { from: 'scheduled', to: 'scheduled' },
    reason: `Reason ${id}`,
    actor: { id: 8, display_name: `Manager ${id}` },
    evidence: {
      axes_changed: ['publication'],
      cascade: id === 3 ? { reminders_cancelled: 1 } : {},
      series: null,
      notifications_suppressed: false,
    },
    created_at: '2026-07-12T10:00:00+00:00',
    immutable: true,
  };
}

function response(
  data: EventLifecycleHistoryEntry[],
  nextCursor: string | null,
): EventLifecycleHistoryResponse {
  return {
    success: true,
    data,
    meta: { per_page: 20, next_cursor: nextCursor, has_more: nextCursor !== null },
  };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((next) => { resolve = next; });
  return { promise, resolve };
}

describe('EventLifecycleHistoryPanel', () => {
  beforeEach(() => vi.clearAllMocks());

  it('loads every cursor page and appends each immutable entry once', async () => {
    const list = vi.spyOn(eventLifecycleHistoryApi, 'list')
      .mockResolvedValueOnce(response([entry(3), entry(2)], 'next-page'))
      .mockResolvedValueOnce(response([entry(2), entry(1)], null));
    const user = userEvent.setup();

    renderEventComponent(<EventLifecycleHistoryPanel eventId={7} />);

    expect(await screen.findByRole('heading', { name: 'Version 3' })).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Load more history' }));

    expect(await screen.findByRole('heading', { name: 'Version 1' })).toBeInTheDocument();
    expect(screen.getAllByText('Immutable')).toHaveLength(3);
    expect(screen.queryByRole('button', { name: 'Load more history' })).not.toBeInTheDocument();
    expect(list).toHaveBeenNthCalledWith(2, 7, 'next-page', expect.objectContaining({
      signal: expect.any(AbortSignal),
    }));
  });

  it('ignores a stale response after the event changes', async () => {
    const stale = deferred<EventLifecycleHistoryResponse>();
    const current = deferred<EventLifecycleHistoryResponse>();
    vi.spyOn(eventLifecycleHistoryApi, 'list')
      .mockReturnValueOnce(stale.promise)
      .mockReturnValueOnce(current.promise);

    const view = renderEventComponent(<EventLifecycleHistoryPanel eventId={7} />);
    view.rerender(<EventLifecycleHistoryPanel eventId={8} />);

    await act(async () => current.resolve(response([entry(80, 8)], null)));
    expect(await screen.findByRole('heading', { name: 'Version 8' })).toBeInTheDocument();

    await act(async () => stale.resolve(response([entry(70, 7)], null)));
    await waitFor(() => {
      expect(screen.queryByRole('heading', { name: 'Version 7' })).not.toBeInTheDocument();
    });
  });

  it('offers retry after an error and then renders the translated empty state', async () => {
    const list = vi.spyOn(eventLifecycleHistoryApi, 'list')
      .mockResolvedValueOnce({ success: false, code: 'EVENT_LIFECYCLE_HISTORY_UNAVAILABLE' })
      .mockResolvedValueOnce(response([], null));
    const user = userEvent.setup();

    renderEventComponent(<EventLifecycleHistoryPanel eventId={7} />);

    expect(await screen.findByText('Lifecycle history could not be loaded')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Try again' }));

    expect(await screen.findByText('No lifecycle changes yet')).toBeInTheDocument();
    expect(list).toHaveBeenCalledTimes(2);
  });
});
