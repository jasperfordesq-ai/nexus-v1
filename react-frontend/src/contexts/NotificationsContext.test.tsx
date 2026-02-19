// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for NotificationsContext
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { NotificationsProvider, useNotifications } from './NotificationsContext';

// Mock Pusher
vi.mock('pusher-js', () => {
  return {
    default: vi.fn().mockImplementation(() => ({
      subscribe: vi.fn().mockReturnValue({
        bind: vi.fn(),
        unbind_all: vi.fn(),
      }),
      unsubscribe: vi.fn(),
      disconnect: vi.fn(),
      connection: {
        bind: vi.fn(),
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
    getAccessToken: vi.fn().mockReturnValue('test-token'),
    getTenantId: vi.fn().mockReturnValue(2),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// Mock AuthContext
let mockIsAuthenticated = true;
let mockUser: { id: number; tenant_id: number } | null = { id: 1, tenant_id: 2 };

vi.mock('./AuthContext', () => ({
  useAuth: () => ({
    isAuthenticated: mockIsAuthenticated,
    user: mockUser,
  }),
}));

// Mock ToastContext
const mockToastInfo = vi.fn();
const mockToastSuccess = vi.fn();
vi.mock('./ToastContext', () => ({
  useToast: () => ({
    info: mockToastInfo,
    success: mockToastSuccess,
    error: vi.fn(),
    warning: vi.fn(),
    toasts: [],
    addToast: vi.fn(),
    removeToast: vi.fn(),
  }),
}));

function TestConsumer() {
  const { unreadCount, isConnected, counts, markAsRead, markAllAsRead, refreshCounts } = useNotifications();

  return (
    <div>
      <div data-testid="unread-count">{unreadCount}</div>
      <div data-testid="connected">{String(isConnected)}</div>
      <div data-testid="message-count">{counts.messages}</div>
      <button onClick={() => markAsRead(1)}>Mark Read</button>
      <button onClick={() => markAllAsRead()}>Mark All Read</button>
      <button onClick={() => refreshCounts()}>Refresh</button>
    </div>
  );
}

describe('NotificationsContext', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    mockIsAuthenticated = true;
    mockUser = { id: 1, tenant_id: 2 };

    // Default API responses
    mockApiGet.mockImplementation((url: string) => {
      if (url.includes('/notifications/counts')) {
        return Promise.resolve({
          success: true,
          data: { total: 5, messages: 2, listings: 1, transactions: 0, connections: 0, events: 1, groups: 0, achievements: 1, system: 0 },
        });
      }
      if (url.includes('/messages/unread-count')) {
        return Promise.resolve({
          success: true,
          data: { count: 3 },
        });
      }
      return Promise.resolve({ success: false });
    });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('provides initial state with zero counts', () => {
    mockIsAuthenticated = false;
    mockUser = null;

    render(
      <NotificationsProvider>
        <TestConsumer />
      </NotificationsProvider>
    );

    expect(screen.getByTestId('unread-count')).toHaveTextContent('0');
    expect(screen.getByTestId('connected')).toHaveTextContent('false');
  });

  it('fetches notification counts when authenticated', async () => {
    render(
      <NotificationsProvider>
        <TestConsumer />
      </NotificationsProvider>
    );

    await act(async () => {
      await vi.runAllTimersAsync();
    });

    await waitFor(() => {
      expect(screen.getByTestId('unread-count')).toHaveTextContent('5');
    });
  });

  it('fetches unread message count separately', async () => {
    render(
      <NotificationsProvider>
        <TestConsumer />
      </NotificationsProvider>
    );

    await act(async () => {
      await vi.runAllTimersAsync();
    });

    await waitFor(() => {
      // Message count comes from /messages/unread-count API (count: 3), not notification count
      expect(screen.getByTestId('message-count')).toHaveTextContent('3');
    });
  });

  it('marks single notification as read', async () => {
    mockApiPost.mockResolvedValue({ success: true });

    render(
      <NotificationsProvider>
        <TestConsumer />
      </NotificationsProvider>
    );

    await act(async () => {
      await vi.runAllTimersAsync();
    });

    await waitFor(() => {
      expect(screen.getByTestId('unread-count')).toHaveTextContent('5');
    });

    await act(async () => {
      screen.getByRole('button', { name: 'Mark Read' }).click();
    });

    expect(mockApiPost).toHaveBeenCalledWith('/v2/notifications/1/read');
    await waitFor(() => {
      expect(screen.getByTestId('unread-count')).toHaveTextContent('4');
    });
  });

  it('marks all notifications as read', async () => {
    mockApiPost.mockResolvedValue({ success: true });

    render(
      <NotificationsProvider>
        <TestConsumer />
      </NotificationsProvider>
    );

    await act(async () => {
      await vi.runAllTimersAsync();
    });

    await waitFor(() => {
      expect(screen.getByTestId('unread-count')).toHaveTextContent('5');
    });

    await act(async () => {
      screen.getByRole('button', { name: 'Mark All Read' }).click();
    });

    expect(mockApiPost).toHaveBeenCalledWith('/v2/notifications/read-all');
    await waitFor(() => {
      expect(screen.getByTestId('unread-count')).toHaveTextContent('0');
    });
  });

  it('throws error when used outside provider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      render(<TestConsumer />);
    }).toThrow('useNotifications must be used within a NotificationsProvider');

    spy.mockRestore();
  });

  it('does not fetch when not authenticated', async () => {
    mockIsAuthenticated = false;
    mockUser = null;

    render(
      <NotificationsProvider>
        <TestConsumer />
      </NotificationsProvider>
    );

    await act(async () => {
      await vi.runAllTimersAsync();
    });

    expect(mockApiGet).not.toHaveBeenCalled();
  });
});
