// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { z } from 'zod';
import { api, type ApiResponse, type RequestOptions } from '@/lib/api';
import { logError } from '@/lib/logger';

const nullableString = z.string().nullable();

export const eventTemplateConfigurationSchema = z.object({
  title: z.string(),
  description: z.string(),
  category_id: z.number().int().positive().nullable(),
  group_id: z.number().int().positive().nullable(),
  location: nullableString,
  latitude: z.number().min(-90).max(90).nullable(),
  longitude: z.number().min(-180).max(180).nullable(),
  max_attendees: z.number().int().positive().nullable(),
  is_online: z.boolean(),
  allow_remote_attendance: z.boolean(),
  timezone: z.string().min(1),
  all_day: z.boolean(),
  federated_visibility: z.enum(['none', 'listed', 'joinable']),
}).strict();

export const eventTemplateVersionSchema = z.object({
  id: z.number().int().positive(),
  number: z.number().int().positive(),
  schema_version: z.number().int().positive(),
  configuration: eventTemplateConfigurationSchema,
  snapshot: z.object({
    hash: z.string().length(64),
    source_lifecycle_version: z.number().int().nonnegative(),
    source_calendar_sequence: z.number().int().nonnegative(),
    source_updated_at: nullableString,
    immutable: z.literal(true),
  }).strict(),
  copied_fields: z.array(z.string()),
  skipped_fields: z.array(z.string()),
  captured_at: nullableString,
}).strict();

export const eventTemplateSchema = z.object({
  id: z.number().int().positive(),
  public_id: z.string().uuid(),
  status: z.enum(['active', 'archived']),
  current_version: z.number().int().positive(),
  source_event: z.object({
    id: z.number().int().positive(),
    title: z.string(),
    updated_at: nullableString,
  }).strict(),
  version: eventTemplateVersionSchema,
  usage: z.object({
    materialization_count: z.number().int().nonnegative(),
    audit_entry_count: z.number().int().nonnegative(),
  }).strict(),
  archive: z.object({
    reason: nullableString,
    archived_at: nullableString,
  }).strict(),
  capabilities: z.object({
    view: z.boolean(),
    revise: z.boolean(),
    archive: z.boolean(),
    materialize: z.boolean(),
    view_audit: z.boolean(),
  }).strict(),
  created_at: nullableString,
  updated_at: nullableString,
}).strict();

const checklistSchema = z.array(z.object({
  code: z.string().min(1),
  passed: z.boolean(),
}).strict());

export const eventTemplateCapturePreviewSchema = z.object({
  kind: z.literal('capture'),
  schema_version: z.number().int().positive(),
  source_event_id: z.number().int().positive(),
  source_lifecycle_version: z.number().int().nonnegative(),
  source_calendar_sequence: z.number().int().nonnegative(),
  configuration: eventTemplateConfigurationSchema,
  snapshot_hash: z.string().length(64),
  copied_fields: z.array(z.string()),
  skipped_fields: z.array(z.string()),
  checklist: checklistSchema,
}).strict();

export const eventTemplateMaterializationPreviewSchema = z.object({
  kind: z.literal('materialization'),
  template_id: z.number().int().positive(),
  template_version_id: z.number().int().positive(),
  template_version: z.number().int().positive(),
  source_event_id: z.number().int().positive(),
  schema_version: z.number().int().positive(),
  template_snapshot_hash: z.string().length(64),
  effective_snapshot_hash: z.string().length(64),
  configuration: eventTemplateConfigurationSchema,
  schedule: z.object({
    start_at: z.string().datetime({ offset: true }),
    end_at: z.string().datetime({ offset: true }).nullable(),
    timezone: z.string().min(1),
    all_day: z.boolean(),
  }).strict(),
  copied_fields: z.array(z.string()),
  skipped_fields: z.array(z.string()),
  override_fields: z.array(z.string()),
  checklist: checklistSchema,
  will_create: z.object({
    publication_status: z.literal('draft'),
    operational_status: z.literal('scheduled'),
    recurring: z.literal(false),
    publish: z.literal(false),
    register: z.literal(false),
    notify: z.literal(false),
    federate: z.literal(false),
  }).strict(),
}).strict();

