/**
 * Messages Page - Conversation list
 *
 * Features:
 * - Real-time unread count updates via Pusher
 * - New message notifications in conversation list
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Input, Avatar, Badge, Button, Modal, ModalContent, ModalHeader, ModalBody, Tabs, Tab } from '@heroui/react';
import { Search, MessageSquare, Circle, Plus, Loader2, Archive, RotateCcw, AlertTriangle, ArrowRightLeft, RefreshCw } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, usePusherOptional, useToast, useTenant } from '@/contexts';
import type { NewMessageEvent } from '@/contexts';
import { api } from '@/lib/api';
import { formatRelativeTime, resolveAvatarUrl } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import type { Conversation, User } from '@/types/api';

// Helper to get the other user from conversation (supports both API formats)
function getOtherUser(conv: Conversation) {
  // Backend returns other_user as the primary field
  if (conv.other_user) {
    return {
      id: conv.other_user.id,
      name: conv.other_user.name || `${conv.other_user.first_name || ''} ${conv.other_user.last_name || ''}`.trim(),
      avatar: conv.other_user.avatar_url || conv.other_user.avatar,
      is_online: conv.other_user.is_online,
    };
  }
  // Fallback to participant (deprecated)
  const p = conv.participant;
  if (!p) return { id: 0, name: 'Unknown', avatar: null, is_online: false };
  return {
    id: p.id,
    name: p.name || `${p.first_name || ''} ${p.last_name || ''}`.trim(),
    avatar: p.avatar,
    is_online: p.is_online,
  };
}

export function MessagesPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { user: currentUser } = useAuth();
  const toast = useToast();
  const pusher = usePusherOptional();
  const { hasFeature } = useTenant();
  const isDirectMessagingEnabled = hasFeature('direct_messaging');
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [archivedConversations, setArchivedConversations] = useState<Conversation[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingArchived, setIsLoadingArchived] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [activeTab, setActiveTab] = useState<'inbox' | 'archived'>('inbox');
  const archivedLoadedRef = useRef(false);

  // New message modal state
  const [isNewMessageOpen, setIsNewMessageOpen] = useState(false);
  const [userSearchQuery, setUserSearchQuery] = useState('');
  const [userSearchResults, setUserSearchResults] = useState<User[]>([]);
  const [isSearchingUsers, setIsSearchingUsers] = useState(false);
  const [userSearchError, setUserSearchError] = useState<string | null>(null);

  // Check for new conversation params
  const toUserId = searchParams.get('to');
  const listingId = searchParams.get('listing');

  /**
   * Handle incoming new message from Pusher
   * Updates conversation list with new message preview and increments unread count
   */
  const handleNewMessage = useCallback((event: NewMessageEvent) => {
    setConversations((prev) => {
      // Find the conversation with this sender
      const existingIndex = prev.findIndex((conv) => {
        const otherUser = getOtherUser(conv);
        return otherUser.id === event.sender_id;
      });

      if (existingIndex >= 0) {
        // Update existing conversation
        const updated = [...prev];
        const conv = { ...updated[existingIndex] };

        // Update last message
        conv.last_message = {
          id: event.id,
          body: event.body,
          sender_id: event.sender_id,
          created_at: event.created_at,
        };

        // Increment unread count
        conv.unread_count = (conv.unread_count || 0) + 1;

        // Move to top of list
        updated.splice(existingIndex, 1);
        updated.unshift(conv);

        return updated;
      }

      // New conversation - we'll reload to get the full conversation data
      // This triggers a refresh which will include the new conversation
      return prev;
    });
  }, []);

  // Memoize loadConversations to use in effects
  const loadConversations = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);
      const response = await api.get<Conversation[]>('/v2/messages');
      if (response.success && response.data) {
        setConversations(response.data);
      } else {
        setError('Failed to load conversations');
      }
    } catch (err) {
      logError('Failed to load conversations', err);
      setError('Failed to load conversations. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  // Subscribe to Pusher for real-time updates
  useEffect(() => {
    if (!pusher) return;

    // Subscribe to the user's notification channel for new messages
    const unsubMessage = pusher.onNewMessage(handleNewMessage);

    // Also listen for unread count updates (triggers a reload)
    const unsubUnread = pusher.onUnreadCount(() => {
      // When we receive a global unread count update, refresh the conversation list
      loadConversations();
    });

    return () => {
      unsubMessage();
      unsubUnread();
    };
  }, [pusher, handleNewMessage, loadConversations]);

  // Load conversations on mount and when query params change
  useEffect(() => {
    loadConversations();
  }, [loadConversations]);

  // Memoize startNewConversation
  const startNewConversation = useCallback((userId: number, _listing?: number) => {
    // Find existing conversation or create new
    const existing = conversations.find((c) => getOtherUser(c).id === userId);
    if (existing) {
      navigate(`/messages/${existing.id}`, { replace: true });
    } else {
      // Navigate with "new" prefix to indicate this is a user ID, not conversation ID
      navigate(`/messages/new/${userId}`, { replace: true });
    }
  }, [conversations, navigate]);

  // Handle new conversation params separately
  useEffect(() => {
    if (toUserId && conversations.length > 0) {
      startNewConversation(parseInt(toUserId), listingId ? parseInt(listingId) : undefined);
    }
  }, [toUserId, listingId, conversations.length, startNewConversation]);

  // Debounced user search
  useEffect(() => {
    if (!userSearchQuery.trim()) {
      setUserSearchResults([]);
      return;
    }

    const timer = setTimeout(() => {
      searchUsers(userSearchQuery);
    }, 300);

    return () => clearTimeout(timer);
  }, [userSearchQuery]);

  const loadArchivedConversations = useCallback(async () => {
    try {
      setIsLoadingArchived(true);
      const response = await api.get<Conversation[]>('/v2/messages?archived=true');
      if (response.success && response.data) {
        setArchivedConversations(response.data);
      }
    } catch (err) {
      logError('Failed to load archived conversations', err);
    } finally {
      setIsLoadingArchived(false);
      archivedLoadedRef.current = true;
    }
  }, []);

  async function restoreConversation(conversationId: number) {
    try {
      const response = await api.post(`/v2/messages/conversations/${conversationId}/restore`);
      if (response.success) {
        // Remove from archived list
        setArchivedConversations((prev) => prev.filter((c) => c.id !== conversationId));
        // Reload main inbox to show restored conversation
        loadConversations();
        toast.success('Conversation restored', 'The conversation has been moved back to your inbox.');
      }
    } catch (error) {
      logError('Failed to restore conversation', error);
      toast.error('Error', 'Failed to restore conversation. Please try again.');
    }
  }

  // Load archived conversations when tab changes (only once)
  useEffect(() => {
    if (activeTab === 'archived' && !archivedLoadedRef.current) {
      loadArchivedConversations();
    }
  }, [activeTab, loadArchivedConversations]);

  async function searchUsers(query: string) {
    try {
      setIsSearchingUsers(true);
      setUserSearchError(null);
      const response = await api.get<User[]>(`/v2/users?q=${encodeURIComponent(query)}&limit=10`);
      if (response.success && response.data) {
        // Filter out current user from results
        const filtered = response.data.filter(u => u.id !== currentUser?.id);
        setUserSearchResults(filtered);
      } else {
        setUserSearchError('Failed to search members');
      }
    } catch (error) {
      logError('Failed to search users', error);
      setUserSearchError('Failed to search members. Please try again.');
    } finally {
      setIsSearchingUsers(false);
    }
  }

  function handleSelectUser(user: User) {
    // Check if we already have a conversation with this user
    const existing = conversations.find((c) => getOtherUser(c).id === user.id);
    if (existing) {
      // Navigate to existing conversation using conversation ID
      navigate(`/messages/${existing.id}`);
    } else {
      // Navigate to new conversation - use "new" prefix to indicate user ID
      navigate(`/messages/new/${user.id}`);
    }
    setIsNewMessageOpen(false);
    setUserSearchQuery('');
    setUserSearchResults([]);
  }


  const filteredConversations = conversations.filter((conv) => {
    const otherUser = getOtherUser(conv);
    return otherUser.name.toLowerCase().includes(searchQuery.toLowerCase());
  });

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, x: -20 },
    visible: { opacity: 1, x: 0 },
  };

  return (
    <div className="max-w-3xl mx-auto space-y-6">
      {/* Messaging Disabled Notice */}
      {!isDirectMessagingEnabled && (
        <GlassCard className="p-4 border-l-4 border-amber-500 bg-amber-500/10">
          <div className="flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
            <div className="flex-1">
              <h3 className="font-semibold text-theme-primary">Direct Messaging Disabled</h3>
              <p className="text-sm text-theme-muted mt-1">
                Direct messaging is not enabled for this community. To arrange services, please use the exchange request system.
              </p>
              <Button
                size="sm"
                className="mt-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<ArrowRightLeft className="w-4 h-4" />}
                onPress={() => navigate('/exchanges')}
              >
                Go to Exchanges
              </Button>
            </div>
          </div>
        </GlassCard>
      )}

      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <MessageSquare className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            Messages
          </h1>
          <p className="text-theme-muted mt-1">Your conversations with community members</p>
        </div>
        <Button
          className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
          startContent={<Plus className="w-4 h-4" />}
          onPress={() => setIsNewMessageOpen(true)}
          isDisabled={!isDirectMessagingEnabled}
        >
          New Message
        </Button>
      </div>

      {/* Tabs and Search */}
      <GlassCard className="p-4 space-y-4">
        <Tabs
          selectedKey={activeTab}
          onSelectionChange={(key) => setActiveTab(key as 'inbox' | 'archived')}
          classNames={{
            tabList: 'gap-4 bg-theme-elevated p-1 rounded-lg',
            cursor: 'bg-theme-hover',
            tab: 'text-theme-muted data-[selected=true]:text-theme-primary',
            tabContent: 'group-data-[selected=true]:text-theme-primary',
          }}
        >
          <Tab
            key="inbox"
            title={
              <div className="flex items-center gap-2">
                <MessageSquare className="w-4 h-4" />
                <span>Inbox</span>
              </div>
            }
          />
          <Tab
            key="archived"
            title={
              <div className="flex items-center gap-2">
                <Archive className="w-4 h-4" />
                <span>Archived</span>
              </div>
            }
          />
        </Tabs>
        <Input
          placeholder="Search conversations..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-subtle" />}
          classNames={{
            input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
            inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
          }}
        />
      </GlassCard>

      {/* New Message Modal */}
      <Modal
        isOpen={isNewMessageOpen}
        onClose={() => {
          setIsNewMessageOpen(false);
          setUserSearchQuery('');
          setUserSearchResults([]);
        }}
        classNames={{
          base: 'bg-theme-card border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-4',
        }}
      >
        <ModalContent>
          <ModalHeader className="text-theme-primary">New Message</ModalHeader>
          <ModalBody>
            <Input
              placeholder="Search for a member..."
              value={userSearchQuery}
              onChange={(e) => setUserSearchQuery(e.target.value)}
              startContent={<Search className="w-4 h-4 text-theme-subtle" />}
              endContent={isSearchingUsers && <Loader2 className="w-4 h-4 text-theme-subtle animate-spin" />}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
              autoFocus
            />

            {/* Search Results */}
            <div className="mt-4 space-y-2 max-h-64 overflow-y-auto">
              {userSearchError ? (
                <div className="text-center py-4">
                  <p className="text-red-500 dark:text-red-400 text-sm">{userSearchError}</p>
                  <Button
                    size="sm"
                    variant="flat"
                    className="mt-2 bg-theme-elevated text-theme-muted"
                    onPress={() => searchUsers(userSearchQuery)}
                  >
                    Try Again
                  </Button>
                </div>
              ) : userSearchResults.length > 0 ? (
                userSearchResults.map((user) => (
                  <button
                    key={user.id}
                    onClick={() => handleSelectUser(user)}
                    className="w-full flex items-center gap-3 p-3 rounded-lg bg-theme-elevated hover:bg-theme-hover transition-colors text-left"
                  >
                    <Avatar
                      src={resolveAvatarUrl(user.avatar_url || user.avatar)}
                      name={user.name}
                      size="sm"
                      className="ring-2 ring-theme-default"
                    />
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-theme-primary truncate">{user.name}</p>
                      {user.tagline && (
                        <p className="text-sm text-theme-subtle truncate">{user.tagline}</p>
                      )}
                    </div>
                  </button>
                ))
              ) : userSearchQuery.trim() && !isSearchingUsers ? (
                <p className="text-center text-theme-subtle py-4">No members found</p>
              ) : !userSearchQuery.trim() ? (
                <p className="text-center text-theme-subtle py-4">
                  Start typing to search for members
                </p>
              ) : null}
            </div>
          </ModalBody>
        </ModalContent>
      </Modal>

      {/* Conversations List */}
      {activeTab === 'inbox' ? (
        // Inbox view
        error ? (
          <GlassCard className="p-8 text-center">
            <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" />
            <h3 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Messages</h3>
            <p className="text-theme-muted mb-4">{error}</p>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={() => loadConversations()}
            >
              Try Again
            </Button>
          </GlassCard>
        ) : isLoading ? (
          <div className="space-y-3">
            {[1, 2, 3, 4, 5].map((i) => (
              <GlassCard key={i} className="p-4 animate-pulse">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-full bg-theme-hover" />
                  <div className="flex-1">
                    <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                    <div className="h-3 bg-theme-hover rounded w-2/3" />
                  </div>
                </div>
              </GlassCard>
            ))}
          </div>
        ) : filteredConversations.length === 0 ? (
          <EmptyState
            icon={<MessageSquare className="w-12 h-12" />}
            title="No messages yet"
            description="Start a conversation with a community member"
            action={
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                startContent={<Plus className="w-4 h-4" />}
                onPress={() => setIsNewMessageOpen(true)}
              >
                New Message
              </Button>
            }
          />
        ) : (
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-3"
          >
            {filteredConversations.map((conversation) => (
              <motion.div key={conversation.id} variants={itemVariants}>
                <ConversationCard conversation={conversation} />
              </motion.div>
            ))}
          </motion.div>
        )
      ) : (
        // Archived view
        isLoadingArchived ? (
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <GlassCard key={i} className="p-4 animate-pulse">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-full bg-theme-hover" />
                  <div className="flex-1">
                    <div className="h-4 bg-theme-hover rounded w-1/3 mb-2" />
                    <div className="h-3 bg-theme-hover rounded w-2/3" />
                  </div>
                </div>
              </GlassCard>
            ))}
          </div>
        ) : archivedConversations.filter((conv) =>
            getOtherUser(conv).name.toLowerCase().includes(searchQuery.toLowerCase())
          ).length === 0 ? (
          <EmptyState
            icon={<Archive className="w-12 h-12" />}
            title="No archived conversations"
            description="Conversations you archive will appear here"
          />
        ) : (
          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-3"
          >
            {archivedConversations
              .filter((conv) =>
                getOtherUser(conv).name.toLowerCase().includes(searchQuery.toLowerCase())
              )
              .map((conversation) => (
                <motion.div key={conversation.id} variants={itemVariants}>
                  <ArchivedConversationCard
                    conversation={conversation}
                    onRestore={() => restoreConversation(conversation.id)}
                  />
                </motion.div>
              ))}
          </motion.div>
        )
      )}
    </div>
  );
}

