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

import { afterEach, describe, it, expect, vi } from 'vitest';
import { resolveAssetUrl, resolveAvatarUrl, resolveBrandingImageUrl, resolveThumbnailUrl, getUserDisplayName, getUserInitials } from './helpers';

afterEach(() => {
  vi.unstubAllEnvs();
});

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

  it('uses VITE_API_URL as the asset base when VITE_API_BASE is not set', async () => {
    vi.resetModules();
    vi.stubEnv('VITE_API_BASE', '');
    vi.stubEnv('VITE_API_URL', 'https://dev-api.example.test');

    const { resolveAssetUrl: resolveWithApiUrl } = await import('./helpers');

    expect(resolveWithApiUrl('/storage/tenant_2/uploads/blog/hero.webp')).toBe(
      'https://dev-api.example.test/storage/tenant_2/uploads/blog/hero.webp',
    );
  });

  it('re-routes stale localhost storage URLs through the configured API server', async () => {
    vi.resetModules();
    vi.stubEnv('VITE_API_BASE', 'https://api.example.test/api');
    vi.stubEnv('VITE_API_URL', '');

    const { resolveAssetUrl: resolveWithApiBase } = await import('./helpers');

    expect(resolveWithApiBase('http://localhost:8090/storage/tenant_2/uploads/blog/hero.webp')).toBe(
      'https://api.example.test/storage/tenant_2/uploads/blog/hero.webp',
    );
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
    expect(result).toContain('/v2/media/thumbnail?');
    expect(result).toContain('src=%2Fuploads%2Favatars%2Fuser123.jpg');
    expect(result).toContain('w=96');
    expect(result).toContain('h=96');
  });

  it('leaves external avatar URLs untouched', () => {
    const url = 'https://gravatar.com/avatar/abc123';

    expect(resolveAvatarUrl(url)).toBe(url);
  });
});

describe('resolveThumbnailUrl', () => {
  it('routes local upload paths through the thumbnail endpoint', () => {
    const result = resolveThumbnailUrl('/uploads/tenants/test/listings/image.jpg', { width: 640, height: 360 });

    expect(result).toContain('/v2/media/thumbnail?');
    expect(result).toContain('src=%2Fuploads%2Ftenants%2Ftest%2Flistings%2Fimage.jpg');
    expect(result).toContain('w=640');
    expect(result).toContain('h=360');
  });

  it('leaves external media untouched', () => {
    const url = 'https://example.com/image.jpg';

    expect(resolveThumbnailUrl(url, { width: 640, height: 360 })).toBe(url);
  });
});

describe('resolveBrandingImageUrl', () => {
  it('leaves frontend static image paths on the app origin', () => {
    expect(resolveBrandingImageUrl('/images/powered-by-nexus-light.png')).toBe('/images/powered-by-nexus-light.png');
  });

  it('routes uploaded branding paths through the API asset base', () => {
    const result = resolveBrandingImageUrl('/uploads/tenants/test/header-logo.png');

    expect(result).toContain('/uploads/tenants/test/header-logo.png');
    expect(result).not.toContain('/v2/media/thumbnail');
  });
});
