// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PusherContext - Real-time messaging via Pusher
 *
 * Provides WebSocket connectivity for real-time features:
 * - New message notifications
 * - Typing indicators
 * - Presence (online status)
 * - Unread count updates
 *
 * Automatically subscribes to user-specific and conversation channels.
 */

import { createContext, useContext, useEffect, useState, useCallback, useRef, type ReactNode } from 'react';
import Pusher, { type Channel } from 'pusher-js';
import { useAuth } from './AuthContext';
import { api, tokenManager } from '@/lib/api';
import { logError } from '@/lib/logger';

interface PusherConfig {
  key: string;
  cluster: string;
  authEndpoint: string;
  enabled: boolean;
}

interface PusherContextValue {
  /** Whether Pusher is connected */
  isConnected: boolean;
  /** Subscribe to a conversation channel */
  subscribeToConversation: (otherUserId: number) => void;
  /** Unsubscribe from a conversation channel */
  unsubscribeFromConversation: (otherUserId: number) => void;
  /** Register a callback for new messages */
  onNewMessage: (callback: (message: NewMessageEvent) => void) => () => void;
  /** Register a callback for typing indicators */
  onTyping: (callback: (event: TypingEvent) => void) => () => void;
  /** Register a callback for unread count updates */
  onUnreadCount: (callback: (event: UnreadCountEvent) => void) => () => void;
  /** Register a callback for new feed posts broadcast to the tenant channel */
  onFeedPost: (callback: (event: FeedPostEvent) => void) => () => void;
  /** Send typing indicator */
  sendTyping: (toUserId: number, isTyping: boolean) => void;
}

export interface NewMessageEvent {
  id: number;
  sender_id: number;
  receiver_id: number;
  body: string;
  created_at: string;
  timestamp: number;
}

export interface TypingEvent {
  user_id: number;
  is_typing: boolean;
  timestamp: number;
}

export interface UnreadCountEvent {
  notifications: number;
  messages: number;
  timestamp: number;
}

export interface FeedPostEvent {
  post: import('@/components/feed/types').FeedItem;
  timestamp: number;
}

const PusherContext = createContext<PusherContextValue | null>(null);

interface PusherProviderProps {
  children: ReactNode;
}

