// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import {
  getChatStarters,
  sendChatMessage,
  type ChatMessage,
  type ChatSource,
} from '@/lib/api/chat';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type DisplayMessage = ChatMessage | { id: string; role: 'thinking'; content: string; created_at: string };

const THINKING_ID = '__thinking__';
const FALLBACK_STARTER_KEYS = ['starter_q1', 'starter_q2', 'starter_q3', 'starter_q4', 'starter_q5'] as const;

function nowIso() {
  return new Date().toISOString();
}

function messageTime(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function avatarForUser(user: unknown) {
  if (!user || typeof user !== 'object') return null;
  const candidate = user as { avatar_url?: string | null; avatar?: string | null };
  return candidate.avatar_url ?? candidate.avatar ?? null;
}

function TypingIndicator({ t }: { t: (key: string) => string }) {
  return (
    <View className="flex-row items-center gap-2">
      <Spinner size="sm" />
      <Text className="text-sm text-muted-foreground">{t('typing_aria')}</Text>
    </View>
  );
}

function SourceChips({
  sources,
  theme,
}: {
  sources: ChatSource[];
  theme: ReturnType<typeof useTheme>;
}) {
  if (sources.length === 0) return null;

  return (
    <View className="mt-2 flex-row flex-wrap gap-1.5">
      {sources.slice(0, 4).map((source) => (
        <Chip key={`${source.type}-${source.id}`} size="sm" variant="secondary">
          <Ionicons name="book-outline" size={12} color={theme.textSecondary} />
          <Chip.Label>{source.title}</Chip.Label>
        </Chip>
      ))}
    </View>
  );
}

function MessageBubble({
  message,
  primary,
  theme,
  userName,
  userAvatar,
  t,
}: {
  message: DisplayMessage;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  userName: string;
  userAvatar?: string | null;
  t: (key: string) => string;
}) {
  const isUser = message.role === 'user';
  const isThinking = message.role === 'thinking';
  const isError = 'is_error' in message && message.is_error === true;
  const sources = 'sources' in message && message.sources ? message.sources : [];

  return (
    <View className={`mb-4 flex-row items-end gap-2 ${isUser ? 'flex-row-reverse' : 'flex-row'}`}>
      {isUser ? (
        <Avatar uri={userAvatar ?? null} name={userName || t('you')} size={34} />
      ) : (
        <View className="size-9 items-center justify-center rounded-2xl" style={{ backgroundColor: primary }}>
          <Ionicons name="sparkles-outline" size={17} color="#ffffff" />
        </View>
      )}

      <View className={`max-w-[78%] gap-1 ${isUser ? 'items-end' : 'items-start'}`}>
        <View
          className={`rounded-2xl px-4 py-3 ${isUser ? 'rounded-br' : 'rounded-bl border border-border'}`}
          style={{
            backgroundColor: isUser
              ? primary
              : isError
                ? withAlpha(theme.error, 0.14)
                : theme.surface,
            borderColor: isError ? withAlpha(theme.error, 0.4) : theme.border,
          }}
        >
          {isThinking ? (
            <TypingIndicator t={t} />
          ) : (
            <>
              {isError ? (
                <View className="mb-1 flex-row items-center gap-1.5">
                  <Ionicons name="alert-circle-outline" size={14} color={theme.error} />
                  <Text className="text-xs font-semibold" style={{ color: theme.error }}>
                    {t('error_label')}
                  </Text>
                </View>
              ) : null}
              <Text className="text-sm leading-5" style={{ color: isUser ? '#ffffff' : isError ? theme.error : theme.text }}>
                {message.content}
              </Text>
            </>
          )}
        </View>

        {!isThinking ? (
          <Text className="px-1 text-[11px]" style={{ color: theme.textMuted }}>
            {messageTime(message.created_at)}
          </Text>
        ) : null}
        {!isUser && !isThinking ? <SourceChips sources={sources} theme={theme} /> : null}
      </View>
    </View>
  );
}

function EmptyChat({
  starters,
  primary,
  theme,
  t,
  onStarterPress,
}: {
  starters: string[];
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string) => string;
  onStarterPress: (prompt: string) => void;
}) {
  return (
    <View className="flex-1 justify-center gap-6 px-4 py-8">
      <View className="items-center gap-3">
        <View className="size-20 items-center justify-center rounded-3xl" style={{ backgroundColor: primary }}>
          <Ionicons name="sparkles-outline" size={34} color="#ffffff" />
        </View>
        <View className="items-center gap-1">
          <Text className="text-2xl font-bold" style={{ color: theme.text }}>
            {t('empty_title')}
          </Text>
          <Text className="max-w-[320px] text-center text-sm leading-5" style={{ color: theme.textSecondary }}>
            {t('empty_description')}
          </Text>
        </View>
      </View>

      <View className="gap-2">
        <Text className="text-center text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
          {t('try_asking')}
        </Text>
        {starters.map((question) => (
          <HeroButton
            key={question}
            variant="secondary"
            className="justify-start rounded-panel-inner"
            onPress={() => onStarterPress(question)}
          >
            <Ionicons name="chatbubble-ellipses-outline" size={16} color={primary} />
            <HeroButton.Label>{question}</HeroButton.Label>
          </HeroButton>
        ))}
      </View>
    </View>
  );
}

