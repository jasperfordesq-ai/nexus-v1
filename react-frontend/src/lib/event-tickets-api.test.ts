// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';

const { apiMock, logErrorMock } = vi.hoisted(() => ({
  apiMock: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
  logErrorMock: vi.fn(),
}));
vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/lib/logger', () => ({ logError: logErrorMock }));

import {
  eventTicketCatalogueSchema,
  eventTicketsApi,
  type EventTicketCatalogue,
  type EventTicketTypePayload,
} from './event-tickets-api';

function catalogue(overrides: Partial<EventTicketCatalogue> = {}): EventTicketCatalogue {
  return {
    contract_version: 1,
    event_id: 42,
    currency: 'time_credit',
    payment_gateway: { free_supported: true, time_credit_supported: false, money_supported: false },
    permissions: { manage: false, reconcile: false, allocate_self: true },
    ticket_types: [{
      id: 9,
      version: 2,
      name: 'Community ticket',
      description: null,
      kind: 'free',
      unit_price_credits: '0.00',
      allocation_limit: 20,
      sales_opens_at: '2029-12-01T00:00:00+00:00',
      sales_closes_at: '2030-01-01T09:00:00+00:00',
      per_member_limit: 1,
      refund_cutoff_at: null,
      organizer_cancel_refundable: false,
      status: 'active',
      availability: {
        eligibility: { eligible: true, reasons: [] },
        allocation_remaining: 12,
        member_remaining: 1,
        sales_window_open: true,
        materialization_supported: true,
        gateway_status: 'not_required',
        attendance_reward_included: false,
        refund_policy: {
          cutoff_at: null,
          organizer_cancel_refundable: false,
          execution_status: 'not_applicable',
        },
      },
      eligibility_policy: null,
    }],
    own_entitlements: [],
    ...overrides,
  };
}

describe('eventTicketsApi', () => {
  beforeEach(() => vi.clearAllMocks());

  it('parses the explicit gateway and entitlement contract', async () => {
    apiMock.get.mockResolvedValue({ success: true, data: catalogue() });

    const response = await eventTicketsApi.get(42);

    expect(response.success).toBe(true);
    expect(response.data?.payment_gateway.time_credit_supported).toBe(false);
    expect(eventTicketCatalogueSchema.parse(response.data).ticket_types[0].id).toBe(9);
  });

  it('fails closed if money materialisation is advertised', async () => {
    apiMock.get.mockResolvedValue({
      success: true,
      data: {
        ...catalogue(),
        payment_gateway: {
          free_supported: true,
          time_credit_supported: false,
          money_supported: true,
        },
      },
    });

    const response = await eventTicketsApi.get(42);

    expect(response.success).toBe(false);
    expect(response.code).toBe('EVENT_TICKET_CONTRACT_DRIFT');
    expect(logErrorMock).toHaveBeenCalledTimes(1);
  });

  it('binds allocations to an idempotency key', async () => {
    apiMock.post.mockResolvedValue({
      success: true,
      data: {
        entitlement: {
          id: 8,
          ticket_type_id: 9,
          units: 1,
          kind: 'free',
          unit_price_credits: '0.00',
          total_price_credits: '0.00',
          status: 'confirmed',
          version: 1,
          confirmed_at: '2030-01-01T09:00:00+00:00',
          cancelled_at: null,
        },
        confirmed_units_after: 1,
        changed: true,
        idempotent_replay: false,
      },
    });

    const response = await eventTicketsApi.allocateSelf(42, 9, 1, 'ticket-allocation-1');

    expect(response.success).toBe(true);
    expect(apiMock.post).toHaveBeenCalledWith(
      '/v2/events/42/tickets/9/allocate',
      { units: 1 },
      { headers: { 'Idempotency-Key': 'ticket-allocation-1' } },
    );
  });

  it('uses the canonical mutation routes for types and entitlement cancellation', async () => {
    apiMock.post.mockResolvedValue({ success: false, code: 'EXPECTED_TEST_FAILURE' });
    apiMock.put.mockResolvedValue({ success: false, code: 'EXPECTED_TEST_FAILURE' });
    const payload: EventTicketTypePayload = {
      name: 'Community ticket',
      description: null,
      kind: 'free',
      unit_price_credits: '0.00',
      allocation_limit: 20,
      sales_opens_at: '2029-12-01T00:00:00+00:00',
      sales_closes_at: '2030-01-01T09:00:00+00:00',
      per_member_limit: 1,
      eligibility_policy: {
        approved_member_required: true,
        minimum_account_age_days: 0,
        required_group_ids: [],
      },
      refund_cutoff_at: null,
      organizer_cancel_refundable: false,
    };

    await eventTicketsApi.createType(42, payload, 'create-key');
    await eventTicketsApi.updateType(42, 9, 2, payload, 'update-key');
    await eventTicketsApi.transitionType(42, 9, 'activate', 2, null, 'activate-key');
    await eventTicketsApi.cancel(42, 8, 1, 'Plans changed', 'cancel-key');

    expect(apiMock.post).toHaveBeenNthCalledWith(
      1,
      '/v2/events/42/ticket-types',
      payload,
      { headers: { 'Idempotency-Key': 'create-key' } },
    );
    expect(apiMock.put).toHaveBeenCalledWith(
      '/v2/events/42/ticket-types/9',
      { ...payload, expected_version: 2 },
      { headers: { 'Idempotency-Key': 'update-key' } },
    );
    expect(apiMock.post).toHaveBeenNthCalledWith(
      2,
      '/v2/events/42/ticket-types/9/activate',
      { expected_version: 2, reason: null },
      { headers: { 'Idempotency-Key': 'activate-key' } },
    );
    expect(apiMock.post).toHaveBeenNthCalledWith(
      3,
      '/v2/events/42/ticket-entitlements/8/cancel',
      { expected_version: 1, reason: 'Plans changed' },
      { headers: { 'Idempotency-Key': 'cancel-key' } },
    );
  });
});
