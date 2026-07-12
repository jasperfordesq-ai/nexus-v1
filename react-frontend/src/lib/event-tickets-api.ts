// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { z } from 'zod';
import { api, type ApiResponse, type RequestOptions } from '@/lib/api';
import { logError } from '@/lib/logger';

const count = z.number().int().nonnegative();
const money = z.string().regex(/^\d+\.\d{2}$/);
const refundPolicySchema = z.object({
  cutoff_at: z.string().datetime({ offset: true }).nullable(),
  organizer_cancel_refundable: z.boolean(),
  execution_status: z.string().min(1),
}).strict();
const quoteSchema = z.object({
  ticket_type_id: z.number().int().positive(),
  kind: z.enum(['free', 'time_credit']),
  units: z.number().int().positive(),
  unit_price_credits: money,
  total_price_credits: money,
  status: z.enum(['draft', 'active', 'paused', 'archived']),
  eligibility: z.object({ eligible: z.boolean(), reasons: z.array(z.string().min(1)) }).strict(),
  allocation_remaining: count,
  member_remaining: count,
  sales_window_open: z.boolean(),
  materialization_supported: z.boolean(),
  gateway_status: z.string().min(1),
  attendance_reward_included: z.literal(false),
  refund_policy: refundPolicySchema,
}).strict();
const ticketAvailabilitySchema = quoteSchema.omit({
  ticket_type_id: true,
  kind: true,
  units: true,
  unit_price_credits: true,
  total_price_credits: true,
  status: true,
});
const eligibilityPolicySchema = z.object({
  approved_member_required: z.boolean(),
  minimum_account_age_days: count,
  required_group_ids: z.array(z.number().int().positive()),
}).strict();
const ticketTypeSchema = z.object({
  id: z.number().int().positive(),
  version: z.number().int().positive(),
  name: z.string().min(1),
  description: z.string().nullable(),
  kind: z.enum(['free', 'time_credit']),
  unit_price_credits: money,
  allocation_limit: count,
  sales_opens_at: z.string().datetime({ offset: true }).nullable(),
  sales_closes_at: z.string().datetime({ offset: true }).nullable(),
  per_member_limit: count,
  refund_cutoff_at: z.string().datetime({ offset: true }).nullable(),
  organizer_cancel_refundable: z.boolean(),
  status: z.enum(['draft', 'active', 'paused', 'archived']),
  availability: ticketAvailabilitySchema,
  eligibility_policy: eligibilityPolicySchema.nullable(),
}).strict();
const entitlementSchema = z.object({
  id: z.number().int().positive(),
  ticket_type_id: z.number().int().positive(),
  units: z.number().int().positive(),
  kind: z.enum(['free', 'time_credit']),
  unit_price_credits: money,
  total_price_credits: money,
  status: z.enum(['confirmed', 'cancelled']),
  version: z.number().int().positive(),
  confirmed_at: z.string().datetime({ offset: true }).nullable(),
  cancelled_at: z.string().datetime({ offset: true }).nullable(),
}).strict();

export const eventTicketCatalogueSchema = z.object({
  contract_version: z.literal(1),
  event_id: z.number().int().positive(),
  currency: z.literal('time_credit'),
  payment_gateway: z.object({
    free_supported: z.boolean(),
    time_credit_supported: z.boolean(),
    money_supported: z.literal(false),
  }).strict(),
  permissions: z.object({
    manage: z.boolean(),
    reconcile: z.boolean(),
    allocate_self: z.boolean(),
  }).strict(),
  ticket_types: z.array(ticketTypeSchema),
  own_entitlements: z.array(entitlementSchema),
}).strict();

const typeMutationSchema = z.object({
  ticket_type: ticketTypeSchema,
  changed: z.boolean(),
  idempotent_replay: z.boolean(),
}).strict();
const entitlementMutationSchema = z.object({
  entitlement: entitlementSchema,
  confirmed_units_after: count,
  changed: z.boolean(),
  idempotent_replay: z.boolean(),
}).strict();
const reconciliationSchema = z.object({
  event_id: z.number().int().positive(),
  read_only: z.literal(true),
  ticket_types: z.array(z.object({
    ticket_type_id: z.number().int().positive(),
    kind: z.enum(['free', 'time_credit']),
    status: z.enum(['draft', 'active', 'paused', 'archived']),
    allocation_limit: count,
    confirmed_units: count,
    cancelled_units: count,
    confirmed_entitlements: count,
    cancelled_entitlements: count,
    registration_mismatches: count,
    price_snapshot_violations: count,
    inventory_delta: z.number().int(),
    latest_inventory_after: count,
    allocation_overrun: z.boolean(),
    inventory_mismatch: z.boolean(),
  }).strict()),
}).strict();

