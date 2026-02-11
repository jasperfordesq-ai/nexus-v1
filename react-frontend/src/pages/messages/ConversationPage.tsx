/**
 * Conversation Page - Individual message thread
 *
 * Features:
 * - Real-time message updates via polling (with cursor for efficiency)
 * - Infinite scroll for message history (scroll up to load older messages)
 * - Voice message playback
 * - Typing indicators (when Pusher is available)
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Input, Avatar, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Dropdown, DropdownTrigger, DropdownMenu, DropdownItem } from '@heroui/react';
import { ArrowLeft, Send, Info, Loader2, MoreVertical, Trash2, Mic, Square, Play, Pause, SmilePlus, Check, CheckCheck, Search, Paperclip, X, FileText, Pencil } from 'lucide-react';
import { useToast } from '@/contexts';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { useAuth, usePusherOptional, useTenant } from '@/contexts';
import type { NewMessageEvent, TypingEvent } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Message, User } from '@/types/api';

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
  const { id, userId } = useParams<{ id?: string; userId?: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const toast = useToast();
  const pusher = usePusherOptional();
  const { hasFeature } = useTenant();
  const isDirectMessagingEnabled = hasFeature('direct_messaging');
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messagesContainerRef = useRef<HTMLDivElement>(null);
  const lastMessageIdRef = useRef<number | null>(null);
  const typingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pollingIntervalRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const mediaStreamRef = useRef<MediaStream | null>(null);

  // Determine if this is a new conversation (user ID) or existing (conversation ID)
  const isNewConversationRoute = !!userId;
  const targetId = userId || id;

  const [conversation, setConversation] = useState<ConversationData | null>(null);
  const [newMessage, setNewMessage] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isSending, setIsSending] = useState(false);
  const [isNewConversation, setIsNewConversation] = useState(false);
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
  const [attachmentPreviews, setAttachmentPreviews] = useState<{ file: File; preview: string; type: 'image' | 'file' }[]>([]);
  const fileInputRef = useRef<HTMLInputElement>(null);
  // Edit/delete state
  const [editingMessageId, setEditingMessageId] = useState<number | null>(null);
  const [editingText, setEditingText] = useState('');

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
      // Only handle messages from the other user in this conversation
      if (event.sender_id === otherUserId) {
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
            body: event.body,
            sender_id: event.sender_id,
            is_own: false,
            created_at: event.created_at,
          };

          return {
            ...prev,
            messages: [...prev.messages, newMsg],
          };
        });

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
      if (response.success && response.data && response.meta?.conversation) {
        const messages = response.data;
        setConversation({
          meta: response.meta.conversation as ConversationMeta,
          messages,
        });

        // Track pagination state from response
        setPagination({
          olderCursor: response.meta.cursor || null,
          newerCursor: null,
          hasOlderMessages: response.meta.has_more || false,
          hasNewerMessages: false,
        });

        // Track the newest message ID for polling
        if (messages.length > 0) {
          lastMessageIdRef.current = messages[messages.length - 1].id;
        }

        // Scroll to bottom on initial load
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

  // Load initial conversation
  useEffect(() => {
    loadConversation();
  }, [loadConversation]);

  // Set up polling (fallback when Pusher not available) - pause when tab hidden
  useEffect(() => {
    // Clear any existing interval
    if (pollingIntervalRef.current) {
      clearInterval(pollingIntervalRef.current);
      pollingIntervalRef.current = null;
    }

    // Only poll if: document visible, not new conversation, have messages, and Pusher not connected
    if (!isDocumentVisible || isNewConversation || !lastMessageIdRef.current || pusher?.isConnected) {
      return;
    }

    pollingIntervalRef.current = setInterval(() => {
      pollForNewMessages();
    }, 5000);

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
        navigate('/messages');
      }
    } catch (error) {
      logError('Failed to load user for new conversation', error);
      navigate('/messages');
    }
  }

  /**
   * Poll for new messages using cursor-based pagination
   * Only fetches messages newer than the last known message
   */
  async function pollForNewMessages() {
    if (!id || !lastMessageIdRef.current) return;

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
    } catch (error) {
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
        toast.success('Conversation archived', 'This conversation has been moved to your archive.');
        navigate('/messages');
      } else {
        throw new Error(response.error || 'Failed to archive conversation');
      }
    } catch (error) {
      logError('Failed to archive conversation', error);
      toast.error('Error', 'Failed to archive conversation. Please try again.');
    } finally {
      setIsArchiving(false);
      setShowArchiveModal(false);
    }
  }

  // Cleanup voice recording on unmount
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
    };
  }, []);

  /**
   * Start voice recording
   */
  async function startRecording() {
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
      toast.error('Microphone Access Required', 'Please allow microphone access to record voice messages.');
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
    if (!audioBlob || !id || isSending) return;

    try {
      setIsSending(true);

      // Create form data with audio file
      const formData = new FormData();
      formData.append('recipient_id', id);
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
      }
    } catch (error) {
      logError('Failed to send voice message', error);
      toast.error('Error', 'Failed to send voice message. Please try again.');
    } finally {
      setIsSending(false);
    }
  }

  /**
   * Format recording time as mm:ss
   */
  function formatRecordingTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
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
  function handleFileSelect(e: React.ChangeEvent<HTMLInputElement>) {
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
  async function sendMessageWithAttachments(e: React.FormEvent) {
    e.preventDefault();
    if ((!newMessage.trim() && attachments.length === 0) || !id || isSending) return;

    try {
      setIsSending(true);

      if (attachments.length > 0) {
        // Create form data with attachments
        const formData = new FormData();
        formData.append('recipient_id', id);
        formData.append('body', newMessage.trim());
        attachments.forEach((file) => {
          formData.append('attachments[]', file);
        });

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
        }
      } else {
        // Regular text message (no attachments)
        const response = await api.post<Message>('/v2/messages', {
          recipient_id: parseInt(id, 10),
          body: newMessage.trim(),
        });

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
        }
      }
    } catch (error) {
      logError('Failed to send message', error);
      toast.error('Error', 'Failed to send message. Please try again.');
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
        toast.success('Message updated', 'Your message has been edited.');
      }
    } catch (error) {
      logError('Failed to edit message', error);
      toast.error('Error', 'Failed to edit message. Please try again.');
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
                ? { ...msg, body: '[Message deleted]', is_deleted: true }
                : msg
            ),
          };
        });
        toast.success('Message deleted', 'Your message has been deleted.');
      }
    } catch (error) {
      logError('Failed to delete message', error);
      toast.error('Error', 'Failed to delete message. Please try again.');
    }
  }

  if (isLoading) {
    return <LoadingScreen message="Loading conversation..." />;
  }

  if (!conversation) {
    return null;
  }

  const { meta, messages } = conversation;
  const other_user = meta.other_user;

  return (
    <div className="max-w-3xl mx-auto h-[calc(100vh-12rem)] flex flex-col">
      {/* Header */}
      <GlassCard className="p-4 mb-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <button
              onClick={() => navigate('/messages')}
              className="text-theme-muted hover:text-theme-primary transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>

            <Link to={`/profile/${other_user.id}`} className="flex items-center gap-3">
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
              aria-label="Search messages"
              onPress={() => setShowSearch(!showSearch)}
            >
              <Search className="w-4 h-4" />
            </Button>

            <Link to={`/profile/${other_user.id}`}>
              <Button
                isIconOnly
                variant="flat"
                size="sm"
                className="bg-theme-elevated text-theme-muted"
                aria-label="View profile"
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
                  aria-label="More options"
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
                  Archive Conversation
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
                placeholder="Search in conversation..."
                value={searchQuery}
                onChange={(e) => handleSearch(e.target.value)}
                startContent={<Search className="w-4 h-4 text-theme-subtle" />}
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
                  {currentSearchIndex + 1} of {searchResults.length}
                </span>
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  onPress={() => navigateSearchResult('prev')}
                  aria-label="Previous result"
                >
                  <ArrowLeft className="w-3 h-3" />
                </Button>
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  onPress={() => navigateSearchResult('next')}
                  aria-label="Next result"
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
              aria-label="Close search"
            >
              <X className="w-4 h-4" />
            </Button>
          </div>
        </GlassCard>
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
              <button
                onClick={loadOlderMessages}
                className="text-sm text-theme-subtle hover:text-theme-muted transition-colors"
              >
                Scroll up or tap to load older messages
              </button>
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
                This is the beginning of your conversation with {other_user.name}. Say hello!
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
          <div className="px-4 py-2 border-t border-theme-subtle">
            <div className="flex items-center gap-2 text-theme-subtle text-sm">
              <div className="flex gap-1">
                <span className="w-1.5 h-1.5 bg-theme-subtle rounded-full animate-bounce" style={{ animationDelay: '0ms' }} />
                <span className="w-1.5 h-1.5 bg-theme-subtle rounded-full animate-bounce" style={{ animationDelay: '150ms' }} />
                <span className="w-1.5 h-1.5 bg-theme-subtle rounded-full animate-bounce" style={{ animationDelay: '300ms' }} />
              </div>
              <span>{other_user.name} is typing...</span>
            </div>
          </div>
        )}

        {/* Input */}
        <div className="p-4 border-t border-theme-default">
          {/* Messaging disabled notice */}
          {!isDirectMessagingEnabled && (
            <div className="flex items-center gap-3 p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg text-center">
              <span className="text-amber-600 dark:text-amber-400 text-sm flex-1">
                Direct messaging is not enabled for this community. Use the exchange request system instead.
              </span>
              <Button
                size="sm"
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={() => navigate('/exchanges')}
              >
                Exchanges
              </Button>
            </div>
          )}

          {/* Voice recording preview */}
          {isDirectMessagingEnabled && audioBlob && !isRecording && (
            <div className="flex items-center gap-3 mb-3 p-3 bg-theme-elevated rounded-lg">
              <VoiceMessagePlayer audioBlob={audioBlob} />
              <div className="flex gap-2 ml-auto">
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  onPress={() => setAudioBlob(null)}
                >
                  Cancel
                </Button>
                <Button
                  size="sm"
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white dark:text-white"
                  onPress={sendVoiceMessage}
                  isLoading={isSending}
                >
                  Send
                </Button>
              </div>
            </div>
          )}

          {/* Recording indicator */}
          {isDirectMessagingEnabled && isRecording && (
            <div className="flex items-center gap-3 mb-3 p-3 bg-red-500/10 rounded-lg border border-red-500/20">
              <div className="w-3 h-3 bg-red-500 rounded-full animate-pulse" />
              <span className="text-theme-primary font-medium">{formatRecordingTime(recordingTime)}</span>
              <span className="text-theme-subtle text-sm">Recording...</span>
              <div className="ml-auto flex gap-2">
                <Button
                  size="sm"
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted"
                  onPress={cancelRecording}
                >
                  Cancel
                </Button>
                <Button
                  size="sm"
                  color="danger"
                  onPress={stopRecording}
                  startContent={<Square className="w-3 h-3" />}
                >
                  Stop
                </Button>
              </div>
            </div>
          )}

          {/* Attachment previews */}
          {isDirectMessagingEnabled && attachmentPreviews.length > 0 && (
            <div className="flex gap-2 mb-3 flex-wrap">
              {attachmentPreviews.map((item, index) => (
                <div key={index} className="relative group">
                  {item.type === 'image' ? (
                    <img
                      src={item.preview}
                      alt={item.file.name}
                      className="w-16 h-16 object-cover rounded-lg border border-theme-default"
                    />
                  ) : (
                    <div className="w-16 h-16 flex flex-col items-center justify-center bg-theme-elevated rounded-lg border border-theme-default">
                      <FileText className="w-6 h-6 text-theme-subtle" />
                      <span className="text-[10px] text-theme-subtle truncate max-w-14 px-1">
                        {item.file.name.split('.').pop()?.toUpperCase()}
                      </span>
                    </div>
                  )}
                  <button
                    type="button"
                    onClick={() => removeAttachment(index)}
                    className="absolute -top-1 -right-1 w-4 h-4 bg-red-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <X className="w-2.5 h-2.5 text-white" />
                  </button>
                </div>
              ))}
            </div>
          )}

          {/* Text input form */}
          {isDirectMessagingEnabled && !isRecording && !audioBlob && (
            <form onSubmit={sendMessageWithAttachments} className="flex gap-3">
              {/* Hidden file input */}
              <input
                ref={fileInputRef}
                type="file"
                multiple
                accept="image/*,.pdf,.doc,.docx,.txt"
                className="hidden"
                onChange={handleFileSelect}
              />
              {/* Attachment button */}
              <Button
                type="button"
                isIconOnly
                variant="flat"
                className="bg-theme-elevated text-theme-muted hover:text-theme-primary"
                onPress={() => fileInputRef.current?.click()}
                aria-label="Add attachment"
                isDisabled={attachments.length >= 5}
              >
                <Paperclip className="w-4 h-4" />
              </Button>
              <Input
                placeholder="Type a message..."
                value={newMessage}
                onChange={(e) => {
                  setNewMessage(e.target.value);
                  // Send debounced typing indicator
                  if (targetId) {
                    sendTypingIndicator(parseInt(targetId, 10), e.target.value.length > 0);
                  }
                }}
                onBlur={() => {
                  // Stop typing indicator when input loses focus
                  if (pusher && targetId) {
                    pusher.sendTyping(parseInt(targetId, 10), false);
                  }
                }}
                classNames={{
                  input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                  inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
                }}
                aria-label="Message input"
              />
              {/* Voice recording button - show when no text and no attachments */}
              {!newMessage.trim() && attachments.length === 0 && (
                <Button
                  type="button"
                  isIconOnly
                  variant="flat"
                  className="bg-theme-elevated text-theme-muted hover:text-theme-primary"
                  onPress={startRecording}
                  aria-label="Record voice message"
                >
                  <Mic className="w-4 h-4" />
                </Button>
              )}
              {/* Send button - show when there's text or attachments */}
              {(newMessage.trim() || attachments.length > 0) && (
                <Button
                  type="submit"
                  isIconOnly
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white dark:text-white"
                  isLoading={isSending}
                >
                  <Send className="w-4 h-4" />
                </Button>
              )}
            </form>
          )}
        </div>
      </GlassCard>

      {/* Archive Confirmation Modal */}
      <Modal
        isOpen={showArchiveModal}
        onOpenChange={setShowArchiveModal}
        classNames={{
          base: 'bg-white dark:bg-gray-900 border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-6',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            Archive Conversation
          </ModalHeader>
          <ModalBody>
            <p className="text-theme-muted">
              Are you sure you want to archive this conversation with{' '}
              <span className="font-semibold text-theme-primary">{other_user.name}</span>?
            </p>
            <p className="text-theme-subtle text-sm mt-2">
              The conversation will be hidden from your inbox but can be restored later.
              {other_user.name} will still see the conversation in their inbox.
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              onPress={() => setShowArchiveModal(false)}
            >
              Cancel
            </Button>
            <Button
              color="danger"
              onPress={archiveConversation}
              isLoading={isArchiving}
            >
              Archive
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

interface MessageBubbleProps {
  id?: string;
  message: Message;
  isOwn: boolean;
  showAvatar: boolean;
  otherUser: ConversationMeta['other_user'];
  onReact?: (messageId: number, emoji: string) => void;
  isHighlighted?: boolean;
  highlightQuery?: string;
  onEdit?: (message: Message) => void;
  onDelete?: (messageId: number) => void;
  isEditing?: boolean;
  editingText?: string;
  onEditingTextChange?: (text: string) => void;
  onSaveEdit?: () => void;
  onCancelEdit?: () => void;
}

// Available reaction emojis
const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

function MessageBubble({
  id,
  message,
  isOwn,
  showAvatar,
  otherUser,
  onReact,
  isHighlighted,
  highlightQuery,
  onEdit,
  onDelete,
  isEditing,
  editingText,
  onEditingTextChange,
  onSaveEdit,
  onCancelEdit,
}: MessageBubbleProps) {
  const [showReactionPicker, setShowReactionPicker] = useState(false);
  const [showMessageMenu, setShowMessageMenu] = useState(false);
  const reactionPickerRef = useRef<HTMLDivElement>(null);
  const messageMenuRef = useRef<HTMLDivElement>(null);
  const isVoiceMessage = message.is_voice || message.audio_url;
  const isDeleted = message.is_deleted;

  // Close popups when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (showReactionPicker && reactionPickerRef.current && !reactionPickerRef.current.contains(event.target as Node)) {
        setShowReactionPicker(false);
      }
      if (showMessageMenu && messageMenuRef.current && !messageMenuRef.current.contains(event.target as Node)) {
        setShowMessageMenu(false);
      }
    }

    if (showReactionPicker || showMessageMenu) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [showReactionPicker, showMessageMenu]);

  // Parse reactions from message (format: { emoji: count, ... } or array)
  const reactions = message.reactions || {};
  const hasReactions = Object.keys(reactions).length > 0;

  // Highlight search terms in message body
  function highlightText(text: string): React.ReactNode {
    if (!highlightQuery || !text) return text;
    const parts = text.split(new RegExp(`(${highlightQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'));
    return parts.map((part, i) =>
      part.toLowerCase() === highlightQuery.toLowerCase() ? (
        <mark key={i} className="bg-yellow-400/40 text-gray-900 dark:text-white rounded px-0.5">{part}</mark>
      ) : part
    );
  }

  return (
    <motion.div
      id={id}
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -10 }}
      className={`flex gap-3 ${isOwn ? 'flex-row-reverse' : ''} group transition-all duration-300 ${isHighlighted ? 'ring-2 ring-yellow-400/30 rounded-lg' : ''}`}
    >
      {showAvatar && !isOwn ? (
        <Avatar
          src={resolveAvatarUrl(otherUser.avatar_url || otherUser.avatar)}
          name={otherUser.name}
          size="sm"
          className="flex-shrink-0"
        />
      ) : (
        <div className="w-8" />
      )}

      <div className={`max-w-[70%] ${isOwn ? 'text-right' : ''} relative`}>
        <div
          className={`
            inline-block px-4 py-2 rounded-2xl relative
            ${isOwn
              ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-br-md'
              : 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white rounded-bl-md'
            }
          `}
        >
          {isEditing ? (
            /* Editing mode */
            <div className="min-w-[200px]">
              <Input
                value={editingText}
                onChange={(e) => onEditingTextChange?.(e.target.value)}
                classNames={{
                  input: 'bg-transparent text-inherit placeholder:text-inherit/40',
                  inputWrapper: 'bg-black/10 dark:bg-white/10 border-black/20 dark:border-white/20',
                }}
                autoFocus
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    onSaveEdit?.();
                  } else if (e.key === 'Escape') {
                    onCancelEdit?.();
                  }
                }}
              />
              <div className="flex gap-2 mt-2 justify-end">
                <Button size="sm" variant="flat" className="bg-black/10 dark:bg-white/10 text-inherit/70" onPress={onCancelEdit}>
                  Cancel
                </Button>
                <Button size="sm" className="bg-black/20 dark:bg-white/20 text-inherit" onPress={onSaveEdit}>
                  Save
                </Button>
              </div>
            </div>
          ) : isDeleted ? (
            /* Deleted message */
            <p className="text-sm opacity-40 italic">[Message deleted]</p>
          ) : isVoiceMessage ? (
            <VoiceMessagePlayer audioUrl={message.audio_url} />
          ) : (
            <>
              {(message.body || message.content) && (
                <p className="text-sm whitespace-pre-wrap">{highlightText(message.body || message.content || '')}</p>
              )}
              {/* Edited indicator */}
              {message.is_edited && (
                <span className="text-[10px] opacity-40 ml-1">(edited)</span>
              )}
              {/* Attachments */}
              {message.attachments && message.attachments.length > 0 && (
                <div className={`flex flex-wrap gap-2 ${message.body ? 'mt-2' : ''}`}>
                  {message.attachments.map((attachment) => (
                    <a
                      key={attachment.id}
                      href={attachment.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block"
                    >
                      {attachment.type === 'image' ? (
                        <img
                          src={attachment.url}
                          alt={attachment.name}
                          className="max-w-[200px] max-h-[200px] rounded-lg object-cover hover:opacity-90 transition-opacity"
                        />
                      ) : (
                        <div className="flex items-center gap-2 px-3 py-2 bg-black/10 dark:bg-white/10 rounded-lg hover:bg-black/20 dark:hover:bg-white/20 transition-colors">
                          <FileText className="w-4 h-4 opacity-60" />
                          <div className="flex flex-col">
                            <span className="text-xs opacity-80 truncate max-w-[150px]">{attachment.name}</span>
                            <span className="text-[10px] opacity-40">
                              {(attachment.size / 1024).toFixed(1)} KB
                            </span>
                          </div>
                        </div>
                      )}
                    </a>
                  ))}
                </div>
              )}
            </>
          )}

          {/* Action buttons - shows on hover (only when not editing) */}
          {!isEditing && !isDeleted && (
            <div className={`absolute -bottom-2 ${isOwn ? '-left-12' : '-right-12'} flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity`}>
              {/* Reaction button */}
              <button
                onClick={() => setShowReactionPicker(!showReactionPicker)}
                className="w-5 h-5 flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-full border border-theme-default hover:bg-gray-200 dark:hover:bg-gray-700"
              >
                <SmilePlus className="w-3 h-3 text-theme-muted" />
              </button>

              {/* Edit/Delete button (only for own messages) */}
              {isOwn && !isVoiceMessage && (
                <button
                  onClick={() => setShowMessageMenu(!showMessageMenu)}
                  className="w-5 h-5 flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-full border border-theme-default hover:bg-gray-200 dark:hover:bg-gray-700"
                >
                  <MoreVertical className="w-3 h-3 text-theme-muted" />
                </button>
              )}
            </div>
          )}

          {/* Reaction picker */}
          {showReactionPicker && (
            <div
              ref={reactionPickerRef}
              className={`
                absolute ${isOwn ? 'left-0' : 'right-0'} -top-10
                flex gap-1 p-1.5 bg-white dark:bg-gray-800 rounded-full border border-theme-default
                shadow-lg z-10
              `}
              role="menu"
              aria-label="Add reaction"
            >
              {REACTION_EMOJIS.map((emoji) => (
                <button
                  key={emoji}
                  onClick={() => {
                    onReact?.(message.id, emoji);
                    setShowReactionPicker(false);
                  }}
                  className="w-7 h-7 flex items-center justify-center hover:bg-gray-100 dark:hover:bg-white/10 rounded-full transition-colors"
                  aria-label={`React with ${emoji}`}
                >
                  {emoji}
                </button>
              ))}
            </div>
          )}

          {/* Message menu (edit/delete) */}
          {showMessageMenu && isOwn && (
            <div
              ref={messageMenuRef}
              className={`
                absolute ${isOwn ? 'left-0' : 'right-0'} -top-16
                flex flex-col p-1 bg-white dark:bg-gray-800 rounded-lg border border-theme-default
                shadow-lg z-10 min-w-[100px]
              `}
              role="menu"
              aria-label="Message options"
            >
              <button
                onClick={() => {
                  onEdit?.(message);
                  setShowMessageMenu(false);
                }}
                className="flex items-center gap-2 px-3 py-1.5 text-sm text-theme-muted hover:bg-gray-100 dark:hover:bg-white/10 rounded"
                role="menuitem"
              >
                <Pencil className="w-3 h-3" aria-hidden="true" />
                Edit
              </button>
              <button
                onClick={() => {
                  onDelete?.(message.id);
                  setShowMessageMenu(false);
                }}
                className="flex items-center gap-2 px-3 py-1.5 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-white/10 rounded"
                role="menuitem"
              >
                <Trash2 className="w-3 h-3" aria-hidden="true" />
                Delete
              </button>
            </div>
          )}
        </div>

        {/* Display existing reactions */}
        {hasReactions && (
          <div className={`flex gap-1 mt-1 ${isOwn ? 'justify-end' : 'justify-start'} px-2`}>
            {Object.entries(reactions).map(([emoji, count]) => (
              <button
                key={emoji}
                onClick={() => onReact?.(message.id, emoji)}
                className="flex items-center gap-0.5 px-1.5 py-0.5 bg-gray-100 dark:bg-white/5 rounded-full text-xs hover:bg-gray-200 dark:hover:bg-white/10 transition-colors"
              >
                <span>{emoji}</span>
                {typeof count === 'number' && count > 1 && (
                  <span className="text-theme-subtle">{count}</span>
                )}
              </button>
            ))}
          </div>
        )}

        <div className={`flex items-center gap-1 mt-1 px-2 ${isOwn ? 'justify-end' : 'justify-start'}`}>
          <span className="text-xs text-gray-400 dark:text-white/30">
            {new Date(message.created_at || message.sent_at || Date.now()).toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit',
            })}
          </span>
          {/* Read receipts - only show for own messages */}
          {isOwn && (
            <span className="text-gray-400 dark:text-white/40">
              {message.is_read || message.read_at ? (
                <CheckCheck className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
              ) : (
                <Check className="w-3.5 h-3.5" />
              )}
            </span>
          )}
        </div>
      </div>
    </motion.div>
  );
}

/**
 * Voice message player component
 */
interface VoiceMessagePlayerProps {
  audioUrl?: string;
  audioBlob?: Blob;
}

function VoiceMessagePlayer({ audioUrl, audioBlob }: VoiceMessagePlayerProps) {
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  useEffect(() => {
    // Create audio element
    const audio = new Audio();
    audioRef.current = audio;

    if (audioBlob) {
      audio.src = URL.createObjectURL(audioBlob);
    } else if (audioUrl) {
      audio.src = audioUrl;
    }

    audio.onloadedmetadata = () => {
      setDuration(audio.duration);
    };

    audio.ontimeupdate = () => {
      setCurrentTime(audio.currentTime);
    };

    audio.onended = () => {
      setIsPlaying(false);
      setCurrentTime(0);
    };

    return () => {
      if (audioBlob) {
        URL.revokeObjectURL(audio.src);
      }
      audio.pause();
    };
  }, [audioUrl, audioBlob]);

  function togglePlay() {
    const audio = audioRef.current;
    if (!audio) return;

    if (isPlaying) {
      audio.pause();
      setIsPlaying(false);
    } else {
      audio.play();
      setIsPlaying(true);
    }
  }

  function formatTime(seconds: number): string {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }

  const progress = duration > 0 ? (currentTime / duration) * 100 : 0;

  return (
    <div className="flex items-center gap-3 min-w-[150px]">
      <button
        onClick={togglePlay}
        className="w-8 h-8 flex items-center justify-center bg-black/20 dark:bg-white/20 rounded-full hover:bg-black/30 dark:hover:bg-white/30 transition-colors"
      >
        {isPlaying ? (
          <Pause className="w-4 h-4" />
        ) : (
          <Play className="w-4 h-4 ml-0.5" />
        )}
      </button>
      <div className="flex-1">
        <div className="h-1 bg-black/20 dark:bg-white/20 rounded-full overflow-hidden">
          <div
            className="h-full bg-black/60 dark:bg-white/60 rounded-full transition-all"
            style={{ width: `${progress}%` }}
          />
        </div>
        <div className="flex justify-between text-xs opacity-50 mt-1">
          <span>{formatTime(currentTime)}</span>
          <span>{formatTime(duration)}</span>
        </div>
      </div>
    </div>
  );
}

export default ConversationPage;
