// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

describe('useTurnstile lazy loading', () => {
  beforeEach(() => {
    vi.resetModules();
    vi.stubEnv('VITE_TURNSTILE_SITE_KEY', 'test-site-key');
    vi.useFakeTimers();
    document.head.innerHTML = '';
    document.body.innerHTML = '';
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
    document.head.innerHTML = '';
    document.body.innerHTML = '';
  });

  it('does not inject Cloudflare Turnstile until explicitly enabled', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { result, rerender } = renderHook(
      ({ enabled }) => useTurnstile({ enabled }),
      { initialProps: { enabled: false } },
    );

    await act(async () => {
      vi.runAllTimers();
    });

    expect(result.current.siteKey).toBe('test-site-key');
    expect(result.current.status).toBe('idle');
    expect(document.getElementById('cf-turnstile-script')).toBeNull();

    rerender({ enabled: true });

    expect(document.getElementById('cf-turnstile-script')).not.toBeNull();
  });
});
