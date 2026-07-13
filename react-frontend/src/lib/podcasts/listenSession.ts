// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Stable per-episode listen-session ids for the analytics dedupe window.
// sessionStorage-backed so refreshes within a browser session don't inflate
// listen counts; falls back to an in-memory map when storage is unavailable.

const fallbackListenSessions = new Map<number, string>();

function createListenSessionId(episodeId: number): string {
  const cryptoApi = globalThis.crypto;
  const randomId = cryptoApi?.randomUUID?.() ?? (() => {
    if (!cryptoApi?.getRandomValues) {
      throw new Error('A cryptographically secure random source is required for podcast listen sessions.');
    }
    const bytes = cryptoApi.getRandomValues(new Uint8Array(16));
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
  })();
  return `${episodeId}:${randomId}`;
}

export function listenSessionId(episodeId: number): string {
  const key = `nexus:podcasts:${episodeId}:listen-session`;

  try {
    const existing = window.sessionStorage.getItem(key);
    if (existing) return existing;

    const next = createListenSessionId(episodeId);
    window.sessionStorage.setItem(key, next);
    return next;
  } catch {
    const existing = fallbackListenSessions.get(episodeId);
    if (existing) return existing;

    const next = createListenSessionId(episodeId);
    fallbackListenSessions.set(episodeId, next);
    return next;
  }
}
