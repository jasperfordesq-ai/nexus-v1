// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  importWithChunkRecovery,
  isChunkLoadError,
  requestStaleChunkRecovery,
} from './lazyWithRetry';

describe('dynamic import chunk recovery', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    sessionStorage.clear();
  });

  it('recognizes the deployed stale-chunk error variants', () => {
    expect(isChunkLoadError(new Error('Failed to fetch dynamically imported module'))).toBe(true);
    expect(isChunkLoadError(new Error('Unable to preload CSS for /assets/app.css'))).toBe(true);

    const namedError = new Error('chunk failed');
    namedError.name = 'ChunkLoadError';
    expect(isChunkLoadError(namedError)).toBe(true);
    expect(isChunkLoadError(new Error('ordinary API failure'))).toBe(false);
  });

  it('returns a successfully imported module unchanged', async () => {
    const module = { value: 'loaded' };
    await expect(importWithChunkRecovery(async () => module)).resolves.toBe(module);
  });

  it('preserves active text entry and surfaces a retryable chunk failure', async () => {
    const input = document.createElement('input');
    document.body.append(input);
    input.focus();
    const error = new Error('Loading chunk 42 failed');

    expect(requestStaleChunkRecovery(error)).toBe(false);
    expect(sessionStorage.length).toBe(0);
    await expect(importWithChunkRecovery(async () => Promise.reject(error))).rejects.toBe(error);
  });

  it('reloads once per route within the recovery window', () => {
    const error = new Error('Failed to fetch dynamically imported module');
    const reload = vi.fn();

    expect(requestStaleChunkRecovery(error, { reload, now: () => 1_000 })).toBe(true);
    expect(requestStaleChunkRecovery(error, { reload, now: () => 2_000 })).toBe(false);
    expect(reload).toHaveBeenCalledTimes(1);

    expect(requestStaleChunkRecovery(error, { reload, now: () => 32_000 })).toBe(true);
    expect(reload).toHaveBeenCalledTimes(2);
  });
});
