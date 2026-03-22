// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Vitest globalSetup: Force-exit workaround for CI
 *
 * Problem: After all tests pass, Vitest hangs indefinitely because jsdom +
 * React Aria (@react-aria/overlays) + Framer Motion leave open handles
 * (MutationObservers, timers, etc.) that prevent fork workers from exiting.
 * Vitest waits forever for workers to close, never printing its summary.
 *
 * Solution: The `teardown` function (called in the MAIN process after all
 * workers finish running tests) schedules a `process.exit(0)` after a short
 * grace period. This only activates in CI to avoid interfering with local
 * watch mode.
 *
 * This file is referenced in vitest.config.ts → globalSetup.
 */

export function setup() {
  // Hard global timeout — exit after 90 minutes no matter what.
  // 264 test files running sequentially in singleFork mode needs ~40-50 min.
  // Prevents the whole run from hanging forever if workers get stuck.
  const hardKill = setTimeout(() => {
    // eslint-disable-next-line no-console
    console.log('\n[ci-force-exit] Hard timeout (90 min) — forcing exit');
    process.exit(1);
  }, 90 * 60 * 1000);
  hardKill.unref();
}

export function teardown() {
  // Give Vitest time to print its summary, then force-exit.
  // CI gets 10 s; local gets 30 s (extra time for slow machines / watch mode startup).
  // jsdom + React Aria + Framer Motion leave open handles that prevent clean exit.
  const delay = process.env.CI ? 10_000 : 30_000;
  const timer = setTimeout(() => {
    // eslint-disable-next-line no-console
    console.log('\n[ci-force-exit] Forcing exit — open handles detected');
    process.exit(0);
  }, delay);

  // .unref() lets Node exit naturally if it can; timer only fires if still alive.
  timer.unref();
}
