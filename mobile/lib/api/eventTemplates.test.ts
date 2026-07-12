// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/api/client', () => ({
  api: { get: jest.fn(), post: jest.fn() },
  ApiResponseError: class ApiResponseError extends Error {
    status: number;
    constructor(status: number, message: string) {
      super(message);
      this.status = status;
    }
  },
}));
jest.mock('@/lib/constants', () => ({ API_V2: '/api/v2' }));
jest.mock('@sentry/react-native', () => ({ captureMessage: jest.fn() }));

import * as Sentry from '@sentry/react-native';
import { api } from '@/lib/api/client';
import {
  getEventTemplates,
  materializeEventTemplate,
} from './eventTemplates';

function templateFixture() {
  return {
    id: 4,
    status: 'active',
    current_version: 1,
    source_event: { id: 9, title: 'Source event' },
    version: {
      number: 1,
      configuration: {
        title: 'Reusable event',
        description: 'Reusable description',
        location: 'Community hall',
        max_attendees: 30,
        timezone: 'UTC',
        all_day: false,
        is_online: false,
        allow_remote_attendance: false,
      },
      snapshot: { immutable: true },
      copied_fields: ['title'],
      skipped_fields: ['related.registrations'],
    },
    usage: { materialization_count: 2 },
    capabilities: { materialize: true },
  };
}

describe('mobile event templates API', () => {
  beforeEach(() => jest.clearAllMocks());

  it('loads only active templates through a cursor-safe envelope', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [templateFixture()],
      meta: { per_page: 20, next_cursor: null, has_more: false },
    });

    const response = await getEventTemplates();

    expect(response.data[0]?.version.snapshot.immutable).toBe(true);
    expect(api.get).toHaveBeenCalledWith('/api/v2/event-templates', {
      status: 'active',
      per_page: '20',
    });
  });

  it('materializes with a header idempotency key and verifies fresh-draft facts', async () => {
    (api.post as jest.Mock).mockResolvedValue({
      data: {
        created_event: {
          id: 21,
          title: 'Fresh draft',
          publication_status: 'draft',
          operational_status: 'scheduled',
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
      start_time: '2030-08-01T10:00',
      end_time: '2030-08-01T12:00',
      overrides: { title: 'Fresh draft' },
    };

    const result = await materializeEventTemplate(4, input, 'mobile-materialize-key');

    expect(result.workflow.fresh_draft).toBe(true);
    expect(result.workflow.registrations_copied).toBe(false);
    expect(api.post).toHaveBeenCalledWith(
      '/api/v2/event-templates/4/materializations',
      input,
      { headers: { 'Idempotency-Key': 'mobile-materialize-key' } },
    );
  });

  it('fails closed and reports only issue paths when the contract drifts', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{ ...templateFixture(), current_version: 0 }],
      meta: { per_page: 20, next_cursor: null, has_more: false },
    });

    await expect(getEventTemplates()).rejects.toThrow('EVENTS_CONTRACT_DRIFT');
    expect(Sentry.captureMessage).toHaveBeenCalledWith(
      'Event templates contract drift',
      expect.objectContaining({
        extra: { issues: expect.any(Array) },
      }),
    );
  });
});
