// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { renderEventComponent } from '@/test/events-test-harness';
import {
  EventFederationStatusPanel,
  type EventFederationStatus,
} from './EventFederationStatusPanel';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));

vi.mock('@/lib/api', () => ({ api: { get: getMock } }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

function status(): EventFederationStatus {
  return {
    contract_version: 1,
    event_id: 42,
    federation_version: 7,
    visibility: 'listed',
    configured_partners: 2,
    recipient_partners: 1,
    health: 'degraded',
    counts: {
      pending: 0,
      retry: 0,
      processing: 0,
      delivered: 0,
      dead_letter: 1,
    },
    partners: [{
      partner_id: 9,
      partner_name: 'Neighbour Network',
      partner_status: 'active',
      events_enabled: true,
      action: 'upsert',
      delivery_status: 'dead_letter',
      attempts: 5,
      max_attempts: 5,
      aggregate_version: 7,
      calendar_version: 12,
      available_at: '2030-02-01T11:00:00Z',
      next_attempt_at: null,
      last_attempt_at: '2030-02-01T11:05:00Z',
      delivered_at: null,
      dead_lettered_at: '2030-02-01T11:05:00Z',
      error_code: 'REMOTE_HTTP_503',
    }],
    generated_at: '2030-02-01T11:06:00Z',
  };
}

describe('EventFederationStatusPanel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockResolvedValue({ success: true, data: status() });
  });

  it('renders payload-free delivery diagnostics from the versioned status contract', async () => {
    renderEventComponent(<EventFederationStatusPanel eventId={42} />);

    expect(await screen.findByRole('heading', { name: 'Federated event sharing' }))
      .toBeInTheDocument();
    expect(getMock).toHaveBeenCalledWith(
      '/v2/events/42/federation-status',
      expect.objectContaining({ signal: expect.any(AbortSignal) }),
    );
    expect(await screen.findByText('Neighbour Network')).toBeInTheDocument();
    expect(screen.getByText('Some deliveries need attention')).toBeInTheDocument();
    expect(screen.getByText('Diagnostic code: REMOTE_HTTP_503')).toBeInTheDocument();
    expect(screen.getByText('7')).toBeInTheDocument();
  });

  it('never renders unexpected roster, registration, meeting-link, claim, or raw-error fields', async () => {
    const unsafeResponse = {
      ...status(),
      attendee_roster: 'PRIVATE ROSTER VALUE',
      registration_answers: 'PRIVATE REGISTRATION ANSWER',
      meeting_link: 'https://meeting.example.test/private-token',
      claim_token: 'PRIVATE CLAIM TOKEN',
      partners: [{
        ...status().partners[0],
        error_code: 'Raw transport error containing PRIVATE ANSWER',
        raw_error: 'PRIVATE RAW ERROR',
        payload: { description: 'PRIVATE DESCRIPTION' },
      }],
    };
    getMock.mockResolvedValue({ success: true, data: unsafeResponse });

    renderEventComponent(<EventFederationStatusPanel eventId={42} />);
    expect(await screen.findByText('Diagnostic code unavailable')).toBeInTheDocument();

    expect(document.body).not.toHaveTextContent('PRIVATE ROSTER VALUE');
    expect(document.body).not.toHaveTextContent('PRIVATE REGISTRATION ANSWER');
    expect(document.body).not.toHaveTextContent('private-token');
    expect(document.body).not.toHaveTextContent('PRIVATE CLAIM TOKEN');
    expect(document.body).not.toHaveTextContent('PRIVATE RAW ERROR');
    expect(document.body).not.toHaveTextContent('PRIVATE DESCRIPTION');
    expect(document.body).not.toHaveTextContent('PRIVATE ANSWER');
  });

  it('offers a translated retry after a failed read', async () => {
    getMock
      .mockResolvedValueOnce({ success: false, code: 'FEDERATION_STATUS_UNAVAILABLE' })
      .mockResolvedValueOnce({ success: true, data: status() });
    const user = userEvent.setup();

    renderEventComponent(<EventFederationStatusPanel eventId={42} />);
    await user.click(await screen.findByRole('button', { name: 'Try again' }));

    await waitFor(() => expect(getMock).toHaveBeenCalledTimes(2));
    expect(await screen.findByText('Neighbour Network')).toBeInTheDocument();
  });
});
