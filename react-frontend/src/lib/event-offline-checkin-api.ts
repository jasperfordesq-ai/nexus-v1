// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { z } from 'zod';
import { api, type ApiResponse, type RequestOptions } from '@/lib/api';
import { logError } from '@/lib/logger';

export const EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION = 1 as const;
const nullableTimestamp = z.string().nullable();

const deviceSchema = z.object({
  id: z.number().int().positive(),
  public_id: z.string().uuid(),
  label: z.string().min(1),
  registered_by_user_id: z.number().int().positive(),
  version: z.number().int().positive(),
  status: z.enum(['active', 'revoked', 'expired']),
  registered_at: z.string(),
  expires_at: z.string(),
  rotated_at: nullableTimestamp,
  revoked_at: nullableTimestamp,
  revocation_reason: z.string().nullable(),
}).strict();

const batchSummarySchema = z.object({
  id: z.number().int().positive(),
  device_id: z.number().int().positive(),
  client_batch_id: z.string().min(1),
  manifest_version: z.number().int().nonnegative(),
  item_count: z.number().int().positive(),
  status: z.enum(['pending', 'processing', 'completed', 'dead_letter']),
  counts: z.object({
    synced: z.number().int().nonnegative(),
    conflict: z.number().int().nonnegative(),
    rejected: z.number().int().nonnegative(),
    pending: z.number().int().nonnegative(),
  }).strict(),
  created_at: z.string(),
  completed_at: nullableTimestamp,
  dead_lettered_at: nullableTimestamp,
  terminal_code: z.string().nullable(),
}).strict();

export const offlineCheckinWorkspaceSchema = z.object({
  contract_version: z.literal(EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION),
  event_id: z.number().int().positive(),
  occurrence_key: z.string().min(1),
  manifest_version: z.number().int().nonnegative(),
  limits: z.object({
    replay_window_minutes: z.number().int().positive(),
    batch_max_items: z.number().int().positive().max(500),
  }).strict(),
  devices: z.array(deviceSchema),
  recent_batches: z.array(batchSummarySchema).max(50),
  open_conflicts: z.number().int().nonnegative(),
  permissions: z.object({
    manage_devices: z.literal(true),
    download_manifest: z.literal(true),
    sync_offline_queue: z.literal(true),
    resolve_conflicts: z.literal(true),
    manual_fallback_required: z.literal(true),
  }).strict(),
  privacy: z.object({
    device_secrets_redacted: z.literal(true),
    credential_secrets_redacted: z.literal(true),
    contact_fields_redacted: z.literal(true),
    wallet_effects_supported: z.literal(false),
  }).strict(),
}).strict();

const manifestRegistrationSchema = z.object({
  registration_id: z.number().int().positive(),
  user_id: z.number().int().positive(),
  display_name: z.string(),
  credential_version: z.number().int().positive(),
  credential_fingerprint: z.string().regex(/^[0-9a-f]{16}$/),
  credential_verifier: z.string().regex(/^[0-9a-f]{64}$/),
  attendance_status: z.string().nullable(),
  attendance_version: z.number().int().nonnegative(),
}).strict();

export const offlineCheckinManifestSchema = z.object({
  schema_version: z.literal(2),
  tenant_id: z.number().int().positive(),
  event_id: z.number().int().positive(),
  occurrence_key: z.string().min(1),
  manifest_version: z.number().int().nonnegative(),
  device: z.object({
    id: z.number().int().positive(),
    version: z.number().int().positive(),
  }).strict(),
  generated_at: z.string(),
  expires_at: z.string(),
  credential_verification: z.object({
    format: z.literal('nqx2'),
    algorithm: z.literal('Ed25519'),
    keys: z.array(z.object({
      kid: z.string().regex(/^[0-9a-f]{16}$/),
      alg: z.literal('Ed25519'),
      public_key: z.string().min(40),
    }).strict()).min(1),
  }).strict(),
  registrations: z.array(manifestRegistrationSchema),
  privacy: z.object({
    credential_contains_pii: z.literal(false),
    encrypted_at_rest_required: z.literal(true),
  }).strict(),
}).strict();

