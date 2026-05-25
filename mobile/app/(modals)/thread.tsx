// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useRef, useCallback, useEffect, useMemo } from 'react';
import {
  View,
  Text,
  FlatList,
  TextInput,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
  Keyboard,
  Alert,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Spinner } from 'heroui-native';

import { useTranslation } from 'react-i18next';
import { getThread, getOrCreateThread, sendMessage, displayName, type Message } from '@/lib/api/messages';
import { useApi } from '@/lib/hooks/useApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';
import Avatar from '@/components/ui/Avatar';
import VoiceMessageBubble from '@/components/VoiceMessageBubble';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import OfflineBanner from '@/components/OfflineBanner';
import TypingIndicator from '@/components/TypingIndicator';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function ThreadScreen() {
  return (
    <ModalErrorBoundary>
      <ThreadScreenInner />
    </ModalErrorBoundary>
  );
}

function ThreadScreenInner() {
  const { t } = useTranslation('messages');
  // `id` = other user's ID (used by conversation list — existing conversation)
  // `recipientId` = other user's ID (used by member-profile / exchange-detail — may be new conversation)
  // Both refer to the other user's ID; `recipientId` signals "this might be a new conversation".
  const { id, recipientId, name } = useLocalSearchParams<{
    id: string;
    recipientId: string;
    name: string;
  }>();
  const { user: authUser } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const navigation = useNavigation();

  // Prefer recipientId (new conversation mode) over id (existing conversation mode)
  const otherUserId = Number(recipientId || id);
  const isNewConversation = !!recipientId;
  const safeConversationId = isNaN(otherUserId) || otherUserId <= 0 ? 0 : otherUserId;

  const { subscribeToMessages } = useRealtimeContext();

  const isValidId = !isNaN(otherUserId) && otherUserId > 0;

  // Use getOrCreateThread for new conversations (handles 404 gracefully),
  // plain getThread for existing conversations opened from the inbox.
  const { data, isLoading, error, refresh } = useApi(
    () => isNewConversation ? getOrCreateThread(safeConversationId) : getThread(safeConversationId),
    [safeConversationId, isNewConversation],
    { enabled: isValidId },
  );

  const [messages, setMessages] = useState<Message[]>([]);
  const [inputText, setInputText] = useState('');
  const [isSending, setIsSending] = useState(false);
  const flatListRef = useRef<FlatList<Message>>(null);
  const inputTextRef = useRef(inputText);
  inputTextRef.current = inputText;

  // Set the header title to the other user's name
  useEffect(() => {
    if (!isValidId) return;
    if (name) {
      navigation.setOptions({ title: name });
    }
  }, [name, navigation, isValidId]);

  // Populate messages from API response — memoize the enrichment to avoid
  // recreating the array on every render (only recomputes when data changes).
  const enrichedMessages = useMemo(() => {
    if (!isValidId || !data?.data) return null;
    const currentUserId = authUser?.id;
    return data.data.map((m) => ({
      ...m,
      is_own: m.is_own ?? (currentUserId != null && m.sender_id === currentUserId),
      sender: m.sender ?? { id: m.sender_id ?? 0, first_name: null, last_name: null, avatar_url: null },
    })).reverse();
  }, [data, isValidId, authUser?.id]);

  useEffect(() => {
    if (enrichedMessages) {
      setMessages(enrichedMessages);
    }
  }, [enrichedMessages]);

  // Live incoming messages via Pusher
  useEffect(() => {
    if (!isValidId || !safeConversationId) return;
    return subscribeToMessages(safeConversationId, (incoming) => {
      setMessages((prev) => {
        if (prev.some((m) => m.id === incoming.id)) return prev; // already present
        return [incoming, ...prev]; // prepend (FlatList inverted = newest first)
      });
    });
  }, [safeConversationId, subscribeToMessages, isValidId]);

  // The recipient's user ID — we already have it from nav params.
  // Also check API metadata as a secondary source.
  const resolvedRecipientId: number | null = (() => {
    if (!isValidId) return null;
    // Primary: from navigation params (always available)
    if (safeConversationId > 0) return safeConversationId;
    // Fallback: API metadata
    const thread = data as (typeof data & { meta?: { conversation?: { other_user?: { id?: number } } } }) | undefined;
    const metaId = thread?.meta?.conversation?.other_user?.id;
    if (metaId) return metaId;
    // Last resort: pick sender id from any incoming message
    const incoming = data?.data?.find((m) => !m.is_own);
    return incoming?.sender?.id ?? incoming?.sender_id ?? null;
  })();

  const handleSend = useCallback(async () => {
    const body = inputTextRef.current.trim();
    if (!body || isSending || resolvedRecipientId === null) return;

    // Optimistic append
    const optimistic: Message = {
      id: Date.now(), // temporary local id
      body,
      sender: { id: -1, name: t('common:labels.you'), avatar_url: null },
      created_at: new Date().toISOString(),
      is_own: true,
      is_voice: false,
      audio_url: null,
      reactions: {},
      is_read: false,
    };
    setMessages((prev) => [optimistic, ...prev]);
    setInputText('');
    Keyboard.dismiss();
    setIsSending(true);

    try {
      const res = await sendMessage(resolvedRecipientId, body);
      // Replace optimistic with server-confirmed message.
      // If Pusher already delivered it, drop the optimistic instead of duplicating.
      setMessages((prev) => {
        if (prev.some((m) => m.id === res.data.id)) {
          return prev.filter((m) => m.id !== optimistic.id);
        }
        return prev.map((m) => (m.id === optimistic.id ? res.data : m));
      });
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      // Remove optimistic message on failure and restore input
      setMessages((prev) => prev.filter((m) => m.id !== optimistic.id));
      setInputText(body);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('errors.sendFailed'), t('thread.sendFailed'));
    } finally {
      setIsSending(false);
    }
  }, [isSending, resolvedRecipientId]);

  if (!isValidId) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center p-8">
        <Text className="text-sm text-danger text-center">{t('thread.invalidConversation')}</Text>
      </SafeAreaView>
    );
  }

  function renderMessage({ item }: { item: Message }) {
    const isOwn = item.is_own;
    const senderName = displayName(item.sender);
    return (
      <View className={`flex-row my-0.5 items-end gap-1.5 ${isOwn ? 'justify-end' : 'justify-start'}`}>
        {!isOwn && (
          <Avatar uri={item.sender?.avatar_url ?? null} name={senderName} size={28} />
        )}
        <View
          className="max-w-[72%] rounded-[18px] px-3.5 pt-2 pb-1.5"
          style={isOwn
            ? { backgroundColor: primary, borderBottomRightRadius: 4 }
            : { backgroundColor: theme.bg, borderBottomLeftRadius: 4 }
          }
        >
          {item.is_voice && item.audio_url ? (
            <VoiceMessageBubble
              audioUrl={item.audio_url}
              isOwn={isOwn}
              primaryColor={primary}
              textColor={theme.text}
              textColorSecondary={theme.textSecondary}
            />
          ) : item.is_voice ? (
            <View className="flex-row items-center gap-1.5">
              <Ionicons
                name="mic"
                size={16}
                color={isOwn ? 'rgba(255,255,255,0.9)' : theme.textSecondary} // contrast on primary
              />
              <Text className={`text-[14px] italic ${isOwn ? 'text-white' : 'text-foreground'}`}>
                {t('thread.voiceMessage')}
              </Text>
            </View>
          ) : (
            <Text className={`text-[15px] leading-5 ${isOwn ? 'text-white' : 'text-foreground'}`}>
              {item.body}
            </Text>
          )}
          <Text
            className="text-[10px] mt-0.5"
            style={isOwn
              ? { color: 'rgba(255,255,255,0.75)', textAlign: 'right' } // contrast on primary
              : { color: theme.textMuted, textAlign: 'right' }
            }
          >
            {formatTime(item.created_at)}
          </Text>
        </View>
      </View>
    );
  }

  if (isLoading && !data) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center p-8">
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (error && !data) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center p-8">
        <Text className="text-sm text-danger text-center mb-3">{t('thread.loadError')}</Text>
        <Pressable onPress={() => void refresh()} className="px-5 py-2.5">
          <Text style={{ color: primary }} className="font-semibold text-[15px]">{t('common:buttons.retry')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-surface">
      <OfflineBanner />
      <KeyboardAvoidingView
        className="flex-1"
        behavior="padding"
        keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 30}
      >
        <FlatList<Message>
          ref={flatListRef}
          data={messages}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderMessage}
          inverted
          contentContainerStyle={{ paddingHorizontal: 12, paddingVertical: 12 }}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={isLoading && messages.length > 0}
              onRefresh={refresh}
              tintColor={primary}
            />
          }
        />

        {/* Typing indicator — wire up via Pusher later */}
        <TypingIndicator visible={false} />

        <View
          className="flex-row items-end px-3 py-2.5 border-t border-border/50 bg-surface gap-2"
          style={{ paddingBottom: Math.max(10, insets.bottom) }}
        >
          <TextInput
            className="flex-1 min-h-[40px] max-h-[120px] border border-border rounded-[20px] px-4 pt-2.5 pb-2.5 text-[15px]"
            style={{ color: theme.text, backgroundColor: theme.surface }}
            value={inputText}
            onChangeText={setInputText}
            placeholder={t('thread.inputPlaceholder')}
            placeholderTextColor={theme.textMuted}
            multiline
            maxLength={1000}
            returnKeyType="default"
          />
          <Pressable
            className="h-10 px-4 rounded-[20px] justify-center items-center"
            style={{ backgroundColor: primary }}
            onPress={handleSend}
            disabled={isSending || !inputText.trim()}
            accessibilityLabel={t('messages:send')}
            accessibilityRole="button"
          >
            {isSending ? (
              <Spinner size="sm" />
            ) : (
              <Text className="text-white font-semibold text-[14px]">{t('thread.send')}</Text>
            )}
          </Pressable>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function formatTime(iso: string): string {
  const date = new Date(iso);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
