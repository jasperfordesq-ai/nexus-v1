/**
 * Messages Page - Conversation list
 */

import { useState, useEffect } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Input, Avatar } from '@heroui/react';
import { Search, MessageSquare, Circle } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { formatRelativeTime, resolveAvatarUrl } from '@/lib/helpers';
import { logError } from '@/lib/logger';
import type { Conversation } from '@/types/api';

// Helper to get the other user from conversation (supports both API formats)
function getOtherUser(conv: Conversation) {
  if (conv.other_user) return conv.other_user;
  const p = conv.participant;
  return {
    id: p.id,
    name: p.name || `${p.first_name} ${p.last_name}`.trim(),
    avatar: p.avatar,
    is_online: p.is_online,
  };
}

export function MessagesPage() {
  const [searchParams] = useSearchParams();
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');

  // Check for new conversation params
  const toUserId = searchParams.get('to');
  const listingId = searchParams.get('listing');

  useEffect(() => {
    loadConversations();

    // If we have params to start a new conversation, handle that
    if (toUserId) {
      startNewConversation(parseInt(toUserId), listingId ? parseInt(listingId) : undefined);
    }
  }, []);

  async function loadConversations() {
    try {
      setIsLoading(true);
      const response = await api.get<Conversation[]>('/v2/messages');
      if (response.success && response.data) {
        setConversations(response.data);
      }
    } catch (error) {
      logError('Failed to load conversations', error);
    } finally {
      setIsLoading(false);
    }
  }

  async function startNewConversation(userId: number, _listing?: number) {
    // Find existing conversation or create new
    const existing = conversations.find((c) => getOtherUser(c).id === userId);
    if (existing) {
      window.location.href = `/messages/${existing.id}`;
    }
    // If no existing, navigate will happen after first message
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
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-white flex items-center gap-3">
          <MessageSquare className="w-7 h-7 text-indigo-400" />
          Messages
        </h1>
        <p className="text-white/60 mt-1">Your conversations with community members</p>
      </div>

      {/* Search */}
      <GlassCard className="p-4">
        <Input
          placeholder="Search conversations..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-white/40" />}
          classNames={{
            input: 'bg-transparent text-white placeholder:text-white/40',
            inputWrapper: 'bg-white/5 border-white/10 hover:bg-white/10',
          }}
        />
      </GlassCard>

      {/* Conversations List */}
      {isLoading ? (
        <div className="space-y-3">
          {[1, 2, 3, 4, 5].map((i) => (
            <GlassCard key={i} className="p-4 animate-pulse">
              <div className="flex items-center gap-4">
                <div className="w-12 h-12 rounded-full bg-white/10" />
                <div className="flex-1">
                  <div className="h-4 bg-white/10 rounded w-1/3 mb-2" />
                  <div className="h-3 bg-white/10 rounded w-2/3" />
                </div>
              </div>
            </GlassCard>
          ))}
        </div>
      ) : filteredConversations.length === 0 ? (
        <EmptyState
          icon={<MessageSquare className="w-12 h-12" />}
          title="No messages yet"
          description="Start a conversation by contacting someone through their listing or profile"
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
    <Link to={`/messages/${conversation.id}`}>
      <GlassCard className="p-4 hover:bg-white/10 transition-colors">
        <div className="flex items-center gap-4">
          <div className="relative">
            <Avatar
              src={resolveAvatarUrl(other_user.avatar)}
              name={other_user.name}
              size="lg"
              className="ring-2 ring-white/20"
            />
            {unread_count > 0 && (
              <span className="absolute -top-1 -right-1 w-5 h-5 bg-indigo-500 rounded-full flex items-center justify-center text-xs text-white font-medium">
                {unread_count > 9 ? '9+' : unread_count}
              </span>
            )}
          </div>

          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between gap-2">
              <h3 className={`font-semibold truncate ${unread_count > 0 ? 'text-white' : 'text-white/80'}`}>
                {other_user.name}
              </h3>
              {last_message && (
                <span className="text-xs text-white/40 whitespace-nowrap">
                  {formatRelativeTime(last_message.created_at || last_message.sent_at)}
                </span>
              )}
            </div>

            {last_message && (
              <p className={`text-sm truncate ${unread_count > 0 ? 'text-white/70' : 'text-white/50'}`}>
                {last_message.content}
              </p>
            )}
          </div>

          {unread_count > 0 && (
            <Circle className="w-3 h-3 fill-indigo-500 text-indigo-500 flex-shrink-0" />
          )}
        </div>
      </GlassCard>
    </Link>
  );
}

export default MessagesPage;
