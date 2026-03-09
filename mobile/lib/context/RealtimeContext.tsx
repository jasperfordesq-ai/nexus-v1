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

interface RealtimeContextValue {
  /** Current unread message count (seeded from API, bumped by Pusher). */
  unreadMessages: number;
  /** Call when the user opens the Messages tab — resets the badge. */
  resetUnread: () => void;
}

const RealtimeContext = createContext<RealtimeContextValue>({
  unreadMessages: 0,
  resetUnread: () => undefined,
});

export function useRealtimeContext(): RealtimeContextValue {
  return useContext(RealtimeContext);
}

export function RealtimeProvider({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuthContext();
  const [unreadMessages, setUnreadMessages] = useState(0);
  const channelRef = useRef<Channel | null>(null);
  const appStateRef = useRef<AppStateStatus>(AppState.currentState);

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
      .get<PusherConfig>('/api/pusher/config')
      .then((config) => {
        if (!mounted) return;
        if (!config.enabled || !config.key) return;

        const client = initRealtime(config);
        if (!client) return;

        const channelName = config.channels?.user;
        if (!channelName) return;

        const ch = client.subscribe(channelName);
        channelRef.current = ch;

        // Bump the unread badge for every inbound message
        ch.bind('new-message', () => {
          if (mounted) setUnreadMessages((prev) => prev + 1);
        });
      })
      .catch(() => { /* Pusher not configured — silent no-op */ });

    return () => {
      mounted = false;
      if (channelRef.current) {
        channelRef.current.unbind_all();
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

  return (
    <RealtimeContext.Provider value={{ unreadMessages, resetUnread }}>
      {children}
    </RealtimeContext.Provider>
  );
}