function ChatHeader({
  hasMessages,
  limits,
  primary,
  theme,
  t,
  onNewConversation,
}: {
  hasMessages: boolean;
  limits: { daily_remaining: number; monthly_remaining: number } | null;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onNewConversation: () => void;
}) {
  return (
    <Surface variant="default" className="mx-4 mb-3 gap-3 rounded-panel p-4">
      <View className="flex-row items-center gap-3">
        <View className="size-11 items-center justify-center rounded-2xl" style={{ backgroundColor: primary }}>
          <Ionicons name="sparkles-outline" size={21} color="#ffffff" />
        </View>
        <View className="min-w-0 flex-1">
          <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>
            {t('header_title')}
          </Text>
          <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
            {t('header_subtitle')}
          </Text>
        </View>
        {hasMessages ? (
          <HeroButton isIconOnly variant="secondary" accessibilityLabel={t('new_conversation_aria')} onPress={onNewConversation}>
            <Ionicons name="refresh-outline" size={18} color={primary} />
          </HeroButton>
        ) : null}
      </View>
      {limits ? (
        <Chip size="sm" variant="secondary">
          <Ionicons name="flash-outline" size={13} color={primary} />
          <Chip.Label>{t('limits_left_today', { count: limits.daily_remaining })}</Chip.Label>
        </Chip>
      ) : null}
    </Surface>
  );
}

export default function ChatScreen() {
  return (
    <ModalErrorBoundary>
      <ChatScreenInner />
    </ModalErrorBoundary>
  );
}

