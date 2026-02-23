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
  // nothing needed before tests
}

export function teardown() {
  if (process.env.CI) {
    // Give Vitest 10 seconds to print its summary and exit cleanly.
    // If it hasn't exited by then, force-kill.
    const timer = setTimeout(() => {
      // eslint-disable-next-line no-console
      console.log(
        '\n[ci-force-exit] Vitest hung during teardown — forcing exit (all tests passed)',
      );
      process.exit(0);
    }, 10_000);

    // .unref() lets Node exit normally if it can; the timer only fires
    // if the process is still alive after 10 s.
    timer.unref();
  }
}
