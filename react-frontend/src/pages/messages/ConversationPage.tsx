// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Conversation Page - Individual message thread
 *
 * Features:
 * - Real-time message updates via polling (with cursor for efficiency)
 * - Infinite scroll for message history (scroll up to load older messages)
 * - Voice message playback
 * - Typing indicators (when Pusher is available)
 */

import { useState, useEffect, useRef, useCallback, type ChangeEvent, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useParams, Link, useNavigate, useSearchParams, Navigate } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import { Button, Avatar, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Input, Tooltip, Skeleton } from '@heroui/react';
import { ArrowLeft, Info, Loader2, MoreVertical, Trash2, Search, X, FileText, AlertTriangle, Languages } from 'lucide-react';
import { useToast, useNotifications } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { useAuth, usePusherOptional, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { PageMeta } from '@/components/seo';
import { VerificationBadgeRow } from '@/components/verification/VerificationBadge';
import type { NewMessageEvent, TypingEvent } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Message, User } from '@/types/api';
import { MessageContextCard } from '@/components/messages/MessageContextCard';
import { MessageBubble } from './components/MessageBubble';
import { MessageInputArea } from './components/MessageInputArea';
import type { AttachmentPreview } from './components/MessageInputArea';

interface OtherUser {
  id: number;
  name: string;
  first_name?: string;
  last_name?: string;
  avatar_url?: string | null;
  avatar?: string | null;
  tagline?: string;
  is_online?: boolean;
}

interface ConversationMeta {
  id: number;
  other_user: OtherUser;
  context_type?: string;
  context_id?: number;
}

interface ConversationData {
  meta: ConversationMeta;
  messages: Message[];
}

interface PaginationState {
  olderCursor: string | null;
  newerCursor: string | null;
  hasOlderMessages: boolean;
  hasNewerMessages: boolean;
}