export function PusherProvider({ children }: PusherProviderProps) {
  const { user, isAuthenticated } = useAuth();
  const [isConnected, setIsConnected] = useState(false);
  const [config, setConfig] = useState<PusherConfig | null>(null);

  const pusherRef = useRef<Pusher | null>(null);
  const userChannelRef = useRef<Channel | null>(null);
  const conversationChannelsRef = useRef<Map<string, Channel>>(new Map());

  // Event listeners
  const messageListenersRef = useRef<Set<(message: NewMessageEvent) => void>>(new Set());
  const typingListenersRef = useRef<Set<(event: TypingEvent) => void>>(new Set());
  const unreadListenersRef = useRef<Set<(event: UnreadCountEvent) => void>>(new Set());
  const feedPostListenersRef = useRef<Set<(event: FeedPostEvent) => void>>(new Set());

  // Tenant feed channel ref (unsubscribed on cleanup alongside user channel)
  const feedChannelRef = useRef<Channel | null>(null);

  // Load Pusher config from API (only when authenticated)
  useEffect(() => {
    if (!isAuthenticated) return;

    async function loadConfig() {
      try {
        const response = await api.get<PusherConfig>('/v2/realtime/config');
        if (response.success && response.data?.enabled) {
          setConfig(response.data);
        }
      } catch (error) {
        // Pusher not configured - this is fine, real-time features just won't work
        logError('Pusher config not available', error);
      }
    }

    loadConfig();
  }, [isAuthenticated]);

  // Initialize Pusher when authenticated and config is available
  useEffect(() => {
    if (!isAuthenticated || !user?.id || !config?.enabled || !config.key) {
      return;
    }

    // Initialize Pusher with Bearer token auth (matching NotificationsContext)
    const accessToken = tokenManager.getAccessToken();
    if (!accessToken) return;

    const pusher = new Pusher(config.key, {
      cluster: config.cluster,
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

    pusher.connection.bind('connected', () => {
      setIsConnected(true);
    });

    pusher.connection.bind('disconnected', () => {
      setIsConnected(false);
    });

    pusher.connection.bind('error', (err: Error) => {
      logError('Pusher connection error', err);
      setIsConnected(false);
    });

    pusherRef.current = pusher;

    // Subscribe to user's personal channel (must match backend PusherService::getUserChannel format)
    const tenantId = user.tenant_id || tokenManager.getTenantId();
    if (!tenantId) {
      logError('Cannot subscribe to Pusher: no tenant_id available');
      return;
    }
    const userChannel = pusher.subscribe(`private-tenant.${tenantId}.user.${user.id}`);

    userChannel.bind('new-message', (data: NewMessageEvent) => {
      messageListenersRef.current.forEach((listener) => listener(data));
    });

    userChannel.bind('unread-count', (data: UnreadCountEvent) => {
      unreadListenersRef.current.forEach((listener) => listener(data));
    });

    userChannelRef.current = userChannel;

    // Subscribe to the tenant-wide feed channel to receive real-time new post events.
    // Channel name must match PusherService::getTenantFeedChannel() on the PHP side.
    const feedChannel = pusher.subscribe(`private-tenant.${tenantId}.feed`);
    feedChannel.bind('feed.post_created', (data: FeedPostEvent) => {
      feedPostListenersRef.current.forEach((listener) => listener(data));
    });
    feedChannelRef.current = feedChannel;

    // Cleanup on unmount — unbind all handlers first, then disconnect().
    // Do NOT call unsubscribe() before disconnect() — it queues an async send
    // that fires after disconnect starts closing the socket, causing
    // "WebSocket is already in CLOSING or CLOSED state" warnings.
    return () => {
      if (userChannelRef.current) {
        userChannelRef.current.unbind_all();
      }
      if (feedChannelRef.current) {
        feedChannelRef.current.unbind_all();
      }
      conversationChannelsRef.current.forEach((ch) => ch.unbind_all());
      pusher.disconnect();
      pusherRef.current = null;
      userChannelRef.current = null;
      feedChannelRef.current = null;
      conversationChannelsRef.current.clear();
      setIsConnected(false);
    };
  }, [isAuthenticated, user?.id, config]);

  /**
   * Subscribe to a conversation channel for real-time messages and typing
   */
  const subscribeToConversation = useCallback((otherUserId: number) => {
    if (!pusherRef.current || !user?.id) return;

    // Create deterministic channel ID (smaller ID first)
    const chatId = user.id < otherUserId
      ? `${user.id}-${otherUserId}`
      : `${otherUserId}-${user.id}`;

    // Must match backend PusherService::getChatChannel format (tenant-scoped)
    const tenantId = user.tenant_id || tokenManager.getTenantId();
    const channelName = `private-tenant.${tenantId}.chat.${chatId}`;

    // Already subscribed?
    if (conversationChannelsRef.current.has(channelName)) {
      return;
    }

    const channel = pusherRef.current.subscribe(channelName);

    channel.bind('message', (data: NewMessageEvent) => {
      messageListenersRef.current.forEach((listener) => listener(data));
    });

    channel.bind('typing', (data: TypingEvent) => {
      typingListenersRef.current.forEach((listener) => listener(data));
    });

    conversationChannelsRef.current.set(channelName, channel);
  }, [user?.id]);

  /**
   * Unsubscribe from a conversation channel
   */
  const unsubscribeFromConversation = useCallback((otherUserId: number) => {
    if (!pusherRef.current || !user?.id) return;

    const chatId = user.id < otherUserId
      ? `${user.id}-${otherUserId}`
      : `${otherUserId}-${user.id}`;

    // Must match backend PusherService::getChatChannel format (tenant-scoped)
    const tenantId = user.tenant_id || tokenManager.getTenantId();
    const channelName = `private-tenant.${tenantId}.chat.${chatId}`;

    const channel = conversationChannelsRef.current.get(channelName);
    if (channel) {
      channel.unbind_all();
      // Only send unsubscribe over the wire if the connection is still open;
      // otherwise disconnect() will clean up and sending would trigger
      // "WebSocket is already in CLOSING or CLOSED state" warnings.
      const state = pusherRef.current.connection.state;
      if (state === 'connected') {
        pusherRef.current.unsubscribe(channelName);
      }
      conversationChannelsRef.current.delete(channelName);
    }
  }, [user?.id]);

  /**
   * Register a callback for new messages
   * Returns an unsubscribe function
   */
  const onNewMessage = useCallback((callback: (message: NewMessageEvent) => void) => {
    messageListenersRef.current.add(callback);
    return () => {
      messageListenersRef.current.delete(callback);
    };
  }, []);

  /**
   * Register a callback for typing indicators
   * Returns an unsubscribe function
   */
  const onTyping = useCallback((callback: (event: TypingEvent) => void) => {
    typingListenersRef.current.add(callback);
    return () => {
      typingListenersRef.current.delete(callback);
    };
  }, []);

  /**
   * Register a callback for unread count updates
   * Returns an unsubscribe function
   */
  const onUnreadCount = useCallback((callback: (event: UnreadCountEvent) => void) => {
    unreadListenersRef.current.add(callback);
    return () => {
      unreadListenersRef.current.delete(callback);
    };
  }, []);

  /**
   * Register a callback for new feed posts on the tenant feed channel
   * Returns an unsubscribe function
   */
  const onFeedPost = useCallback((callback: (event: FeedPostEvent) => void) => {
    feedPostListenersRef.current.add(callback);
    return () => {
      feedPostListenersRef.current.delete(callback);
    };
  }, []);

  /**
   * Send typing indicator to another user
   */
  const sendTyping = useCallback(async (toUserId: number, isTyping: boolean) => {
    try {
      await api.post('/v2/messages/typing', {
        recipient_id: toUserId,
        is_typing: isTyping,
      });
    } catch {
      // Silent fail - typing indicators are not critical
    }
  }, []);

  const value: PusherContextValue = {
    isConnected,
    subscribeToConversation,
    unsubscribeFromConversation,
    onNewMessage,
    onTyping,
    onUnreadCount,
    onFeedPost,
    sendTyping,
  };

  return (
    <PusherContext.Provider value={value}>
      {children}
    </PusherContext.Provider>
  );
}

/**
 * Hook to access Pusher context
 */
export function usePusher(): PusherContextValue {
  const context = useContext(PusherContext);
  if (!context) {
    throw new Error('usePusher must be used within a PusherProvider');
  }
  return context;
}

/**
 * Hook to optionally access Pusher context (returns null if not available)
 */
export function usePusherOptional(): PusherContextValue | null {
  return useContext(PusherContext);
}
