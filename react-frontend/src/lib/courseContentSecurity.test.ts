// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { normalizeCourseMediaUrl } from './courseContentSecurity';

describe('normalizeCourseMediaUrl', () => {
  it('allows http and https media URLs', () => {
    expect(normalizeCourseMediaUrl('https://example.com/lesson.pdf')).toBe('https://example.com/lesson.pdf');
    expect(normalizeCourseMediaUrl('http://example.com/video.mp4')).toBe('http://example.com/video.mp4');
  });

  it('blocks scriptable and non-web URL schemes', () => {
    expect(normalizeCourseMediaUrl('javascript:alert(1)')).toBeNull();
    expect(normalizeCourseMediaUrl('data:text/html,<script>alert(1)</script>')).toBeNull();
    expect(normalizeCourseMediaUrl('file:///C:/secret.pdf')).toBeNull();
  });

  it('returns null for malformed URLs', () => {
    expect(normalizeCourseMediaUrl('not a url')).toBeNull();
    expect(normalizeCourseMediaUrl('')).toBeNull();
  });
});
