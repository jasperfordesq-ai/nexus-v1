// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import type { Channel } from 'pusher-js';

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';
import { initRealtime, getRealtimeClient, type PusherConfig } from '@/lib/realtime';
import { registerRefreshCallback, unregisterRefreshCallback } from '@/lib/notifications';
import { useAuthContext } from '@/lib/context/AuthContext';
import type { Message } from '@/lib/api/messages';

type MessageHandler = (msg: Message) => void;

interface RealtimeContextValue {
  /** Current unread message count (seeded from API, bumped by Pusher). */
  unreadMessages: number;
  /** Call when the user opens the Messages tab — resets the badge. */
  resetUnread: () => void;
  /**
   * Subscribe to incoming Pusher messages for a specific conversation.
   * Returns an unsubscribe function — call it in a useEffect cleanup.
   */
  subscribeToMessages: (conversationId: number, handler: MessageHandler) => () => void;
}

const RealtimeContext = createContext<RealtimeContextValue>({
  unreadMessages: 0,
  resetUnread: () => undefined,
  subscribeToMessages: () => () => undefined,
});

export function useRealtimeContext(): RealtimeContextValue {
  return useContext(RealtimeContext);
}

/** Validate incoming Pusher payload shape at runtime. */
function isMessagePayload(data: unknown): data is { conversation_id: number; message: Message } {
  if (typeof data !== 'object' || data === null) return false;
  const obj = data as Record<string, unknown>;
  return typeof obj.conversation_id === 'number' && typeof obj.message === 'object' && obj.message !== null;
}

export function RealtimeProvider({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuthContext();
  const [unreadMessages, setUnreadMessages] = useState(0);
  const channelRef = useRef<Channel | null>(null);
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);
  /** conversation_id → set of handlers listening for new messages */
  const messageListenersRef = useRef<Map<number, Set<MessageHandler>>>(new Map());

  // Seed unread count from REST API whenever auth state changes
  useEffect(() => {
    if (!isAuthenticated) {
      setUnreadMessages(0);
      return;
    }

    api
      .get<{ data: { messages: number; notifications: number } }>(`${API_V2}/notifications/counts`)
      .then((res) => setUnreadMessages(res.data.messages))
      .catch(() => { /* non-critical — badge just stays at 0 */ });
  }, [isAuthenticated]);

  // Connect to Pusher and subscribe to personal channel for live updates
  useEffect(() => {
    if (!isAuthenticated) return;

    let mounted = true;

    api
      .get<PusherConfig>(`${API_V2}/pusher/config`)
      .then((config) => {
        if (!mounted) return;
        if (!config.enabled || !config.key) return;

        const client = initRealtime(config);
        if (!client) return;

        const channelName = config.channels?.user;
        if (!channelName) return;

        const ch = client.subscribe(channelName);
        channelRef.current = ch;

        // Bump the unread badge and notify any open thread screens
        ch.bind('new-message', (rawPayload: unknown) => {
          if (!mounted) return;

          let hasActiveListener = false;
          if (isMessagePayload(rawPayload)) {
            const listeners = messageListenersRef.current;
            // Dispatch by conversation ID
            const convListeners = listeners.get(rawPayload.conversation_id);
            if (convListeners && convListeners.size > 0) {
              hasActiveListener = true;
              convListeners.forEach((handler) => handler(rawPayload.message));
            }
            // Also dispatch by sender's user ID — the thread screen subscribes
            // using the other user's ID (not the conversation row ID)
            const senderId = rawPayload.message.sender?.id;
            if (senderId && senderId !== rawPayload.conversation_id) {
              const senderListeners = listeners.get(senderId);
              if (senderListeners && senderListeners.size > 0) {
                hasActiveListener = true;
                senderListeners.forEach((handler) => handler(rawPayload.message));
              }
            }
          }

          // Only bump badge if no thread screen is actively viewing this conversation
          if (!hasActiveListener) {
            setUnreadMessages((prev) => prev + 1);
          }
        });
      })
      .catch(() => { /* Pusher not configured — silent no-op */ });

    return () => {
      mounted = false;
      if (channelRef.current) {
        channelRef.current.unbind_all();
        getRealtimeClient()?.unsubscribe(channelRef.current.name);
        channelRef.current = null;
      }
    };
  }, [isAuthenticated]);

  // Re-fetch unread count when the app returns to the foreground,
  // and reconnect Pusher if it dropped while backgrounded.
  useEffect(() => {
    if (!isAuthenticated) return;

    function refreshCounts() {
      api
        .get<{ data: { messages: number; notifications: number } }>(`${API_V2}/notifications/counts`)
        .then((res) => setUnreadMessages(res.data.messages))
        .catch(() => { /* non-critical */ });
    }

    // AppState: re-fetch whenever app comes back to foreground
    const appStateSubscription = AppState.addEventListener('change', (nextState: AppStateStatus) => {
      const prev = appStateRef.current;
      appStateRef.current = nextState;

      if (prev !== 'active' && nextState === 'active') {
        refreshCounts();

        // Reconnect Pusher if it disconnected while backgrounded
        const client = getRealtimeClient();
        if (client && client.connection.state !== 'connected') {
          client.connect();
        }
      }
    });

    // Push notifications: silent data pushes also trigger a count refresh
    registerRefreshCallback(refreshCounts);

    return () => {
      appStateSubscription.remove();
      unregisterRefreshCallback();
    };
  }, [isAuthenticated]);

  const resetUnread = useCallback(() => setUnreadMessages(0), []);

  const subscribeToMessages = useCallback(
    (conversationId: number, handler: MessageHandler): (() => void) => {
      const map = messageListenersRef.current;
      if (!map.has(conversationId)) map.set(conversationId, new Set());
      map.get(conversationId)!.add(handler);
      return () => {
        map.get(conversationId)?.delete(handler);
      };
    },
    [],
  );

  return (
    <RealtimeContext.Provider value={{ unreadMessages, resetUnread, subscribeToMessages }}>
      {children}
    </RealtimeContext.Provider>
  );
}
