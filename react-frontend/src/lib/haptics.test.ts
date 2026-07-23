// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, describe, expect, it, vi } from 'vitest';

import { triggerHaptic } from './haptics';

describe('triggerHaptic', () => {
  afterEach(() => {
    delete (navigator as { vibrate?: unknown }).vibrate;
  });

  it('vibrates with the light pattern by default', () => {
    const vibrate = vi.fn();
    (navigator as { vibrate?: unknown }).vibrate = vibrate;

    triggerHaptic();

    expect(vibrate).toHaveBeenCalledWith(10);
  });

  it('vibrates with the medium pattern when requested', () => {
    const vibrate = vi.fn();
    (navigator as { vibrate?: unknown }).vibrate = vibrate;

    triggerHaptic('medium');

    expect(vibrate).toHaveBeenCalledWith(20);
  });

  it('is a no-op when the Vibration API is unavailable (iOS Safari)', () => {
    expect(() => triggerHaptic('light')).not.toThrow();
  });

  it('swallows vibration failures from permissions policy', () => {
    (navigator as { vibrate?: unknown }).vibrate = () => {
      throw new Error('blocked by permissions policy');
    };

    expect(() => triggerHaptic('medium')).not.toThrow();
  });
});
