// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useRef, useCallback, useMemo } from 'react';
import {
  View,
  Text,
  FlatList,
  TextInput,
  TouchableOpacity,
  KeyboardAvoidingView,
  Platform,
  StyleSheet,
  SafeAreaView,
  ActivityIndicator,
  RefreshControl,
  Keyboard,
  Alert,
} from 'react-native';
import { useLocalSearchParams, useNavigation } from 'expo-router';
import { useEffect } from 'react';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';
import { getThread, getOrCreateThread, sendMessage, type Message } from '@/lib/api/messages';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';
import Avatar from '@/components/ui/Avatar';
import VoiceMessageBubble from '@/components/VoiceMessageBubble';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import OfflineBanner from '@/components/OfflineBanner';

export default function ThreadScreen() {
  const { t } = useTranslation('messages');
  // `id` = other user's ID (used by conversation list — existing conversation)
  // `recipientId` = other user's ID (used by member-profile / exchange-detail — may be new conversation)
  // Both refer to the other user's ID; `recipientId` signals "this might be a new conversation".
  const { id, recipientId, name } = useLocalSearchParams<{
    id: string;
    recipientId: string;
    name: string;
  }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const navigation = useNavigation();

  // Prefer recipientId (new conversation mode) over id (existing conversation mode)
  const otherUserId = Number(recipientId || id);
  const isNewConversation = !!recipientId;
  const safeConversationId = isNaN(otherUserId) || otherUserId <= 0 ? 0 : otherUserId;

  const { subscribeToMessages } = useRealtimeContext();

  // Use getOrCreateThread for new conversations (handles 404 gracefully),
  // plain getThread for existing conversations opened from the inbox.
  const { data, isLoading, error, refresh } = useApi(
    () => isNewConversation ? getOrCreateThread(safeConversationId) : getThread(safeConversationId),
    [safeConversationId, isNewConversation],
  );

  const [messages, setMessages] = useState<Message[]>([]);
  const [inputText, setInputText] = useState('');
  const [isSending, setIsSending] = useState(false);
  const flatListRef = useRef<FlatList<Message>>(null);

  if (isNaN(otherUserId) || otherUserId <= 0) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>Invalid conversation.</Text>
      </SafeAreaView>
    );
  }

  // Set the header title to the other user's name
  useEffect(() => {
    if (name) {
      navigation.setOptions({ title: name });
    }
  }, [name, navigation]);

  // Populate messages from API response
  useEffect(() => {
    if (data?.data) {
      // API returns oldest-first; FlatList is inverted so we reverse for display
      setMessages([...data.data].reverse());
    }
  }, [data]);

  // Live incoming messages via Pusher
  useEffect(() => {
    if (!safeConversationId) return;
    return subscribeToMessages(safeConversationId, (incoming) => {
      setMessages((prev) => {
        if (prev.some((m) => m.id === incoming.id)) return prev; // already present
        return [incoming, ...prev]; // prepend (FlatList inverted = newest first)
      });
    });
  }, [safeConversationId, subscribeToMessages]);

  // The recipient's user ID — we already have it from nav params.
  // Also check API metadata as a secondary source.
  const resolvedRecipientId: number | null = (() => {
    // Primary: from navigation params (always available)
    if (safeConversationId > 0) return safeConversationId;
    // Fallback: API metadata
    const thread = data as (typeof data & { meta?: { conversation?: { other_user?: { id?: number } } } }) | undefined;
    const metaId = thread?.meta?.conversation?.other_user?.id;
    if (metaId) return metaId;
    // Last resort: pick sender id from any incoming message
    const incoming = data?.data?.find((m) => !m.is_own);
    return incoming?.sender.id ?? null;
  })();

  const handleSend = useCallback(async () => {
    const body = inputText.trim();
    if (!body || isSending || resolvedRecipientId === null) return;

    // Optimistic append
    const optimistic: Message = {
      id: Date.now(), // temporary local id
      body,
      sender: { id: -1, name: 'You', avatar_url: null },
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
      Alert.alert('Send failed', 'Message could not be sent. Please try again.');
    } finally {
      setIsSending(false);
    }
  }, [inputText, isSending, resolvedRecipientId]);

  function renderMessage({ item }: { item: Message }) {
    const isOwn = item.is_own;
    return (
      <View style={[styles.bubbleRow, isOwn ? styles.bubbleRowOwn : styles.bubbleRowOther]}>
        {!isOwn && (
          <Avatar uri={item.sender.avatar_url} name={item.sender.name} size={28} />
        )}
        <View
          style={[
            styles.bubble,
            isOwn
              ? [styles.bubbleOwn, { backgroundColor: primary }]
              : styles.bubbleOther,
          ]}
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
            <View style={styles.voiceRow}>
              <Ionicons
                name="mic"
                size={16}
                color={isOwn ? 'rgba(255,255,255,0.9)' : theme.textSecondary}
              />
              <Text style={[styles.voiceLabel, isOwn ? styles.bubbleTextOwn : styles.bubbleTextOther]}>
                {t('thread.voiceMessage')}
              </Text>
            </View>
          ) : (
            <Text style={[styles.bubbleText, isOwn ? styles.bubbleTextOwn : styles.bubbleTextOther]}>
              {item.body}
            </Text>
          )}
          <Text style={[styles.bubbleTime, isOwn ? styles.bubbleTimeOwn : styles.bubbleTimeOther]}>
            {formatTime(item.created_at)}
          </Text>
        </View>
      </View>
    );
  }

  if (isLoading && !data) {
    return (
      <SafeAreaView style={styles.centered}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (error && !data) {
    return (
      <SafeAreaView style={styles.centered}>
        <Text style={styles.errorText}>{t('thread.loadError')}</Text>
        <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
          <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>Retry</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <OfflineBanner />
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}
      >
        <FlatList<Message>
          ref={flatListRef}
          data={messages}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderMessage}
          inverted
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl
              refreshing={isLoading && messages.length > 0}
              onRefresh={refresh}
              tintColor={primary}
            />
          }
        />

        <View style={styles.inputRow}>
          <TextInput
            style={styles.input}
            value={inputText}
            onChangeText={setInputText}
            placeholder={t('thread.inputPlaceholder')}
            placeholderTextColor={theme.textMuted}
            multiline
            maxLength={1000}
            returnKeyType="default"
          />
          <TouchableOpacity
            style={[styles.sendButton, { backgroundColor: primary }]}
            onPress={handleSend}
            disabled={isSending || !inputText.trim()}
            activeOpacity={0.8}
          >
            {isSending ? (
              <ActivityIndicator color="#fff" size="small" />
            ) : (
              <Text style={styles.sendButtonText}>{t('thread.send')}</Text>
            )}
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function formatTime(iso: string): string {
  const date = new Date(iso);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    flex: { flex: 1 },
    container: { flex: 1, backgroundColor: theme.surface },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },

    listContent: { paddingHorizontal: 12, paddingVertical: 12 },

    bubbleRow: {
      flexDirection: 'row',
      marginVertical: 3,
      alignItems: 'flex-end',
      gap: 6,
    },
    bubbleRowOwn: { justifyContent: 'flex-end' },
    bubbleRowOther: { justifyContent: 'flex-start' },

    bubble: {
      maxWidth: '72%',
      borderRadius: 18,
      paddingHorizontal: 14,
      paddingTop: 8,
      paddingBottom: 6,
    },
    bubbleOwn: {
      borderBottomRightRadius: 4,
    },
    bubbleOther: {
      backgroundColor: theme.bg,
      borderBottomLeftRadius: 4,
    },

    voiceRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
    voiceLabel: { fontSize: 14, fontStyle: 'italic' },
    bubbleText: { fontSize: 15, lineHeight: 20 },
    bubbleTextOwn: { color: '#fff' },
    bubbleTextOther: { color: theme.text },

    bubbleTime: { fontSize: 10, marginTop: 2 },
    bubbleTimeOwn: { color: 'rgba(255,255,255,0.75)', textAlign: 'right' },
    bubbleTimeOther: { color: theme.textMuted, textAlign: 'right' },

    inputRow: {
      flexDirection: 'row',
      alignItems: 'flex-end',
      paddingHorizontal: 12,
      paddingVertical: 10,
      borderTopWidth: 1,
      borderTopColor: theme.borderSubtle,
      backgroundColor: theme.surface,
      gap: 8,
    },
    input: {
      flex: 1,
      minHeight: 40,
      maxHeight: 120,
      borderWidth: 1,
      borderColor: theme.border,
      borderRadius: 20,
      paddingHorizontal: 16,
      paddingTop: 10,
      paddingBottom: 10,
      fontSize: 15,
      color: theme.text,
      backgroundColor: theme.surface,
    },
    sendButton: {
      height: 40,
      paddingHorizontal: 18,
      borderRadius: 20,
      justifyContent: 'center',
      alignItems: 'center',
    },
    sendButtonText: { color: '#fff', fontWeight: '600', fontSize: 14 },
  });
}
