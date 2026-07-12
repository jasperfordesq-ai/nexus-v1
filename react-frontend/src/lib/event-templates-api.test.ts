// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { api } from '@/lib/api';
import { eventTemplatesApi } from './event-templates-api';

const mockApi = vi.mocked(api);

function templateFixture() {
  return {
    id: 4,
    public_id: '123e4567-e89b-12d3-a456-426614174000',
    status: 'active',
    current_version: 1,
    source_event: { id: 9, title: 'Source event', updated_at: '2026-07-01T10:00:00+00:00' },
    version: {
      id: 10,
      number: 1,
      schema_version: 1,
      configuration: {
        title: 'Reusable event',
        description: 'Reusable description',
        category_id: null,
        group_id: null,
        location: 'Community hall',
        latitude: null,
        longitude: null,
        max_attendees: 30,
        is_online: false,
        allow_remote_attendance: false,
        timezone: 'Europe/Dublin',
        all_day: false,
        federated_visibility: 'none',
      },
      snapshot: {
        hash: 'a'.repeat(64),
        source_lifecycle_version: 2,
        source_calendar_sequence: 3,
        source_updated_at: '2026-07-01T10:00:00+00:00',
        immutable: true,
      },
      copied_fields: ['title', 'description'],
      skipped_fields: ['related.registrations', 'related.tickets'],
      captured_at: '2026-07-01T10:00:00+00:00',
    },
    usage: { materialization_count: 0, audit_entry_count: 1 },
    archive: { reason: null, archived_at: null },
    capabilities: {
      view: true,
      revise: true,
      archive: true,
      materialize: true,
      view_audit: true,
    },
    created_at: '2026-07-01T10:00:00+00:00',
    updated_at: '2026-07-01T10:00:00+00:00',
  };
}

describe('eventTemplatesApi', () => {
  beforeEach(() => vi.clearAllMocks());

  it('parses a policy-filtered template page and cursor metadata', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: [templateFixture()],
      meta: { per_page: 20, next_cursor: '4', has_more: true },
    });

    const response = await eventTemplatesApi.list({ status: 'active', per_page: 20 });

    expect(response.success).toBe(true);
    expect(response.data?.[0]?.version.snapshot.immutable).toBe(true);
    expect(response.meta?.next_cursor).toBe('4');
    expect(mockApi.get).toHaveBeenCalledWith('/v2/event-templates?status=active&per_page=20');
  });

  it('sends the idempotency key outside the mutation body', async () => {
    mockApi.post.mockResolvedValue({
      success: true,
      data: { template: templateFixture(), changed: true, idempotent_replay: false },
    });

    const response = await eventTemplatesApi.capture(9, 'capture-key');

    expect(response.success).toBe(true);
    expect(mockApi.post).toHaveBeenCalledWith(
      '/v2/events/9/templates',
      {},
      { headers: { 'Idempotency-Key': 'capture-key' } },
    );
  });

  it('validates the fresh-draft workflow before exposing a materialization', async () => {
    mockApi.post.mockResolvedValue({
      success: true,
      data: {
        created_event: {
          id: 21,
          title: 'Fresh draft',
          publication_status: 'draft',
          operational_status: 'scheduled',
          edit_path: '/events/21/edit',
        },
        provenance: {
          id: 6,
          template_id: 4,
          template_version: 1,
          source_event_id: 9,
          schema_version: 1,
          schedule: {
            start_at: '2026-09-01T10:00:00+00:00',
            end_at: '2026-09-01T12:00:00+00:00',
            timezone: 'Europe/Dublin',
            all_day: false,
          },
          override_fields: ['title'],
          federation_normalized: true,
          created_at: '2026-07-01T10:00:00+00:00',
          immutable: true,
        },
        changed: true,
        idempotent_replay: false,
        workflow: {
          fresh_draft: true,
          published: false,
          registrations_copied: false,
          notifications_sent: false,
          federated: false,
        },
      },
    });
    const input = {
      template_version: 1,
      start_time: '2026-09-01T11:00',
      end_time: '2026-09-01T13:00',
      overrides: { title: 'Fresh draft' },
    };

    const response = await eventTemplatesApi.materialize(4, input, 'materialize-key');

    expect(response.data?.workflow).toEqual({
      fresh_draft: true,
      published: false,
      registrations_copied: false,
      notifications_sent: false,
      federated: false,
    });
    expect(mockApi.post).toHaveBeenCalledWith(
      '/v2/event-templates/4/materializations',
      input,
      { headers: { 'Idempotency-Key': 'materialize-key' } },
    );
  });

  it('fails closed when an unexpected private field drifts into the resource', async () => {
    mockApi.get.mockResolvedValue({
      success: true,
      data: [{ ...templateFixture(), tenant_id: 2 }],
      meta: { per_page: 20, next_cursor: null, has_more: false },
    });

    const response = await eventTemplatesApi.list();

    expect(response.success).toBe(false);
    expect(response.code).toBe('EVENTS_CONTRACT_DRIFT');
    expect(response.data).toBeUndefined();
  });
});
