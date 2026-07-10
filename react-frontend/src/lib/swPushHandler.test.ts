// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { runInNewContext } from 'node:vm';
import { describe, expect, it, vi } from 'vitest';

type WorkerListener = (event: Record<string, unknown>) => void;

function loadPushWorker(clientList: Array<Record<string, unknown>> = []) {
  const listeners = new Map<string, WorkerListener>();
  const showNotification = vi.fn(async () => undefined);
  const openWindow = vi.fn(async () => undefined);
  const worker = {
    location: { origin: 'https://app.project-nexus.ie' },
    registration: { showNotification },
    clients: { matchAll: vi.fn(async () => clientList), openWindow },
    addEventListener: (type: string, listener: WorkerListener) => listeners.set(type, listener),
  };
  const context = { self: worker, URL };
  const source = readFileSync(resolve(process.cwd(), 'public/sw-push-handler.js'), 'utf8');
  runInNewContext(source, context);

  return {
    listeners,
    openWindow,
    showNotification,
    normalizePushTarget: (context as unknown as {
      normalizePushTarget: (value: string, tenantPath: string) => string;
    }).normalizePushTarget,
  };
}

describe('service-worker push routing', () => {
  it('qualifies same-origin relative destinations with the payload tenant path', () => {
    const { normalizePushTarget } = loadPushWorker();

    expect(normalizePushTarget('/listings/42?tab=offers', '/hour-timebank')).toBe(
      '/hour-timebank/listings/42?tab=offers',
    );
    expect(normalizePushTarget('/hour-timebank/messages', '/hour-timebank')).toBe(
      '/hour-timebank/messages',
    );
  });

  it('rejects cross-origin and traversal-like tenant destinations', () => {
    const { normalizePushTarget } = loadPushWorker();

    expect(normalizePushTarget('https://attacker.example/path', '/hour-timebank')).toBe(
      '/hour-timebank/',
    );
    expect(normalizePushTarget('/', '/../hour-timebank/')).toBe('/hour-timebank/');
  });

  it('stores the normalized tenant destination in displayed notification data', async () => {
    const { listeners, showNotification } = loadPushWorker();
    let completion: Promise<unknown> | undefined;

    listeners.get('push')?.({
      data: {
        json: () => ({
          title: 'Update',
          body: 'Body',
          url: '/messages/7',
          tenant_path: '/hour-timebank',
        }),
      },
      waitUntil: (promise: Promise<unknown>) => { completion = promise; },
    });

    await completion;
    expect(showNotification).toHaveBeenCalledWith(
      'Update',
      expect.objectContaining({
        data: expect.objectContaining({ url: '/hour-timebank/messages/7' }),
      }),
    );
  });

  it('focuses and navigates an existing same-origin client on notification click', async () => {
    const focus = vi.fn(async () => undefined);
    const navigate = vi.fn(async () => undefined);
    const client = {
      url: 'https://app.project-nexus.ie/hour-timebank/feed',
      focus,
      navigate,
    };
    const { listeners, openWindow } = loadPushWorker([client]);
    const close = vi.fn();
    let completion: Promise<unknown> | undefined;

    listeners.get('notificationclick')?.({
      notification: {
        close,
        data: { url: '/messages/9', tenant_path: '/hour-timebank' },
      },
      waitUntil: (promise: Promise<unknown>) => { completion = promise; },
    });

    await completion;
    expect(close).toHaveBeenCalledOnce();
    expect(focus).toHaveBeenCalledOnce();
    expect(navigate).toHaveBeenCalledWith('/hour-timebank/messages/9');
    expect(openWindow).not.toHaveBeenCalled();
  });

  it('opens the tenant-qualified destination when no client exists', async () => {
    const { listeners, openWindow } = loadPushWorker();
    let completion: Promise<unknown> | undefined;

    listeners.get('notificationclick')?.({
      notification: {
        close: vi.fn(),
        data: { url: '/wallet', tenant_path: '/hour-timebank' },
      },
      waitUntil: (promise: Promise<unknown>) => { completion = promise; },
    });

    await completion;
    expect(openWindow).toHaveBeenCalledWith('/hour-timebank/wallet');
  });
});
