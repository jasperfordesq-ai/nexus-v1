// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for PusherContext
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { PusherProvider, usePusher, usePusherOptional } from './PusherContext';

// Mock Pusher
const mockSubscribe = vi.fn().mockReturnValue({
  bind: vi.fn(),
});
const mockUnsubscribe = vi.fn();
const mockDisconnect = vi.fn();
const mockConnectionBind = vi.fn();

vi.mock('pusher-js', () => {
  return {
    default: vi.fn().mockImplementation(() => ({
      subscribe: mockSubscribe,
      unsubscribe: mockUnsubscribe,
      disconnect: mockDisconnect,
      connection: {
        bind: mockConnectionBind,
      },
    })),
  };
});

// Mock api
const mockApiGet = vi.fn();
const mockApiPost = vi.fn();
vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    post: (...args: unknown[]) => mockApiPost(...args),
  },
  tokenManager: {
    getTenantId: vi.fn().mockReturnValue(2),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// Mock AuthContext
let mockIsAuthenticated = false;
let mockUser: { id: number; tenant_id: number } | null = null;

vi.mock('./AuthContext', () => ({
  useAuth: () => ({
    isAuthenticated: mockIsAuthenticated,
    user: mockUser,
  }),
}));

function TestConsumer() {
  const { isConnected, onNewMessage, onTyping, onUnreadCount, sendTyping } = usePusher();

  return (
    <div>
      <div data-testid="connected">{String(isConnected)}</div>
      <button onClick={() => sendTyping(2, true)}>Send Typing</button>
      <button onClick={() => {
        const unsub = onNewMessage(() => {});
        // Store for later cleanup
        (window as any).__testUnsub = unsub;
      }}>Subscribe Messages</button>
      <button onClick={() => {
        const unsub = onTyping(() => {});
        (window as any).__testTypingUnsub = unsub;
      }}>Subscribe Typing</button>
      <button onClick={() => {
        const unsub = onUnreadCount(() => {});
        (window as any).__testUnreadUnsub = unsub;
      }}>Subscribe Unread</button>
    </div>
  );
}

describe('PusherContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockIsAuthenticated = false;
    mockUser = null;

    // Default: Pusher config not available
    mockApiGet.mockResolvedValue({ success: false });
  });

  it('provides initial disconnected state', () => {
    render(
      <PusherProvider>
        <TestConsumer />
      </PusherProvider>
    );

    expect(screen.getByTestId('connected')).toHaveTextContent('false');
  });

  it('sends typing indicator via API', async () => {
    mockApiPost.mockResolvedValue({ success: true });

    render(
      <PusherProvider>
        <TestConsumer />
      </PusherProvider>
    );

    await act(async () => {
      screen.getByRole('button', { name: 'Send Typing' }).click();
    });

    expect(mockApiPost).toHaveBeenCalledWith('/v2/messages/typing', {
      recipient_id: 2,
      is_typing: true,
    });
  });

  it('registers and unregisters message listeners', () => {
    render(
      <PusherProvider>
        <TestConsumer />
      </PusherProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Subscribe Messages' }).click();
    });

    // Unsubscribe returns a function
    expect(typeof (window as any).__testUnsub).toBe('function');

    // Calling it should not throw
    expect(() => (window as any).__testUnsub()).not.toThrow();
  });

  it('registers typing listeners', () => {
    render(
      <PusherProvider>
        <TestConsumer />
      </PusherProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Subscribe Typing' }).click();
    });

    expect(typeof (window as any).__testTypingUnsub).toBe('function');
  });

  it('registers unread count listeners', () => {
    render(
      <PusherProvider>
        <TestConsumer />
      </PusherProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Subscribe Unread' }).click();
    });

    expect(typeof (window as any).__testUnreadUnsub).toBe('function');
  });

  it('throws error when usePusher is outside provider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      render(<TestConsumer />);
    }).toThrow('usePusher must be used within a PusherProvider');

    spy.mockRestore();
  });

  it('handles typing send failure silently', async () => {
    mockApiPost.mockRejectedValue(new Error('Network error'));

    render(
      <PusherProvider>
        <TestConsumer />
      </PusherProvider>
    );

    // Should not throw
    await act(async () => {
      screen.getByRole('button', { name: 'Send Typing' }).click();
    });
  });
});

describe('usePusherOptional', () => {
  it('returns null when outside provider', () => {
    function OptionalConsumer() {
      const context = usePusherOptional();
      return <div data-testid="optional">{context === null ? 'null' : 'exists'}</div>;
    }

    render(<OptionalConsumer />);
    expect(screen.getByTestId('optional')).toHaveTextContent('null');
  });

  it('returns context when inside provider', () => {
    function OptionalConsumer() {
      const context = usePusherOptional();
      return <div data-testid="optional">{context === null ? 'null' : 'exists'}</div>;
    }

    render(
      <PusherProvider>
        <OptionalConsumer />
      </PusherProvider>
    );

    expect(screen.getByTestId('optional')).toHaveTextContent('exists');
  });
});
