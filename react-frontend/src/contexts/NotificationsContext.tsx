// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NEXUS Notifications Context
 *
 * Provides:
 * - Real-time notifications via Pusher
 * - Unread count tracking
 * - Toast notifications for new events
 * - Notification polling fallback
 */

import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  useMemo,
  useRef,
  type ReactNode,
} from 'react';
import Pusher, { type Channel } from 'pusher-js';
import { api, tokenManager } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useAuth } from './AuthContext';
import { useToast } from './ToastContext';
import type { Notification } from '@/types';

// ─────────────────────────────────────────────────────────────────────────────
// Configuration
// ─────────────────────────────────────────────────────────────────────────────

const PUSHER_KEY = import.meta.env.VITE_PUSHER_KEY || 'f7af200cb94bb29afbd3';
const PUSHER_CLUSTER = import.meta.env.VITE_PUSHER_CLUSTER || 'eu';
const POLLING_INTERVAL = 60000; // 60 seconds fallback polling

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface NotificationCounts {
  total: number;
  messages: number;
  listings: number;
  transactions: number;
  connections: number;
  events: number;
  groups: number;
  achievements: number;
  system: number;
}

interface NotificationsState {
  unreadCount: number;
  counts: NotificationCounts;
  isConnected: boolean;
  connectionError: string | null;
}

interface NotificationsContextValue extends NotificationsState {
  refreshCounts: () => Promise<void>;
  markAsRead: (id: number) => Promise<void>;
  markAllAsRead: () => Promise<void>;
}

// ─────────────────────────────────────────────────────────────────────────────
// Context
// ─────────────────────────────────────────────────────────────────────────────

const NotificationsContext = createContext<NotificationsContextValue | null>(null);

// ─────────────────────────────────────────────────────────────────────────────
// Provider
// ─────────────────────────────────────────────────────────────────────────────

interface NotificationsProviderProps {
  children: ReactNode;
}