export type EventTicketCatalogue = z.infer<typeof eventTicketCatalogueSchema>;
export type EventTicketType = z.infer<typeof ticketTypeSchema>;
export type EventTicketEntitlement = z.infer<typeof entitlementSchema>;
export type EventTicketQuote = z.infer<typeof quoteSchema>;
export type EventTicketReconciliation = z.infer<typeof reconciliationSchema>;

export interface EventTicketTypePayload {
  name: string;
  description: string | null;
  kind: 'free' | 'time_credit';
  unit_price_credits: string;
  allocation_limit: number;
  sales_opens_at: string;
  sales_closes_at: string;
  per_member_limit: number;
  eligibility_policy: {
    approved_member_required: boolean;
    minimum_account_age_days: number;
    required_group_ids: number[];
  };
  refund_cutoff_at: string | null;
  organizer_cancel_refundable: boolean;
}

function parse<T>(endpoint: string, response: ApiResponse<unknown>, schema: z.ZodType<T>): ApiResponse<T> {
  if (!response.success || response.data === undefined) return response as ApiResponse<T>;
  const parsed = schema.safeParse(response.data);
  if (parsed.success) return { ...response, data: parsed.data };
  logError('Events ticket contract validation failed', {
    endpoint,
    issues: parsed.error.issues.map((issue) => ({ path: issue.path.join('.'), code: issue.code })),
  });
  return { ...response, success: false, data: undefined, code: 'EVENT_TICKET_CONTRACT_DRIFT' };
}

function idempotency(key: string): RequestOptions {
  return { headers: { 'Idempotency-Key': key } };
}

export const eventTicketsApi = {
  async get(eventId: number, options?: RequestOptions): Promise<ApiResponse<EventTicketCatalogue>> {
    const endpoint = `/v2/events/${eventId}/tickets`;
    return parse(endpoint, await api.get(endpoint, options), eventTicketCatalogueSchema);
  },

  async quote(eventId: number, ticketTypeId: number, units: number): Promise<ApiResponse<EventTicketQuote>> {
    const endpoint = `/v2/events/${eventId}/tickets/${ticketTypeId}/quote`;
    return parse(endpoint, await api.post(endpoint, { units }), quoteSchema);
  },

  async createType(eventId: number, payload: EventTicketTypePayload, key: string) {
    const endpoint = `/v2/events/${eventId}/ticket-types`;
    return parse(endpoint, await api.post(endpoint, payload, idempotency(key)), typeMutationSchema);
  },

  async updateType(
    eventId: number,
    ticketTypeId: number,
    expectedVersion: number,
    payload: EventTicketTypePayload,
    key: string,
  ) {
    const endpoint = `/v2/events/${eventId}/ticket-types/${ticketTypeId}`;
    return parse(
      endpoint,
      await api.put(endpoint, { ...payload, expected_version: expectedVersion }, idempotency(key)),
      typeMutationSchema,
    );
  },

  async transitionType(
    eventId: number,
    ticketTypeId: number,
    action: 'activate' | 'pause' | 'archive',
    expectedVersion: number,
    reason: string | null,
    key: string,
  ) {
    const endpoint = `/v2/events/${eventId}/ticket-types/${ticketTypeId}/${action}`;
    return parse(
      endpoint,
      await api.post(endpoint, { expected_version: expectedVersion, reason }, idempotency(key)),
      typeMutationSchema,
    );
  },

  async allocateSelf(eventId: number, ticketTypeId: number, units: number, key: string) {
    const endpoint = `/v2/events/${eventId}/tickets/${ticketTypeId}/allocate`;
    return parse(
      endpoint,
      await api.post(endpoint, { units }, idempotency(key)),
      entitlementMutationSchema,
    );
  },

  async cancel(
    eventId: number,
    entitlementId: number,
    expectedVersion: number,
    reason: string,
    key: string,
  ) {
    const endpoint = `/v2/events/${eventId}/ticket-entitlements/${entitlementId}/cancel`;
    return parse(
      endpoint,
      await api.post(endpoint, { expected_version: expectedVersion, reason }, idempotency(key)),
      entitlementMutationSchema,
    );
  },

  async reconcile(eventId: number): Promise<ApiResponse<EventTicketReconciliation>> {
    const endpoint = `/v2/events/${eventId}/tickets/reconciliation`;
    return parse(endpoint, await api.get(endpoint), reconciliationSchema);
  },
};
