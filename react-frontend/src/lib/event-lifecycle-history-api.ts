// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { z } from 'zod';
import { api, type ApiResponse, type RequestOptions } from '@/lib/api';
import { logError } from '@/lib/logger';

const publicationStateSchema = z.enum(['draft', 'pending_review', 'published', 'archived']);
const operationalStateSchema = z.enum(['scheduled', 'postponed', 'cancelled', 'completed']);

export const eventLifecycleHistoryEntrySchema = z.object({
  id: z.number().int().positive(),
  lifecycle_version: z.number().int().positive(),
  publication: z.object({
    from: publicationStateSchema,
    to: publicationStateSchema,
  }).strict(),
  operational: z.object({
    from: operationalStateSchema,
    to: operationalStateSchema,
  }).strict(),
  reason: z.string().nullable(),
  actor: z.object({
    id: z.number().int().positive(),
    display_name: z.string().nullable(),
  }).strict(),
  evidence: z.object({
    axes_changed: z.array(z.enum(['publication', 'operational'])),
    cascade: z.object({
      reminders_cancelled: z.number().int().nonnegative().optional(),
      waitlist_cancelled: z.number().int().nonnegative().optional(),
      registrations_cancelled: z.number().int().nonnegative().optional(),
    }).strict(),
    series: z.object({
      root_event_id: z.number().int().positive(),
      member_type: z.enum(['template', 'occurrence']),
    }).strict().nullable(),
    notifications_suppressed: z.boolean(),
  }).strict(),
  created_at: z.string().datetime({ offset: true }).nullable(),
  immutable: z.literal(true),
}).strict();

const paginationMetaSchema = z.object({
  per_page: z.number().int().min(1).max(100),
  next_cursor: z.string().nullable(),
  has_more: z.boolean(),
}).passthrough();

export type EventLifecycleHistoryEntry = z.infer<typeof eventLifecycleHistoryEntrySchema>;
export type EventLifecycleHistoryResponse = Omit<
  ApiResponse<EventLifecycleHistoryEntry[]>,
  'meta'
> & { meta?: z.infer<typeof paginationMetaSchema> };

function reportContractDrift(endpoint: string, error: z.ZodError): void {
  logError('Event lifecycle history contract drift', {
    endpoint,
    issues: error.issues.map((issue) => ({
      path: issue.path.map(String).join('.'),
      code: issue.code,
    })),
  });
}

function parseCollection(
  endpoint: string,
  response: ApiResponse<unknown>,
): EventLifecycleHistoryResponse {
  if (!response.success) return response as EventLifecycleHistoryResponse;

  const data = z.array(eventLifecycleHistoryEntrySchema).safeParse(response.data);
  if (!data.success) {
    reportContractDrift(endpoint, data.error);
    return {
      ...response,
      success: false,
      data: undefined,
      meta: undefined,
      code: 'EVENTS_CONTRACT_DRIFT',
    };
  }
  const meta = paginationMetaSchema.safeParse(response.meta);
  if (!meta.success) {
    reportContractDrift(`${endpoint}#meta`, meta.error);
    return {
      ...response,
      success: false,
      data: undefined,
      meta: undefined,
      code: 'EVENTS_CONTRACT_DRIFT',
    };
  }

  return { ...response, data: data.data, meta: meta.data };
}

function queryString(cursor?: string, perPage = 20): string {
  const query = new URLSearchParams({ per_page: String(perPage) });
  if (cursor) query.set('cursor', cursor);
  return `?${query.toString()}`;
}

export const eventLifecycleHistoryApi = {
  async list(
    eventId: number | string,
    cursor?: string,
    options?: RequestOptions,
  ): Promise<EventLifecycleHistoryResponse> {
    const endpoint = `/v2/events/${eventId}/lifecycle-history${queryString(cursor)}`;
    return parseCollection(endpoint, await api.get(endpoint, options));
  },
};
