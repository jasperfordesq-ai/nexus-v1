// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * haptics — Tiny wrapper around the Vibration API for native-feel feedback.
 *
 * Android Chrome/WebView vibrates; iOS Safari has no Vibration API, so every
 * call degrades to a silent no-op there. Never throws: haptics are decoration,
 * a failure must not break the interaction that triggered it.
 */

export type HapticStyle = 'light' | 'medium';

const PATTERNS: Record<HapticStyle, number> = {
  /** Subtle tick — gesture thresholds (pull-to-refresh commit) */
  light: 10,
  /** Firmer pulse — long-press activation, matching Android context menus */
  medium: 20,
};

export function triggerHaptic(style: HapticStyle = 'light'): void {
  if (typeof navigator === 'undefined' || typeof navigator.vibrate !== 'function') {
    return;
  }

  try {
    navigator.vibrate(PATTERNS[style]);
  } catch {
    // Vibration can be blocked by permissions policy in embedded contexts.
  }
}
