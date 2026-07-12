// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import * as Sentry from '@sentry/react-native';
import { api, ApiResponseError } from '@/lib/api/client';
import {
  getEventLifecycleHistory,
  mobileEventLifecycleHistoryEntrySchema,
} from './eventLifecycleHistory';

jest.mock('@/lib/api/client', () => {
  class MockApiResponseError extends Error {
    status: number;
    code: string;
    constructor(status: number, code: string) {
      super(code);
      this.status = status;
      this.code = code;
    }
  }
  return { api: { get: jest.fn() }, ApiResponseError: MockApiResponseError };
});
jest.mock('@sentry/react-native', () => ({ captureMessage: jest.fn() }));

const entry = {
  id: 8,
  lifecycle_version: 2,
  publication: { from: 'pending_review' as const, to: 'published' as const },
  operational: { from: 'scheduled' as const, to: 'scheduled' as const },
  reason: 'Approved',
  actor: { id: 4, display_name: 'Morgan Owner' },
  evidence: {
    axes_changed: ['publication' as const],
    cascade: { reminders_cancelled: 0 },
    series: null,
    notifications_suppressed: false,
  },
  created_at: '2026-07-12T10:00:00+00:00',
  immutable: true as const,
};

describe('event lifecycle history API', () => {
  beforeEach(() => jest.clearAllMocks());

  it('parses the bounded contract and forwards only the opaque cursor', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [entry],
      meta: { base_url: '/api/v2', per_page: 20, next_cursor: 'opaque-next', has_more: true },
    });

    const result = await getEventLifecycleHistory(42, 'opaque-current');

    expect(result.data[0]?.actor.display_name).toBe('Morgan Owner');
    expect(mobileEventLifecycleHistoryEntrySchema.parse(result.data[0]).immutable).toBe(true);
    expect(api.get).toHaveBeenCalledWith('/api/v2/events/42/lifecycle-history', {
      per_page: '20',
      cursor: 'opaque-current',
    });
  });

  it('fails closed when non-allowlisted metadata reaches the native client', async () => {
    (api.get as jest.Mock).mockResolvedValue({
      data: [{
        ...entry,
        evidence: { ...entry.evidence, private_recipients: [11, 12] },
      }],
      meta: { base_url: '/api/v2', per_page: 20, next_cursor: null, has_more: false },
    });

    await expect(getEventLifecycleHistory(42)).rejects.toBeInstanceOf(ApiResponseError);
    expect(Sentry.captureMessage).toHaveBeenCalledWith(
      'Events lifecycle history contract drift',
      expect.objectContaining({
        tags: expect.objectContaining({ endpoint: '/api/v2/events/{id}/lifecycle-history' }),
      }),
    );
  });
});
