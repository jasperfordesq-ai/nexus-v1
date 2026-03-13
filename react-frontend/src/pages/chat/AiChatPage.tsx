// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AI Chat Page
 *
 * Full-height chat interface for the AI assistant feature.
 * Uses POST /api/ai/chat for non-streaming responses.
 * Manages conversation history in local state and sends it with each request.
 * Feature-gated by 'ai_chat'.
 */

import { useState, useEffect, useRef, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Button,
  Avatar,
  Textarea,
  ScrollShadow,
  Card,
  CardBody,
  Chip,
} from '@heroui/react';
import { Bot, Send, RefreshCw, Sparkles, AlertCircle, Zap } from 'lucide-react';
import { useAuth, useTenant, useToast } from '@/contexts';
import { API_BASE, tokenManager } from '@/lib/api';
import { usePageTitle } from '@/hooks';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface ChatMessage {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  timestamp: Date;
  isError?: boolean;
}

interface AiChatResponse {
  success: boolean;
  conversation_id: number;
  message: {
    id: number;
    role: 'assistant';
    content: string;
  };
  tokens_used?: number;
  model?: string;
  provider?: string;
  limits?: {
    daily_remaining: number;
    monthly_remaining: number;
  };
  error?: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Suggested starter questions
// ─────────────────────────────────────────────────────────────────────────────

const STARTER_QUESTION_KEYS = [
  'starter_q1',
  'starter_q2',
  'starter_q3',
  'starter_q4',
  'starter_q5',
] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Typing indicator
// ─────────────────────────────────────────────────────────────────────────────

function TypingIndicator() {
  const { t } = useTranslation('chat');

  return (
    <motion.div
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: 8 }}
      className="flex items-end gap-2 mb-4"
    >
      <Avatar
        icon={<Bot className="w-4 h-4" aria-hidden="true" />}
        size="sm"
        classNames={{
          base: 'bg-gradient-to-br from-indigo-500 to-purple-600 flex-shrink-0',
          icon: 'text-white',
        }}
      />
      <div className="bg-[var(--color-surface)] border border-[var(--border-default)] rounded-2xl rounded-bl-sm px-4 py-3">
        <div className="flex items-center gap-1" aria-label={t('typing_aria')}>
          {[0, 1, 2].map((i) => (
            <motion.span
              key={i}
              className="w-2 h-2 rounded-full bg-indigo-400"
              animate={{ opacity: [0.3, 1, 0.3], y: [0, -4, 0] }}
              transition={{ duration: 0.8, repeat: Infinity, delay: i * 0.15 }}
            />
          ))}
        </div>
      </div>
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Message bubble
// ─────────────────────────────────────────────────────────────────────────────

interface MessageBubbleProps {
  message: ChatMessage;
  userName?: string;
  userAvatar?: string;
}

function MessageBubble({ message, userName, userAvatar }: MessageBubbleProps) {
  const { t } = useTranslation('chat');
  const isUser = message.role === 'user';
  const time = message.timestamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.2 }}
      className={`flex items-end gap-2 mb-4 ${isUser ? 'flex-row-reverse' : 'flex-row'}`}
    >
      {/* Avatar */}
      {isUser ? (
        <Avatar
          name={userName}
          src={userAvatar}
          size="sm"
          showFallback
          classNames={{ base: 'flex-shrink-0' }}
        />
      ) : (
        <Avatar
          icon={<Bot className="w-4 h-4" aria-hidden="true" />}
          size="sm"
          classNames={{
            base: 'bg-gradient-to-br from-indigo-500 to-purple-600 flex-shrink-0',
            icon: 'text-white',
          }}
        />
      )}

      {/* Bubble */}
      <div className={`max-w-[80%] sm:max-w-[70%] ${isUser ? 'items-end' : 'items-start'} flex flex-col gap-1`}>
        <div
          className={`px-4 py-3 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap break-words ${
            isUser
              ? 'bg-gradient-to-br from-indigo-500 to-purple-600 text-white rounded-br-sm'
              : message.isError
              ? 'bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 rounded-bl-sm'
              : 'bg-[var(--color-surface)] border border-[var(--border-default)] text-[var(--color-text)] rounded-bl-sm'
          }`}
        >
          {message.isError && (
            <div className="flex items-center gap-1.5 mb-1 font-medium">
              <AlertCircle className="w-3.5 h-3.5" aria-hidden="true" />
              <span className="text-xs">{t('error_label')}</span>
            </div>
          )}
          {message.content}
        </div>
        <span className="text-xs text-[var(--color-text-muted)] px-1">{time}</span>
      </div>
    </motion.div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Feature not available state
// ─────────────────────────────────────────────────────────────────────────────

