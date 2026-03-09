// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useRef, useCallback } from 'react';
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
} from 'react-native';
import { useLocalSearchParams, useNavigation } from 'expo-router';
import { useEffect } from 'react';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

import { getThread, sendMessage, type Message } from '@/lib/api/messages';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

export default function ThreadScreen() {
  const { id, name } = useLocalSearchParams<{ id: string; name: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = makeStyles(theme);
  const navigation = useNavigation();

  const conversationId = Number(id);

  const { data, isLoading, error } = useApi(
    () => getThread(conversationId),
    [conversationId],
  );

  const [messages, setMessages] = useState<Message[]>([]);
  const [inputText, setInputText] = useState('');
  const [isSending, setIsSending] = useState(false);
  const flatListRef = useRef<FlatList<Message>>(null);

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

  // Derive the other user's ID from the first non-own message's sender
  const otherUserId: number | null = (() => {
    const thread = data as (typeof data & { meta?: { conversation?: { other_user?: { id?: number } } } }) | undefined;
    const metaId = thread?.meta?.conversation?.other_user?.id;
    if (metaId) return metaId;
    // Fallback: pick sender id from any incoming message
    const incoming = data?.data?.find((m) => !m.is_own);
    return incoming?.sender.id ?? null;
  })();

  const handleSend = useCallback(async () => {
    const body = inputText.trim();
    if (!body || isSending || otherUserId === null) return;

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
    setIsSending(true);

    try {
      const res = await sendMessage(otherUserId, body);
      // Replace optimistic message with real one from server
      setMessages((prev) =>
        prev.map((m) => (m.id === optimistic.id ? res.data : m)),
      );
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      // Remove optimistic message on failure and restore input
      setMessages((prev) => prev.filter((m) => m.id !== optimistic.id));
      setInputText(body);
    } finally {
      setIsSending(false);
    }
  }, [inputText, isSending, otherUserId]);

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
          {item.is_voice ? (
            <View style={styles.voiceRow}>
              <Ionicons
                name="mic"
                size={16}
                color={isOwn ? 'rgba(255,255,255,0.9)' : theme.textSecondary}
              />
              <Text style={[styles.voiceLabel, isOwn ? styles.bubbleTextOwn : styles.bubbleTextOther]}>
                Voice message
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
        <Text style={styles.errorText}>Could not load messages. Please try again.</Text>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
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
        />

        <View style={styles.inputRow}>
          <TextInput
            style={styles.input}
            value={inputText}
            onChangeText={setInputText}
            placeholder="Write a message…"
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
              <Text style={styles.sendButtonText}>Send</Text>
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
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center' },

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