interface ConversationCardProps {
  conversation: Conversation;
}

function ConversationCard({ conversation }: ConversationCardProps) {
  const other_user = getOtherUser(conversation);
  const { last_message, unread_count } = conversation;

  return (
    <Link
      to={`/messages/${conversation.id}`}
      aria-label={`Conversation with ${other_user.name}${unread_count > 0 ? `, ${unread_count} unread message${unread_count > 1 ? 's' : ''}` : ''}`}
    >
      <GlassCard className="p-4 hover:bg-theme-hover transition-colors">
        <div className="flex items-center gap-4">
          <Badge
            content={unread_count > 9 ? '9+' : unread_count}
            color="primary"
            size="sm"
            isInvisible={unread_count === 0}
            placement="top-right"
          >
            <Avatar
              src={resolveAvatarUrl(other_user.avatar)}
              name={other_user.name}
              size="lg"
              className="ring-2 ring-theme-default"
            />
          </Badge>

          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between gap-2">
              <h3 className={`font-semibold truncate ${unread_count > 0 ? 'text-theme-primary' : 'text-theme-muted'}`}>
                {other_user.name}
              </h3>
              {last_message && (
                <span className="text-xs text-theme-subtle whitespace-nowrap">
                  {formatRelativeTime(last_message.created_at || last_message.sent_at || '')}
                </span>
              )}
            </div>

            {last_message && (
              <p className={`text-sm truncate ${unread_count > 0 ? 'text-theme-muted' : 'text-theme-subtle'}`}>
                {last_message.body || last_message.content}
              </p>
            )}
          </div>

          {unread_count > 0 && (
            <Circle className="w-3 h-3 fill-indigo-500 text-indigo-500 flex-shrink-0" aria-hidden="true" />
          )}
        </div>
      </GlassCard>
    </Link>
  );
}