export function ConversationPage() {
  const { t, i18n } = useTranslation('messages');
  const [pageTitle, setPageTitle] = useState(t('title'));
  usePageTitle(pageTitle);
  const { id, userId } = useParams<{ id?: string; userId?: string }>();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const { refreshCounts } = useNotifications();
  const pusher = usePusherOptional();
  const { hasFeature, hasModule, tenantPath, tenantSlug } = useTenant();
  const isDirectMessagingEnabled = hasFeature('direct_messaging');
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messagesContainerRef = useRef<HTMLDivElement>(null);
  const lastMessageIdRef = useRef<number | null>(null);
  const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pollingIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const isMountedRef = useRef(true);
  const mediaStreamRef = useRef<MediaStream | null>(null);

  // Determine if this is a new conversation (user ID) or existing (conversation ID)
  const isNewConversationRoute = !!userId;
  const targetId = userId || id;

  // Get listing ID from query params if provided
  const listingIdParam = searchParams.get('listing');
  const listingId = listingIdParam ? parseInt(listingIdParam) : null;

  // Get context from query params (MS1: contextual messaging)
  const contextTypeParam = searchParams.get('context_type');
  const contextIdParam = searchParams.get('context_id');
  const contextType = contextTypeParam || undefined;
  const contextId = contextIdParam ? parseInt(contextIdParam) : undefined;

  const [conversation, setConversation] = useState<ConversationData | null>(null);
  const [newMessage, setNewMessage] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isSending, setIsSending] = useState(false);
  const [isNewConversation, setIsNewConversation] = useState(false);
  const [listing, setListing] = useState<{ id: number; title: string; type: string } | null>(null);
  const [isLoadingOlder, setIsLoadingOlder] = useState(false);
  const [isOtherUserTyping, setIsOtherUserTyping] = useState(false);
  const [showArchiveModal, setShowArchiveModal] = useState(false);
  const [deleteScope, setDeleteScope] = useState<'self' | 'everyone'>('self');
  const [isArchiving, setIsArchiving] = useState(false);
  const [isDocumentVisible, setIsDocumentVisible] = useState(true);
  // Voice recording state
  const [isRecording, setIsRecording] = useState(false);
  const [recordingTime, setRecordingTime] = useState(0);
  const [audioBlob, setAudioBlob] = useState<Blob | null>(null);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const recordingIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);
  const [pagination, setPagination] = useState<PaginationState>({
    olderCursor: null,
    newerCursor: null,
    hasOlderMessages: false,
    hasNewerMessages: false,
  });
  // Search state
  const [showSearch, setShowSearch] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<number[]>([]); // message IDs
  const [currentSearchIndex, setCurrentSearchIndex] = useState(0);
  // File attachment state
  const [attachments, setAttachments] = useState<File[]>([]);
  const [attachmentPreviews, setAttachmentPreviews] = useState<AttachmentPreview[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);
  // Edit/delete state
  const [editingMessageId, setEditingMessageId] = useState<number | null>(null);
  const [editingText, setEditingText] = useState('');
  const [pendingDeleteId, setPendingDeleteId] = useState<number | null>(null);

  // Safeguarding notice state (reappears on reload)
  const [isSafeguardingDismissed, setIsSafeguardingDismissed] = useState(false);

  // Broker messaging restriction state
  const [messagingRestriction, setMessagingRestriction] = useState<{
    messaging_disabled: boolean;
    under_monitoring: boolean;
    restriction_reason: string | null;
  } | null>(null);

  // ── Translation hint banner (dismissed per-user, scoped to tenant) ──
  const tenantScope = tenantSlug || 'default';
  const TRANSLATION_HINT_KEY = `nexus_translation_hint_dismissed_${tenantScope}`;
  const translationFeatureEnabled = hasFeature('message_translation');
  const [translationHintDismissed, setTranslationHintDismissed] = useState(() =>
    localStorage.getItem(TRANSLATION_HINT_KEY) === '1'
  );
  const dismissTranslationHint = useCallback(() => {
    setTranslationHintDismissed(true);
    localStorage.setItem(TRANSLATION_HINT_KEY, '1');
  }, [TRANSLATION_HINT_KEY]);

  // ── Auto-translate state (scoped to tenant) ──────────────────────────────
  const STORAGE_KEY = `nexus_auto_translate_${tenantScope}`;

  function getAutoTranslatePrefs(): Record<string, string> {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch { return {}; }
  }

  function isAutoTranslateEnabled(otherUserId: number): boolean {
    return !!getAutoTranslatePrefs()[String(otherUserId)];
  }

  function toggleAutoTranslate(otherUserId: number, targetLang: string): boolean {
    const prefs = getAutoTranslatePrefs();
    if (prefs[String(otherUserId)]) {
      delete prefs[String(otherUserId)];
      localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
      return false;
    } else {
      prefs[String(otherUserId)] = targetLang;
      localStorage.setItem(STORAGE_KEY, JSON.stringify(prefs));
      return true;
    }
  }

  const [autoTranslateOn, setAutoTranslateOn] = useState(false);
  const [autoTranslations, setAutoTranslations] = useState<Map<number, string>>(new Map());
  const autoTranslatingRef = useRef(false);
  const translatedIdsRef = useRef<Set<number>>(new Set());
  const autoTranslateAbortRef = useRef<AbortController | null>(null);

  // Sync autoTranslateOn from localStorage when the other user changes
  useEffect(() => {
    if (conversation?.meta?.other_user?.id) {
      setAutoTranslateOn(isAutoTranslateEnabled(conversation.meta.other_user.id));
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps -- only re-check when other_user changes
  }, [conversation?.meta?.other_user?.id]);

  // Auto-translate effect: translate untranslated messages from the other user
  useEffect(() => {
    if (!autoTranslateOn || !conversation?.messages?.length || autoTranslatingRef.current) return;
    const otherUserId = conversation?.meta?.other_user?.id;
    if (!otherUserId) return;
    const targetLang = (i18n.language || 'en').split('-')[0] || 'en';

    // Find messages from the other user that haven't been translated or queued yet
    const untranslated = conversation.messages.filter(
      (msg) => msg.sender_id === otherUserId
        && !autoTranslations.has(msg.id)
        && !translatedIdsRef.current.has(msg.id)
        && (msg.body || msg.content)
    );
    if (untranslated.length === 0) return;

    // Mark these as queued immediately to prevent duplicate API calls
    for (const msg of untranslated) {
      translatedIdsRef.current.add(msg.id);
    }
    autoTranslatingRef.current = true;

    // Abort any previous in-flight translation loop (e.g., conversation switch)
    if (autoTranslateAbortRef.current) {
      autoTranslateAbortRef.current.abort();
    }
    const controller = new AbortController();
    autoTranslateAbortRef.current = controller;

    // Translate each message sequentially to avoid overwhelming the API
    (async () => {
      const newTranslations = new Map(autoTranslations);
      for (const msg of untranslated) {
        if (controller.signal.aborted || !isMountedRef.current) return;
        try {
          const response = await api.post<{ translated_text: string }>(`/v2/messages/${msg.id}/translate`, {
            target_language: targetLang,
          }, { signal: controller.signal });
          if (controller.signal.aborted || !isMountedRef.current) return;
          const translated = response.data?.translated_text;
          if (translated) {
            newTranslations.set(msg.id, translated);
          }
        } catch {
          // Remove from dedup set so retry is possible on next cycle
          translatedIdsRef.current.delete(msg.id);
        }
      }
      if (controller.signal.aborted || !isMountedRef.current) return;
      setAutoTranslations(newTranslations);
      autoTranslatingRef.current = false;
    })();
  // eslint-disable-next-line react-hooks/exhaustive-deps -- autoTranslateOn + messages length + language change trigger re-translation
  }, [autoTranslateOn, conversation?.messages?.length, i18n.language]);

  function handleAutoTranslateToggle() {
    const otherUser = conversation?.meta?.other_user;
    if (!otherUser) return;
    const targetLang = (i18n.language || 'en').split('-')[0] || 'en';
    const nowEnabled = toggleAutoTranslate(otherUser.id, targetLang);
    setAutoTranslateOn(nowEnabled);

    if (nowEnabled) {
      toast.success(t('auto_translate.enabled_toast'));
    } else {
      toast.info(t('auto_translate.disabled_toast'));
      // Clear auto-translations and dedup set when disabled
      setAutoTranslations(new Map());
      translatedIdsRef.current.clear();
    }
  }
  // ── End auto-translate state ──────────────────────────────────────────────

  // Update page title when conversation loads
  useEffect(() => {
    if (conversation?.meta?.other_user?.name) {
      setPageTitle(t('conversation_with', '{{name}} \u2014 Messages', { name: conversation.meta.other_user.name }));
    }
  }, [conversation?.meta?.other_user?.name, t]);

  // Track document visibility for polling optimization
  useEffect(() => {
    function handleVisibilityChange() {
      setIsDocumentVisible(!document.hidden);
    }
    document.addEventListener('visibilitychange', handleVisibilityChange);
    return () => document.removeEventListener('visibilitychange', handleVisibilityChange);
  }, []);

  // Debounced typing indicator sender
  const typingDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const sendTypingIndicator = useCallback((otherUserId: number, isTyping: boolean) => {
    if (typingDebounceRef.current) {
      clearTimeout(typingDebounceRef.current);
    }
    typingDebounceRef.current = setTimeout(() => {
      if (pusher) {
        pusher.sendTyping(otherUserId, isTyping);
      }
    }, 500);
  }, [pusher]);

  // Subscribe to Pusher channel for real-time updates
  useEffect(() => {
    if (!targetId || !pusher) return;

    const otherUserId = parseInt(targetId, 10);
    pusher.subscribeToConversation(otherUserId);

    // Listen for new messages
    const unsubMessage = pusher.onNewMessage((event: NewMessageEvent) => {
      // Backend may send from_user_id instead of sender_id; normalize
      const senderId: number | undefined = event.sender_id || event.from_user_id;

      // Only handle messages from the other user in this conversation
      if (senderId === otherUserId) {
        // Update last message ID
        lastMessageIdRef.current = event.id;

        // Add the message to the conversation
        setConversation((prev) => {
          if (!prev) return null;

          // Check if message already exists
          if (prev.messages.some((m) => m.id === event.id)) {
            return prev;
          }

          const newMsg: Message = {
            id: event.id,
            body: event.body || event.preview || '',
            sender_id: senderId,
            is_own: false,
            created_at: event.created_at || new Date().toISOString(),
          };

          return {
            ...prev,
            messages: [...prev.messages, newMsg],
          };
        });

        // Mark incoming message as read immediately (user is viewing this conversation)
        if (targetId) {
          api.put(`/v2/messages/${targetId}/read`).catch(() => {
            // non-critical — will sync on next load
          });
        }

        // Scroll to bottom for new messages
        requestAnimationFrame(() => scrollToBottom());
      }
    });

    // Listen for typing indicators
    const unsubTyping = pusher.onTyping((event: TypingEvent) => {
      if (event.user_id === otherUserId) {
        setIsOtherUserTyping(event.is_typing);

        // Auto-clear typing after 5 seconds (in case stop event is missed)
        if (event.is_typing) {
          if (typingTimeoutRef.current) {
            clearTimeout(typingTimeoutRef.current);
          }
          typingTimeoutRef.current = setTimeout(() => {
            setIsOtherUserTyping(false);
          }, 5000);
        }
      }
    });

    return () => {
      if (pusher) {
        pusher.unsubscribeFromConversation(otherUserId);
        if (typeof unsubMessage === 'function') unsubMessage();
        if (typeof unsubTyping === 'function') unsubTyping();
      }
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, [targetId, pusher, id]);

  const loadUserForNewConversation = useCallback(async (userIdToLoad: number) => {
    try {
      const response = await api.get<User>(`/v2/users/${userIdToLoad}`);
      if (response.success && response.data) {
        const userData = response.data;
        // Create a mock conversation with the user
        setConversation({
          meta: {
            id: userIdToLoad,
            other_user: {
              id: userData.id,
              name: userData.name || `${userData.first_name || ''} ${userData.last_name || ''}`.trim(),
              first_name: userData.first_name,
              last_name: userData.last_name,
              avatar_url: userData.avatar_url,
              avatar: userData.avatar,
              tagline: userData.tagline,
            },
          },
          messages: [],
        });
        setIsNewConversation(true);
      } else {
        // User not found - go back to messages
        navigate(tenantPath('/messages'));
      }
    } catch (error) {
      logError('Failed to load user for new conversation', error);
      navigate(tenantPath('/messages'));
    }
  }, [navigate, tenantPath]);

  /**
   * Poll for new messages using cursor-based pagination
   * Only fetches messages newer than the last known message
   */
  const pollForNewMessages = useCallback(async () => {
    // Only poll when we have a target user ID and a known last message
    if (!targetId || !lastMessageIdRef.current) return;

    try {
      // Use the last message ID as cursor to get only newer messages
      const cursor = btoa(String(lastMessageIdRef.current));
      const response = await api.get<Message[]>(`/v2/messages/${targetId}?direction=newer&cursor=${cursor}`);

      if (response.success && response.data && response.data.length > 0) {
        const newMessages = response.data;

        // Update the last message ID
        const lastNewMsg = newMessages[newMessages.length - 1];
        if (lastNewMsg) lastMessageIdRef.current = lastNewMsg.id;

        // Append new messages to the conversation
        setConversation((prev) => {
          if (!prev) return null;

          // Filter out any duplicates (by ID)
          const existingIds = new Set(prev.messages.map((m) => m.id));
          const uniqueNewMessages = newMessages.filter((m) => !existingIds.has(m.id));

          if (uniqueNewMessages.length === 0) return prev;

          return {
            ...prev,
            messages: [...prev.messages, ...uniqueNewMessages],
          };
        });
      }
    } catch {
      // Silent fail for polling - don't spam console
    }
  }, [targetId]);

  // Memoize loadConversation
  const loadConversation = useCallback(async () => {
    if (!targetId) return;

    // Clear auto-translate state from previous conversation
    if (autoTranslateAbortRef.current) {
      autoTranslateAbortRef.current.abort();
      autoTranslateAbortRef.current = null;
    }
    setAutoTranslations(new Map());
    translatedIdsRef.current.clear();
    autoTranslatingRef.current = false;

    try {
      setIsLoading(true);
      setIsNewConversation(false);

      // ALWAYS try to load existing messages from the API first, regardless
      // of whether this is the /messages/new/:userId or /messages/:id route.
      // This prevents the recurring regression where refreshing a "new" conversation
      // URL wiped all chat history because it skipped the API call entirely.
      const response = await api.get<Message[]>(`/v2/messages/${targetId}`);
      const meta = response.meta;
      if (response.success && response.data && meta?.conversation) {
        const messages = response.data as Message[];

        // API returns messages in descending order (newest first).
        // Reverse to chronological order (oldest first) for chat display.
        const chronologicalMessages = [...messages].reverse();

        setConversation({
          meta: meta.conversation as ConversationMeta,
          messages: chronologicalMessages,
        });

        // Track pagination state from response
        setPagination({
          olderCursor: meta.cursor || null,
          newerCursor: null,
          hasOlderMessages: meta.has_more || false,
          hasNewerMessages: false,
        });

        // Track the newest message ID for polling (last in chronological = newest)
        if (chronologicalMessages.length > 0) {
          const newestMsg = chronologicalMessages[chronologicalMessages.length - 1];
          if (newestMsg) lastMessageIdRef.current = newestMsg.id;
        }

        // If we're on the /messages/new/ route but messages exist, redirect to
        // the canonical /messages/:id URL so refreshes continue to work correctly.
        if (isNewConversationRoute && chronologicalMessages.length > 0) {
          navigate(tenantPath(`/messages/${targetId}`), { replace: true });
        }

        // Scroll to bottom on initial load
        setTimeout(() => scrollToBottom(), 100);
      } else if (response.success && response.data && response.data.length > 0) {
        // Fallback: messages loaded but meta.conversation missing — recover gracefully
        const messages = [...(response.data as Message[])].reverse();
        setConversation({
          meta: { id: parseInt(targetId, 10), other_user: { id: parseInt(targetId, 10), name: '' } },
          messages,
        });
        if (messages.length > 0) {
          const newestMsg = messages[messages.length - 1];
          if (newestMsg) lastMessageIdRef.current = newestMsg.id;
        }
        if (isNewConversationRoute) {
          navigate(tenantPath(`/messages/${targetId}`), { replace: true });
        }
        setTimeout(() => scrollToBottom(), 100);
      } else {
        // No existing messages — this is genuinely a new conversation.
        // Load user profile to show their info in the empty chat view.
        await loadUserForNewConversation(parseInt(targetId, 10));
      }
    } catch (error) {
      // API returned error (e.g., 404 for unknown user) — try new conversation fallback
      logError('Failed to load conversation, trying new conversation', error);
      await loadUserForNewConversation(parseInt(targetId, 10));
    } finally {
      setIsLoading(false);
    }
  }, [targetId, isNewConversationRoute, loadUserForNewConversation, navigate, tenantPath]);

  // Cleanup ref for unmount guard
  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
      if (autoTranslateAbortRef.current) {
        autoTranslateAbortRef.current.abort();
        autoTranslateAbortRef.current = null;
      }
    };
  }, []);

  // Load initial conversation
  useEffect(() => {
    loadConversation();
  }, [loadConversation]);

  // Mark conversation as read when it loads and when new messages arrive
  useEffect(() => {
    if (!conversation?.messages?.length || !targetId) return;

    // Find unread messages from the other user
    const unreadMessages = conversation.messages.filter(
      (msg) => msg.sender_id !== user?.id && !msg.is_read && !msg.read_at
    );

    if (unreadMessages.length === 0) return;

    // Mark the conversation as read via the API and refresh global unread badge
    api.put(`/v2/messages/${targetId}/read`).then(() => {
      refreshCounts().catch(() => { /* non-critical */ });
    }).catch((err) => {
      logError('Failed to mark conversation as read', err);
    });

    // Update local state to reflect read status
    setConversation((prev) => {
      if (!prev) return null;
      return {
        ...prev,
        messages: prev.messages.map((msg) =>
          msg.sender_id !== user?.id
            ? { ...msg, is_read: true, read_at: new Date().toISOString() }
            : msg
        ),
      };
    });
  // eslint-disable-next-line react-hooks/exhaustive-deps -- messages.length is sufficient; including messages ref causes double-fire
  }, [conversation?.messages?.length, targetId, user?.id]);

  // Fetch messaging restriction status (broker monitoring)
  const refreshRestrictionStatus = useCallback(() => {
    api.get<{ messaging_disabled: boolean; under_monitoring: boolean; restriction_reason: string | null }>(
      '/v2/messages/restriction-status'
    ).then((res) => {
      if (isMountedRef.current && res.success && res.data) {
        setMessagingRestriction(res.data);
      }
    }).catch(() => { /* non-critical */ });
  }, []);

  useEffect(() => {
    refreshRestrictionStatus();
  }, [refreshRestrictionStatus]);

  // Set up polling (fallback when Pusher not available) - pause when tab hidden
  useEffect(() => {
    // Clear any existing interval
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }

    // Only poll if: document visible, not new conversation, and have messages loaded
    if (!isDocumentVisible || isNewConversation || !lastMessageIdRef.current) {
      return;
    }

    // When Pusher is connected, use a longer polling interval as a reliability fallback.
    // When disconnected, poll more frequently as it's the primary update mechanism.
    const interval = pusher?.isConnected ? 30000 : 5000;

    pollingIntervalRef.current = setInterval(() => {
      pollForNewMessages();
    }, interval);

    return () => {
      if (pollingIntervalRef.current) {
        clearInterval(pollingIntervalRef.current);
        pollingIntervalRef.current = null;
      }
    };
  }, [targetId, isNewConversation, pusher?.isConnected, isDocumentVisible, pollForNewMessages]);

  // Scroll to bottom when messages change (only for new messages, not history)
  useEffect(() => {
    // Only auto-scroll if we're near the bottom already
    const container = messagesContainerRef.current;
    if (container) {
      const isNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
      if (isNearBottom) {
        scrollToBottom();
      }
    }
  }, [conversation?.messages.length]);

  // Fetch listing details if listing ID is provided
  useEffect(() => {
    if (!listingId) return;

    async function fetchListing() {
      try {
        const response = await api.get<{ id: number; title: string; type: string }>(`/v2/listings/${listingId}`);
        if (response.success && response.data) {
          setListing({
            id: response.data.id,
            title: response.data.title,
            type: response.data.type,
          });
        }
      } catch (error) {
        logError('Failed to fetch listing for message context', error);
      }
    }

    fetchListing();
  }, [listingId]);

  /**
   * Load older messages when user scrolls to top
   */
  const loadOlderMessages = useCallback(async () => {
    if (!targetId || !pagination.hasOlderMessages || isLoadingOlder || !pagination.olderCursor) return;

    try {
      setIsLoadingOlder(true);

      const response = await api.get<Message[]>(
        `/v2/messages/${targetId}?direction=older&cursor=${pagination.olderCursor}`
      );

      if (response.success && response.data) {
        // API returns older messages in descending order — reverse to chronological
        const olderMessages = [...response.data].reverse();

        // Update pagination state
        setPagination((prev) => ({
          ...prev,
          olderCursor: response.meta?.cursor || null,
          hasOlderMessages: response.meta?.has_more || false,
        }));

        // Prepend older messages (now in ascending order) before existing messages
        setConversation((prev) => {
          if (!prev) return null;

          // Filter out duplicates
          const existingIds = new Set(prev.messages.map((m) => m.id));
          const uniqueOlderMessages = olderMessages.filter((m) => !existingIds.has(m.id));

          if (uniqueOlderMessages.length === 0) return prev;

          return {
            ...prev,
            messages: [...uniqueOlderMessages, ...prev.messages],
          };
        });

        // Preserve scroll position after prepending
        const container = messagesContainerRef.current;
        if (container) {
          const scrollHeightBefore = container.scrollHeight;
          requestAnimationFrame(() => {
            const scrollHeightAfter = container.scrollHeight;
            container.scrollTop = scrollHeightAfter - scrollHeightBefore;
          });
        }
      }
    } catch (error) {
      logError('Failed to load older messages', error);
    } finally {
      setIsLoadingOlder(false);
    }
  }, [targetId, pagination.hasOlderMessages, pagination.olderCursor, isLoadingOlder]);

  // Handle scroll to detect when user wants to load older messages
  const handleScroll = useCallback(() => {
    const container = messagesContainerRef.current;
    if (!container) return;

    // If scrolled near top (within 50px), load older messages
    if (container.scrollTop < 50 && pagination.hasOlderMessages && !isLoadingOlder) {
      loadOlderMessages();
    }
  }, [pagination.hasOlderMessages, isLoadingOlder, loadOlderMessages]);

  function scrollToBottom() {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }

  /**
   * Delete the conversation — 'self' hides from current user only; 'everyone' hides from both.
   */
  async function deleteConversation(scope: 'self' | 'everyone') {
    if (!targetId || isArchiving) return;

    try {
      setIsArchiving(true);
      const response = await api.delete(`/v2/messages/conversations/${targetId}?scope=${scope}`);

      if (response.success) {
        if (scope === 'everyone') {
          toast.success(t('conversation_deleted_everyone'), t('conversation_deleted_everyone_desc'));
        } else {
          toast.success(t('conversation_archived'), t('conversation_archived_desc'));
        }
        navigate(tenantPath('/messages'));
      } else {
        throw new Error(response.error || t('archive_failed'));
      }
    } catch (error) {
      logError('Failed to delete conversation', error);
      toast.error(t('error_title'), t('archive_failed'));
    } finally {
      setIsArchiving(false);
      setShowArchiveModal(false);
    }
  }

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      // Stop any active recording
      if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
        mediaRecorderRef.current.stop();
      }
      // Release microphone
      if (mediaStreamRef.current) {
        mediaStreamRef.current.getTracks().forEach((track) => track.stop());
      }
      // Clear recording interval
      if (recordingIntervalRef.current) {
        clearInterval(recordingIntervalRef.current);
      }
      // Clear typing debounce timeout
      if (typingDebounceRef.current) {
        clearTimeout(typingDebounceRef.current);
      }
      // Revoke any remaining blob URLs
      attachmentPreviews.forEach((a) => { if (a.preview) URL.revokeObjectURL(a.preview); });
    };
  }, [attachmentPreviews]);

  /**
   * Start voice recording
   */
  async function startRecording() {
    if (messagingRestriction?.messaging_disabled) return;
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      mediaStreamRef.current = stream;
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      mediaRecorder.onstop = () => {
        const blob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
        setAudioBlob(blob);
        // Stop all tracks to release the microphone
        stream.getTracks().forEach((track) => track.stop());
        mediaStreamRef.current = null;
      };

      mediaRecorder.start();
      setIsRecording(true);
      setRecordingTime(0);

      // Start timer
      recordingIntervalRef.current = setInterval(() => {
        setRecordingTime((prev) => prev + 1);
      }, 1000);
    } catch (error) {
      logError('Failed to start recording', error);
      toast.error(t('mic_required_title'), t('mic_required_subtitle'));
    }
  }

  /**
   * Stop voice recording
   */
  function stopRecording() {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
      setIsRecording(false);
      if (recordingIntervalRef.current) {
        clearInterval(recordingIntervalRef.current);
        recordingIntervalRef.current = null;
      }
    }
  }

  /**
   * Cancel voice recording
   */
  function cancelRecording() {
    if (mediaRecorderRef.current && isRecording) {
      mediaRecorderRef.current.stop();
    }
    setIsRecording(false);
    setAudioBlob(null);
    setRecordingTime(0);
    if (recordingIntervalRef.current) {
      clearInterval(recordingIntervalRef.current);
      recordingIntervalRef.current = null;
    }
  }

  /**
   * Send voice message
   */
  async function sendVoiceMessage() {
    if (!audioBlob || !targetId || isSending) return;
    if (messagingRestriction?.messaging_disabled) {
      toast.error(t('error'), t('messaging_restricted'));
      return;
    }

    try {
      setIsSending(true);

      // Create form data with audio file
      const formData = new FormData();
      formData.append('recipient_id', targetId);
      formData.append('voice_message', audioBlob, 'voice-message.webm');

      const response = await api.upload<Message>('/v2/messages/voice', formData);

      if (response.success && response.data) {
        const sentMessage = response.data;
        lastMessageIdRef.current = sentMessage.id;

        setConversation((prev) => {
          if (!prev) return null;
          return {
            ...prev,
            messages: [...prev.messages, sentMessage],
          };
        });

        // Clear the audio blob
        setAudioBlob(null);
        setRecordingTime(0);

        if (isNewConversation) {
          setIsNewConversation(false);
          navigate(tenantPath(`/messages/${targetId}`), { replace: true });
        }

        setTimeout(() => scrollToBottom(), 50);
      } else {
        toast.error(t('error_title'), response.error || t('voice_send_error'));
        refreshRestrictionStatus();
      }
    } catch (error) {
      logError('Failed to send voice message', error);
      toast.error(t('error_title'), t('voice_send_error'));
      refreshRestrictionStatus();
    } finally {
      setIsSending(false);
    }
  }

  /**
   * Handle message reaction (toggle)
   */
  async function handleReaction(messageId: number, emoji: string) {
    try {
      const response = await api.post<{ action: 'added' | 'removed' }>(`/v2/messages/${messageId}/reactions`, { emoji });

      if (response.success) {
        // Update the message's reactions locally
        setConversation((prev) => {
          if (!prev) return null;

          return {
            ...prev,
            messages: prev.messages.map((msg) => {
              if (msg.id !== messageId) return msg;

              const reactions = { ...(msg.reactions || {}) };
              const currentCount = reactions[emoji] || 0;

              if (response.data?.action === 'removed') {
                // Reaction was removed
                if (currentCount <= 1) {
                  delete reactions[emoji];
                } else {
                  reactions[emoji] = currentCount - 1;
                }
              } else {
                // Reaction was added
                reactions[emoji] = currentCount + 1;
              }

              return { ...msg, reactions };
            }),
          };
        });
      }
    } catch (error) {
      logError('Failed to react to message', error);
    }
  }

  /**
   * Search messages in the current conversation
   */
  function handleSearch(query: string) {
    setSearchQuery(query);
    if (!query.trim() || !conversation) {
      setSearchResults([]);
      setCurrentSearchIndex(0);
      return;
    }

    const lowerQuery = query.toLowerCase();
    const matchingIds = conversation.messages
      .filter((msg) => (msg.body || msg.content || '').toLowerCase().includes(lowerQuery))
      .map((msg) => msg.id);

    setSearchResults(matchingIds);
    setCurrentSearchIndex(0);

    // Scroll to first result if found
    if (matchingIds.length > 0) {
      const firstId = matchingIds[0];
      if (firstId !== undefined) scrollToMessage(firstId);
    }
  }

  /**
   * Navigate to next/previous search result
   */
  function navigateSearchResult(direction: 'next' | 'prev') {
    if (searchResults.length === 0) return;

    let newIndex = currentSearchIndex;
    if (direction === 'next') {
      newIndex = (currentSearchIndex + 1) % searchResults.length;
    } else {
      newIndex = (currentSearchIndex - 1 + searchResults.length) % searchResults.length;
    }

    setCurrentSearchIndex(newIndex);
    const resultId = searchResults[newIndex];
    if (resultId !== undefined) scrollToMessage(resultId);
  }

  /**
   * Handle file selection for attachments
   */
  function handleFileSelect(e: ChangeEvent<HTMLInputElement>) {
    const files = Array.from(e.target.files || []);
    if (files.length === 0) return;

    // Limit to 5 files at a time
    const newFiles = files.slice(0, 5 - attachments.length);

    // Create previews for images
    const newPreviews = newFiles.map((file) => {
      const isImage = file.type.startsWith('image/');
      return {
        file,
        preview: isImage ? URL.createObjectURL(file) : '',
        type: isImage ? 'image' as const : 'file' as const,
      };
    });

    setAttachments((prev) => [...prev, ...newFiles]);
    setAttachmentPreviews((prev) => [...prev, ...newPreviews]);

    // Reset file input
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  }

  /**
   * Remove an attachment
   */
  function removeAttachment(index: number) {
    // Revoke object URL if it's an image preview
    if (attachmentPreviews[index]?.preview) {
      URL.revokeObjectURL(attachmentPreviews[index].preview);
    }
    setAttachments((prev) => prev.filter((_, i) => i !== index));
    setAttachmentPreviews((prev) => prev.filter((_, i) => i !== index));
  }

  /**
   * Send message with attachments
   */
  async function sendMessageWithAttachments(e: FormEvent) {
    e.preventDefault();
    if ((!newMessage.trim() && attachments.length === 0) || !targetId || isSending) return;
    if (messagingRestriction?.messaging_disabled) {
      toast.error(t('error'), t('messaging_restricted'));
      return;
    }

    try {
      setIsSending(true);

      if (attachments.length > 0) {
        // Create form data with attachments
        const formData = new FormData();
        formData.append('recipient_id', targetId);
        formData.append('body', newMessage.trim());
        attachments.forEach((file) => {
          formData.append('attachments[]', file);
        });

        // Include listing ID if this is the first message in conversation
        if (listingId && isNewConversation) {
          formData.append('listing_id', listingId.toString());
        }

        // MS1: Include context if provided via URL params
        if (contextType && contextId && isNewConversation) {
          formData.append('context_type', contextType);
          formData.append('context_id', contextId.toString());
        }

        const response = await api.upload<Message>('/v2/messages', formData);

        if (response.success && response.data) {
          const sentMessage = response.data;
          lastMessageIdRef.current = sentMessage.id;

          setConversation((prev) => {
            if (!prev) return null;
            return {
              ...prev,
              messages: [...prev.messages, sentMessage],
            };
          });

          // Clear form
          setNewMessage('');
          setAttachments([]);
          attachmentPreviews.forEach((p) => {
            if (p.preview) URL.revokeObjectURL(p.preview);
          });
          setAttachmentPreviews([]);

          if (isNewConversation) {
            setIsNewConversation(false);
            navigate(tenantPath(`/messages/${targetId}`), { replace: true });
          }

          setTimeout(() => scrollToBottom(), 50);
        } else {
          toast.error(t('error_title'), response.error || t('send_error'));
          refreshRestrictionStatus();
        }
      } else {
        // Regular text message (no attachments)
        const payload: Record<string, unknown> = {
          recipient_id: parseInt(targetId, 10),
          body: newMessage.trim(),
        };

        // Include listing ID if this is the first message in conversation
        if (listingId && isNewConversation) {
          payload.listing_id = listingId;
        }

        // MS1: Include context if provided via URL params
        if (contextType && contextId && isNewConversation) {
          payload.context_type = contextType;
          payload.context_id = contextId;
        }

        const response = await api.post<Message>('/v2/messages', payload);

        if (response.success && response.data) {
          const sentMessage = response.data;
          lastMessageIdRef.current = sentMessage.id;

          setConversation((prev) => {
            if (!prev) return null;
            return {
              ...prev,
              messages: [...prev.messages, sentMessage],
            };
          });
          setNewMessage('');

          if (isNewConversation) {
            setIsNewConversation(false);
            // Redirect to the canonical conversation URL so refreshes load
            // message history from the API instead of showing empty state.
            navigate(tenantPath(`/messages/${targetId}`), { replace: true });
          }

          setTimeout(() => scrollToBottom(), 50);
        } else {
          logError('Message send failed', response);
          toast.error(t('error_title'), response.error || t('send_error'));
          refreshRestrictionStatus();
        }
      }
    } catch (error) {
      logError('Failed to send message', error);
      toast.error(t('error_title'), t('send_error'));
    } finally {
      setIsSending(false);
    }
  }

  /**
   * Send a GIF as a message (body contains the GIF URL)
   */
  async function handleGifSelect(gifUrl: string) {
    if (!targetId || isSending) return;
    if (messagingRestriction?.messaging_disabled) {
      toast.error(t('error'), t('messaging_restricted'));
      return;
    }

    try {
      setIsSending(true);
      const payload: Record<string, unknown> = {
        recipient_id: parseInt(targetId, 10),
        body: gifUrl,
      };

      if (listingId && isNewConversation) {
        payload.listing_id = listingId;
      }
      if (contextType && contextId && isNewConversation) {
        payload.context_type = contextType;
        payload.context_id = contextId;
      }

      const response = await api.post<Message>('/v2/messages', payload);

      if (response.success && response.data) {
        const sentMessage = response.data;
        lastMessageIdRef.current = sentMessage.id;

        setConversation((prev) => {
          if (!prev) return null;
          return {
            ...prev,
            messages: [...prev.messages, sentMessage],
          };
        });

        if (isNewConversation) {
          setIsNewConversation(false);
          navigate(tenantPath(`/messages/${targetId}`), { replace: true });
        }

        setTimeout(() => scrollToBottom(), 50);
      } else {
        logError('GIF message send failed', response);
        toast.error(t('error_title'), response.error || t('send_error'));
        refreshRestrictionStatus();
      }
    } catch (error) {
      logError('Failed to send GIF message', error);
      toast.error(t('error_title'), t('send_error'));
    } finally {
      setIsSending(false);
    }
  }

  /**
   * Scroll to a specific message by ID
   */
  function scrollToMessage(messageId: number) {
    const element = document.getElementById(`message-${messageId}`);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      // Add highlight effect
      element.classList.add('ring-2', 'ring-yellow-400/50');
      setTimeout(() => {
        element.classList.remove('ring-2', 'ring-yellow-400/50');
      }, 2000);
    }
  }

  /**
   * Start editing a message
   */
  function startEditing(message: Message) {
    setEditingMessageId(message.id);
    setEditingText(message.body || message.content || '');
  }

  /**
   * Cancel editing
   */
  function cancelEditing() {
    setEditingMessageId(null);
    setEditingText('');
  }

  /**
   * Save edited message
   */
  async function saveEdit() {
    if (!editingMessageId || !editingText.trim()) return;

    try {
      const response = await api.put<Message>(`/v2/messages/${editingMessageId}`, {
        body: editingText.trim(),
      });

      if (response.success) {
        setConversation((prev) => {
          if (!prev) return null;
          return {
            ...prev,
            messages: prev.messages.map((msg) =>
              msg.id === editingMessageId
                ? { ...msg, body: editingText.trim(), is_edited: true }
                : msg
            ),
          };
        });
        cancelEditing();
        toast.success(t('message_updated'), t('message_updated_subtitle'));
      }
    } catch (error) {
      logError('Failed to edit message', error);
      toast.error(t('error_title'), t('edit_error'));
    }
  }

  /**
   * Execute a scoped message delete after the user picks an option in the modal.
   */
  async function executeDelete(scope: 'self' | 'everyone') {
    if (pendingDeleteId === null) return;
    const messageId = pendingDeleteId;
    setPendingDeleteId(null);

    try {
      const response = await api.delete(`/v2/messages/${messageId}?scope=${scope}`);

      if (response.success) {
        if (scope === 'self') {
          // Remove from this user's view entirely
          setConversation((prev) => {
            if (!prev) return null;
            return { ...prev, messages: prev.messages.filter((msg) => msg.id !== messageId) };
          });
          toast.success(t('message_removed_self'), t('message_removed_self_subtitle'));
        } else {
          // Show deleted placeholder to both parties
          setConversation((prev) => {
            if (!prev) return null;
            return {
              ...prev,
              messages: prev.messages.map((msg) =>
                msg.id === messageId
                  ? { ...msg, body: t('message_deleted_placeholder'), is_deleted: true }
                  : msg
              ),
            };
          });
          toast.success(t('message_deleted'), t('message_deleted_subtitle'));
        }
      }
    } catch (error) {
      logError('Failed to delete message', error);
      toast.error(t('error_title'), t('delete_error'));
    }
  }

  // Feature gate: redirect if messages module is disabled for this tenant
  if (!hasModule('messages')) {
    return <Navigate to="/" replace />;
  }

  if (isLoading) {
    return <LoadingScreen message={t('loading')} />;
  }

  if (!conversation) {
    return (
      <div className="max-w-3xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h3 className="text-lg font-semibold text-theme-primary mb-2">{t('load_error_title')}</h3>
          <p className="text-theme-muted mb-4">{t('conversation_load_failed')}</p>
          <div className="flex gap-3 justify-center">
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              onPress={() => navigate(tenantPath('/messages'))}
            >
              {t('back_to_messages')}
            </Button>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              onPress={() => loadConversation()}
            >
              {t('try_again')}
            </Button>
          </div>
        </GlassCard>
      </div>
    );
  }

  const { meta, messages } = conversation;
  const other_user = meta.other_user;

  return (
    <div className="-my-6 sm:-my-8 h-[calc(100dvh-4rem-4rem)] md:h-[calc(100dvh-4rem)] flex flex-col max-w-3xl mx-auto">
      <PageMeta title={t('page_meta.conversation.title')} noIndex />
      {/* Header */}
      <GlassCard className="p-4 mb-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Button
              isIconOnly
              size="sm"
              variant="light"
              className="text-theme-muted"
              onPress={() => navigate(tenantPath('/messages'))}
              aria-label={t('aria_back')}
            >
              <ArrowLeft className="w-5 h-5" aria-hidden="true" />
            </Button>

            <Link to={tenantPath(`/profile/${other_user.id}`)} className="flex items-center gap-3">
              <h1 className="sr-only">{t('conversation_with', { name: other_user.name })}</h1>
              <Avatar
                src={resolveAvatarUrl(other_user.avatar_url || other_user.avatar)}
                name={other_user.name}
                size="md"
                className="ring-2 ring-white/20"
              />
              <div>
                <div className="flex items-center gap-1.5">
                  {other_user.name ? (
                    <h2 className="font-semibold text-theme-primary">{other_user.name}</h2>
                  ) : (
                    <Skeleton className="rounded-md">
                      <div className="h-4 w-32 rounded-md bg-default-300" />
                    </Skeleton>
                  )}
                  <VerificationBadgeRow userId={other_user.id} size="sm" />
                </div>
                {other_user.tagline && (
                  <p className="text-xs text-theme-subtle">{other_user.tagline}</p>
                )}
              </div>
            </Link>
          </div>

          <div className="flex items-center gap-2">
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              className="bg-theme-elevated text-theme-muted"
              aria-label={t('aria_search_messages')}
              onPress={() => setShowSearch(!showSearch)}
            >
              <Search className="w-4 h-4" />
            </Button>

            {/* Auto-translate toggle */}
            {translationFeatureEnabled && (
              <Tooltip content={autoTranslateOn ? t('auto_translate.tooltip_on') : t('auto_translate.tooltip_off')}>
                <Button
                  isIconOnly
                  variant="flat"
                  size="sm"
                  className={autoTranslateOn
                    ? 'bg-indigo-500/20 text-indigo-500 ring-1 ring-indigo-500/30'
                    : 'bg-theme-elevated text-theme-muted'
                  }
                  aria-label={autoTranslateOn ? t('auto_translate.tooltip_on') : t('auto_translate.tooltip_off')}
                  onPress={handleAutoTranslateToggle}
                >
                  <Languages className="w-4 h-4" />
                </Button>
              </Tooltip>
            )}

            <Link to={tenantPath(`/profile/${other_user.id}`)}>
              <Button
                isIconOnly
                variant="flat"
                size="sm"
                className="bg-theme-elevated text-theme-muted"
                aria-label={t('aria_view_profile')}
              >
                <Info className="w-4 h-4" />
              </Button>
            </Link>

            <Dropdown>
              <DropdownTrigger>
                <Button
                  isIconOnly
                  variant="flat"
                  size="sm"
                  className="bg-theme-elevated text-theme-muted"
                  aria-label={t('aria_more_options')}
                >
                  <MoreVertical className="w-4 h-4" />
                </Button>
              </DropdownTrigger>
              <DropdownMenu aria-label={t('aria_conversation_actions')}>
                <DropdownItem
                  key="delete_self"
                  startContent={<Trash2 className="w-4 h-4" />}
                  className="text-danger"
                  color="danger"
                  onPress={() => { setDeleteScope('self'); setShowArchiveModal(true); }}
                >
                  {t('delete_conversation_for_me')}
                </DropdownItem>
                <DropdownItem
                  key="delete_everyone"
                  startContent={<Trash2 className="w-4 h-4" />}
                  className="text-danger"
                  color="danger"
                  onPress={() => { setDeleteScope('everyone'); setShowArchiveModal(true); }}
                >
                  {t('delete_conversation_for_everyone')}
                </DropdownItem>
              </DropdownMenu>
            </Dropdown>
          </div>
        </div>
      </GlassCard>

      {/* Search Bar */}
      {showSearch && (
        <GlassCard className="p-3 mb-2">
          <div className="flex items-center gap-3">
            <div className="flex-1 relative">
              <Input
                placeholder={t('conversation_search_placeholder')}
                value={searchQuery}
                onChange={(e) => handleSearch(e.target.value)}
                startContent={<Search className="w-4 h-4 text-theme-subtle" />}
                aria-label={t('conversation_search_placeholder')}
                classNames={{
                  input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                  inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                }}
                autoFocus
              />
            </div>
            {searchResults.length > 0 && (
              <div className="flex items-center gap-2">
                <span className="text-sm text-theme-subtle">
                  {t('search_result_count', { current: currentSearchIndex + 1, total: searchResults.length })}
                </span>
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  onPress={() => navigateSearchResult('prev')}
                  aria-label={t('aria_prev_result')}
                >
                  <ArrowLeft className="w-3 h-3" />
                </Button>
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  onPress={() => navigateSearchResult('next')}
                  aria-label={t('aria_next_result')}
                >
                  <ArrowLeft className="w-3 h-3 rotate-180" />
                </Button>
              </div>
            )}
            <Button
              isIconOnly
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              onPress={() => {
                setShowSearch(false);
                setSearchQuery('');
                setSearchResults([]);
              }}
              aria-label={t('aria_close_search')}
            >
              <X className="w-4 h-4" />
            </Button>
          </div>
        </GlassCard>
      )}

      {/* Safeguarding / Broker Monitoring Notice */}
      {!isSafeguardingDismissed && (
        <div className="flex items-start gap-3 p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg" role="alert">
          <AlertTriangle className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
          <p className="text-amber-700 dark:text-amber-300 text-sm flex-1">
            {t('safeguarding_notice')}
          </p>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            className="text-amber-500 hover:text-amber-700 dark:hover:text-amber-300 flex-shrink-0 -mt-0.5"
            onPress={() => setIsSafeguardingDismissed(true)}
            aria-label={t('aria_dismiss_safeguarding')}
          >
            <X className="w-4 h-4" />
          </Button>
        </div>
      )}

      {/* Translation feature hint — shown once, dismissible */}
      {translationFeatureEnabled && !translationHintDismissed && (
        <div className="flex items-start gap-3 p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-lg" role="status">
          <Languages className="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
          <div className="flex-1 text-sm text-indigo-700 dark:text-indigo-300">
            <p className="font-medium">{t('translate_hint.title')}</p>
            <p className="mt-0.5 opacity-80">{t('translate_hint.body')}</p>
          </div>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            className="text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-200 flex-shrink-0 -mt-0.5"
            onPress={dismissTranslationHint}
            aria-label={t('translate_hint.dismiss')}
          >
            <X className="w-4 h-4" />
          </Button>
        </div>
      )}

      {/* Auto-translate active indicator */}
      {translationFeatureEnabled && autoTranslateOn && (
        <div className="flex items-center gap-2 px-3 py-2 bg-indigo-500/10 rounded-lg" role="status">
          <Languages className="w-4 h-4 text-indigo-500 flex-shrink-0" aria-hidden="true" />
          <p className="text-xs text-indigo-600 dark:text-indigo-300 flex-1">
            {t('auto_translate.active_banner')}
          </p>
          <Button
            size="sm"
            variant="light"
            className="h-6 min-w-0 px-2 text-xs text-indigo-500"
            onPress={handleAutoTranslateToggle}
          >
            {t('auto_translate.turn_off')}
          </Button>
        </div>
      )}

      {/* Listing Context Card */}
      {listing && (
        <GlassCard className="p-4">
          <div className="flex items-start gap-3">
            <FileText className="w-5 h-5 text-primary flex-shrink-0 mt-0.5" aria-hidden="true" />
            <div className="flex-1 min-w-0">
              <p className="text-sm text-theme-muted mb-1">
                {t('regarding_context', { type: listing.type === 'offer' ? 'offer' : 'request' })}
              </p>
              <Link
                to={tenantPath(`/listings/${listing.id}`)}
                className="font-medium text-theme-heading hover:text-primary transition-colors"
              >
                {listing.title}
              </Link>
            </div>
          </div>
        </GlassCard>
      )}

      {/* MS1: Contextual Message Card — shows linked context from conversation meta or URL params */}
      {!listing && (conversation?.meta?.context_type && conversation?.meta?.context_id) && (
        <MessageContextCard
          contextType={conversation.meta.context_type}
          contextId={conversation.meta.context_id}
        />
      )}
      {!listing && !conversation?.meta?.context_type && contextType && contextId && (
        <MessageContextCard
          contextType={contextType}
          contextId={contextId}
        />
      )}

      {/* Messages */}
      <GlassCard className="flex-1 overflow-hidden flex flex-col">
        <div
          ref={messagesContainerRef}
          className="flex-1 overflow-y-auto p-4 space-y-4"
          onScroll={handleScroll}
        >
          {/* Loading indicator for older messages */}
          {isLoadingOlder && (
            <div className="flex justify-center py-2">
              <Loader2 className="w-5 h-5 text-theme-subtle animate-spin" />
            </div>
          )}

          {/* "Load more" indicator when there are older messages */}
          {pagination.hasOlderMessages && !isLoadingOlder && (
            <div className="flex justify-center py-2">
              <Button
                variant="light"
                size="sm"
                className="text-sm text-theme-subtle"
                onPress={loadOlderMessages}
              >
                {t('load_older_hint')}
              </Button>
            </div>
          )}

          {messages.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-center">
              <Avatar
                src={resolveAvatarUrl(other_user.avatar_url || other_user.avatar)}
                name={other_user.name}
                className="w-20 h-20 ring-4 ring-theme-default mb-4"
              />
              <h3 className="text-lg font-semibold text-theme-primary mb-1">{other_user.name}</h3>
              <p className="text-theme-subtle text-sm max-w-xs">
                {t('conversation_start', { name: other_user.name })}
              </p>
            </div>
          ) : (
            <AnimatePresence mode="popLayout">
              {messages.map((message, index) => (
                <MessageBubble
                  key={message.id}
                  id={`message-${message.id}`}
                  message={message}
                  isOwn={message.sender_id === user?.id}
                  showAvatar={
                    index === 0 ||
                    messages[index - 1]?.sender_id !== message.sender_id
                  }
                  otherUser={other_user}
                  onReact={handleReaction}
                  isHighlighted={searchResults.includes(message.id)}
                  highlightQuery={searchQuery}
                  onEdit={startEditing}
                  onDelete={(id) => setPendingDeleteId(id)}
                  isEditing={editingMessageId === message.id}
                  editingText={editingMessageId === message.id ? editingText : ''}
                  onEditingTextChange={setEditingText}
                  onSaveEdit={saveEdit}
                  onCancelEdit={cancelEditing}
                  autoTranslatedText={autoTranslations.get(message.id) ?? null}
                />
              ))}
            </AnimatePresence>
          )}
          <div ref={messagesEndRef} />
        </div>

        {/* Typing Indicator */}
        <div aria-live="polite" aria-atomic="true">
          {isOtherUserTyping && (
            <div className="px-4 py-2 border-t border-theme-default">
              <div className="flex items-center gap-2 text-theme-subtle text-sm">
                <div className="flex gap-1" aria-hidden="true">
                  <span className="w-1.5 h-1.5 bg-indigo-500/60 rounded-full animate-bounce" />
                  <span className="w-1.5 h-1.5 bg-indigo-500/60 rounded-full animate-bounce [animation-delay:150ms]" />
                  <span className="w-1.5 h-1.5 bg-indigo-500/60 rounded-full animate-bounce [animation-delay:300ms]" />
                </div>
                <span>{t('typing_indicator', { name: other_user.name })}</span>
              </div>
            </div>
          )}
        </div>

        {/* Message Input Area */}
        <MessageInputArea
          isDirectMessagingEnabled={isDirectMessagingEnabled}
          messagingRestriction={messagingRestriction}
          newMessage={newMessage}
          onNewMessageChange={setNewMessage}
          onSendMessage={sendMessageWithAttachments}
          isSending={isSending}
          onTypingIndicator={(value) => {
            if (targetId) {
              sendTypingIndicator(parseInt(targetId, 10), value.length > 0);
            }
          }}
          onBlurTypingStop={() => {
            if (pusher && targetId) {
              pusher.sendTyping(parseInt(targetId, 10), false);
            }
          }}
          isRecording={isRecording}
          recordingTime={recordingTime}
          audioBlob={audioBlob}
          onStartRecording={startRecording}
          onStopRecording={stopRecording}
          onCancelRecording={cancelRecording}
          onSendVoiceMessage={sendVoiceMessage}
          onClearAudioBlob={() => setAudioBlob(null)}
          attachments={attachments}
          attachmentPreviews={attachmentPreviews}
          fileInputRef={fileInputRef}
          onFileSelect={handleFileSelect}
          onRemoveAttachment={removeAttachment}
          onGifSelect={handleGifSelect}
        />
      </GlassCard>

      {/* Archive Confirmation Modal */}
      <Modal
        isOpen={showArchiveModal}
        onOpenChange={setShowArchiveModal}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {deleteScope === 'everyone'
              ? t('delete_conversation_for_everyone')
              : t('delete_conversation_for_me')}
          </ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              {deleteScope === 'everyone'
                ? t('delete_conversation_everyone_prompt', { name: other_user.name })
                : t('delete_conversation_self_prompt', { name: other_user.name })}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              onPress={() => setShowArchiveModal(false)}
            >
              {t('cancel')}
            </Button>
            <Button
              color="danger"
              onPress={() => deleteConversation(deleteScope)}
              isLoading={isArchiving}
            >
              {t('delete_confirm')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Message Modal */}
      <Modal
        isOpen={pendingDeleteId !== null}
        onOpenChange={(open) => { if (!open) setPendingDeleteId(null); }}
        classNames={{
          base: 'bg-content1 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('delete_message_title')}
          </ModalHeader>
          <ModalBody>
            <p className="text-theme-muted text-sm">{t('delete_message_body')}</p>
          </ModalBody>
          <ModalFooter className="flex-col gap-2">
            <Button color="danger" variant="flat" fullWidth onPress={() => executeDelete('everyone')}>
              {t('delete_for_everyone')}
            </Button>
            <Button variant="bordered" fullWidth onPress={() => executeDelete('self')}>
              {t('delete_for_me')}
            </Button>
            <Button variant="light" fullWidth onPress={() => setPendingDeleteId(null)}>
              {t('cancel')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ConversationPage;
