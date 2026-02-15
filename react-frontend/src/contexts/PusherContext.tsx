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
import { api } from '@/lib/api';
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

  // Load Pusher config from API
  useEffect(() => {
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
  }, []);

  // Initialize Pusher when authenticated and config is available
  useEffect(() => {
    if (!isAuthenticated || !user?.id || !config?.enabled || !config.key) {
      return;
    }

    // Initialize Pusher
    const pusher = new Pusher(config.key, {
      cluster: config.cluster,
      authEndpoint: config.authEndpoint,
      auth: {
        headers: {
          'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      },
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
    const userChannel = pusher.subscribe(`private-tenant.${user.tenant_id}.user.${user.id}`);

    userChannel.bind('new-message', (data: NewMessageEvent) => {
      messageListenersRef.current.forEach((listener) => listener(data));
    });

    userChannel.bind('unread-count', (data: UnreadCountEvent) => {
      unreadListenersRef.current.forEach((listener) => listener(data));
    });

    userChannelRef.current = userChannel;

    // Cleanup on unmount
    return () => {
      pusher.disconnect();
      pusherRef.current = null;
      userChannelRef.current = null;
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

    const channelName = `private-chat-${chatId}`;

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

    const channelName = `private-chat-${chatId}`;

    const channel = conversationChannelsRef.current.get(channelName);
    if (channel) {
      pusherRef.current.unsubscribe(channelName);
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
   * Send typing indicator to another user
   */
  const sendTyping = useCallback(async (toUserId: number, isTyping: boolean) => {
    try {
      await api.post('/v2/messages/typing', {
        recipient_id: toUserId,
        is_typing: isTyping,
      });
    } catch (error) {
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