export const offlineCheckinBatchSchema = z.object({
  contract_version: z.literal(EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION),
  event_id: z.number().int().positive(),
  batch: z.object({
    id: z.number().int().positive(),
    client_batch_id: z.string().min(1),
    status: z.enum(['pending', 'processing', 'completed', 'dead_letter']),
    item_count: z.number().int().positive(),
    accepted_count: z.number().int().nonnegative(),
    conflict_count: z.number().int().nonnegative(),
    rejected_count: z.number().int().nonnegative(),
    created_at: z.string().nullable(),
    completed_at: nullableTimestamp,
  }).strict(),
  items: z.array(z.object({
    id: z.number().int().positive(),
    position: z.number().int().positive(),
    client_nonce: z.string().min(8),
    operation: z.enum(['check_in', 'check_out', 'no_show', 'undo']),
    observed_at: z.string(),
    expected_attendance_version: z.number().int().nonnegative(),
    state: z.enum(['pending', 'synced', 'conflict', 'rejected']),
    decision_version: z.number().int().positive().nullable(),
    code: z.string().nullable(),
    reason: z.string().nullable(),
    decided_at: nullableTimestamp,
  }).strict()),
  privacy: z.object({
    credential_redacted: z.literal(true),
    attendee_identity_redacted: z.literal(true),
  }).strict(),
}).strict();

export const offlineCheckinConflictsSchema = z.object({
  contract_version: z.literal(EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION),
  event_id: z.number().int().positive(),
  items: z.array(z.object({
    item_id: z.number().int().positive(),
    batch_id: z.number().int().positive(),
    client_batch_id: z.string().min(1),
    member: z.object({
      id: z.number().int().positive(),
      display_name: z.string(),
    }).strict(),
    operation: z.enum(['check_in', 'check_out', 'no_show', 'undo']),
    observed_at: z.string(),
    submitted_reason: z.string().nullable(),
    expected_attendance_version: z.number().int().nonnegative(),
    current_attendance: z.object({
      state: z.string(),
      version: z.number().int().nonnegative(),
    }).strict(),
    conflict: z.object({
      decision_version: z.number().int().positive(),
      code: z.string().min(1),
      decided_at: z.string(),
    }).strict(),
    device: z.object({
      id: z.number().int().positive(),
      label: z.string().nullable(),
    }).strict(),
  }).strict()),
  total: z.number().int().nonnegative(),
  page: z.number().int().positive(),
  per_page: z.number().int().positive(),
  privacy: z.object({
    credential_redacted: z.literal(true),
    contact_fields_redacted: z.literal(true),
    free_text_member_profile_redacted: z.literal(true),
  }).strict(),
}).strict();

const credentialShapeSchema = z.object({
  id: z.number().int().positive(),
  registration_id: z.number().int().positive().optional(),
  version: z.number().int().positive(),
  status: z.enum(['active', 'rotated', 'revoked', 'expired']),
  expires_at: z.string().optional(),
  revoked_at: nullableTimestamp.optional(),
  token: z.string().nullable().optional(),
  token_one_shot: z.boolean().optional(),
  contains_pii: z.literal(false).optional(),
}).strict();

const credentialResponseSchema = z.object({
  contract_version: z.literal(EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION),
  event_id: z.number().int().positive(),
  credential: credentialShapeSchema.nullable(),
  manifest_version: z.number().int().nonnegative().optional(),
}).strict();

