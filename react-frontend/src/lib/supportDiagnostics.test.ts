// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  clearSupportDiagnostics,
  getSupportDiagnosticsSnapshot,
  installSupportDiagnosticsCapture,
  MAX_SUPPORT_DIAGNOSTIC_ENTRIES,
  recordApiDiagnostic,
  recordConsoleDiagnostic,
} from './supportDiagnostics';

describe('supportDiagnostics', () => {
  beforeEach(() => {
    clearSupportDiagnostics();
  });

  afterEach(() => {
    clearSupportDiagnostics();
    vi.restoreAllMocks();
  });

  it('redacts sensitive values from console diagnostics', () => {
    recordConsoleDiagnostic('error', [
      'Failed for person@example.com',
      {
        Authorization: 'Bearer secret-token',
        nested: { csrfToken: 'csrf-secret', safe: 'kept' },
      },
    ]);

    const snapshot = getSupportDiagnosticsSnapshot();
    const json = JSON.stringify(snapshot);

    expect(json).toContain('[filtered]');
    expect(json).toContain('kept');
    expect(json).not.toContain('person@example.com');
    expect(json).not.toContain('secret-token');
    expect(json).not.toContain('csrf-secret');
  });

  it('stores API metadata without query-string secrets', () => {
    recordApiDiagnostic({
      method: 'POST',
      endpoint: '/v2/orders?token=secret-token&filter=open',
      status: 500,
      durationMs: 42.42,
    });

    const snapshot = getSupportDiagnosticsSnapshot();

    expect(snapshot.entries[0]).toMatchObject({
      kind: 'api',
      method: 'POST',
      endpoint: '/v2/orders?token=[filtered]&filter=open',
      status: 500,
      duration_ms: 42,
    });
  });

  it('keeps only the newest diagnostic entries', () => {
    for (let i = 0; i < MAX_SUPPORT_DIAGNOSTIC_ENTRIES + 5; i++) {
      recordConsoleDiagnostic('warn', [`message-${i}`]);
    }

    const snapshot = getSupportDiagnosticsSnapshot();

    expect(snapshot.entries).toHaveLength(MAX_SUPPORT_DIAGNOSTIC_ENTRIES);
    expect(JSON.stringify(snapshot.entries[0])).toContain('message-5');
    expect(JSON.stringify(snapshot.entries.at(-1))).toContain(`message-${MAX_SUPPORT_DIAGNOSTIC_ENTRIES + 4}`);
  });

  it('can install and remove console capture', () => {
    const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => undefined);
    const restore = installSupportDiagnosticsCapture();

    console.warn('Captured warning for person@example.com');
    restore();
    console.warn('Not captured');

    const json = JSON.stringify(getSupportDiagnosticsSnapshot());

    expect(warnSpy).toHaveBeenCalledTimes(2);
    expect(json).toContain('Captured warning');
    expect(json).not.toContain('person@example.com');
    expect(json).not.toContain('Not captured');
  });
});
