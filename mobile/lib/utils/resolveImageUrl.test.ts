// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

jest.mock('@/lib/constants', () => ({ API_BASE_URL: 'https://api.project-nexus.ie' }));

import { resolveMediaUrl, resolveImageUrl } from './resolveImageUrl';

describe('resolveMediaUrl', () => {
  it('returns null for null/undefined/empty', () => {
    expect(resolveMediaUrl(null)).toBeNull();
    expect(resolveMediaUrl(undefined)).toBeNull();
    expect(resolveMediaUrl('')).toBeNull();
  });

  it('prefixes server-relative paths with the API base URL', () => {
    // Regression: voice messages stored as /uploads/2/voice_messages/x.m4a were
    // passed to expo-av as a relative URI and failed to load ("Failed").
    expect(resolveMediaUrl('/uploads/2/voice_messages/voice_abc.m4a')).toBe(
      'https://api.project-nexus.ie/uploads/2/voice_messages/voice_abc.m4a',
    );
    expect(resolveMediaUrl('/uploads/avatars/1.jpg')).toBe(
      'https://api.project-nexus.ie/uploads/avatars/1.jpg',
    );
  });

  it('passes through already-absolute URLs unchanged', () => {
    expect(resolveMediaUrl('https://cdn.example.com/a.webm')).toBe('https://cdn.example.com/a.webm');
    expect(resolveMediaUrl('http://example.com/a.jpg')).toBe('http://example.com/a.jpg');
    expect(resolveMediaUrl('data:audio/mp4;base64,AAAA')).toBe('data:audio/mp4;base64,AAAA');
  });

  it('passes through local file/content URIs unchanged (optimistic just-recorded media)', () => {
    expect(resolveMediaUrl('file:///data/user/0/app/cache/recording.m4a')).toBe(
      'file:///data/user/0/app/cache/recording.m4a',
    );
    expect(resolveMediaUrl('content://media/external/audio/123')).toBe(
      'content://media/external/audio/123',
    );
  });

  it('resolveImageUrl is an alias of resolveMediaUrl', () => {
    expect(resolveImageUrl).toBe(resolveMediaUrl);
  });
});