const deviceResponseSchema = z.object({
  contract_version: z.literal(EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION),
  event_id: z.number().int().positive(),
  device: z.object({
    id: z.number().int().positive(),
    public_id: z.string().uuid().optional(),
    label: z.string().optional(),
    version: z.number().int().positive(),
    status: z.enum(['active', 'revoked', 'expired']),
    expires_at: z.string().optional(),
    revoked_at: nullableTimestamp.optional(),
    secret: z.string().nullable().optional(),
    secret_one_shot: z.boolean().optional(),
    purge_local_data_required: z.boolean().optional(),
  }).strict(),
  manifest_version: z.number().int().nonnegative().optional(),
}).strict();

export type OfflineCheckinWorkspace = z.infer<typeof offlineCheckinWorkspaceSchema>;
export type OfflineCheckinManifest = z.infer<typeof offlineCheckinManifestSchema>;
export type OfflineCheckinBatch = z.infer<typeof offlineCheckinBatchSchema>;
export type OfflineCheckinConflicts = z.infer<typeof offlineCheckinConflictsSchema>;
export type OfflineOperation = 'check_in' | 'check_out' | 'no_show' | 'undo';
export type EventCheckinCredential = z.infer<typeof credentialShapeSchema>;

function requestOptions(
  options?: RequestOptions,
  idempotencyKey?: string,
): RequestOptions {
  return {
    ...options,
    headers: {
      ...options?.headers,
      'X-Events-Contract': '2',
      'X-Event-Checkin-Contract': String(EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION),
      ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
    },
  };
}

function parse<T>(
  endpoint: string,
  response: ApiResponse<unknown>,
  schema: z.ZodType<T>,
): ApiResponse<T> {
  if (!response.success) return response as ApiResponse<T>;
  const parsed = schema.safeParse(response.data);
  if (parsed.success) return { ...response, data: parsed.data };
  logError('Event offline check-in contract drift', {
    endpoint: endpoint.replace(/\/\d+(?=\/|$)/g, '/{id}'),
    version: EVENT_OFFLINE_CHECKIN_CONTRACT_VERSION,
    issues: parsed.error.issues.map((issue) => ({
      path: issue.path.map(String).join('.'),
      code: issue.code,
    })),
  });

  return {
    ...response,
    success: false,
    data: undefined,
    error: undefined,
    code: 'EVENT_CHECKIN_CONTRACT_DRIFT',
  };
}

