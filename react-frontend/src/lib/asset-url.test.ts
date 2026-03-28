// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for resolveAssetUrl and resolveAvatarUrl from helpers.ts
 *
 * These functions handle URL resolution for images/assets served from
 * the PHP backend. They are critical because:
 * 1. They fix stale domain references stored in the database
 * 2. They handle relative, absolute, and protocol-relative URLs
 * 3. They provide fallback defaults for missing avatars
 */

import { describe, it, expect } from 'vitest';
import { resolveAssetUrl, resolveAvatarUrl, getUserDisplayName, getUserInitials } from './helpers';

describe('resolveAssetUrl', () => {
  it('returns empty string for null input', () => {
    expect(resolveAssetUrl(null)).toBe('');
  });

  it('returns empty string for undefined input', () => {
    expect(resolveAssetUrl(undefined)).toBe('');
  });

  it('returns empty string for empty string input', () => {
    expect(resolveAssetUrl('')).toBe('');
  });

  it('returns fallback when url is null', () => {
    expect(resolveAssetUrl(null, '/default.png')).toBe('/default.png');
  });

  it('returns fallback when url is empty', () => {
    expect(resolveAssetUrl('', '/default.png')).toBe('/default.png');
  });

  it('returns absolute URL as-is when not an upload path', () => {
    const url = 'https://example.com/some-image.png';
    expect(resolveAssetUrl(url)).toBe(url);
  });

  it('prefixes relative path with API base', () => {
    const result = resolveAssetUrl('/uploads/avatars/user.jpg');
    // Should have some base prepended (in test env, API_ASSET_BASE may be empty)
    expect(result).toContain('/uploads/avatars/user.jpg');
  });

  it('adds leading slash to bare relative paths', () => {
    const result = resolveAssetUrl('uploads/file.pdf');
    expect(result).toContain('/uploads/file.pdf');
  });

  it('converts protocol-relative URLs to https', () => {
    const result = resolveAssetUrl('//cdn.example.com/image.png');
    expect(result).toBe('https://cdn.example.com/image.png');
  });

  it('re-routes stale domain upload paths through API server', () => {
    // Legacy DB rows may have the old frontend domain stored
    const result = resolveAssetUrl('https://hour-timebank.ie/uploads/avatars/old.jpg');
    expect(result).toContain('/uploads/avatars/old.jpg');
    // Should NOT contain the stale domain
    expect(result).not.toContain('hour-timebank.ie');
  });

  it('leaves non-upload absolute URLs from external domains untouched', () => {
    const url = 'https://gravatar.com/avatar/abc123';
    expect(resolveAssetUrl(url)).toBe(url);
  });
});

describe('resolveAvatarUrl', () => {
  it('returns default avatar URL for null input', () => {
    const result = resolveAvatarUrl(null);
    expect(result).toContain('default_avatar.png');
  });

  it('returns default avatar URL for undefined input', () => {
    const result = resolveAvatarUrl(undefined);
    expect(result).toContain('default_avatar.png');
  });

  it('returns resolved URL for valid avatar path', () => {
    const result = resolveAvatarUrl('/uploads/avatars/user123.jpg');
    expect(result).toContain('/uploads/avatars/user123.jpg');
  });
});