interface ArchivedConversationCardProps {
  conversation: Conversation;
  onRestore: () => void;
}

function ArchivedConversationCard({ conversation, onRestore }: ArchivedConversationCardProps) {
  const other_user = getOtherUser(conversation);
  const { last_message } = conversation;

  return (
    <GlassCard className="p-4">
      <div className="flex items-center gap-4">
        <Avatar
          src={resolveAvatarUrl(other_user.avatar)}
          name={other_user.name}
          size="lg"
          className="ring-2 ring-theme-default opacity-60"
        />

        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2">
            <h3 className="font-semibold truncate text-theme-muted">
              {other_user.name}
            </h3>
            {last_message && (
              <span className="text-xs text-theme-subtle whitespace-nowrap">
                {formatRelativeTime(last_message.created_at || last_message.sent_at || '')}
              </span>
            )}
          </div>

          {last_message && (
            <p className="text-sm truncate text-theme-subtle">
              {last_message.body || last_message.content}
            </p>
          )}
        </div>

        <Button
          size="sm"
          variant="flat"
          className="bg-theme-hover text-theme-muted hover:bg-theme-elevated"
          startContent={<RotateCcw className="w-3 h-3" />}
          onPress={onRestore}
        >
          Restore
        </Button>
      </div>
    </GlassCard>
  );
}

export default MessagesPage;
