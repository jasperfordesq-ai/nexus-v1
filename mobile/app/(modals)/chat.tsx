// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  TextInput,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

import { sendChatMessage, type ChatMessage } from '@/lib/api/chat';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// Internal message type extends ChatMessage to support the transient "thinking" state
type DisplayMessage = ChatMessage | { id: string; role: 'thinking'; content: string; created_at: string };

const THINKING_ID = '__thinking__';

// ─── Message Bubble ───────────────────────────────────────────────────────────

function MessageBubble({
  message,
  primary,
  theme,
  styles,
  t,
}: {
  message: DisplayMessage;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
  t: (key: string) => string;
}) {
  const isUser = message.role === 'user';
  const isThinking = message.role === 'thinking';

  return (
    <View style={[styles.bubbleRow, isUser ? styles.bubbleRowRight : styles.bubbleRowLeft]}>
      <View
        style={[
          styles.bubble,
          isUser
            ? [styles.bubbleUser, { backgroundColor: primary }]
            : styles.bubbleAssistant,
        ]}
      >
        {isThinking ? (
          <View style={styles.thinkingWrap}>
            <ActivityIndicator size="small" color={theme.textSecondary} />
            <Text style={[styles.bubbleText, { color: theme.textSecondary }]}>{t('chat:thinking')}</Text>
          </View>
        ) : (
          <Text style={[styles.bubbleText, isUser ? { color: '#fff' } : { color: theme.text }]}>{/* contrast on primary */}
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
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
        theme={theme}
        styles={styles}
        t={t}
      />
    ),
    [primary, theme, styles, t],
  );

  return (
      <SafeAreaView style={styles.container}>
        <KeyboardAvoidingView
          style={styles.flex}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
          keyboardVerticalOffset={Platform.OS === 'ios' ? 88 : 0}
        >
          {/* Message list — inverted so newest messages appear at bottom */}
          {messages.length === 0 ? (
            <View style={styles.emptyWrap}>
              <Ionicons name="chatbubble-ellipses-outline" size={40} color={theme.textMuted} />
              <Text style={styles.emptyText}>{t('chat:empty')}</Text>
            </View>
          ) : (
            <FlatList<DisplayMessage>
              ref={listRef}
              data={messages}
              keyExtractor={(item) => item.id}
              renderItem={renderItem}
              inverted
              contentContainerStyle={styles.listContent}
              onContentSizeChange={scrollToBottom}
            />
          )}

          {/* Input area */}
          <View style={[styles.inputArea, { borderTopColor: theme.border }]}>
            <TextInput
              style={[styles.textInput, { borderColor: theme.border, color: theme.text, backgroundColor: theme.surface }]}
              placeholder={t('chat:placeholder')}
              placeholderTextColor={theme.textMuted}
              value={inputText}
              onChangeText={setInputText}
              multiline
              maxLength={2000}
              returnKeyType="default"
              accessibilityLabel={t('chat:placeholder')}
            />
            <TouchableOpacity
              style={[styles.sendBtn, { backgroundColor: primary, opacity: !inputText.trim() || sending ? 0.5 : 1 }]}
              onPress={() => void handleSend()}
              disabled={!inputText.trim() || sending}
              activeOpacity={0.8}
              accessibilityLabel={t('chat:send')}
              accessibilityRole="button"
            >
              {sending ? (
                <ActivityIndicator size="small" color="#fff" />
              ) : (
                <Ionicons name="send" size={18} color="#fff" />
              )}
            </TouchableOpacity>
          </View>

          {/* Disclaimer */}
          <Text style={[styles.disclaimer, { paddingBottom: Math.max(SPACING.sm, insets.bottom) }]}>{t('chat:disclaimer')}</Text>
        </KeyboardAvoidingView>
      </SafeAreaView>
  );
}

// ─── Styles ───────────────────────────────────────────────────────────────────

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    flex: { flex: 1 },
    listContent: { paddingHorizontal: 12, paddingVertical: SPACING.md },

    // Empty state
    emptyWrap: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: 12 },
    emptyText: { ...TYPOGRAPHY.label, color: theme.textMuted, textAlign: 'center', paddingHorizontal: SPACING.xl },

    // Message bubbles
    bubbleRow: { marginBottom: 10, maxWidth: '80%' },
    bubbleRowRight: { alignSelf: 'flex-end' },
    bubbleRowLeft: { alignSelf: 'flex-start' },
    bubble: {
      borderRadius: SPACING.md,
      paddingHorizontal: RADIUS.lg,
      paddingVertical: RADIUS.md,
    },
    bubbleUser: {
      borderBottomRightRadius: 4,
    },
    bubbleAssistant: {
      backgroundColor: theme.surface,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      borderBottomLeftRadius: 4,
    },
    bubbleText: { ...TYPOGRAPHY.body },
    thinkingWrap: { flexDirection: 'row', alignItems: 'center', gap: 8 },

    // Input area
    inputArea: {
      flexDirection: 'row',
      alignItems: 'flex-end',
      gap: 10,
      paddingHorizontal: 12,
      paddingVertical: 10,
      borderTopWidth: 1,
    },
    textInput: {
      flex: 1,
      borderWidth: 1,
      borderRadius: RADIUS.xl,
      paddingHorizontal: RADIUS.lg,
      paddingVertical: 9,
      fontSize: TYPOGRAPHY.body.fontSize,
      maxHeight: 120,
      minHeight: 40,
    },
    sendBtn: {
      width: 40,
      height: 40,
      borderRadius: 20,
      alignItems: 'center',
      justifyContent: 'center',
    },

    // Disclaimer
    disclaimer: {
      fontSize: 11,
      color: theme.textMuted,
      textAlign: 'center',
      paddingHorizontal: SPACING.md,
      paddingBottom: SPACING.sm,
    },
  });
}
