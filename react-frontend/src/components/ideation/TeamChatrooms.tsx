// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Team Chatrooms Component (I4)
 *
 * Channel-based messaging within a group/team workspace.
 * - List of channels (sidebar) with category labels & lock icon for private
 * - Create new channel
 * - Message list with post/delete/pin/unpin
 * - Pinned messages section
 * - Real-time-ready structure
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Button,
  Chip,
  Input,
  Spinner,
  Avatar,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Tooltip,
  useDisclosure,
} from '@heroui/react';
import Hash from 'lucide-react/icons/hash';
import Lock from 'lucide-react/icons/lock';
import Pin from 'lucide-react/icons/pin';
import PinOff from 'lucide-react/icons/pin-off';
import Plus from 'lucide-react/icons/plus';
import Send from 'lucide-react/icons/send';
import Trash2 from 'lucide-react/icons/trash-2';
import MessageSquare from 'lucide-react/icons/message-square';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast, usePusherOptional } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Chatroom {
  id: number;
  group_id: number;
  name: string;
  description: string | null;
  category: string | null;
  is_default: boolean;
  is_private: boolean;
  messages_count: number;
  created_at: string;
}

interface ChatMessage {
  id: number;
  chatroom_id: number;
  user_id: number;
  body: string;
  created_at: string;
  author: {
    id: number;
    name: string;
    avatar_url: string | null;
  };
}

interface PinnedMessage extends ChatMessage {
  pinned_by: number;
  pinned_at: string;
}

interface TeamChatroomsProps {
  groupId: number;
  isGroupAdmin: boolean;
}

/* ───────────────────────── Main Component ───────────────────────── */