function FeatureNotAvailable() {
  const { t } = useTranslation('chat');

  return (
    <div className="flex flex-col items-center justify-center h-full px-6 py-16 text-center">
      <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/30 dark:to-purple-900/30 flex items-center justify-center mb-4">
        <Bot className="w-8 h-8 text-indigo-500" aria-hidden="true" />
      </div>
      <h2 className="text-xl font-semibold text-[var(--color-text)] mb-2">{t('feature_unavailable_title')}</h2>
      <p className="text-[var(--color-text-muted)] max-w-sm">
        {t('feature_unavailable_description')}
      </p>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Empty state
// ─────────────────────────────────────────────────────────────────────────────

interface EmptyStateProps {
  onQuestionClick: (q: string) => void;
}

function EmptyState({ onQuestionClick }: EmptyStateProps) {
  const { t } = useTranslation('chat');

  return (
    <div className="flex flex-col items-center justify-center h-full px-4 py-8">
      <motion.div
        initial={{ scale: 0.8, opacity: 0 }}
        animate={{ scale: 1, opacity: 1 }}
        transition={{ duration: 0.4 }}
        className="w-20 h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center mb-5 shadow-lg"
      >
        <Sparkles className="w-10 h-10 text-white" aria-hidden="true" />
      </motion.div>

      <motion.div
        initial={{ y: 10, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        transition={{ delay: 0.1, duration: 0.3 }}
        className="text-center mb-8"
      >
        <h2 className="text-2xl font-bold text-[var(--color-text)] mb-2">{t('empty_title')}</h2>
        <p className="text-[var(--color-text-muted)] max-w-sm">
          {t('empty_description')}
        </p>
      </motion.div>

      <motion.div
        initial={{ y: 10, opacity: 0 }}
        animate={{ y: 0, opacity: 1 }}
        transition={{ delay: 0.2, duration: 0.3 }}
        className="w-full max-w-md"
      >
        <p className="text-xs font-semibold text-[var(--color-text-muted)] uppercase tracking-wider mb-3 text-center">
          {t('try_asking')}
        </p>
        <div className="flex flex-col gap-2">
          {STARTER_QUESTION_KEYS.map((key, i) => {
            const question = t(key);
            return (
              <motion.div
                key={key}
                initial={{ x: -10, opacity: 0 }}
                animate={{ x: 0, opacity: 1 }}
                transition={{ delay: 0.25 + i * 0.06, duration: 0.25 }}
              >
              <Button
                variant="flat"
                onPress={() => onQuestionClick(question)}
                className="w-full text-left px-4 py-3 rounded-xl bg-[var(--color-surface)] border border-[var(--border-default)] text-sm text-[var(--color-text)] hover:border-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/20 transition-all justify-start h-auto"
              >
                {question}
              </Button>
            </motion.div>
            );
          })}
        </div>
      </motion.div>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export default function AiChatPage() {
  const { t } = useTranslation('chat');

  usePageTitle(t('page_title'));

  const { user } = useAuth();
  const { hasFeature } = useTenant();
  const { warning, error: toastError } = useToast();

  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [conversationId, setConversationId] = useState<number | null>(null);
  const [limits, setLimits] = useState<{ daily_remaining: number; monthly_remaining: number } | null>(null);

  const scrollRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Feature check
  const isEnabled = hasFeature('ai_chat');

  // Scroll to bottom on new messages
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, isLoading]);

  const sendMessage = useCallback(async (text: string) => {
    const trimmed = text.trim();
    if (!trimmed || isLoading) return;

    const userMsg: ChatMessage = {
      id: `user-${Date.now()}`,
      role: 'user',
      content: trimmed,
      timestamp: new Date(),
    };

    setMessages(prev => [...prev, userMsg]);
    setInput('');
    setIsLoading(true);

    try {
      const token = tokenManager.getAccessToken();
      const tenantId = tokenManager.getTenantId();
      const csrfToken = tokenManager.getCsrfToken();

      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      };
      if (token) headers['Authorization'] = `Bearer ${token}`;
      if (tenantId) headers['X-Tenant-ID'] = tenantId;
      if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

      const body: Record<string, unknown> = { message: trimmed };
      if (conversationId) body['conversation_id'] = conversationId;

      const response = await fetch(`${API_BASE}/ai/chat`, {
        method: 'POST',
        headers,
        credentials: 'include',
        body: JSON.stringify(body),
      });

      const data = (await response.json()) as AiChatResponse;

      if (response.ok && data.success && data.message) {
        const assistantMsg: ChatMessage = {
          id: `assistant-${Date.now()}`,
          role: 'assistant',
          content: data.message.content,
          timestamp: new Date(),
        };
        setMessages(prev => [...prev, assistantMsg]);

        if (data.conversation_id) {
          setConversationId(data.conversation_id);
        }
        if (data.limits) {
          setLimits(data.limits);
        }
      } else {
        const errorText = data.error ?? t('error_generic');
        const isLimit = response.status === 429;

        const errorMsg: ChatMessage = {
          id: `error-${Date.now()}`,
          role: 'assistant',
          content: isLimit
            ? t('error_rate_limit')
            : errorText,
          timestamp: new Date(),
          isError: true,
        };
        setMessages(prev => [...prev, errorMsg]);

        if (isLimit) {
          warning(t('toast_rate_limit'));
        }
      }
    } catch {
      const errorMsg: ChatMessage = {
        id: `error-${Date.now()}`,
        role: 'assistant',
        content: t('error_connection'),
        timestamp: new Date(),
        isError: true,
      };
      setMessages(prev => [...prev, errorMsg]);
      toastError(t('toast_connection_error'));
    } finally {
      setIsLoading(false);
    }
  }, [isLoading, conversationId, warning, toastError, t]);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      void sendMessage(input);
    }
  };

  const handleNewConversation = () => {
    setMessages([]);
    setConversationId(null);
    setLimits(null);
    setInput('');
    textareaRef.current?.focus();
  };

  // Resolve avatar
  const userAvatarUrl = user?.avatar_url ?? user?.avatar ?? undefined;

  if (!isEnabled) {
    return (
      <div className="min-h-[calc(100vh-4rem)] flex flex-col">
        <FeatureNotAvailable />
      </div>
    );
  }

  const hasMessages = messages.length > 0;

  return (
    <div
      className="flex flex-col h-[calc(100dvh-4rem)]"
      aria-label={t('aria_chat')}
    >
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-[var(--border-default)] bg-[var(--color-surface)] flex-shrink-0">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
            <Bot className="w-5 h-5 text-white" aria-hidden="true" />
          </div>
          <div>
            <h1 className="font-semibold text-[var(--color-text)]">{t('header_title')}</h1>
            <p className="text-xs text-[var(--color-text-muted)]">{t('header_subtitle')}</p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          {limits && (
            <Chip
              size="sm"
              variant="flat"
              color="default"
              startContent={<Zap className="w-3 h-3" aria-hidden="true" />}
              className="text-xs hidden sm:flex"
            >
              {t('limits_left_today', { count: limits.daily_remaining })}
            </Chip>
          )}
          {hasMessages && (
            <Button
              size="sm"
              variant="light"
              isIconOnly
              onPress={handleNewConversation}
              aria-label={t('new_conversation_aria')}
              className="text-[var(--color-text-muted)] hover:text-[var(--color-text)]"
            >
              <RefreshCw className="w-4 h-4" aria-hidden="true" />
            </Button>
          )}
        </div>
      </div>

      {/* Messages area */}
      <div className="flex-1 overflow-hidden relative">
        <ScrollShadow
          ref={scrollRef}
          className="h-full overflow-y-auto px-4 py-4"
          hideScrollBar={false}
        >
          {!hasMessages ? (
            <EmptyState onQuestionClick={(q) => void sendMessage(q)} />
          ) : (
            <div className="max-w-2xl mx-auto">
              <AnimatePresence initial={false}>
                {messages.map((msg) => (
                  <MessageBubble
                    key={msg.id}
                    message={msg}
                    userName={user ? `${user.first_name ?? ''} ${user.last_name ?? ''}`.trim() : undefined}
                    userAvatar={userAvatarUrl}
                  />
                ))}
              </AnimatePresence>

              {isLoading && (
                <AnimatePresence>
                  <TypingIndicator />
                </AnimatePresence>
              )}

              <div ref={messagesEndRef} aria-hidden="true" />
            </div>
          )}
        </ScrollShadow>
      </div>

      {/* Input area */}
      <div className="flex-shrink-0 border-t border-[var(--border-default)] bg-[var(--color-surface)] px-4 py-3">
        <div className="max-w-2xl mx-auto">
          <Card
            className="bg-[var(--color-surface-elevated)] border border-[var(--border-default)] shadow-sm"
            radius="lg"
          >
            <CardBody className="p-0">
              <div className="flex items-end gap-2 p-2">
                <Textarea
                  ref={textareaRef}
                  aria-label={t('input_aria')}
                  placeholder={t('input_placeholder')}
                  value={input}
                  onValueChange={setInput}
                  onKeyDown={handleKeyDown}
                  minRows={1}
                  maxRows={5}
                  classNames={{
                    base: 'flex-1',
                    inputWrapper: 'bg-transparent border-0 shadow-none p-0 hover:bg-transparent focus-within:bg-transparent data-[hover=true]:bg-transparent',
                    input: 'text-sm text-[var(--color-text)] placeholder:text-[var(--color-text-muted)] resize-none px-2 py-1.5',
                  }}
                  disabled={isLoading}
                />
                <Button
                  isIconOnly
                  color="primary"
                  className="bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex-shrink-0 mb-0.5"
                  size="sm"
                  onPress={() => void sendMessage(input)}
                  isDisabled={!input.trim() || isLoading}
                  aria-label={t('send_aria')}
                >
                  <Send className="w-4 h-4" aria-hidden="true" />
                </Button>
              </div>
            </CardBody>
          </Card>
          <p className="text-center text-xs text-[var(--color-text-muted)] mt-2">
            {t('disclaimer')}
          </p>
        </div>
      </div>
    </div>
  );
}
