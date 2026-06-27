// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import { runConfirmedMutation } from './confirmedMutation';

describe('runConfirmedMutation', () => {
  it('runs onConfirmed (not onRejected) and returns true on a confirmed success', async () => {
    const onConfirmed = vi.fn();
    const onRejected = vi.fn();

    const result = await runConfirmedMutation(
      () => Promise.resolve({ success: true }),
      { onConfirmed, onRejected },
    );

    expect(result).toBe(true);
    expect(onConfirmed).toHaveBeenCalledTimes(1);
    expect(onRejected).not.toHaveBeenCalled();
  });

  it('does NOT run onConfirmed on a { success: false } response (no fake success)', async () => {
    const onConfirmed = vi.fn();
    const onRejected = vi.fn();

    const result = await runConfirmedMutation(
      () => Promise.resolve({ success: false }),
      { onConfirmed, onRejected },
    );

    expect(result).toBe(false);
    expect(onConfirmed).not.toHaveBeenCalled();
    expect(onRejected).toHaveBeenCalledTimes(1);
  });

  it('treats a thrown error as a rejection and reports it', async () => {
    const onConfirmed = vi.fn();
    const onRejected = vi.fn();
    const onError = vi.fn();
    const boom = new Error('network down');

    const result = await runConfirmedMutation(
      () => Promise.reject(boom),
      { onConfirmed, onRejected, onError },
    );

    expect(result).toBe(false);
    expect(onConfirmed).not.toHaveBeenCalled();
    expect(onRejected).toHaveBeenCalledTimes(1);
    expect(onError).toHaveBeenCalledWith(boom);
  });
});
