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
 * 1. Admin tab calls sendImpersonationToken(token) → opens new tab
 * 2. New tab (via useImpersonationListener) signals 'ready' on the channel
 * 3. Admin tab receives 'ready' and sends the token
 * 4. New tab receives token, sets it as the auth token, reloads
 * 5. Both sides close the channel
 */

import { tokenManager } from '@/lib/api';

const CHANNEL_NAME = 'nexus_impersonate';
const HANDOFF_TIMEOUT_MS = 30_000; // 30 seconds for new tab to be ready

/**
 * Called from admin tab after receiving the impersonation token from the API.
 * Opens a new tab and waits for it to request the token via BroadcastChannel.
 */
export function sendImpersonationToken(token: string, url: string): void {
  const channel = new BroadcastChannel(CHANNEL_NAME);

  channel.onmessage = (event: MessageEvent) => {
    if (event.data?.type === 'ready') {
      channel.postMessage({ type: 'token', token });
      // Keep channel open briefly so message is delivered, then close
      setTimeout(() => channel.close(), 1000);
    }
  };

  // Open the target page in a new tab
  window.open(url, '_blank');

  // Auto-close channel if new tab never signals ready
  setTimeout(() => {
    try { channel.close(); } catch { /* already closed */ }
  }, HANDOFF_TIMEOUT_MS);
}

/**
 * Called once on app mount (in App.tsx) to listen for impersonation tokens.
 * Returns a cleanup function.
 */
export function listenForImpersonationToken(
  onReceived: () => void,
): () => void {
  const channel = new BroadcastChannel(CHANNEL_NAME);
  let closed = false;

  channel.onmessage = (event: MessageEvent) => {
    if (event.data?.type === 'token' && typeof event.data.token === 'string') {
      tokenManager.setAccessToken(event.data.token);
      cleanup();
      onReceived();
    }
  };

  // Signal to the admin tab that this tab is ready to receive
  channel.postMessage({ type: 'ready' });

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
