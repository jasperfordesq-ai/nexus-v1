// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Team Chatrooms Component (I4)
 *
 * Channel-based messaging within a group/team workspace.
 * - List of channels (sidebar)
 * - Create new channel
 * - Message list with post/delete
 * - Real-time-ready structure
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  Button,
  Input,
  Spinner,
  Avatar,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Hash,
  Plus,
  Send,
  Trash2,
  MessageSquare,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/feedback';
import { useAuth, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, formatRelativeTime } from '@/lib/helpers';

/* ───────────────────────── Types ───────────────────────── */

interface Chatroom {
  id: number;
  group_id: number;
  name: string;
  description: string | null;
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

interface TeamChatroomsProps {
  groupId: number;
  isGroupAdmin: boolean;
}

/* ───────────────────────── Main Component ───────────────────────── */

export function TeamChatrooms({ groupId, isGroupAdmin }: TeamChatroomsProps) {
  const { t } = useTranslation('ideation');
  const { isAuthenticated, user } = useAuth();
  const toast = useToast();

  const [chatrooms, setChatrooms] = useState<Chatroom[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [activeChatroomId, setActiveChatroomId] = useState<number | null>(null);

  // Messages
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [isLoadingMessages, setIsLoadingMessages] = useState(false);
  const [newMessage, setNewMessage] = useState('');
  const [isSending, setIsSending] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Create channel modal
  const { isOpen: isCreateOpen, onOpen: onCreateOpen, onClose: onCreateClose } = useDisclosure();
  const [newChannelName, setNewChannelName] = useState('');
  const [isCreatingChannel, setIsCreatingChannel] = useState(false);

  // Delete channel
  const { isOpen: isDeleteOpen, onOpen: onDeleteOpen, onClose: onDeleteClose } = useDisclosure();
  const [isDeletingChannel, setIsDeletingChannel] = useState(false);

  const isAdmin = isGroupAdmin || (user?.role && ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'].includes(user.role));

  const fetchChatrooms = useCallback(async () => {
    try {
      setIsLoading(true);
      const response = await api.get<Chatroom[]>(`/v2/groups/${groupId}/chatrooms`);
      if (response.success && response.data) {
        const rooms = Array.isArray(response.data) ? response.data : [];
        setChatrooms(rooms);
        if (rooms.length > 0 && !activeChatroomId) {
          setActiveChatroomId(rooms[0].id);
        }
      }
    } catch (err) {
      logError('Failed to fetch chatrooms', err);
    } finally {
      setIsLoading(false);
    }
  }, [groupId, activeChatroomId]);

  const fetchMessages = useCallback(async (chatroomId: number) => {
    try {
      setIsLoadingMessages(true);
      const response = await api.get<ChatMessage[]>(`/v2/chatrooms/${chatroomId}/messages`);
      if (response.success && response.data) {
        setMessages(Array.isArray(response.data) ? response.data : []);
      }
    } catch (err) {
      logError('Failed to fetch messages', err);
    } finally {
      setIsLoadingMessages(false);
    }
  }, []);

  useEffect(() => {
    fetchChatrooms();
  }, [fetchChatrooms]);

  useEffect(() => {
    if (activeChatroomId) {
      fetchMessages(activeChatroomId);
    }
  }, [activeChatroomId, fetchMessages]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSendMessage = async () => {
    if (!newMessage.trim() || !activeChatroomId) return;

    setIsSending(true);
    try {
      await api.post(`/v2/chatrooms/${activeChatroomId}/messages`, {
        body: newMessage.trim(),
      });
      toast.success(t('toast.message_posted'));
      setNewMessage('');
      fetchMessages(activeChatroomId);
    } catch (err) {
      logError('Failed to send message', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsSending(false);
    }
  };

  const handleDeleteMessage = async (messageId: number) => {
    try {
      await api.delete(`/v2/chatroom-messages/${messageId}`);
      setMessages(prev => prev.filter(m => m.id !== messageId));
    } catch (err) {
      logError('Failed to delete message', err);
      toast.error(t('toast.error_generic'));
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
        toast.success(t('toast.chatroom_created'));
        setNewChannelName('');
        onCreateClose();
        fetchChatrooms();
        setActiveChatroomId(response.data.id);
      }
    } catch (err) {
      logError('Failed to create channel', err);
      toast.error(t('toast.error_generic'));
    } finally {
      setIsCreatingChannel(false);
    }
  };

  const handleDeleteChannel = async () => {
    if (!activeChatroomId) return;

    setIsDeletingChannel(true);
    try {
      await api.delete(`/v2/chatrooms/${activeChatroomId}`);
      toast.success(t('toast.chatroom_deleted'));
      onDeleteClose();
      setActiveChatroomId(null);
      setMessages([]);
      fetchChatrooms();
    } catch (err) {
      logError('Failed to delete channel', err);
      toast.error(t('toast.error_generic'));
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
              <Hash className="w-3.5 h-3.5 shrink-0" />
              <span className="truncate">{room.name}</span>
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
                <Hash className="w-4 h-4 text-[var(--color-text-tertiary)]" />
                <span className="font-semibold text-[var(--color-text)]">
                  {activeChatroom?.name}
                </span>
              </div>
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