const eventTemplateMutationSchema = z.object({
  template: eventTemplateSchema,
  changed: z.boolean(),
  idempotent_replay: z.boolean(),
}).strict();

export const eventTemplateMaterializationSchema = z.object({
  created_event: z.object({
    id: z.number().int().positive(),
    title: z.string(),
    publication_status: z.literal('draft'),
    operational_status: z.literal('scheduled'),
    edit_path: z.string().startsWith('/events/'),
  }).strict(),
  provenance: z.object({
    id: z.number().int().positive(),
    template_id: z.number().int().positive(),
    template_version: z.number().int().positive(),
    source_event_id: z.number().int().positive(),
    schema_version: z.number().int().positive(),
    schedule: z.object({
      start_at: z.string().datetime({ offset: true }).nullable(),
      end_at: z.string().datetime({ offset: true }).nullable(),
      timezone: z.string().min(1),
      all_day: z.boolean(),
    }).strict(),
    override_fields: z.array(z.string()),
    federation_normalized: z.literal(true),
    created_at: nullableString,
    immutable: z.literal(true),
  }).strict(),
  changed: z.boolean(),
  idempotent_replay: z.boolean(),
  workflow: z.object({
    fresh_draft: z.literal(true),
    published: z.literal(false),
    registrations_copied: z.literal(false),
    notifications_sent: z.literal(false),
    federated: z.literal(false),
  }).strict(),
}).strict();

export const eventTemplateAuditSchema = z.object({
  id: z.number().int().positive(),
  action: z.enum(['captured', 'revised', 'archived', 'materialized']),
  template_version: z.number().int().positive(),
  source_event_id: z.number().int().positive(),
  materialized_event_id: z.number().int().positive().nullable(),
  evidence: z.record(z.string(), z.unknown()),
  created_at: nullableString,
  immutable: z.literal(true),
}).strict();

const paginationMetaSchema = z.object({
  per_page: z.number().int().positive(),
  next_cursor: nullableString,
  has_more: z.boolean(),
}).passthrough();

export type EventTemplate = z.infer<typeof eventTemplateSchema>;
export type EventTemplateCapturePreview = z.infer<typeof eventTemplateCapturePreviewSchema>;
export type EventTemplateMaterializationPreview = z.infer<typeof eventTemplateMaterializationPreviewSchema>;
export type EventTemplateMaterialization = z.infer<typeof eventTemplateMaterializationSchema>;
export type EventTemplateAudit = z.infer<typeof eventTemplateAuditSchema>;

export type EventTemplateOverrides = Partial<Pick<
  EventTemplate['version']['configuration'],
  | 'title'
  | 'description'
  | 'category_id'
  | 'group_id'
  | 'location'
  | 'latitude'
  | 'longitude'
  | 'max_attendees'
  | 'is_online'
  | 'allow_remote_attendance'
  | 'timezone'
  | 'all_day'
>>;

export interface EventTemplateMaterializationInput {
  template_version: number;
  start_time: string;
  end_time: string | null;
  overrides: EventTemplateOverrides;
}

export type EventTemplateListResponse = Omit<ApiResponse<EventTemplate[]>, 'meta'> & {
  meta?: z.infer<typeof paginationMetaSchema>;
};

export type EventTemplateHistoryResponse = Omit<ApiResponse<EventTemplateAudit[]>, 'meta'> & {
  meta?: z.infer<typeof paginationMetaSchema>;
};

function optionsWithIdempotency(key: string): RequestOptions {
  return { headers: { 'Idempotency-Key': key } };
}

function reportContractDrift(endpoint: string, error: z.ZodError): void {
  logError('Event templates contract drift', {
    endpoint,
    issues: error.issues.map((issue) => ({
      path: issue.path.map(String).join('.'),
      code: issue.code,
    })),
  });
}

function parseResponse<T>(
  endpoint: string,
  response: ApiResponse<unknown>,
  schema: z.ZodType<T>,
): ApiResponse<T> {
  if (!response.success) return response as ApiResponse<T>;
  const parsed = schema.safeParse(response.data);
  if (parsed.success) return { ...response, data: parsed.data };

  reportContractDrift(endpoint, parsed.error);
  return { ...response, success: false, data: undefined, code: 'EVENTS_CONTRACT_DRIFT' };
}

