// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '@/lib/api';
import {
  createGroupWebhook,
  deleteGroupWebhook,
  listGroupWebhooks,
  setGroupWebhookActive,
} from './webhooks';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  },
}));

const webhook = {
  id: 3,
  url: 'https://example.test/groups-hook',
  events: ['group.updated'],
  is_active: true,
  last_fired_at: null,
  failure_count: 0,
};

describe('group-webhooks adapter', () => {
  beforeEach(() => vi.clearAllMocks());

  it('lists webhooks and forwards cancellation', async () => {
    const controller = new AbortController();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [webhook] });

    await expect(listGroupWebhooks(7, { signal: controller.signal })).resolves.toEqual([webhook]);
    expect(api.get).toHaveBeenCalledWith('/v2/groups/7/webhooks', { signal: controller.signal });
  });

  it('rejects false-success and malformed list payloads', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: false, code: 'HTTP_500' })
      .mockResolvedValueOnce({ success: true, data: null as never });

    await expect(listGroupWebhooks(7)).rejects.toMatchObject({ code: 'SERVER_ERROR' });
    await expect(listGroupWebhooks(7)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });

  it('creates with an exact typed payload', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: webhook });
    const input = {
      url: webhook.url,
      events: webhook.events,
      secret: 'test-secret',
    };

    await expect(createGroupWebhook(7, input)).resolves.toEqual(webhook);
    expect(api.post).toHaveBeenCalledWith('/v2/groups/7/webhooks', input);
  });

  it('normalizes toggle and delete mutation failures', async () => {
    vi.mocked(api.put).mockResolvedValue({ success: false, code: 'HTTP_409' });
    vi.mocked(api.delete).mockRejectedValue(new TypeError('Private transport detail'));

    await expect(setGroupWebhookActive(7, 3, false)).rejects.toMatchObject({ code: 'CONFLICT' });
    expect(api.put).toHaveBeenCalledWith('/v2/groups/7/webhooks/3/toggle', { is_active: false });
    await expect(deleteGroupWebhook(7, 3)).rejects.toMatchObject({
      code: 'NETWORK_ERROR',
      retryable: true,
    });
  });

  it('deletes through the exact parent/child route', async () => {
    vi.mocked(api.delete).mockResolvedValue({ success: true });

    await expect(deleteGroupWebhook(7, 3)).resolves.toBeUndefined();
    expect(api.delete).toHaveBeenCalledWith('/v2/groups/7/webhooks/3');
  });
});