function ChatScreenInner() {
  const { t } = useTranslation(['chat', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { user, displayName } = useAuth();

  const [messages, setMessages] = useState<DisplayMessage[]>([]);
  const [inputText, setInputText] = useState('');
  const [sending, setSending] = useState(false);
  const [starters, setStarters] = useState<string[]>([]);
  const [limits, setLimits] = useState<{ daily_remaining: number; monthly_remaining: number } | null>(null);
  const conversationIdRef = useRef<string | null>(null);
  const listRef = useRef<FlatList<DisplayMessage>>(null);
  const thinkingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fallbackStarters = useMemo(() => FALLBACK_STARTER_KEYS.map((key) => t(key)), [t]);
  const starterPrompts = starters.length > 0 ? starters : fallbackStarters;
  const userAvatar = avatarForUser(user);

  useEffect(() => {
    let cancelled = false;
    void getChatStarters()
      .then((response) => {
        if (!cancelled && Array.isArray(response.starters) && response.starters.length > 0) {
          setStarters(response.starters);
        }
      })
      .catch(() => {
        // Non-critical: the localized fallback prompts are shown instead.
      });
    return () => {
      cancelled = true;
      if (thinkingTimeoutRef.current) clearTimeout(thinkingTimeoutRef.current);
    };
  }, []);

  const scrollToBottom = useCallback(() => {
    if (listRef.current && messages.length > 0) {
      listRef.current.scrollToOffset({ offset: 0, animated: true });
    }
  }, [messages.length]);

  const startNewConversation = useCallback(() => {
    if (thinkingTimeoutRef.current) clearTimeout(thinkingTimeoutRef.current);
    thinkingTimeoutRef.current = null;
    conversationIdRef.current = null;
    setMessages([]);
    setInputText('');
    setSending(false);
    setLimits(null);
  }, []);

  const handleSend = useCallback(async (rawText?: string) => {
    const text = (rawText ?? inputText).trim();
    if (!text || sending) return;

    setInputText('');
    setSending(true);

    const userMsg: ChatMessage = {
      id: `local-${Date.now()}`,
      role: 'user',
      content: text,
      created_at: nowIso(),
    };

    const thinkingMsg: DisplayMessage = {
      id: THINKING_ID,
      role: 'thinking',
      content: '',
      created_at: nowIso(),
    };

    setMessages((prev) => [thinkingMsg, userMsg, ...prev]);

    thinkingTimeoutRef.current = setTimeout(() => {
      setMessages((prev) => {
        if (!prev.some((m) => m.id === THINKING_ID)) return prev;
        const withoutThinking = prev.filter((m) => m.id !== THINKING_ID);
        return [{
          id: `timeout-${Date.now()}`,
          role: 'assistant',
          content: t('timeout'),
          created_at: nowIso(),
          is_error: true,
        }, ...withoutThinking];
      });
      setSending(false);
    }, 30_000);

    try {
      const result = await sendChatMessage(text, conversationIdRef.current);
      if (thinkingTimeoutRef.current) {
        clearTimeout(thinkingTimeoutRef.current);
        thinkingTimeoutRef.current = null;
      }

      conversationIdRef.current = result.data.conversation_id || conversationIdRef.current;
      setLimits(result.data.limits ?? null);
      const assistantMsg = result.data.message;

      setMessages((prev) => [assistantMsg, ...prev.filter((m) => m.id !== THINKING_ID)]);
    } catch {
      if (thinkingTimeoutRef.current) {
        clearTimeout(thinkingTimeoutRef.current);
        thinkingTimeoutRef.current = null;
      }
      setMessages((prev) => [{
        id: `error-${Date.now()}`,
        role: 'assistant',
        content: t('error_connection'),
        created_at: nowIso(),
        is_error: true,
      }, ...prev.filter((m) => m.id !== THINKING_ID)]);
    } finally {
      setSending(false);
    }
  }, [inputText, sending, t]);

  const renderItem = useCallback(
    ({ item }: { item: DisplayMessage }) => (
      <MessageBubble
        message={item}
        primary={primary}
        theme={theme}
        userName={displayName}
        userAvatar={userAvatar}
        t={t}
      />
    ),
    [displayName, primary, t, theme, userAvatar],
  );

  const hasMessages = messages.length > 0;

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('page_title')} backLabel={t('common:back')} fallbackHref="/(tabs)/home" />
      <ChatHeader
        hasMessages={hasMessages}
        limits={limits}
        primary={primary}
        theme={theme}
        t={t}
        onNewConversation={startNewConversation}
      />

      <KeyboardAvoidingView
        className="flex-1"
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 88 : 0}
      >
        {!hasMessages ? (
          <EmptyChat
            starters={starterPrompts}
            primary={primary}
            theme={theme}
            t={t}
            onStarterPress={(prompt) => void handleSend(prompt)}
          />
        ) : (
          <FlatList<DisplayMessage>
            ref={listRef}
            data={messages}
            keyExtractor={(item) => item.id}
            renderItem={renderItem}
            inverted
            contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 12 }}
            onContentSizeChange={scrollToBottom}
            accessibilityLabel={t('messages_region')}
          />
        )}

        <View className="border-t border-border bg-background px-4 pt-3">
          <HeroCard className="rounded-panel p-0">
            <HeroCard.Body className="p-2">
              <View className="flex-row items-end gap-2">
                <TextInput
                  className="min-h-[42px] flex-1 px-3 py-2 text-sm"
                  style={{ maxHeight: 126, color: theme.text }}
                  placeholder={t('input_placeholder')}
                  placeholderTextColor={theme.textMuted}
                  value={inputText}
                  onChangeText={setInputText}
                  multiline
                  maxLength={2000}
                  editable={!sending}
                  accessibilityLabel={t('input_aria')}
                />
                <HeroButton
                  isIconOnly
                  variant="primary"
                  accessibilityLabel={t('send_aria')}
                  isDisabled={!inputText.trim() || sending}
                  onPress={() => void handleSend()}
                >
                  {sending ? <Spinner size="sm" /> : <Ionicons name="send" size={18} color="#ffffff" />}
                </HeroButton>
              </View>
            </HeroCard.Body>
          </HeroCard>
          <Text
            className="px-2 pt-2 text-center text-[11px]"
            style={{ color: theme.textMuted, paddingBottom: Math.max(8, insets.bottom) }}
          >
            {t('disclaimer')}
          </Text>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
