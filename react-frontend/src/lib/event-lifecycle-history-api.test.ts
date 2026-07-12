// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';

const { apiMock, logErrorMock } = vi.hoisted(() => ({
  apiMock: { get: vi.fn() },
  logErrorMock: vi.fn(),
}));

vi.mock('@/lib/api', () => ({ api: apiMock }));
vi.mock('@/lib/logger', () => ({ logError: logErrorMock }));

import {
  eventLifecycleHistoryApi,
  eventLifecycleHistoryEntrySchema,
  type EventLifecycleHistoryEntry,
} from './event-lifecycle-history-api';

function entry(overrides: Partial<EventLifecycleHistoryEntry> = {}): EventLifecycleHistoryEntry {
  return {
    id: 41,
    lifecycle_version: 3,
    publication: { from: 'pending_review', to: 'published' },
    operational: { from: 'scheduled', to: 'scheduled' },
    reason: 'Approved after review',
    actor: { id: 8, display_name: 'Morgan Owner' },
    evidence: {
      axes_changed: ['publication'],
      cascade: { reminders_cancelled: 0 },
      series: null,
      notifications_suppressed: false,
    },
    created_at: '2026-07-12T10:00:00+00:00',
    immutable: true,
    ...overrides,
  };
}

describe('eventLifecycleHistoryApi', () => {
  beforeEach(() => vi.clearAllMocks());

  it('parses the strict paginated history contract and sends an opaque cursor', async () => {
    const signal = new AbortController().signal;
    apiMock.get.mockResolvedValue({
      success: true,
      data: [entry()],
      meta: { per_page: 20, next_cursor: 'opaque_cursor', has_more: true, base_url: '/api' },
    });

    const response = await eventLifecycleHistoryApi.list(7, 'previous_cursor', { signal });

    expect(response.success).toBe(true);
    expect(response.data?.[0]?.immutable).toBe(true);
    expect(response.meta?.next_cursor).toBe('opaque_cursor');
    expect(eventLifecycleHistoryEntrySchema.parse(response.data?.[0]).actor.display_name)
      .toBe('Morgan Owner');
    expect(apiMock.get).toHaveBeenCalledWith(
      '/v2/events/7/lifecycle-history?per_page=20&cursor=previous_cursor',
      { signal },
    );
  });

  it('fails closed when the server leaks non-allowlisted lifecycle metadata', async () => {
    apiMock.get.mockResolvedValue({
      success: true,
      data: [{
        ...entry(),
        evidence: {
          ...entry().evidence,
          private_recipients: [11, 12],
        },
      }],
      meta: { per_page: 20, next_cursor: null, has_more: false },
    });

    const response = await eventLifecycleHistoryApi.list(7);

    expect(response.success).toBe(false);
    expect(response.code).toBe('EVENTS_CONTRACT_DRIFT');
    expect(response.data).toBeUndefined();
    expect(logErrorMock).toHaveBeenCalledOnce();
  });
});
