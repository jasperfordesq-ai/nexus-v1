/**
 * Conversation Page - Individual message thread
 */

import { useState, useEffect, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { motion, AnimatePresence } from 'framer-motion';
import { Button, Input, Avatar } from '@heroui/react';
import { ArrowLeft, Send, Phone, Video, Info } from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { LoadingScreen } from '@/components/feedback';
import { useAuth } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { Message, User } from '@/types/api';

interface ConversationMeta {
  id: number;
  other_user: User;
}

interface ConversationData {
  meta: ConversationMeta;
  messages: Message[];
}

export function ConversationPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const [conversation, setConversation] = useState<ConversationData | null>(null);
  const [newMessage, setNewMessage] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isSending, setIsSending] = useState(false);

  useEffect(() => {
    loadConversation();

    // Poll for new messages
    const interval = setInterval(loadMessages, 10000);
    return () => clearInterval(interval);
  }, [id]);

  useEffect(() => {
    scrollToBottom();
  }, [conversation?.messages]);

  async function loadConversation() {
    if (!id) return;

    try {
      setIsLoading(true);
      // API returns messages as data with conversation info in meta
      const response = await api.get<Message[]>(`/v2/messages/${id}`);
      if (response.success && response.data && response.meta?.conversation) {
        setConversation({
          meta: response.meta.conversation as ConversationMeta,
          messages: response.data,
        });
      } else {
        navigate('/messages');
      }
    } catch (error) {
      logError('Failed to load conversation', error);
      navigate('/messages');
    } finally {
      setIsLoading(false);
    }
  }

  async function loadMessages() {
    if (!id || !conversation) return;

    try {
      // Fetch newer messages for polling
      const response = await api.get<Message[]>(`/v2/messages/${id}?direction=newer`);
      if (response.success && response.data) {
        setConversation((prev) => prev ? { ...prev, messages: response.data! } : null);
      }
    } catch (error) {
      logError('Failed to load messages', error);
    }
  }

  async function sendMessage(e: React.FormEvent) {
    e.preventDefault();
    if (!newMessage.trim() || !id || isSending) return;

    try {
      setIsSending(true);
      // POST to /v2/messages with recipient_id in body
      const response = await api.post<Message>('/v2/messages', {
        recipient_id: parseInt(id, 10),
        body: newMessage.trim(),
      });

      if (response.success && response.data) {
        setConversation((prev) => {
          if (!prev) return null;
          return {
            ...prev,
            messages: [...prev.messages, response.data!],
          };
        });
        setNewMessage('');
      }
    } catch (error) {
      logError('Failed to send message', error);
    } finally {
      setIsSending(false);
    }
  }

  function scrollToBottom() {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
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
              className="text-white/60 hover:text-white transition-colors"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>

            <Link to={`/profile/${other_user.id}`} className="flex items-center gap-3">
              <Avatar
                src={other_user.avatar || undefined}
                name={other_user.name}
                size="md"
                className="ring-2 ring-white/20"
              />
              <div>
                <h2 className="font-semibold text-white">{other_user.name}</h2>
                {other_user.tagline && (
                  <p className="text-xs text-white/50">{other_user.tagline}</p>
                )}
              </div>
            </Link>
          </div>

          <div className="flex items-center gap-2">
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              className="bg-white/5 text-white/60"
            >
              <Phone className="w-4 h-4" />
            </Button>
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              className="bg-white/5 text-white/60"
            >
              <Video className="w-4 h-4" />
            </Button>
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              className="bg-white/5 text-white/60"
            >
              <Info className="w-4 h-4" />
            </Button>
          </div>
        </div>
      </GlassCard>

      {/* Messages */}
      <GlassCard className="flex-1 overflow-hidden flex flex-col">
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          <AnimatePresence mode="popLayout">
            {messages.map((message, index) => (
              <MessageBubble
                key={message.id}
                message={message}
                isOwn={message.sender_id === user?.id}
                showAvatar={
                  index === 0 ||
                  messages[index - 1]?.sender_id !== message.sender_id
                }
                otherUser={other_user}
              />
            ))}
          </AnimatePresence>
          <div ref={messagesEndRef} />
        </div>

        {/* Input */}
        <div className="p-4 border-t border-white/10">
          <form onSubmit={sendMessage} className="flex gap-3">
            <Input
              placeholder="Type a message..."
              value={newMessage}
              onChange={(e) => setNewMessage(e.target.value)}
              classNames={{
                input: 'bg-transparent text-white placeholder:text-white/40',
                inputWrapper: 'bg-white/5 border-white/10 hover:bg-white/10',
              }}
            />
            <Button
              type="submit"
              isIconOnly
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              isDisabled={!newMessage.trim()}
              isLoading={isSending}
            >
              <Send className="w-4 h-4" />
            </Button>
          </form>
        </div>
      </GlassCard>
    </div>
  );
}

interface MessageBubbleProps {
  message: Message;
  isOwn: boolean;
  showAvatar: boolean;
  otherUser: User;
}

function MessageBubble({ message, isOwn, showAvatar, otherUser }: MessageBubbleProps) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -10 }}
      className={`flex gap-3 ${isOwn ? 'flex-row-reverse' : ''}`}
    >
      {showAvatar && !isOwn ? (
        <Avatar
          src={otherUser.avatar || undefined}
          name={otherUser.name}
          size="sm"
          className="flex-shrink-0"
        />
      ) : (
        <div className="w-8" />
      )}

      <div className={`max-w-[70%] ${isOwn ? 'text-right' : ''}`}>
        <div
          className={`
            inline-block px-4 py-2 rounded-2xl
            ${isOwn
              ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-br-md'
              : 'bg-white/10 text-white rounded-bl-md'
            }
          `}
        >
          <p className="text-sm whitespace-pre-wrap">{message.content}</p>
        </div>
        <p className="text-xs text-white/30 mt-1 px-2">
          {new Date(message.created_at || message.sent_at).toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
          })}
        </p>
      </div>
    </motion.div>
  );
}

export default ConversationPage;
