// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import {
  AVATAR_MAX_BYTES,
  AVATAR_UPLOAD_ACCEPT,
  isAvatarFileTooLarge,
  isSupportedAvatarFile,
} from './avatarUpload';

function file(name: string, type = ''): File {
  return new File(['avatar'], name, { type });
}

describe('avatarUpload', () => {
  it('accepts the same avatar formats as the backend image pipeline', () => {
    expect(isSupportedAvatarFile(file('profile.jpg', 'image/jpeg'))).toBe(true);
    expect(isSupportedAvatarFile(file('profile.jpeg', 'image/jpeg'))).toBe(true);
    expect(isSupportedAvatarFile(file('profile.png', 'image/png'))).toBe(true);
    expect(isSupportedAvatarFile(file('profile.gif', 'image/gif'))).toBe(true);
    expect(isSupportedAvatarFile(file('profile.webp', 'image/webp'))).toBe(true);
  });

  it('rejects image-like formats the backend avatar endpoint cannot store', () => {
    expect(isSupportedAvatarFile(file('profile.heic', 'image/heic'))).toBe(false);
    expect(isSupportedAvatarFile(file('profile.avif', 'image/avif'))).toBe(false);
    expect(isSupportedAvatarFile(file('profile.svg', 'image/svg+xml'))).toBe(false);
  });

  it('requires a supported file extension even when the browser supplies a valid MIME type', () => {
    expect(isSupportedAvatarFile(file('profile.jfif', 'image/jpeg'))).toBe(false);
    expect(isSupportedAvatarFile(file('profile', 'image/jpeg'))).toBe(false);
  });

  it('allows extension-only checks when the browser omits a MIME type', () => {
    expect(isSupportedAvatarFile(file('PROFILE.PNG'))).toBe(true);
  });

  it('keeps the file-picker accept list aligned with the allowed formats', () => {
    expect(AVATAR_UPLOAD_ACCEPT).toContain('image/jpeg');
    expect(AVATAR_UPLOAD_ACCEPT).toContain('.webp');
    expect(AVATAR_UPLOAD_ACCEPT).not.toContain('image/*');
  });

  it('flags avatars larger than the frontend limit', () => {
    expect(isAvatarFileTooLarge({ size: AVATAR_MAX_BYTES })).toBe(false);
    expect(isAvatarFileTooLarge({ size: AVATAR_MAX_BYTES + 1 })).toBe(true);
  });
});
