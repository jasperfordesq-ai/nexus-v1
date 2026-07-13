// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const STORAGE_PREFIX = 'nexus_oauth_browser_verifier:';
const CHALLENGE_PATTERN = /^[A-Za-z0-9_-]{43}$/;

function base64Url(bytes: Uint8Array): string {
  const binary = Array.from(bytes, (byte) => String.fromCharCode(byte)).join('');
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/u, '');
}

/**
 * Create a per-tab OAuth browser proof and retain only its raw verifier in
 * sessionStorage. The public SHA-256 challenge is safe to send to the API and
 * include in signed state or callback URLs.
 */
export async function createOAuthBrowserBinding(): Promise<{ challenge: string }> {
  if (!globalThis.crypto?.getRandomValues || !globalThis.crypto?.subtle) {
    throw new Error('secure_browser_crypto_unavailable');
  }

  const random = new Uint8Array(32);
  globalThis.crypto.getRandomValues(random);
  const verifier = base64Url(random);
  const digest = await globalThis.crypto.subtle.digest(
    'SHA-256',
    new TextEncoder().encode(verifier),
  );
  const challenge = base64Url(new Uint8Array(digest));

  window.sessionStorage.setItem(`${STORAGE_PREFIX}${challenge}`, verifier);
  return { challenge };
}

export function getOAuthBrowserVerifier(challenge: string | null): string | null {
  if (!challenge || !CHALLENGE_PATTERN.test(challenge)) return null;

  try {
    return window.sessionStorage.getItem(`${STORAGE_PREFIX}${challenge}`);
  } catch {
    return null;
  }
}

export function clearOAuthBrowserVerifier(challenge: string | null): void {
  if (!challenge || !CHALLENGE_PATTERN.test(challenge)) return;

  try {
    window.sessionStorage.removeItem(`${STORAGE_PREFIX}${challenge}`);
  } catch {
    // Storage cleanup is best-effort after a server-confirmed one-time exchange.
  }
}
