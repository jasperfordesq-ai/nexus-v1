// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { sendImpersonationToken, listenForImpersonationToken } from './impersonate';

// ── Mock @/lib/api (tokenManager only) ──────────────────────────────────────
vi.mock('@/lib/api', () => ({
  tokenManager: {
    setAccessToken: vi.fn(),
  },
}));

import { tokenManager } from '@/lib/api';

// ── BroadcastChannel mock ────────────────────────────────────────────────────
// jsdom does not implement BroadcastChannel; we provide a minimal in-memory stub
// that lets tests drive onmessage handlers synchronously.

interface MockBCInstance {
  name: string;
  postMessage: ReturnType<typeof vi.fn>;
  close: ReturnType<typeof vi.fn>;
  onmessage: ((event: MessageEvent) => void) | null;
  _simulateMessage: (data: unknown) => void;
}

const bcInstances: MockBCInstance[] = [];

class MockBroadcastChannel {
  name: string;
  postMessage = vi.fn();
  onmessage: ((event: MessageEvent) => void) | null = null;
  private _closed = false;

  close = vi.fn().mockImplementation(() => {
    // Simulate real BroadcastChannel: close silences future messages
    this._closed = true;
  });

  constructor(name: string) {
    this.name = name;
    bcInstances.push(this as unknown as MockBCInstance);
  }

  _simulateMessage(data: unknown) {
    if (!this._closed && this.onmessage) {
      this.onmessage({ data } as MessageEvent);
    }
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function setHash(hash: string) {
  Object.defineProperty(window, 'location', {
    writable: true,
    value: {
      ...window.location,
      hash,
      pathname: '/hour-timebank/dashboard',
      search: '',
    },
  });
}

function clearHash() {
  setHash('');
}

// ── Setup / teardown ─────────────────────────────────────────────────────────

beforeEach(() => {
  bcInstances.length = 0;
  vi.useFakeTimers();
  vi.stubGlobal('BroadcastChannel', MockBroadcastChannel);
  // Provide a stable crypto.randomUUID
  vi.stubGlobal('crypto', {
    randomUUID: vi.fn().mockReturnValue('test-session-uuid-1234'),
  });
  // Stub window.open so it does not throw
  vi.spyOn(window, 'open').mockReturnValue(null);
  // Stub history.replaceState so clearImpersonationHash works
  vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
  clearHash();
  vi.mocked(tokenManager.setAccessToken).mockClear();
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  clearHash();
});

// ── sendImpersonationToken ───────────────────────────────────────────────────

describe('sendImpersonationToken', () => {
  it('opens a new tab with #impersonate=<sessionId> appended', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/hour-timebank/dashboard');
    expect(window.open).toHaveBeenCalledWith(
      'https://example.com/hour-timebank/dashboard#impersonate=test-session-uuid-1234',
      '_blank',
    );
  });

  it('uses & separator when URL already contains a hash fragment', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/hour-timebank/dashboard#foo=bar');
    expect(window.open).toHaveBeenCalledWith(
      'https://example.com/hour-timebank/dashboard#foo=bar&impersonate=test-session-uuid-1234',
      '_blank',
    );
  });

  it('creates a BroadcastChannel on the correct channel name', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/target');
    expect(bcInstances).toHaveLength(1);
    expect(bcInstances[0].name).toBe('nexus_impersonate');
  });

  it('sends the token when a matching ready message arrives', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/target');
    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    // Simulate new tab posting 'ready' with matching session id
    bc._simulateMessage({ type: 'ready', sessionId: 'test-session-uuid-1234' });

    expect(bc.postMessage).toHaveBeenCalledWith({
      type: 'token',
      token: 'jwt-abc',
      sessionId: 'test-session-uuid-1234',
    });
  });

  it('ignores a ready message with a mismatched session id', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/target');
    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    bc._simulateMessage({ type: 'ready', sessionId: 'wrong-session-id' });

    // postMessage should NOT have been called with a token (only close/setup overhead)
    expect(bc.postMessage).not.toHaveBeenCalled();
  });

  it('ignores a message with an unexpected type', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/target');
    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    bc._simulateMessage({ type: 'unknown', sessionId: 'test-session-uuid-1234' });

    expect(bc.postMessage).not.toHaveBeenCalled();
  });

  it('ignores a null/missing message payload', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/target');
    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    bc._simulateMessage(null);
    bc._simulateMessage(undefined);

    expect(bc.postMessage).not.toHaveBeenCalled();
  });

  it('closes the channel ~1 s after the token is sent', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/target');
    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    bc._simulateMessage({ type: 'ready', sessionId: 'test-session-uuid-1234' });

    expect(bc.close).not.toHaveBeenCalled();

    vi.advanceTimersByTime(1001);

    expect(bc.close).toHaveBeenCalledTimes(1);
  });

  it('auto-closes the channel after 30 s if the new tab never signals ready', () => {
    sendImpersonationToken('jwt-abc', 'https://example.com/target');
    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    vi.advanceTimersByTime(30_001);

    expect(bc.close).toHaveBeenCalledTimes(1);
  });

  it('uses the crypto.randomUUID fallback when randomUUID is unavailable', () => {
    // Override crypto to remove randomUUID
    vi.stubGlobal('crypto', {});

    sendImpersonationToken('jwt-fallback', 'https://example.com/target');

    // Should still open a tab (with some generated session id, just not the UUID)
    expect(window.open).toHaveBeenCalled();
    const calledUrl = vi.mocked(window.open).mock.calls[0][0] as string;
    expect(calledUrl).toContain('#impersonate=');
    // The fragment should be a non-empty string
    const fragment = calledUrl.split('#impersonate=')[1];
    expect(fragment.length).toBeGreaterThan(0);
  });
});

