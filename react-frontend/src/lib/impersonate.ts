// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Secure impersonation token handoff between admin tab and new tab.
 *
 * Uses BroadcastChannel API for memory-only transfer — the token never
 * touches localStorage, sessionStorage, URL params, or any persistent store.
 *
 * Flow:
 * 1. Admin tab generates a unique session id, opens the new tab with
 *    `#impersonate=<sessionId>` so the listener can match its own session
 * 2. New tab's listener detects the hash, joins the channel, posts 'ready'
 *    with the session id
 * 3. Admin tab receives 'ready' (matching session id) and sends the token,
 *    also tagged with the session id
 * 4. New tab receives token, sets it as the auth token, reloads
 * 5. Both sides close the channel
 *
 * The session id is the critical fix: BroadcastChannel delivers messages to
 * EVERY same-origin instance, so without per-session tagging the admin
 * tab's own listener (registered by TenantShell) would catch the broadcast
 * and stomp the admin's auth, logging both tabs out.
 */

import { tokenManager } from '@/lib/api';

const CHANNEL_NAME = 'nexus_impersonate';
const HANDOFF_TIMEOUT_MS = 30_000; // 30 seconds for new tab to be ready
const HASH_KEY = 'impersonate';

function generateSessionId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return Math.random().toString(36).slice(2) + Date.now().toString(36);
}

/**
 * Called from admin tab after receiving the impersonation token from the API.
 * Opens a new tab and waits for it to request the token via BroadcastChannel.
 *
 * @param token  The single-use impersonation JWT
 * @param url    Target URL — should already be on the impersonated user's tenant
 *               (e.g. `${origin}/${targetTenantSlug}/dashboard`)
 */
export function sendImpersonationToken(token: string, url: string): void {
  const sessionId = generateSessionId();
  const channel = new BroadcastChannel(CHANNEL_NAME);

  channel.onmessage = (event: MessageEvent) => {
    if (event.data?.type === 'ready' && event.data?.sessionId === sessionId) {
      channel.postMessage({ type: 'token', token, sessionId });
      // Keep channel open briefly so message is delivered, then close
      setTimeout(() => channel.close(), 1000);
    }
  };

  // Append the session id as a hash fragment — survives the slug-recovery
  // redirect and identifies this tab as an impersonation target on mount.
  const separator = url.includes('#') ? '&' : '#';
  const targetUrl = `${url}${separator}${HASH_KEY}=${sessionId}`;
  window.open(targetUrl, '_blank');

  // Auto-close channel if new tab never signals ready
  setTimeout(() => {
    try { channel.close(); } catch { /* already closed */ }
  }, HANDOFF_TIMEOUT_MS);
}

/**
 * Read the impersonation session id from the current URL hash, if present.
 * Returns null when this tab is not an impersonation target.
 */
function readSessionIdFromHash(): string | null {
  if (typeof window === 'undefined') return null;
  const hash = window.location.hash.replace(/^#/, '');
  if (!hash) return null;
  // Support both `#impersonate=<id>` and `#foo&impersonate=<id>` shapes
  for (const part of hash.split('&')) {
    const [k, v] = part.split('=');
    if (k === HASH_KEY && v) return decodeURIComponent(v);
  }
  return null;
}

/**
 * Strip the impersonation hash from the URL once consumed. Avoids the hash
 * sticking around after reload (which could trigger another handshake).
 */
function clearImpersonationHash(): void {
  try {
    const hash = window.location.hash.replace(/^#/, '');
    const filtered = hash.split('&').filter(p => !p.startsWith(`${HASH_KEY}=`));
    const newHash = filtered.length ? '#' + filtered.join('&') : '';
    const newUrl = window.location.pathname + window.location.search + newHash;
    window.history.replaceState(null, '', newUrl);
  } catch { /* best-effort */ }
}

/**
 * Called from TenantShell on mount. Only joins the broadcast channel when
 * the URL hash carries an impersonation session id — that means this tab
 * was opened by the admin tab specifically as an impersonation target.
 *
 * Without the session-id guard, every TenantShell mount (including the
 * admin's own tab) would catch the broadcast and stomp its own auth.
 */
export function listenForImpersonationToken(
  onReceived: () => void,
): () => void {
  const sessionId = readSessionIdFromHash();
  if (!sessionId) {
    // Not an impersonation target — do nothing, return a no-op cleanup.
    return () => {};
  }

  const channel = new BroadcastChannel(CHANNEL_NAME);
  let closed = false;

  channel.onmessage = (event: MessageEvent) => {
    if (
      event.data?.type === 'token'
      && typeof event.data.token === 'string'
      && event.data.sessionId === sessionId
    ) {
      tokenManager.setAccessToken(event.data.token);
      clearImpersonationHash();
      cleanup();
      onReceived();
    }
  };

  // Signal to the admin tab that this tab is ready to receive (with our id)
  channel.postMessage({ type: 'ready', sessionId });

  // Auto-close after timeout
  const timeout = setTimeout(() => cleanup(), HANDOFF_TIMEOUT_MS);

  function cleanup() {
    if (closed) return;
    closed = true;
    clearTimeout(timeout);
    try { channel.close(); } catch { /* already closed */ }
  }

  return cleanup;
}
