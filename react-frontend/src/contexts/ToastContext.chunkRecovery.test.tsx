// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Regression: the ToastViewport chunk must go through the shared stale-chunk
 * recovery wrapper (Sentry: "Failed to fetch dynamically imported module:
 * ToastViewport" after a deploy).
 *
 * ToastViewport is rendered globally by ToastProvider the moment any toast
 * fires, so if it loaded via bare React.lazy() a re-hashed chunk on a tab left
 * open across a deploy would 404 and crash the current view to the root error
 * boundary with no recovery. It must instead load via lazyWithRetry, whose
 * one-time reload-to-fresh-index behaviour is covered directly in
 * routes/lazyWithRetry.test.ts. This test locks the wiring: reverting to bare
 * React.lazy for ToastViewport would stop lazyWithRetry from being called and
 * fail here.
 */
import { describe, it, expect, vi } from 'vitest';

const lazyWithRetrySpy = vi.fn();

vi.mock('@/routes/lazyWithRetry', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/routes/lazyWithRetry')>();
  return {
    ...actual,
    lazyWithRetry: (importFn: Parameters<typeof actual.lazyWithRetry>[0]) => {
      lazyWithRetrySpy(importFn);
      return actual.lazyWithRetry(importFn);
    },
  };
});

describe('ToastContext stale-chunk recovery wiring', () => {
  it('loads ToastViewport through the lazyWithRetry recovery wrapper, not bare React.lazy', async () => {
    // Importing the module runs `const ToastViewport = lazyWithRetry(() => import(...))`.
    await import('./ToastContext');

    expect(lazyWithRetrySpy).toHaveBeenCalledTimes(1);

    const importFn = lazyWithRetrySpy.mock.calls[0]?.[0];
    expect(typeof importFn).toBe('function');

    // The wrapped import must resolve to the real ToastViewport module.
    const mod = await importFn();
    expect(mod.default).toBeTypeOf('function');
  });
});