export const eventOfflineCheckinApi = {
  async myCredential(eventId: number) {
    const endpoint = `/v2/events/${eventId}/offline-checkin/credentials/me`;
    return parse(endpoint, await api.get(endpoint, requestOptions()), credentialResponseSchema);
  },

  async workspace(eventId: number, options?: RequestOptions): Promise<ApiResponse<OfflineCheckinWorkspace>> {
    const endpoint = `/v2/events/${eventId}/offline-checkin`;
    return parse(endpoint, await api.get(endpoint, requestOptions(options)), offlineCheckinWorkspaceSchema);
  },

  async registerDevice(
    eventId: number,
    label: string,
    expiresAt: string | null,
    idempotencyKey: string,
  ) {
    const endpoint = `/v2/events/${eventId}/offline-checkin/devices`;
    return parse(endpoint, await api.post(endpoint, {
      label,
      expires_at: expiresAt,
    }, requestOptions(undefined, idempotencyKey)), deviceResponseSchema);
  },

  async rotateDevice(
    eventId: number,
    deviceId: number,
    expectedVersion: number,
    idempotencyKey: string,
  ) {
    const endpoint = `/v2/events/${eventId}/offline-checkin/devices/${deviceId}/rotate`;
    return parse(endpoint, await api.post(endpoint, {
      expected_version: expectedVersion,
    }, requestOptions(undefined, idempotencyKey)), deviceResponseSchema);
  },

  async revokeDevice(
    eventId: number,
    deviceId: number,
    expectedVersion: number,
    reason: string,
    idempotencyKey: string,
  ) {
    const endpoint = `/v2/events/${eventId}/offline-checkin/devices/${deviceId}/revoke`;
    return parse(endpoint, await api.post(endpoint, {
      expected_version: expectedVersion,
      reason,
    }, requestOptions(undefined, idempotencyKey)), deviceResponseSchema);
  },

  async manifest(
    eventId: number,
    deviceSecret: string,
    ttlMinutes?: number,
  ): Promise<ApiResponse<OfflineCheckinManifest>> {
    const endpoint = `/v2/events/${eventId}/offline-checkin/manifest`;
    return parse(endpoint, await api.post(endpoint, {
      device_secret: deviceSecret,
      ttl_minutes: ttlMinutes,
    }, requestOptions()), offlineCheckinManifestSchema);
  },

  async stage(
    eventId: number,
    input: {
      deviceSecret: string;
      clientBatchId: string;
      manifestVersion: number;
      items: Array<{
        client_nonce: string;
        operation: OfflineOperation;
        observed_at: string;
        expected_attendance_version: number;
        credential_fingerprint: string;
        credential_hash_reference: string;
        reason?: string;
      }>;
    },
  ): Promise<ApiResponse<OfflineCheckinBatch>> {
    const endpoint = `/v2/events/${eventId}/offline-checkin/sync`;
    return parse(endpoint, await api.post(endpoint, {
      device_secret: input.deviceSecret,
      client_batch_id: input.clientBatchId,
      manifest_version: input.manifestVersion,
      items: input.items,
    }, requestOptions()), offlineCheckinBatchSchema);
  },

  async batch(eventId: number, batchId: number): Promise<ApiResponse<OfflineCheckinBatch>> {
    const endpoint = `/v2/events/${eventId}/offline-checkin/batches/${batchId}`;
    return parse(endpoint, await api.get(endpoint, requestOptions()), offlineCheckinBatchSchema);
  },

  async conflicts(eventId: number, page = 1): Promise<ApiResponse<OfflineCheckinConflicts>> {
    const endpoint = `/v2/events/${eventId}/offline-checkin/conflicts?page=${page}`;
    return parse(endpoint, await api.get(endpoint, requestOptions()), offlineCheckinConflictsSchema);
  },

  async resolveConflict(
    eventId: number,
    itemId: number,
    input: {
      expectedDecisionVersion: number;
      disposition: 'apply' | 'reject';
      expectedAttendanceVersion: number;
      reason: string;
    },
    idempotencyKey: string,
  ): Promise<ApiResponse<OfflineCheckinConflicts>> {
    const endpoint = `/v2/events/${eventId}/offline-checkin/conflicts/${itemId}`;
    return parse(endpoint, await api.post(endpoint, {
      expected_decision_version: input.expectedDecisionVersion,
      disposition: input.disposition,
      expected_attendance_version: input.expectedAttendanceVersion,
      reason: input.reason,
    }, requestOptions(undefined, idempotencyKey)), offlineCheckinConflictsSchema);
  },

  async issueCredential(
    eventId: number,
    registrationId: number | null,
    idempotencyKey: string,
  ) {
    const endpoint = `/v2/events/${eventId}/offline-checkin/credentials`;
    return parse(endpoint, await api.post(endpoint, {
      registration_id: registrationId,
    }, requestOptions(undefined, idempotencyKey)), credentialResponseSchema);
  },

  async rotateCredential(
    eventId: number,
    credentialId: number,
    expectedVersion: number,
    idempotencyKey: string,
  ) {
    const endpoint = `/v2/events/${eventId}/offline-checkin/credentials/${credentialId}/rotate`;
    return parse(endpoint, await api.post(endpoint, {
      expected_version: expectedVersion,
    }, requestOptions(undefined, idempotencyKey)), credentialResponseSchema);
  },

  async revokeCredential(
    eventId: number,
    credentialId: number,
    expectedVersion: number,
    reason: string,
  ) {
    const endpoint = `/v2/events/${eventId}/offline-checkin/credentials/${credentialId}/revoke`;
    return parse(endpoint, await api.post(endpoint, {
      expected_version: expectedVersion,
      reason,
    }, requestOptions()), credentialResponseSchema);
  },
};