export function TeamChatrooms({ groupId, isGroupAdmin }: TeamChatroomsProps) {
  const { t } = useTranslation('ideation');
  const { t: tGroups } = useTranslation('groups');
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();
  const pusher = usePusherOptional();

  const [chatrooms, setChatrooms] = useState<Chatroom[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [activeChatroomId, setActiveChatroomId] = useState<number | null>(null);

  // Messages
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [isLoadingMessages, setIsLoadingMessages] = useState(false);
  const [newMessage, setNewMessage] = useState('');
  const [isSending, setIsSending] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Pinned messages
  const [pinnedMessages, setPinnedMessages] = useState<PinnedMessage[]>([]);
  const [pinnedIds, setPinnedIds] = useState<Set<number>>(new Set());
  const [showPinned, setShowPinned] = useState(false);

  // Create channel modal
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [newChannelName, setNewChannelName] = useState('');
  const [isCreatingChannel, setIsCreatingChannel] = useState(false);

  // Delete channel
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const [isDeletingChannel, setIsDeletingChannel] = useState(false);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const isAdmin = isGroupAdmin || (user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role));

  const fetchChatrooms = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      const response = await api.get<Chatroom[]>(`/v2/groups/${groupId}/chatrooms`);
      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        const rooms = Array.isArray(response.data) ? response.data : [];
        setChatrooms(rooms);
        if (rooms.length > 0 && !activeChatroomId) {
          setActiveChatroomId(rooms[0]?.id ?? null);
        }
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to fetch chatrooms', err);
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
      }
    }
  }, [groupId, activeChatroomId]);

  const fetchMessages = useCallback(async (chatroomId: number) => {
    try {
      setIsLoadingMessages(true);
      const response = await api.get<ChatMessage[]>(`/v2/group-chatrooms/${chatroomId}/messages`);
      if (response.success && response.data) {
        setMessages(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to fetch messages', err);
    } finally {
      setIsLoadingMessages(false);
    }
  }, []);

  const fetchPinnedMessages = useCallback(async (chatroomId: number) => {
    try {
      const response = await api.get<PinnedMessage[]>(`/v2/groups/${groupId}/chatrooms/${chatroomId}/pinned`);
      if (response.success && response.data) {
        const pinned = Array.isArray(response.data) ? response.data : [];
        setPinnedMessages(pinned);
        setPinnedIds(new Set(pinned.map((p) => p.id)));
      }
    } catch (err) {
      logError('Failed to fetch pinned messages', err);
    }
  }, [groupId]);

  useEffect(() => {
    fetchChatrooms();
  }, [fetchChatrooms]);

  useEffect(() => {
    if (activeChatroomId) {
      fetchMessages(activeChatroomId);
      fetchPinnedMessages(activeChatroomId);
      setShowPinned(false);
    }
  }, [activeChatroomId, fetchMessages, fetchPinnedMessages]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Real-time message updates via Pusher
  useEffect(() => {
    if (!pusher?.client || !pusher.tenantId || !groupId || !activeChatroomId) return;

    const channelName = `private-tenant.${pusher.tenantId}.group.${groupId}`;

    try {
      const channel = pusher.client.subscribe(channelName);

      const handler = (data: { chatroom_id: number; message: ChatMessage }) => {
        if (data.chatroom_id === activeChatroomId && data.message.user_id !== user?.id) {
          setMessages(prev => [...prev, data.message]);
        }
      };

      channel.bind('chatroom.message_posted', handler);

      return () => {
        channel.unbind('chatroom.message_posted', handler);
      };
    } catch {
      // Pusher not available — fall back to polling
    }
  }, [pusher, groupId, activeChatroomId, user?.id]);

  const handleSendMessage = async () => {
    if (!newMessage.trim() || !activeChatroomId) return;

    setIsSending(true);
    try {
      await api.post(`/v2/group-chatrooms/${activeChatroomId}/messages`, {
        body: newMessage.trim(),
      });
      toastRef.current.success(tRef.current('toast.message_posted'));
      setNewMessage('');
      fetchMessages(activeChatroomId);
    } catch (err) {
      logError('Failed to send message', err);
      toastRef.current.error(tRef.current('toast.error_generic'));
    } finally {
      setIsSending(false);
    }
  };

  const handleDeleteMessage = async (messageId: number) => {
    try {
      await api.delete(`/v2/group-chatroom-messages/${messageId}`);
      setMessages(prev => prev.filter(m => m.id !== messageId));
      // Also remove from pinned if it was pinned
      if (pinnedIds.has(messageId)) {
        setPinnedMessages(prev => prev.filter(p => p.id !== messageId));
        setPinnedIds(prev => {
          const next = new Set(prev);
          next.delete(messageId);
          return next;
        });
      }
    } catch (err) {
      logError('Failed to delete message', err);
      toastRef.current.error(tRef.current('toast.error_generic'));
    }
  };

  const handlePinMessage = async (messageId: number) => {
    if (!activeChatroomId) return;
    try {
      await api.post(`/v2/groups/${groupId}/chatrooms/${activeChatroomId}/pin/${messageId}`, {});
      toastRef.current.success(tGroups('chatrooms.pinned_success'));
      fetchPinnedMessages(activeChatroomId);
    } catch (err) {
      logError('Failed to pin message', err);
      toastRef.current.error(tRef.current('toast.error_generic'));
    }
  };

  const handleUnpinMessage = async (messageId: number) => {
    if (!activeChatroomId) return;
    try {
      await api.delete(`/v2/groups/${groupId}/chatrooms/${activeChatroomId}/pin/${messageId}`);
      toastRef.current.success(tGroups('chatrooms.unpinned_success'));
      setPinnedMessages(prev => prev.filter(p => p.id !== messageId));
      setPinnedIds(prev => {
        const next = new Set(prev);
        next.delete(messageId);
        return next;
      });
    } catch (err) {
      logError('Failed to unpin message', err);
      toastRef.current.error(tRef.current('toast.error_generic'));
    }
  };

  const handleCreateChannel = async () => {
    if (!newChannelName.trim()) return;

    setIsCreatingChannel(true);
    try {
      const response = await api.post<Chatroom>(`/v2/groups/${groupId}/chatrooms`, {
        name: newChannelName.trim(),
      });
      if (response.success && response.data) {
        toastRef.current.success(tRef.current('toast.chatroom_created'));
        setNewChannelName('');
        onCreateClose();
        fetchChatrooms();
        setActiveChatroomId(response.data.id);
      }
    } catch (err) {
      logError('Failed to create channel', err);
      toastRef.current.error(tRef.current('toast.error_generic'));
    } finally {
      setIsCreatingChannel(false);
    }
  };

  const handleDeleteChannel = async () => {
    if (!activeChatroomId) return;

    setIsDeletingChannel(true);
    try {
      await api.delete(`/v2/group-chatrooms/${activeChatroomId}`);
      toastRef.current.success(tRef.current('toast.chatroom_deleted'));
      onDeleteClose();
      setActiveChatroomId(null);
      setMessages([]);
      setPinnedMessages([]);
      setPinnedIds(new Set());
      fetchChatrooms();
    } catch (err) {
      logError('Failed to delete channel', err);
      toastRef.current.error(tRef.current('toast.error_generic'));
    } finally {
      setIsDeletingChannel(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendMessage();
    }
  };

  if (isLoading) {
    return (
      <div className="flex justify-center py-8">
        <Spinner size="md" />
      </div>
    );
  }

  const activeChatroom = chatrooms.find(c => c.id === activeChatroomId);

  return (
    <div className="flex gap-4 min-h-[400px]">
      {/* Channel Sidebar */}
      <div className="w-48 shrink-0">
        <div className="flex items-center justify-between mb-3">
          <h3 className="text-sm font-semibold text-[var(--color-text)]">
            {t('chatrooms.title')}
          </h3>
          {isAdmin && (
            <Button
              isIconOnly
              variant="light"
              size="sm"
              onPress={onCreateOpen}
              aria-label={t('chatrooms.create')}
            >
              <Plus className="w-4 h-4" />
            </Button>
          )}
        </div>

        <div className="space-y-1">
          {chatrooms.map((room) => (
            <Button
              key={room.id}
              onPress={() => setActiveChatroomId(room.id)}
              variant="light"
              className={`w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center gap-2 justify-start h-auto ${
                activeChatroomId === room.id
                  ? 'bg-primary/10 text-primary font-medium'
                  : 'text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)]'
              }`}
            >
              {room.is_private ? (
                <Lock className="w-3.5 h-3.5 shrink-0" />
              ) : (
                <Hash className="w-3.5 h-3.5 shrink-0" />
              )}
              <span className="truncate flex-1">{room.name}</span>
              {room.category && (
                <Chip size="sm" variant="flat" className="text-[10px] h-4 min-h-0 px-1">
                  {room.category}
                </Chip>
              )}
            </Button>
          ))}
        </div>

        {chatrooms.length === 0 && (
          <p className="text-xs text-[var(--color-text-tertiary)] px-3 py-2">
            {t('chatrooms.empty_title')}
          </p>
        )}
      </div>

      {/* Message Area */}
      <div className="flex-1 flex flex-col">
        {!activeChatroomId ? (
          <EmptyState
            icon={<MessageSquare className="w-10 h-10 text-theme-subtle" />}
            title={t('chatrooms.empty_title')}
            description={t('chatrooms.empty_description')}
          />
        ) : (
          <>
            {/* Channel Header */}
            <div className="flex items-center justify-between mb-3 pb-3 border-b border-[var(--color-border)]">
              <div className="flex items-center gap-2">
                {activeChatroom?.is_private ? (
                  <Lock className="w-4 h-4 text-[var(--color-text-tertiary)]" />
                ) : (
                  <Hash className="w-4 h-4 text-[var(--color-text-tertiary)]" />
                )}
                <span className="font-semibold text-[var(--color-text)]">
                  {activeChatroom?.name}
                </span>
                {activeChatroom?.category && (
                  <Chip size="sm" variant="flat" color="default">
                    {activeChatroom.category}
                  </Chip>
                )}
                {activeChatroom?.is_private && (
                  <Chip size="sm" variant="flat" color="warning">
                    {tGroups('chatrooms.private_label')}
                  </Chip>
                )}
              </div>
              <div className="flex items-center gap-1">
                {pinnedMessages.length > 0 && (
                  <Tooltip content={tGroups('chatrooms.show_pinned')}>
                    <Button
                      isIconOnly
                      variant="light"
                      size="sm"
                      onPress={() => setShowPinned(!showPinned)}
                      aria-label={tGroups('chatrooms.show_pinned')}
                      className={showPinned ? 'text-warning' : ''}
                    >
                      <Pin className="w-3.5 h-3.5" />
                      <span className="text-[10px] absolute -top-0.5 -right-0.5 bg-warning text-warning-foreground rounded-full w-3.5 h-3.5 flex items-center justify-center">
                        {pinnedMessages.length}
                      </span>
                    </Button>
                  </Tooltip>
                )}
                {isAdmin && (
                  <Button
                    isIconOnly
                    variant="light"
                    size="sm"
                    color="danger"
                    onPress={onDeleteOpen}
                    aria-label={t('chatrooms.delete_channel')}
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </Button>
                )}
              </div>
            </div>

            {/* Pinned Messages Section */}
            {showPinned && pinnedMessages.length > 0 && (
              <div className="mb-3 p-3 rounded-lg bg-warning/10 border border-warning/20">
                <div className="flex items-center gap-2 mb-2">
                  <Pin className="w-3.5 h-3.5 text-warning" />
                  <span className="text-sm font-semibold text-[var(--color-text)]">
                    {tGroups('chatrooms.pinned_messages')}
                  </span>
                </div>
                <div className="space-y-2">
                  {pinnedMessages.map((msg) => (
                    <div key={msg.id} className="flex items-start gap-2 group">
                      <Avatar
                        src={resolveAvatarUrl(msg.author.avatar_url)}
                        size="sm"
                        className="w-5 h-5 shrink-0 mt-0.5"
                        name={msg.author.name}
                      />
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-1.5">
                          <span className="text-xs font-medium text-[var(--color-text)]">
                            {msg.author.name}
                          </span>
                          <span className="text-[10px] text-[var(--color-text-tertiary)]">
                            {formatRelativeTime(msg.created_at)}
                          </span>
                          {isAdmin && (
                            <Button
                              isIconOnly
                              size="sm"
                              variant="light"
                              onPress={() => handleUnpinMessage(msg.id)}
                              className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 min-w-0 w-auto h-auto"
                              aria-label={tGroups('chatrooms.unpin')}
                            >
                              <PinOff className="w-3 h-3 text-[var(--color-text-tertiary)] hover:text-warning" />
                            </Button>
                          )}
                        </div>
                        <p className="text-xs text-[var(--color-text-secondary)] whitespace-pre-wrap line-clamp-2">
                          {msg.body}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Messages */}
            <div className="flex-1 overflow-y-auto space-y-3 mb-3 max-h-96">
              {isLoadingMessages && (
                <div className="flex justify-center py-4">
                  <Spinner size="sm" />
                </div>
              )}

              {!isLoadingMessages && messages.length === 0 && (
                <EmptyState
                  icon={<MessageSquare className="w-8 h-8 text-theme-subtle" />}
                  title={t('chatrooms.empty_title')}
                  description={t('chatrooms.empty_description')}
                />
              )}

              {messages.map((msg) => (
                <div key={msg.id} className="flex items-start gap-2.5 group">
                  <Avatar
                    src={resolveAvatarUrl(msg.author.avatar_url)}
                    size="sm"
                    className="w-7 h-7 shrink-0 mt-0.5"
                    name={msg.author.name}
                  />
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-[var(--color-text)]">
                        {msg.author.name}
                      </span>
                      <span className="text-xs text-[var(--color-text-tertiary)]">
                        {formatRelativeTime(msg.created_at)}
                      </span>
                      {pinnedIds.has(msg.id) && (
                        <Pin className="w-3 h-3 text-warning" />
                      )}
                      {isAdmin && (
                        <Tooltip content={pinnedIds.has(msg.id) ? tGroups('chatrooms.unpin') : tGroups('chatrooms.pin')}>
                          <Button
                            isIconOnly
                            size="sm"
                            variant="light"
                            onPress={() =>
                              pinnedIds.has(msg.id)
                                ? handleUnpinMessage(msg.id)
                                : handlePinMessage(msg.id)
                            }
                            className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 min-w-0 w-auto h-auto"
                            aria-label={pinnedIds.has(msg.id) ? tGroups('chatrooms.unpin') : tGroups('chatrooms.pin')}
                          >
                            {pinnedIds.has(msg.id) ? (
                              <PinOff className="w-3 h-3 text-warning" />
                            ) : (
                              <Pin className="w-3 h-3 text-[var(--color-text-tertiary)] hover:text-warning" />
                            )}
                          </Button>
                        </Tooltip>
                      )}
                      {(user?.id === msg.user_id || isAdmin) && (
                        <Button
                          isIconOnly
                          size="sm"
                          variant="light"
                          onPress={() => handleDeleteMessage(msg.id)}
                          className="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 min-w-0 w-auto h-auto"
                          aria-label={t('comments.delete')}
                        >
                          <Trash2 className="w-3 h-3 text-[var(--color-text-tertiary)] hover:text-danger" />
                        </Button>
                      )}
                    </div>
                    <p className="text-sm text-[var(--color-text-secondary)] whitespace-pre-wrap">
                      {msg.body}
                    </p>
                  </div>
                </div>
              ))}
              <div ref={messagesEndRef} />
            </div>

            {/* Message Input */}
            {isAuthenticated && (
              <div className="flex gap-2">
                <Input
                  placeholder={t('chatrooms.message_placeholder')}
                  aria-label={t('chatrooms.message_placeholder')}
                  value={newMessage}
                  onValueChange={setNewMessage}
                  onKeyDown={handleKeyDown}
                  variant="bordered"
                  size="sm"
                  className="flex-1"
                />
                <Button
                  isIconOnly
                  color="primary"
                  size="sm"
                  isLoading={isSending}
                  isDisabled={!newMessage.trim()}
                  onPress={handleSendMessage}
                  aria-label={t('chatrooms.send')}
                >
                  <Send className="w-4 h-4" />
                </Button>
              </div>
            )}
          </>
        )}
      </div>

      {/* Create Channel Modal */}
      <Modal isOpen={isCreateOpen} onClose={onCreateClose}>
        <ModalContent>
          <ModalHeader>{t('chatrooms.create')}</ModalHeader>
          <ModalBody>
            <Input
              label={t('categories.name_label')}
              placeholder={t('chatrooms.general')}
              value={newChannelName}
              onValueChange={setNewChannelName}
              variant="bordered"
              isRequired
              startContent={<Hash className="w-4 h-4 text-[var(--color-text-tertiary)]" />}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onCreateClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="primary"
              isLoading={isCreatingChannel}
              isDisabled={!newChannelName.trim()}
              onPress={handleCreateChannel}
            >
              {t('chatrooms.create')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>

      {/* Delete Channel Modal */}
      <Modal isOpen={isDeleteOpen} onClose={onDeleteClose}>
        <ModalContent>
          <ModalHeader>{t('chatrooms.delete_channel')}</ModalHeader>
          <ModalBody>
            <p className="text-[var(--color-text-secondary)]">
              {t('chatrooms.delete_channel_confirm')}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onDeleteClose}>
              {t('form.cancel')}
            </Button>
            <Button
              color="danger"
              isLoading={isDeletingChannel}
              onPress={handleDeleteChannel}
            >
              {t('chatrooms.delete_channel')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default TeamChatrooms;
