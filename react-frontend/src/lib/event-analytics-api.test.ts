// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';

const { apiMock, logErrorMock } = vi.hoisted(() => ({
  apiMock: { get: vi.fn(), download: vi.fn() },
  logErrorMock: vi.fn(),
}));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/lib/logger', () => ({ logError: logErrorMock }));

import {
  eventAnalyticsApi,
  eventAnalyticsSummarySchema,
  type EventAnalyticsSummary,
} from './event-analytics-api';

function summary(overrides: Partial<EventAnalyticsSummary> = {}): EventAnalyticsSummary {
  const rate = { numerator: 1, denominator: 2, basis_points: 5000, suppressed: false };
  const deliveries = { pending: 0, delivered: 2, suppressed: 1, failed: 0, dead_lettered: 0 };
  return {
    contract_version: 1,
    event_id: 42,
    event_title: 'Community summit',
    generated_at: '2030-01-01T12:00:00+00:00',
    privacy_threshold: 5,
    registration: {
      capacity_limit: 20,
      confirmed: 10,
      pending: 1,
      invited: 2,
      declined: 1,
      cancelled: 1,
      remaining: 10,
      completion_transitions: 10,
      cancellation_transitions: 1,
    },
    invitation: { available: true, issued: 5, accepted: 2, revoked: 1, expired: 0, conversion: rate },
    waitlist: {
      current_waiting: 2,
      current_offered: 1,
      joined: 4,
      offered: 3,
      accepted: 2,
      expired: 1,
      cancelled: 0,
      conversion: rate,
    },
    attendance: { checked_in: 2, checked_out: 1, attended: 4, no_show: 1, attendance_rate: rate },
    tickets: {
      available: true,
      redacted: false,
      confirmed_entitlements: 2,
      confirmed_units: 3,
      cancelled_units: 1,
      confirmed_credit_value: '0.00',
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
      start_to_registration_conversion: { ...rate, suppressed: true, basis_points: null },
    },
    safeguarding: { available: true, guardian_consents: { value: null, suppressed: true } },
    ...overrides,
  };
}

describe('eventAnalyticsApi', () => {
  beforeEach(() => vi.clearAllMocks());

  it('accepts the strict identity-free analytics contract', async () => {
    apiMock.get.mockResolvedValue({ success: true, data: summary() });

    const response = await eventAnalyticsApi.get(42);

    expect(response.success).toBe(true);
    expect(response.data?.registration.confirmed).toBe(10);
    expect(eventAnalyticsSummarySchema.parse(response.data).event_id).toBe(42);
    expect(apiMock.get).toHaveBeenCalledWith('/v2/events/42/analytics', undefined);
  });

  it('fails closed when privacy suppression and values disagree', async () => {
    apiMock.get.mockResolvedValue({
      success: true,
      data: summary({
        optional_funnel: {
          event_views: { value: 4, suppressed: true },
          registration_starts: { value: 5, suppressed: false },
          start_to_registration_conversion: {
            numerator: 5,
            denominator: 5,
            basis_points: 10000,
            suppressed: false,
          },
        },
      }),
    });

    const response = await eventAnalyticsApi.get(42);

    expect(response.success).toBe(false);
    expect(response.code).toBe('EVENT_ANALYTICS_CONTRACT_DRIFT');
    expect(response.data).toBeUndefined();
    expect(logErrorMock).toHaveBeenCalledTimes(1);
  });

  it('downloads from the scoped organizer export endpoint', async () => {
    apiMock.download.mockResolvedValue(new Blob());

    await eventAnalyticsApi.download(42);

    expect(apiMock.download).toHaveBeenCalledWith('/v2/events/42/analytics/export.csv', {
      filename: 'event-42-analytics.csv',
    });
  });
});
