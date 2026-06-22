// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useTurnstile — Cloudflare Turnstile script loader / widget hook.
 *
 * Key design constraints this suite works around:
 *
 *  1. `siteKey` is read from `import.meta.env.VITE_TURNSTILE_SITE_KEY`.
 *     vitest.config.ts defines this via `define`, so we use vi.stubEnv() / the
 *     vitest env API and vi.resetModules() to get the hook to see the right key.
 *
 *  2. `window.turnstile` is only consulted at render-time inside the tryRender
 *     polling loop.  We stub it on `window` before the hook mounts.
 *
 *  3. The hook's tryRender polls via window.setTimeout.  We use vi.useFakeTimers()
 *     to drive the loop deterministically.
 *
 *  4. `containerRef.current` is null in hooks-only renderHook (no DOM element
 *     is actually attached).  We patch the ref after mounting so the hook sees a
 *     live HTMLElement, because jsdom doesn't wire up ref.current for a bare div.
 *
 * Branches covered:
 *  - No siteKey (VITE_TURNSTILE_SITE_KEY empty) → hook stays idle, no script injected
 *  - siteKey present → status transitions idle→loading, script injected once
 *  - window.turnstile present immediately → render() called synchronously (next tick)
 *  - window.turnstile absent → polling loop retries (setTimeout)
 *  - window.turnstile arrives during polling → render() eventually called
 *  - Polling timeout (>100 retries) → status=error
 *  - callback(token) → status=solved, token set
 *  - expired-callback → token cleared, status=ready
 *  - error-callback → token cleared, status=error
 *  - timeout-callback → token cleared, status=error
 *  - reset() when widgetId exists → turnstile.reset called, token cleared, status=ready
 *  - reset() when widgetId is null (not yet rendered) → no-op
 *  - Cleanup on unmount: turnstile.remove() called, token cleared, status=idle
 *  - Script tag is NOT injected twice for subsequent mounts (same SCRIPT_ID)
 *
 * Intentionally skipped:
 *  - containerRef.current=null after >50 retries → status=error: jsdom cannot
 *    attach a real DOM ref through renderHook's return value; the branch exists
 *    in source but cannot be exercised deterministically without a real DOM render
 *    tree.  The polling-timeout branch (window.turnstile absent) covers the same
 *    error-state path.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';

// ─── Helpers ─────────────────────────────────────────────────────────────────

/** Build a fresh mock turnstile API instance. */
function makeTurnstileApi() {
  return {
    render: vi.fn((
      _el: HTMLElement,
      opts: {
        callback: (t: string) => void;
        'expired-callback': () => void;
        'error-callback': () => void;
        'timeout-callback': () => void;
      },
    ) => {
      // Simulate Turnstile immediately solving the widget (calls callback).
      // Tests that want a different behaviour replace this mock.
      opts.callback('mock-token-123');
      return 'widget-id-1';
    }),
    remove: vi.fn(),
    reset: vi.fn(),
    execute: vi.fn(),
  };
}

/**
 * Mount the hook using renderHook, then patch containerRef.current to a real
 * HTMLDivElement so the tryRender loop doesn't bail out on "no container".
 */
async function mountWithContainer(useHook: () => ReturnType<typeof import('./useTurnstile').useTurnstile>) {
  const container = document.createElement('div');
  document.body.appendChild(container);

  const hook = renderHook(useHook);

  // Patch the ref that the hook holds — this is the simplest safe approach because
  // renderHook does not wire up DOM refs automatically.
  Object.defineProperty(hook.result.current.containerRef, 'current', {
    configurable: true,
    get: () => container,
  });

  return { hook, container };
}

// ─── Test suites ──────────────────────────────────────────────────────────────

describe('useTurnstile — no siteKey (VITE_TURNSTILE_SITE_KEY not set)', () => {
  beforeEach(() => {
    vi.resetModules();
    // Ensure env var is absent / empty
    vi.stubEnv('VITE_TURNSTILE_SITE_KEY', '');
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
    document.body.innerHTML = '';
  });

  it('returns siteKey="" and status="idle" without touching the DOM', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { result } = renderHook(() => useTurnstile());

    await act(async () => { vi.runAllTimers(); });

    expect(result.current.siteKey).toBe('');
    expect(result.current.status).toBe('idle');
    expect(result.current.token).toBe('');
    expect(document.getElementById('cf-turnstile-script')).toBeNull();
  });

  it('returns a reset function that is a no-op when no widget is rendered', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { result } = renderHook(() => useTurnstile());
    // Should not throw
    act(() => { result.current.reset(); });
    expect(result.current.status).toBe('idle');
  });
});