export function NotificationsProvider({ children }: NotificationsProviderProps) {
  const { user, isAuthenticated } = useAuth();
  const toast = useToast();

  const [state, setState] = useState<NotificationsState>({
    unreadCount: 0,
    counts: {
      total: 0,
      messages: 0,
      listings: 0,
      transactions: 0,
      connections: 0,
      events: 0,
      groups: 0,
      achievements: 0,
      system: 0,
    },
    isConnected: false,
    connectionError: null,
  });

  const pusherRef = useRef<Pusher | null>(null);
  const channelRef = useRef<Channel | null>(null);
  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

  // ─────────────────────────────────────────────────────────────────────────
  // Fetch Notification Counts
  // ─────────────────────────────────────────────────────────────────────────

  const refreshCounts = useCallback(async () => {
    if (!isAuthenticated) return;

    try {
      // Fetch both notification counts and unread message count in parallel
      const [notifResponse, messagesResponse] = await Promise.all([
        api.get<NotificationCounts>('/v2/notifications/counts'),
        api.get<{ count: number }>('/v2/messages/unread-count').catch(() => null),
      ]);

      if (notifResponse.success && notifResponse.data) {
        const counts = notifResponse.data;
        // Use actual unread message count from Messages API (not notification count)
        const unreadMessages = messagesResponse?.success ? messagesResponse.data?.count ?? 0 : 0;

        setState((prev) => ({
          ...prev,
          unreadCount: counts.total ?? 0,
          counts: {
            total: counts.total ?? 0,
            messages: unreadMessages, // Use actual unread messages, not notification count
            listings: counts.listings ?? 0,
            transactions: counts.transactions ?? 0,
            connections: counts.connections ?? 0,
            events: counts.events ?? 0,
            groups: counts.groups ?? 0,
            achievements: counts.achievements ?? 0,
            system: counts.system ?? 0,
          },
        }));
      }
    } catch (error) {
      logError('Failed to fetch notification counts', error);
    }
  }, [isAuthenticated]);

  // ─────────────────────────────────────────────────────────────────────────
  // Mark as Read
  // ─────────────────────────────────────────────────────────────────────────

  const markAsRead = useCallback(async (id: number) => {
    try {
      const response = await api.post(`/v2/notifications/${id}/read`);
      if (response.success) {
        setState((prev) => ({
          ...prev,
          unreadCount: Math.max(0, prev.unreadCount - 1),
        }));
      }
    } catch (error) {
      logError('Failed to mark notification as read', error);
    }
  }, []);

  const markAllAsRead = useCallback(async () => {
    try {
      const response = await api.post('/v2/notifications/read-all');
      if (response.success) {
        setState((prev) => ({
          ...prev,
          unreadCount: 0,
          counts: {
            ...prev.counts,
            total: 0,
            messages: 0,
            listings: 0,
            transactions: 0,
            connections: 0,
            events: 0,
            groups: 0,
            achievements: 0,
            system: 0,
          },
        }));
      }
    } catch (error) {
      logError('Failed to mark all notifications as read', error);
    }
  }, []);

  // ─────────────────────────────────────────────────────────────────────────
  // Handle Incoming Notification
  // ─────────────────────────────────────────────────────────────────────────

  const handleNewNotification = useCallback((data: Notification) => {
    // Update unread count
    setState((prev) => ({
      ...prev,
      unreadCount: prev.unreadCount + 1,
    }));

    // Show toast notification
    const toastConfig = getToastConfig(data.type);
    toast.info(toastConfig.title, data.message || data.body || data.title);
  }, [toast]);

  // ─────────────────────────────────────────────────────────────────────────
  // Initialize Pusher
  // ─────────────────────────────────────────────────────────────────────────

  useEffect(() => {
    if (!isAuthenticated || !user?.id) {
      // Clean up if logged out
      if (pusherRef.current) {
        pusherRef.current.disconnect();
        pusherRef.current = null;
        channelRef.current = null;
      }
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
      setState((prev) => ({
        ...prev,
        isConnected: false,
        unreadCount: 0,
      }));
      return;
    }

    // Fetch initial counts
    refreshCounts();

    // Initialize Pusher
    try {
      const pusher = new Pusher(PUSHER_KEY, {
        cluster: PUSHER_CLUSTER,
        authEndpoint: `${import.meta.env.VITE_API_URL || ''}/api/pusher/auth`,
        auth: {
          headers: {
            Authorization: `Bearer ${tokenManager.getAccessToken()}`,
          },
        },
      });

      pusherRef.current = pusher;

      // Subscribe to private user channel (must match backend PusherService::getUserChannel format)
      const tenantId = user.tenant_id || tokenManager.getTenantId();
      if (!tenantId) {
        logError('Cannot subscribe to Pusher: no tenant_id available');
        return;
      }
      const channelName = `private-tenant.${tenantId}.user.${user.id}`;
      const channel = pusher.subscribe(channelName);
      channelRef.current = channel;

      // Bind to events
      channel.bind('pusher:subscription_succeeded', () => {
        setState((prev) => ({
          ...prev,
          isConnected: true,
          connectionError: null,
        }));
      });

      channel.bind('pusher:subscription_error', (error: unknown) => {
        logError('Pusher subscription error', error);
        setState((prev) => ({
          ...prev,
          isConnected: false,
          connectionError: 'Failed to connect to notifications',
        }));
      });

      // Notification events
      channel.bind('notification', handleNewNotification);
      channel.bind('new-notification', handleNewNotification);

      // Message events (update unread count)
      channel.bind('new-message', (data: { conversation_id: number; message: string }) => {
        setState((prev) => ({
          ...prev,
          unreadCount: prev.unreadCount + 1,
          counts: {
            ...prev.counts,
            messages: prev.counts.messages + 1,
          },
        }));
        toast.info('New Message', data.message?.substring(0, 50) || 'You have a new message');
      });

      // Transaction events
      channel.bind('transaction', (data: { type: string; amount: number }) => {
        refreshCounts();
        toast.success(
          'Transaction Complete',
          `${data.type === 'credit' ? '+' : '-'}${data.amount} time credits`
        );
      });

      // Connection state
      pusher.connection.bind('connected', () => {
        setState((prev) => ({ ...prev, isConnected: true, connectionError: null }));
      });

      pusher.connection.bind('disconnected', () => {
        setState((prev) => ({ ...prev, isConnected: false }));
      });

      pusher.connection.bind('error', (error: unknown) => {
        logError('Pusher connection error', error);
        setState((prev) => ({
          ...prev,
          isConnected: false,
          connectionError: 'Connection error',
        }));
      });

    } catch (error) {
      logError('Pusher initialization error', error);
      setState((prev) => ({
        ...prev,
        connectionError: 'Failed to initialize real-time notifications',
      }));
    }

    // Set up polling fallback
    pollingRef.current = setInterval(refreshCounts, POLLING_INTERVAL);

    // Cleanup
    return () => {
      if (pusherRef.current) {
        if (channelRef.current) {
          channelRef.current.unbind_all();
          const cleanupTenantId = user.tenant_id || tokenManager.getTenantId();
          if (cleanupTenantId) {
            pusherRef.current.unsubscribe(`private-tenant.${cleanupTenantId}.user.${user.id}`);
          }
        }
        pusherRef.current.disconnect();
        pusherRef.current = null;
        channelRef.current = null;
      }
      if (pollingRef.current) {
        clearInterval(pollingRef.current);
        pollingRef.current = null;
      }
    };
  }, [isAuthenticated, user?.id, refreshCounts, handleNewNotification]);

  // ─────────────────────────────────────────────────────────────────────────
  // Context Value
  // ─────────────────────────────────────────────────────────────────────────

  const value = useMemo<NotificationsContextValue>(
    () => ({
      ...state,
      refreshCounts,
      markAsRead,
      markAllAsRead,
    }),
    [state, refreshCounts, markAsRead, markAllAsRead]
  );

  return (
    <NotificationsContext.Provider value={value}>
      {children}
    </NotificationsContext.Provider>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Hook
// ─────────────────────────────────────────────────────────────────────────────

export function useNotifications(): NotificationsContextValue {
  const context = useContext(NotificationsContext);

  if (!context) {
    throw new Error('useNotifications must be used within a NotificationsProvider');
  }

  return context;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper Functions
// ─────────────────────────────────────────────────────────────────────────────

function getToastConfig(type: string): { title: string } {
  const configs: Record<string, { title: string }> = {
    message: { title: 'New Message' },
    listing: { title: 'Listing Update' },
    transaction: { title: 'Transaction' },
    connection: { title: 'Connection Request' },
    event: { title: 'Event Update' },
    group: { title: 'Group Notification' },
    achievement: { title: 'Achievement Unlocked!' },
    system: { title: 'System Notification' },
  };

  return configs[type] || { title: 'Notification' };
}

export default NotificationsContext;