// ── listenForImpersonationToken ───────────────────────────────────────────────

describe('listenForImpersonationToken', () => {
  it('returns a no-op cleanup when no impersonation hash is present', () => {
    // No hash set — readSessionIdFromHash returns null
    const onReceived = vi.fn();
    const cleanup = listenForImpersonationToken(onReceived);

    // Should not open a channel
    expect(bcInstances).toHaveLength(0);

    // Cleanup should be callable without throwing
    expect(() => cleanup()).not.toThrow();
    expect(onReceived).not.toHaveBeenCalled();
  });

  it('does NOT join the channel when hash has a different key', () => {
    setHash('#foo=bar&other=value');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    expect(bcInstances).toHaveLength(0);
    expect(onReceived).not.toHaveBeenCalled();
  });

  it('joins the channel and posts "ready" with the session id from hash', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    expect(bcInstances).toHaveLength(1);
    expect(bcInstances[0].name).toBe('nexus_impersonate');
    expect(bcInstances[0].postMessage).toHaveBeenCalledWith({
      type: 'ready',
      sessionId: 'my-session-id',
    });
  });

  it('reads sessionId from #impersonate=<id> (simple hash)', () => {
    setHash('#impersonate=simple-session');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    expect(bc.postMessage).toHaveBeenCalledWith({
      type: 'ready',
      sessionId: 'simple-session',
    });
  });

  it('reads sessionId from compound hash (#foo&impersonate=<id>)', () => {
    setHash('#foo=bar&impersonate=compound-session');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    expect(bc.postMessage).toHaveBeenCalledWith({
      type: 'ready',
      sessionId: 'compound-session',
    });
  });

  it('calls tokenManager.setAccessToken and onReceived when matching token message arrives', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    bc._simulateMessage({
      type: 'token',
      token: 'the-impersonation-jwt',
      sessionId: 'my-session-id',
    });

    expect(tokenManager.setAccessToken).toHaveBeenCalledWith('the-impersonation-jwt');
    expect(onReceived).toHaveBeenCalledTimes(1);
  });

  it('calls clearImpersonationHash (replaceState) when token is received', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    bc._simulateMessage({
      type: 'token',
      token: 'the-jwt',
      sessionId: 'my-session-id',
    });

    expect(window.history.replaceState).toHaveBeenCalled();
  });

  it('closes the channel after receiving the token', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    bc._simulateMessage({
      type: 'token',
      token: 'the-jwt',
      sessionId: 'my-session-id',
    });

    expect(bc.close).toHaveBeenCalledTimes(1);
  });

  it('ignores a token message with mismatched session id', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    bc._simulateMessage({
      type: 'token',
      token: 'the-jwt',
      sessionId: 'wrong-session-id',
    });

    expect(tokenManager.setAccessToken).not.toHaveBeenCalled();
    expect(onReceived).not.toHaveBeenCalled();
    expect(bc.close).not.toHaveBeenCalled();
  });

  it('ignores a token message where token is not a string', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    bc._simulateMessage({
      type: 'token',
      token: 12345,        // not a string
      sessionId: 'my-session-id',
    });

    expect(tokenManager.setAccessToken).not.toHaveBeenCalled();
    expect(onReceived).not.toHaveBeenCalled();
  });

  it('ignores a message of wrong type', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    bc._simulateMessage({ type: 'ready', sessionId: 'my-session-id' });

    expect(tokenManager.setAccessToken).not.toHaveBeenCalled();
    expect(onReceived).not.toHaveBeenCalled();
  });

  it('ignores a null message payload', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    bc._simulateMessage(null);

    expect(onReceived).not.toHaveBeenCalled();
  });

  it('does not call onReceived more than once even if two matching messages arrive', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    const msg = { type: 'token', token: 'the-jwt', sessionId: 'my-session-id' };
    bc._simulateMessage(msg);
    // Channel is closed now; second message arrives on the (already closed) handler
    bc._simulateMessage(msg);

    expect(onReceived).toHaveBeenCalledTimes(1);
  });

  it('auto-closes the channel after 30 s without receiving a token', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    vi.advanceTimersByTime(30_001);

    expect(bc.close).toHaveBeenCalledTimes(1);
    expect(onReceived).not.toHaveBeenCalled();
  });

  it('returned cleanup function closes the channel immediately', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    const cleanup = listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    cleanup();

    expect(bc.close).toHaveBeenCalledTimes(1);
  });

  it('calling cleanup twice does not close the channel a second time', () => {
    setHash('#impersonate=my-session-id');
    const onReceived = vi.fn();
    const cleanup = listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;

    cleanup();
    cleanup(); // second call — `closed` guard should prevent a double-close

    expect(bc.close).toHaveBeenCalledTimes(1);
  });

  it('decodes a URI-encoded session id from the hash', () => {
    // Session ids from randomUUID are plain, but test defensive decode path
    setHash('#impersonate=hello%20world');
    const onReceived = vi.fn();
    listenForImpersonationToken(onReceived);

    const bc = bcInstances[0] as unknown as MockBCInstance & MockBroadcastChannel;
    expect(bc.postMessage).toHaveBeenCalledWith({
      type: 'ready',
      sessionId: 'hello world',
    });
  });
});