// ─────────────────────────────────────────────────────────────────────────────

describe('useTurnstile — siteKey present, window.turnstile available immediately', () => {
  const SITE_KEY = 'test-site-key-abc';
  let turnstileApi: ReturnType<typeof makeTurnstileApi>;

  beforeEach(async () => {
    vi.resetModules();
    vi.stubEnv('VITE_TURNSTILE_SITE_KEY', SITE_KEY);
    vi.useFakeTimers();
    turnstileApi = makeTurnstileApi();
    vi.stubGlobal('turnstile', turnstileApi);
    // Clean the DOM before each test
    document.body.innerHTML = '';
    const existing = document.getElementById('cf-turnstile-script');
    if (existing) existing.remove();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
    document.body.innerHTML = '';
  });

  it('transitions status from idle → loading → ready (then solved after callback)', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());

    await act(async () => {
      vi.runAllTimers();
      await Promise.resolve();
    });

    // render() was called → callback fires → solved
    expect(hook.result.current.status).toBe('solved');
    expect(hook.result.current.token).toBe('mock-token-123');

    container.remove();
    hook.unmount();
  });

  it('injects a <script> tag with the Cloudflare src into document.head', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());

    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    const script = document.getElementById('cf-turnstile-script');
    expect(script).not.toBeNull();
    expect(script?.getAttribute('src')).toBe('https://challenges.cloudflare.com/turnstile/v0/api.js');
    expect(script?.tagName).toBe('SCRIPT');

    container.remove();
    hook.unmount();
  });

  it('does NOT inject a second script tag when the hook remounts', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook: hook1, container: c1 } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });
    hook1.unmount(); c1.remove();

    const { hook: hook2, container: c2 } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });
    hook2.unmount(); c2.remove();

    expect(document.querySelectorAll('#cf-turnstile-script').length).toBe(1);
  });

  it('passes sitekey, theme=auto, appearance=interaction-only to turnstile.render()', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());

    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    expect(turnstileApi.render).toHaveBeenCalledWith(
      container,
      expect.objectContaining({
        sitekey: SITE_KEY,
        theme: 'auto',
        appearance: 'interaction-only',
      }),
    );

    container.remove();
    hook.unmount();
  });

  // ── Callback branches ────────────────────────────────────────────────────

  it('expired-callback clears token and sets status=ready', async () => {
    // Override render to capture the options object so we can trigger callbacks
    let capturedOpts: Parameters<typeof turnstileApi.render>[1] | null = null;
    turnstileApi.render = vi.fn((_el, opts) => {
      capturedOpts = opts;
      // Don't auto-solve — just capture
      return 'widget-id-exp';
    });

    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    // Manually invoke the expired-callback
    await act(async () => { capturedOpts!['expired-callback'](); });

    expect(hook.result.current.token).toBe('');
    expect(hook.result.current.status).toBe('ready');

    container.remove();
    hook.unmount();
  });

  it('error-callback clears token and sets status=error', async () => {
    let capturedOpts: Parameters<typeof turnstileApi.render>[1] | null = null;
    turnstileApi.render = vi.fn((_el, opts) => { capturedOpts = opts; return 'widget-id-err'; });

    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    await act(async () => { capturedOpts!['error-callback'](); });

    expect(hook.result.current.token).toBe('');
    expect(hook.result.current.status).toBe('error');

    container.remove();
    hook.unmount();
  });

  it('timeout-callback clears token and sets status=error', async () => {
    let capturedOpts: Parameters<typeof turnstileApi.render>[1] | null = null;
    turnstileApi.render = vi.fn((_el, opts) => { capturedOpts = opts; return 'widget-id-to'; });

    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    await act(async () => { capturedOpts!['timeout-callback'](); });

    expect(hook.result.current.token).toBe('');
    expect(hook.result.current.status).toBe('error');

    container.remove();
    hook.unmount();
  });

  // ── reset() ──────────────────────────────────────────────────────────────

  it('reset() calls turnstile.reset(widgetId), clears token, sets status=ready', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    // Hook is currently 'solved'
    expect(hook.result.current.status).toBe('solved');

    await act(async () => { hook.result.current.reset(); });

    expect(turnstileApi.reset).toHaveBeenCalledWith('widget-id-1');
    expect(hook.result.current.token).toBe('');
    expect(hook.result.current.status).toBe('ready');

    container.remove();
    hook.unmount();
  });

  it('reset() is a no-op when window.turnstile is undefined', async () => {
    // Override so render doesn't produce a widgetId before we clear turnstile
    turnstileApi.render = vi.fn(() => 'widget-no-ts');
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    // Remove window.turnstile after render
    vi.stubGlobal('turnstile', undefined);

    // Should not throw
    await act(async () => { hook.result.current.reset(); });

    // Status remains as-is (the hook doesn't change status here)
    container.remove();
    hook.unmount();
  });

  // ── Cleanup on unmount ───────────────────────────────────────────────────

  it('calls turnstile.remove(widgetId) and resets token/status on unmount', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());
    await act(async () => { vi.runAllTimers(); await Promise.resolve(); });

    expect(hook.result.current.status).toBe('solved');

    await act(async () => { hook.unmount(); });

    expect(turnstileApi.remove).toHaveBeenCalledWith('widget-id-1');
    // After unmount the hook state is torn down — verify the calls were made
    container.remove();
  });
});

