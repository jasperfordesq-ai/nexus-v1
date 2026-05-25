// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  Pressable,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import { sendChatMessage, type ChatMessage } from '@/lib/api/chat';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// Internal message type extends ChatMessage to support the transient "thinking" state
type DisplayMessage = ChatMessage | { id: string; role: 'thinking'; content: string; created_at: string };

const THINKING_ID = '__thinking__';

// ─── Message Bubble ───────────────────────────────────────────────────────────

function MessageBubble({
  message,
  primary,
  t,
}: {
  message: DisplayMessage;
  primary: string;
  t: (key: string) => string;
}) {
  const isUser = message.role === 'user';
  const isThinking = message.role === 'thinking';

  return (
    <View className={`mb-2.5 max-w-[80%] ${isUser ? 'self-end' : 'self-start'}`}>
      <View
        className={`rounded-2xl px-4 py-2 ${
          isUser
            ? 'rounded-br-[4px]'
            : 'bg-surface border border-border/50 rounded-bl-[4px]'
        }`}
        style={isUser ? { backgroundColor: primary } : undefined}
      >
        {isThinking ? (
          <View className="flex-row items-center gap-2">
            <Spinner size="sm" />
            <Text className="text-sm text-muted-foreground">{t('chat:thinking')}</Text>
          </View>
        ) : (
          <Text className={`text-sm ${isUser ? 'text-white' : 'text-foreground'}`}>
            {message.content}
          </Text>
        )}
      </View>
    </View>
  );
}

// ─── Screen ───────────────────────────────────────────────────────────────────

export default function ChatScreen() {
  return (
    <ModalErrorBoundary>
      <ChatScreenInner />
    </ModalErrorBoundary>
  );
}

function ChatScreenInner() {
  const { t } = useTranslation('chat');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const insets = useSafeAreaInsets();

  const [messages, setMessages] = useState<DisplayMessage[]>([]);
  const [inputText, setInputText] = useState('');
  const [sending, setSending] = useState(false);
  const conversationIdRef = useRef<string | null>(null);
  const listRef = useRef<FlatList<DisplayMessage>>(null);
  const thinkingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    navigation.setOptions({ title: t('chat:title') });
  }, [navigation, t]);

  useEffect(() => {
    return () => {
      if (thinkingTimeoutRef.current) clearTimeout(thinkingTimeoutRef.current);
    };
  }, []);

  const scrollToBottom = useCallback(() => {
    if (listRef.current && messages.length > 0) {
      listRef.current.scrollToOffset({ offset: 0, animated: true });
    }
  }, [messages.length]);

  async function handleSend() {
    const text = inputText.trim();
    if (!text || sending) return;

    setInputText('');
    setSending(true);

    // Optimistically add user message
    const userMsg: ChatMessage = {
      id: `local-${Date.now()}`,
      role: 'user',
      content: text,
      created_at: new Date().toISOString(),
    };

    // Add thinking indicator
    const thinkingMsg: DisplayMessage = {
      id: THINKING_ID,
      role: 'thinking',
      content: '',
      created_at: new Date().toISOString(),
    };

    setMessages((prev) => [thinkingMsg, userMsg, ...prev]);

    thinkingTimeoutRef.current = setTimeout(() => {
      setMessages((prev) => {
        if (!prev.some((m) => m.id === THINKING_ID)) return prev;
        const withoutThinking = prev.filter((m) => m.id !== THINKING_ID);
        const timeoutMsg: ChatMessage = {
          id: `timeout-${Date.now()}`,
          role: 'assistant',
          content: t('chat:timeout'),
          created_at: new Date().toISOString(),
        };
        return [timeoutMsg, ...withoutThinking];
      });
      setSending(false);
    }, 30_000);

    try {
      const result = await sendChatMessage(text, conversationIdRef.current);
      if (thinkingTimeoutRef.current) { clearTimeout(thinkingTimeoutRef.current); thinkingTimeoutRef.current = null; }
      // Store conversation ID from first response
      conversationIdRef.current = result.data.conversation_id;

      const assistantMsg = result.data.message;

      setMessages((prev) =>
        prev
          .filter((m) => m.id !== THINKING_ID)
          .map((m) => (m.id === userMsg.id ? userMsg : m))
          .concat() // keep order (inverted list, newest at index 0)
          // insert assistant reply at beginning (top of inverted list = bottom of chat)
          .reduce<DisplayMessage[]>((acc, m, idx) => {
            if (idx === 0) return [assistantMsg, m];
            return [...acc, m];
          }, []),
      );
    } catch {
      if (thinkingTimeoutRef.current) { clearTimeout(thinkingTimeoutRef.current); thinkingTimeoutRef.current = null; }
      setMessages((prev) => {
        const withoutThinking = prev.filter((m) => m.id !== THINKING_ID);
        const errorMsg: ChatMessage = {
          id: `error-${Date.now()}`,
          role: 'assistant',
          content: t('chat:error'),
          created_at: new Date().toISOString(),
        };
        return [errorMsg, ...withoutThinking];
      });
    } finally {
      setSending(false);
    }
  }

  const renderItem = useCallback(
    ({ item }: { item: DisplayMessage }) => (
      <MessageBubble
        message={item}
        primary={primary}
        t={t}
      />
    ),
    [primary, t],
  );

  return (
    <SafeAreaView className="flex-1 bg-background">
      <KeyboardAvoidingView
        className="flex-1"
        behavior="padding"
        keyboardVerticalOffset={Platform.OS === 'ios' ? 88 : 30}
      >
        {/* Message list — inverted so newest messages appear at bottom */}
        {messages.length === 0 ? (
          <View className="flex-1 items-center justify-center gap-3">
            <Ionicons name="chatbubble-ellipses-outline" size={40} color={theme.textMuted} />
            <Text className="text-xs text-muted-foreground text-center px-6">{t('chat:empty')}</Text>
          </View>
        ) : (
          <FlatList<DisplayMessage>
            ref={listRef}
            data={messages}
            keyExtractor={(item) => item.id}
            renderItem={renderItem}
            inverted
            contentContainerStyle={{ paddingHorizontal: 12, paddingVertical: 16 }}
            onContentSizeChange={scrollToBottom}
          />
        )}

        {/* Input area */}
        <View
          className="flex-row items-end gap-2.5 px-3 py-2.5 border-t border-border"
        >
          <TextInput
            className="flex-1 border border-border rounded-2xl px-4 py-2 text-sm bg-surface text-foreground"
            style={{ maxHeight: 120, minHeight: 40, color: theme.text, backgroundColor: theme.surface, borderColor: theme.border }}
            placeholder={t('chat:placeholder')}
            placeholderTextColor={theme.textMuted}
            value={inputText}
            onChangeText={setInputText}
            multiline
            maxLength={2000}
            returnKeyType="default"
            accessibilityLabel={t('chat:placeholder')}
          />
          <Pressable
            className="w-10 h-10 rounded-full items-center justify-center"
            style={{ backgroundColor: primary, opacity: !inputText.trim() || sending ? 0.5 : 1 }}
            onPress={() => void handleSend()}
            disabled={!inputText.trim() || sending}
            accessibilityLabel={t('chat:send')}
            accessibilityRole="button"
          >
            {sending ? (
              <Spinner size="sm" />
            ) : (
              <Ionicons name="send" size={18} color="#fff" />
            )}
          </Pressable>
        </View>

        {/* Disclaimer */}
        <Text
          className="text-[11px] text-muted-foreground text-center px-4 pb-2"
          style={{ paddingBottom: Math.max(8, insets.bottom) }}
        >
          {t('chat:disclaimer')}
        </Text>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
