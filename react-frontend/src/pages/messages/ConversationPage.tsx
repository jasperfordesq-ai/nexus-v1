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
import { useParams, Link, useNavigate, useSearchParams } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import { Button, Avatar, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem, Input } from '@heroui/react';
import { ArrowLeft, Info, Loader2, MoreVertical, Trash2, Search, X, FileText, AlertTriangle } from 'lucide-react';
import { useToast } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { useAuth, usePusherOptional, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
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
  const { t } = useTranslation('messages');
  const [pageTitle, setPageTitle] = useState(t('title'));
  usePageTitle(pageTitle);
  const { id, userId } = useParams<{ id?: string; userId?: string }>();
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const pusher = usePusherOptional();
  const { hasFeature, tenantPath } = useTenant();
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

  // Safeguarding notice state (reappears on reload)
  const [isSafeguardingDismissed, setIsSafeguardingDismissed] = useState(false);

  // Broker messaging restriction state
  const [messagingRestriction, setMessagingRestriction] = useState<{
    messaging_disabled: boolean;
    under_monitoring: boolean;
    restriction_reason: string | null;
  } | null>(null);

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
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const raw = event as any;
      const senderId: number | undefined = event.sender_id || raw.from_user_id;

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
            body: event.body || raw.preview || '',
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
        if (id) {
          api.put(`/v2/messages/${id}/read`).catch(() => {
            // non-critical — will sync on next load
          });
        }

        // Scroll to bottom for new messages
        setTimeout(() => scrollToBottom(), 50);
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
      pusher.unsubscribeFromConversation(otherUserId);
      unsubMessage();
      unsubTyping();
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, [targetId, pusher]);

  // Memoize loadConversation
  const loadConversation = useCallback(async () => {
    if (!targetId) return;

    try {
      setIsLoading(true);
      setIsNewConversation(false);

      // If this is a new conversation route, load user profile directly
      if (isNewConversationRoute) {
        await loadUserForNewConversation(parseInt(targetId, 10));
        return;
      }

      // API returns messages as data with conversation info in meta
      const response = await api.get<Message[]>(`/v2/messages/${targetId}`);
      const meta = response.meta;
      if (response.success && response.data && meta?.conversation) {
        const messages = response.data as Message[];
        setConversation({
          meta: meta.conversation as ConversationMeta,
          messages,
        });

        // Track pagination state from response
        setPagination({
          olderCursor: meta.cursor || null,
          newerCursor: null,
          hasOlderMessages: meta.has_more || false,
          hasNewerMessages: false,
        });

        // Track the newest message ID for polling
        if (messages.length > 0) {
          lastMessageIdRef.current = messages[messages.length - 1].id;
        }

        // Scroll to bottom on initial load
        setTimeout(() => scrollToBottom(), 100);
      } else if (response.success && response.data && response.data.length > 0) {
        // Fallback: messages loaded but meta.conversation missing — recover gracefully
        const messages = response.data as Message[];
        setConversation({
          meta: { id: parseInt(targetId, 10), other_user: { id: parseInt(targetId, 10), name: '' } },
          messages,
        });
        if (messages.length > 0) {
          lastMessageIdRef.current = messages[messages.length - 1].id;
        }
        setTimeout(() => scrollToBottom(), 100);
      } else {
        // No existing conversation - this might be a new conversation
        // Try to fetch the user's profile to show their info
        await loadUserForNewConversation(parseInt(targetId, 10));
      }
    } catch (error) {
      // API returned error (e.g., 404) - try to start a new conversation
      logError('Failed to load conversation, trying new conversation', error);
      await loadUserForNewConversation(parseInt(targetId, 10));
    } finally {
      setIsLoading(false);
    }
  }, [targetId, isNewConversationRoute]);

  // Cleanup ref for unmount guard
  useEffect(() => {
    isMountedRef.current = true;
    return () => { isMountedRef.current = false; };
  }, []);

  // Load initial conversation
  useEffect(() => {
    loadConversation();
  }, [loadConversation]);

  // Mark conversation as read when it loads and when new messages arrive
  useEffect(() => {
    if (!conversation?.messages?.length || !id || isNewConversationRoute) return;

    // Find unread messages from the other user
    const unreadMessages = conversation.messages.filter(
      (msg) => msg.sender_id !== user?.id && !msg.is_read && !msg.read_at
    );

    if (unreadMessages.length === 0) return;

    // Mark the conversation as read via the API
    api.put(`/v2/messages/${id}/read`).catch((err) => {
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
  }, [conversation?.messages?.length, id, isNewConversationRoute, user?.id]);

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
  }, [targetId, isNewConversation, pusher?.isConnected, isDocumentVisible]);

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

  async function loadUserForNewConversation(userIdToLoad: number) {
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
  }

  /**
   * Poll for new messages using cursor-based pagination
   * Only fetches messages newer than the last known message
   */
  async function pollForNewMessages() {
    // Only poll for existing conversations (not new ones started via user ID)
    if (!id || isNewConversationRoute || !lastMessageIdRef.current) return;

    try {
      // Use the last message ID as cursor to get only newer messages
      const cursor = btoa(String(lastMessageIdRef.current));
      const response = await api.get<Message[]>(`/v2/messages/${id}?direction=newer&cursor=${cursor}`);

      if (response.success && response.data && response.data.length > 0) {
        const newMessages = response.data;

        // Update the last message ID
        lastMessageIdRef.current = newMessages[newMessages.length - 1].id;

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
  }

  /**
   * Load older messages when user scrolls to top
   */
  const loadOlderMessages = useCallback(async () => {
    if (!id || !pagination.hasOlderMessages || isLoadingOlder || !pagination.olderCursor) return;

    try {
      setIsLoadingOlder(true);

      const response = await api.get<Message[]>(
        `/v2/messages/${id}?direction=older&cursor=${pagination.olderCursor}`
      );

      if (response.success && response.data) {
        const olderMessages = response.data;

        // Update pagination state
        setPagination((prev) => ({
          ...prev,
          olderCursor: response.meta?.cursor || null,
          hasOlderMessages: response.meta?.has_more || false,
        }));

        // Prepend older messages
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
  }, [id, pagination.hasOlderMessages, pagination.olderCursor, isLoadingOlder]);

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
   * Archive the conversation (soft delete for current user only)
   */
  async function archiveConversation() {
    if (!id || isArchiving) return;

    try {
      setIsArchiving(true);
      const response = await api.delete(`/v2/messages/conversations/${id}`);

      if (response.success) {
        toast.success(t('conversation_archived'), t('conversation_archived_desc'));
        navigate(tenantPath('/messages'));
      } else {
        throw new Error(response.error || t('archive_failed'));
      }
    } catch (error) {
      logError('Failed to archive conversation', error);
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
  }, []);

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
      scrollToMessage(matchingIds[0]);
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
    scrollToMessage(searchResults[newIndex]);
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
   * Delete a message
   */
  async function deleteMessage(messageId: number) {
    try {
      const response = await api.delete(`/v2/messages/${messageId}`);

      if (response.success) {
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
    } catch (error) {
      logError('Failed to delete message', error);
      toast.error(t('error_title'), t('delete_error'));
    }
  }

  if (isLoading) {
    return <LoadingScreen message={t('loading')} />;
  }

  if (!conversation) {
    return (
      <div className="max-w-3xl mx-auto">
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" />
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
                <h2 className="font-semibold text-theme-primary">{other_user.name}</h2>
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
              <DropdownMenu aria-label="Conversation actions">
                <DropdownItem
                  key="archive"
                  startContent={<Trash2 className="w-4 h-4" />}
                  className="text-danger"
                  color="danger"
                  onPress={() => setShowArchiveModal(true)}
                >
                  {t('archive_title')}
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
                  onDelete={deleteMessage}
                  isEditing={editingMessageId === message.id}
                  editingText={editingMessageId === message.id ? editingText : ''}
                  onEditingTextChange={setEditingText}
                  onSaveEdit={saveEdit}
                  onCancelEdit={cancelEditing}
                />
              ))}
            </AnimatePresence>
          )}
          <div ref={messagesEndRef} />
        </div>

        {/* Typing Indicator */}
        {isOtherUserTyping && (
          <div className="px-4 py-2 border-t border-theme-default">
            <div className="flex items-center gap-2 text-theme-subtle text-sm">
              <div className="flex gap-1">
                <span className="w-1.5 h-1.5 bg-theme-elevated rounded-full animate-bounce" />
                <span className="w-1.5 h-1.5 bg-theme-elevated rounded-full animate-bounce [animation-delay:150ms]" />
                <span className="w-1.5 h-1.5 bg-theme-elevated rounded-full animate-bounce [animation-delay:300ms]" />
              </div>
              <span>{t('typing_indicator', { name: other_user.name })}</span>
            </div>
          </div>
        )}

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
            {t('archive_title')}
          </ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              {t('archive_confirm_prompt', { name: other_user.name })}
            </p>
            <p className="text-theme-subtle text-sm mt-2">
              {t('archive_confirm_body')}
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
              onPress={archiveConversation}
              isLoading={isArchiving}
            >
              {t('archive_confirm')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default ConversationPage;