function parseCollection<T>(
  endpoint: string,
  response: ApiResponse<unknown>,
  itemSchema: z.ZodType<T>,
): Omit<ApiResponse<T[]>, 'meta'> & { meta?: z.infer<typeof paginationMetaSchema> } {
  const parsed = parseResponse(endpoint, response, z.array(itemSchema));
  if (!parsed.success) return { ...parsed, meta: undefined };
  const meta = paginationMetaSchema.safeParse(response.meta);
  if (meta.success) return { ...parsed, meta: meta.data };

  reportContractDrift(`${endpoint}#meta`, meta.error);
  return { ...parsed, success: false, data: undefined, code: 'EVENTS_CONTRACT_DRIFT', meta: undefined };
}

function queryString(params: Record<string, string | number | undefined>): string {
  const query = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') query.set(key, String(value));
  });
  const encoded = query.toString();
  return encoded ? `?${encoded}` : '';
}

export const eventTemplatesApi = {
  async list(params: {
    status?: 'active' | 'archived' | 'all';
    source_event_id?: number;
    search?: string;
    cursor?: string;
    per_page?: number;
  } = {}): Promise<EventTemplateListResponse> {
    const endpoint = `/v2/event-templates${queryString(params)}`;
    return parseCollection(endpoint, await api.get(endpoint), eventTemplateSchema);
  },

  async get(templateId: number): Promise<ApiResponse<EventTemplate>> {
    const endpoint = `/v2/event-templates/${templateId}`;
    return parseResponse(endpoint, await api.get(endpoint), eventTemplateSchema);
  },

  async history(templateId: number, cursor?: string): Promise<EventTemplateHistoryResponse> {
    const endpoint = `/v2/event-templates/${templateId}/history${queryString({ cursor, per_page: 50 })}`;
    return parseCollection(endpoint, await api.get(endpoint), eventTemplateAuditSchema);
  },

  async previewCapture(sourceEventId: number): Promise<ApiResponse<EventTemplateCapturePreview>> {
    const endpoint = `/v2/events/${sourceEventId}/template-preview`;
    return parseResponse(
      endpoint,
      await api.post(endpoint, {}),
      eventTemplateCapturePreviewSchema,
    );
  },

  async capture(sourceEventId: number, idempotencyKey: string) {
    const endpoint = `/v2/events/${sourceEventId}/templates`;
    return parseResponse(
      endpoint,
      await api.post(endpoint, {}, optionsWithIdempotency(idempotencyKey)),
      eventTemplateMutationSchema,
    );
  },

  async revise(templateId: number, expectedVersion: number, idempotencyKey: string) {
    const endpoint = `/v2/event-templates/${templateId}/revisions`;
    return parseResponse(
      endpoint,
      await api.post(
        endpoint,
        { expected_version: expectedVersion },
        optionsWithIdempotency(idempotencyKey),
      ),
      eventTemplateMutationSchema,
    );
  },

  async archive(templateId: number, expectedVersion: number, reason: string, idempotencyKey: string) {
    const endpoint = `/v2/event-templates/${templateId}/archive`;
    return parseResponse(
      endpoint,
      await api.post(
        endpoint,
        { expected_version: expectedVersion, reason },
        optionsWithIdempotency(idempotencyKey),
      ),
      eventTemplateMutationSchema,
    );
  },

  async previewMaterialization(
    templateId: number,
    input: EventTemplateMaterializationInput,
  ): Promise<ApiResponse<EventTemplateMaterializationPreview>> {
    const endpoint = `/v2/event-templates/${templateId}/materialization-preview`;
    return parseResponse(
      endpoint,
      await api.post(endpoint, input),
      eventTemplateMaterializationPreviewSchema,
    );
  },

  async materialize(
    templateId: number,
    input: EventTemplateMaterializationInput,
    idempotencyKey: string,
  ): Promise<ApiResponse<EventTemplateMaterialization>> {
    const endpoint = `/v2/event-templates/${templateId}/materializations`;
    return parseResponse(
      endpoint,
      await api.post(endpoint, input, optionsWithIdempotency(idempotencyKey)),
      eventTemplateMaterializationSchema,
    );
  },
};
