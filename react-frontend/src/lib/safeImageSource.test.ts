// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { safeImageSource } from './safeImageSource';

describe('safeImageSource', () => {
  it('accepts and normalises HTTP(S) and relative image sources', () => {
    expect(safeImageSource('https://cdn.example.test/image.jpg')).toBe('https://cdn.example.test/image.jpg');
    expect(safeImageSource('/uploads/image.jpg')).toBe(`${window.location.origin}/uploads/image.jpg`);
    expect(safeImageSource('/thumbnail?width=640&height=360'))
      .toBe(`${window.location.origin}/thumbnail?width=640&height=360`);
  });

  it.each([
    'javascript:alert(1)',
    'java\tscript:alert(1)',
    'data:image/svg+xml,<svg onload=alert(1)>',
    'vbscript:msgbox(1)',
    'file:///etc/passwd',
  ])('rejects an active or local scheme: %s', (source) => {
    expect(safeImageSource(source)).toBeNull();
  });

  it('allows object URLs only for explicit local-preview callers', () => {
    const objectUrl = `blob:${window.location.origin}/6d583774-8a24-440f-a2d5-e2bd4431f06e`;
    expect(safeImageSource(objectUrl)).toBeNull();
    expect(safeImageSource(objectUrl, { allowBlob: true })).toBe(objectUrl);
  });
});
