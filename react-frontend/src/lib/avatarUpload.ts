// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const ALLOWED_AVATAR_MIME_TYPES = new Set([
  'image/jpeg',
  'image/png',
  'image/gif',
  'image/webp',
]);

const ALLOWED_AVATAR_EXTENSIONS = new Set([
  'jpg',
  'jpeg',
  'png',
  'gif',
  'webp',
]);

export const AVATAR_MAX_BYTES = 5 * 1024 * 1024;

// Must stay the bare wildcard: a concrete MIME/extension list makes mobile
// browsers open a document picker WITHOUT the "Take Photo" camera option.
// Type enforcement happens in isSupportedAvatarFile() and server-side.
export const AVATAR_UPLOAD_ACCEPT = 'image/*';

function getExtension(name: string): string {
  const lastDot = name.lastIndexOf('.');
  return lastDot >= 0 ? name.slice(lastDot + 1).toLowerCase() : '';
}

export function isSupportedAvatarFile(file: Pick<File, 'name' | 'type'>): boolean {
  const extension = getExtension(file.name);
  const mimeType = file.type.toLowerCase();

  if (!ALLOWED_AVATAR_EXTENSIONS.has(extension)) {
    return false;
  }

  return mimeType === '' || ALLOWED_AVATAR_MIME_TYPES.has(mimeType);
}

export function isAvatarFileTooLarge(file: Pick<File, 'size'>): boolean {
  return file.size > AVATAR_MAX_BYTES;
}
