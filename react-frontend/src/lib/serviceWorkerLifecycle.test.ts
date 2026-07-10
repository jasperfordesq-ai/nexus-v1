// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { installServiceWorkerLifecycle } from './serviceWorkerLifecycle';

interface WorkerHarness {
  dispatchControllerChange: () => void;
  register: ReturnType<typeof vi.fn>;
  update: ReturnType<typeof vi.fn>;
  navigatorRef: Navigator;
}

function workerHarness(): WorkerHarness {
  const listeners = new Set<EventListenerOrEventListenerObject>();
  const update = vi.fn(async () => undefined);
  const registration = { update } as unknown as ServiceWorkerRegistration;
  const register = vi.fn(async () => registration);
  const serviceWorker = {
    addEventListener: vi.fn((type: string, listener: EventListenerOrEventListenerObject) => {
      if (type === 'controllerchange') listeners.add(listener);
    }),
    removeEventListener: vi.fn((_type: string, listener: EventListenerOrEventListenerObject) => {
      listeners.delete(listener);
    }),
    register,
    getRegistration: vi.fn(async () => registration),
  } as unknown as ServiceWorkerContainer;

  return {
    register,
    update,
    navigatorRef: { serviceWorker } as Navigator,
    dispatchControllerChange: () => {
      const event = new Event('controllerchange');
      for (const listener of listeners) {
        if (typeof listener === 'function') listener(event);
        else listener.handleEvent(event);
      }
    },
  };
}

describe('service-worker upgrade lifecycle', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    document.body.innerHTML = '';
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('registers without HTTP-cache reuse and polls for deployed updates', async () => {
    const worker = workerHarness();
    const cleanup = installServiceWorkerLifecycle({
      breadcrumb: vi.fn(),
      schedule: (callback) => callback(),
      navigatorRef: worker.navigatorRef,
    });

    await vi.waitFor(() => {
      expect(worker.register).toHaveBeenCalledWith('/sw.js', {
        scope: '/',
        updateViaCache: 'none',
      });
    });

    await vi.advanceTimersByTimeAsync(5 * 60 * 1000);
    expect(worker.update).toHaveBeenCalledTimes(1);
    cleanup();
  });

  it('reloads once with a cache-busting URL after the new worker takes control', () => {
    const worker = workerHarness();
    const reload = vi.fn();
    installServiceWorkerLifecycle({
      breadcrumb: vi.fn(),
      schedule: vi.fn(),
      navigatorRef: worker.navigatorRef,
      reload,
      now: () => 1234,
    });

    worker.dispatchControllerChange();
    worker.dispatchControllerChange();

    expect(reload).toHaveBeenCalledTimes(1);
    expect(reload.mock.calls[0]?.[0]).toContain('nexus_refresh=1234');
  });

  it('defers the upgrade reload while editing and resumes on blur', () => {
    const worker = workerHarness();
    const reload = vi.fn();
    const input = document.createElement('input');
    document.body.append(input);
    input.focus();

    installServiceWorkerLifecycle({
      breadcrumb: vi.fn(),
      schedule: vi.fn(),
      navigatorRef: worker.navigatorRef,
      reload,
    });

    worker.dispatchControllerChange();
    expect(reload).not.toHaveBeenCalled();

    input.dispatchEvent(new FocusEvent('blur'));
    expect(reload).toHaveBeenCalledTimes(1);
  });
});

