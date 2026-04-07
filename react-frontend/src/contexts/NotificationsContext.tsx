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
import i18n from 'i18next';
import { api, tokenManager } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useAuth } from './AuthContext';
import { useToast } from './ToastContext';
import type { Notification } from '@/types';

// ─────────────────────────────────────────────────────────────────────────────
// Configuration
// ─────────────────────────────────────────────────────────────────────────────

// Read at call-site (not module load) so vi.stubEnv() in tests takes effect.
const getPusherKey = () => import.meta.env.VITE_PUSHER_KEY as string | undefined;
const getPusherCluster = () => (import.meta.env.VITE_PUSHER_CLUSTER as string | undefined) || 'eu';
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
            // Preserve messages count — messages and notifications are separate systems
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

    // Initialize Pusher (skip if key not configured)
    const pusherKey = getPusherKey();
    if (!pusherKey) {
      if (import.meta.env.DEV) {
        console.warn('[NotificationsContext] VITE_PUSHER_KEY is not set — real-time notifications disabled, using polling.');
      }
      // Still set up polling fallback even without Pusher
      pollingRef.current = setInterval(refreshCounts, POLLING_INTERVAL);
      return () => {
        if (pollingRef.current) {
          clearInterval(pollingRef.current);
          pollingRef.current = null;
        }
      };
    }
    try {
      const pusher = new Pusher(pusherKey, {
        cluster: getPusherCluster(),
        // Use a custom authorizer instead of authEndpoint so that the request
        // goes through our api client, which handles CORS, content-type, and
        // token refresh — avoiding the 405 error that occurs when Pusher's
        // built-in XHR POST hits the backend without proper CORS headers.
        authorizer: (channel) => ({
          authorize: (socketId, callback) => {
            api.post<{ auth: string; channel_data?: string }>('/pusher/auth', {
              socket_id: socketId,
              channel_name: channel.name,
            })
              .then((response) => {
                if (response.success && response.data) {
                  callback(null, response.data as { auth: string });
                } else {
                  callback(new Error(response.error || 'Pusher auth failed'), null as never);
                }
              })
              .catch((err) => {
                callback(err instanceof Error ? err : new Error('Pusher auth failed'), null as never);
              });
          },
        }),
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
      // Backend sends: { sender_id, body, preview, from_user_id, ... }
      channel.bind('new-message', (data: { body?: string; preview?: string; message?: string }) => {
        setState((prev) => ({
          ...prev,
          unreadCount: prev.unreadCount + 1,
          counts: {
            ...prev.counts,
            messages: prev.counts.messages + 1,
          },
        }));
        const text = data.body || data.preview || data.message || '';
        toast.info(i18n.t('realtime.new_message', { ns: 'notifications' }), text.substring(0, 50) || i18n.t('realtime.new_message_fallback', { ns: 'notifications' }));
      });

      // Transaction events
      channel.bind('transaction', (data: { type: string; amount: number }) => {
        refreshCounts();
        toast.success(
          i18n.t('realtime.transaction_complete', { ns: 'notifications' }),
          i18n.t('realtime.transaction_amount', { ns: 'notifications', sign: data.type === 'credit' ? '+' : '-', amount: data.amount })
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

    // Cleanup — disconnect() handles unsubscribing internally;
    // calling unsubscribe() before disconnect() causes "WebSocket is already
    // in CLOSING or CLOSED state" warnings because unsubscribe queues an
    // async send that fires after disconnect starts closing the socket.
    return () => {
      if (pusherRef.current) {
        if (channelRef.current) {
          channelRef.current.unbind_all();
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
  }, [isAuthenticated, user?.id, refreshCounts, handleNewNotification, toast, user?.tenant_id]);

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
  const t = (key: string, fallback: string) => i18n.t(`realtime.toast_${key}`, { ns: 'notifications', defaultValue: fallback });
  const configs: Record<string, { title: string }> = {
    message: { title: t('message', 'New Message') },
    listing: { title: t('listing', 'Listing Update') },
    transaction: { title: t('transaction', 'Transaction') },
    connection: { title: t('connection', 'Connection Request') },
    event: { title: t('event', 'Event Update') },
    group: { title: t('group', 'Group Notification') },
    achievement: { title: t('achievement', 'Achievement Unlocked!') },
    broker_review: { title: t('broker_review', 'Message for Review') },
    safeguarding_flag: { title: t('safeguarding_flag', 'Safeguarding Alert') },
    safeguarding_assignment: { title: t('safeguarding_assignment', 'Safeguarding Assignment') },
    system: { title: t('system', 'System Notification') },
    vol_application_received: { title: t('vol_application_received', 'New Application') },
    vol_application_approved: { title: t('vol_application_approved', 'Application Approved') },
    vol_application_declined: { title: t('vol_application_declined', 'Application Declined') },
    vol_application_withdrawn: { title: t('vol_application_withdrawn', 'Application Withdrawn') },
    vol_shift_signup: { title: t('vol_shift_signup', 'Shift Sign-up') },
    vol_shift_cancelled: { title: t('vol_shift_cancelled', 'Shift Cancelled') },
    vol_hours_approved: { title: t('vol_hours_approved', 'Hours Approved') },
    vol_hours_declined: { title: t('vol_hours_declined', 'Hours Declined') },
    vol_opportunity_closed: { title: t('vol_opportunity_closed', 'Opportunity Closed') },
    job_application_status: { title: t('job_application_status', 'Application Update') },
    job_application: { title: t('job_application', 'Job Application') },
    job_interview_proposed: { title: t('job_interview_proposed', 'Interview Requested') },
    job_interview_accepted: { title: t('job_interview_accepted', 'Interview Accepted') },
    job_interview_declined: { title: t('job_interview_declined', 'Interview Declined') },
    job_interview_cancelled: { title: t('job_interview_cancelled', 'Interview Cancelled') },
    job_offer_received: { title: t('job_offer_received', 'Job Offer Received!') },
    job_offer_accepted: { title: t('job_offer_accepted', 'Offer Accepted') },
    job_offer_rejected: { title: t('job_offer_rejected', 'Offer Rejected') },
    job_offer_withdrawn: { title: t('job_offer_withdrawn', 'Offer Withdrawn') },
    job_alert_match: { title: t('job_alert_match', 'New Job Match') },
    job_interview_reminder: { title: t('job_interview_reminder', 'Interview Reminder') },
    job_completion_credits: { title: t('job_completion_credits', 'Time Credits Earned!') },
  };

  return configs[type] || { title: t('default', 'Notification') };
}

export default NotificationsContext;
