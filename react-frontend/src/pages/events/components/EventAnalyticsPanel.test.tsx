// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
  eventAnalyticsApi,
  type EventAnalyticsSummary,
} from '@/lib/event-analytics-api';
import { renderEventComponent } from '@/test/events-test-harness';
import { EventAnalyticsPanel } from './EventAnalyticsPanel';

const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts/ToastContext', () => ({ useToast: () => mockToast }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

function summary(): EventAnalyticsSummary {
  const rate = { numerator: 1, denominator: 2, basis_points: 5000, suppressed: false };
  const deliveries = { pending: 0, delivered: 8, suppressed: 1, failed: 0, dead_lettered: 0 };
  return {
    contract_version: 1,
    event_id: 1,
    event_title: 'Community summit',
    generated_at: '2030-01-01T12:00:00+00:00',
    privacy_threshold: 5,
    registration: {
      capacity_limit: 20,
      confirmed: 12,
      pending: 2,
      invited: 1,
      declined: 1,
      cancelled: 3,
      remaining: 8,
      completion_transitions: 12,
      cancellation_transitions: 3,
    },
    invitation: { available: true, issued: 6, accepted: 3, revoked: 1, expired: 1, conversion: rate },
    waitlist: {
      current_waiting: 2,
      current_offered: 1,
      joined: 6,
      offered: 4,
      accepted: 3,
      expired: 1,
      cancelled: 0,
      conversion: rate,
    },
    attendance: { checked_in: 2, checked_out: 1, attended: 5, no_show: 1, attendance_rate: rate },
    tickets: {
      available: true,
      redacted: true,
      confirmed_entitlements: null,
      confirmed_units: null,
      cancelled_units: null,
      confirmed_credit_value: null,
    },
    credits: {
      completed_claims: 0,
      completed_amount: '0.00',
      pending_claims: 0,
      failed_claims: 0,
      reversed_claims: 0,
    },
    communications: { ...deliveries, delivery_rate: rate, by_channel: { email: deliveries } },
    optional_funnel: {
      event_views: { value: null, suppressed: true },
      registration_starts: { value: 5, suppressed: false },
      start_to_registration_conversion: { ...rate, basis_points: null, suppressed: true },
    },
    safeguarding: { available: true, guardian_consents: { value: null, suppressed: true } },
  };
}

describe('EventAnalyticsPanel', () => {
  beforeEach(() => vi.clearAllMocks());

  it('renders exact operational totals and clearly marks privacy-suppressed metrics', async () => {
    vi.spyOn(eventAnalyticsApi, 'get').mockResolvedValue({ success: true, data: summary() });

    renderEventComponent(<EventAnalyticsPanel eventId={1} />);

    expect(await screen.findByRole('heading', { name: 'Event analytics' })).toBeInTheDocument();
    expect(screen.getByText('12')).toBeInTheDocument();
    expect(screen.getAllByText('Suppressed').length).toBeGreaterThanOrEqual(2);
    expect(screen.getByText('Restricted by your role')).toBeInTheDocument();
    expect(screen.getAllByRole('table')).toHaveLength(6);
  });

  it('offers a translated retry after a failed read', async () => {
    const get = vi.spyOn(eventAnalyticsApi, 'get')
      .mockResolvedValueOnce({ success: false, code: 'EVENT_ANALYTICS_UNAVAILABLE' })
      .mockResolvedValueOnce({ success: true, data: summary() });
    const user = userEvent.setup();

    renderEventComponent(<EventAnalyticsPanel eventId={1} />);
    await user.click(await screen.findByRole('button', { name: 'Try again' }));

    await waitFor(() => expect(get).toHaveBeenCalledTimes(2));
    expect(await screen.findByRole('heading', { name: 'Event analytics' })).toBeInTheDocument();
  });

  it('downloads the audited CSV and reports only translated outcomes', async () => {
    vi.spyOn(eventAnalyticsApi, 'get').mockResolvedValue({ success: true, data: summary() });
    const download = vi.spyOn(eventAnalyticsApi, 'download').mockResolvedValue(new Blob());
    const user = userEvent.setup();

    renderEventComponent(<EventAnalyticsPanel eventId={1} />);
    await user.click(await screen.findByRole('button', { name: 'Export CSV' }));

    await waitFor(() => expect(download).toHaveBeenCalledWith(1));
    expect(mockToast.success).toHaveBeenCalledWith('Analytics export prepared.');
  });
});