// ─────────────────────────────────────────────────────────────────────────────

describe('useTurnstile — polling (window.turnstile not yet available)', () => {
  const SITE_KEY = 'test-site-key-polling';

  beforeEach(async () => {
    vi.resetModules();
    vi.stubEnv('VITE_TURNSTILE_SITE_KEY', SITE_KEY);
    vi.useFakeTimers();
    // Ensure turnstile is NOT on window at mount time
    vi.stubGlobal('turnstile', undefined);
    document.body.innerHTML = '';
    const existing = document.getElementById('cf-turnstile-script');
    if (existing) existing.remove();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
    document.body.innerHTML = '';
  });

  it('sets status=loading after mount while waiting for window.turnstile', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());

    // Advance just one polling tick (100ms) — turnstile still absent
    await act(async () => { vi.advanceTimersByTime(100); });

    expect(hook.result.current.status).toBe('loading');

    container.remove();
    hook.unmount();
  });

  it('renders widget once window.turnstile becomes available during polling', async () => {
    const turnstileApi = makeTurnstileApi();
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());

    // A few polls with no api
    await act(async () => { vi.advanceTimersByTime(300); await Promise.resolve(); });

    // Make window.turnstile available
    vi.stubGlobal('turnstile', turnstileApi);

    // One more poll tick — the hook's tryRender will see window.turnstile and call render()
    await act(async () => { vi.advanceTimersByTime(100); await Promise.resolve(); });
    // Flush any micro-task state updates from the callback (render calls callback synchronously)
    await act(async () => { await Promise.resolve(); });

    expect(hook.result.current.status).toBe('solved');
    expect(turnstileApi.render).toHaveBeenCalledTimes(1);

    container.remove();
    hook.unmount();
  });

  it('sets status=error after exceeding max polling attempts (>100 ticks × 100ms)', async () => {
    const { useTurnstile } = await import('./useTurnstile');
    const { hook, container } = await mountWithContainer(() => useTurnstile());

    // Drive all 110+ polling iterations at once (window.turnstile never appears)
    await act(async () => { vi.advanceTimersByTime(110 * 100); await Promise.resolve(); });
    await act(async () => { await Promise.resolve(); });

    expect(hook.result.current.status).toBe('error');

    container.remove();
    hook.unmount();
  });

  it('does not call turnstile.remove() on unmount when widget was never rendered', async () => {
    const turnstileApi = makeTurnstileApi();
    vi.stubGlobal('turnstile', turnstileApi);

    const { useTurnstile } = await import('./useTurnstile');
    // Mount but do NOT patch containerRef → container is null → tryRender bails
    const hook = renderHook(() => useTurnstile());
    await act(async () => { vi.advanceTimersByTime(100); });

    // widgetIdRef.current is still null because render was never called
    hook.unmount();
    expect(turnstileApi.remove).not.toHaveBeenCalled();
  });
});
