// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  Keyboard,
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Button as HeroButton, Card as HeroCard, Spinner, Surface } from 'heroui-native';

import { useTranslation } from 'react-i18next';
import { displayName, getOrCreateThread, getThread, sendMessage, type Message, type SendMessageOptions } from '@/lib/api/messages';
import { useApi } from '@/lib/hooks/useApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useRealtimeContext } from '@/lib/context/RealtimeContext';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import OfflineBanner from '@/components/OfflineBanner';
import TypingIndicator from '@/components/TypingIndicator';
import VoiceMessageBubble from '@/components/VoiceMessageBubble';

export default function ThreadScreen() {
  return (
    <ModalErrorBoundary>
      <ThreadScreenInner />
    </ModalErrorBoundary>
  );
}

function ThreadScreenInner() {
  const { t } = useTranslation('messages');
  const { id, recipientId, name, listing, context_type, context_id } = useLocalSearchParams<{
    id?: string | string[];
    recipientId?: string | string[];
    name?: string | string[];
    listing?: string | string[];
    context_type?: string | string[];
    context_id?: string | string[];
  }>();
  const { user: authUser } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const insets = useSafeAreaInsets();
  const { subscribeToMessages } = useRealtimeContext();

  const rawRecipientId = firstParam(recipientId);
  const rawConversationId = firstParam(id);
  const directRecipientId = Number(rawRecipientId);
  const conversationId = Number(rawConversationId);
  const isNewConversation = Number.isFinite(directRecipientId) && directRecipientId > 0;
  const threadLookupId = isNewConversation ? directRecipientId : conversationId;
  const isValidId = Number.isFinite(threadLookupId) && threadLookupId > 0;
  const safeThreadLookupId = isValidId ? threadLookupId : 0;
  const recipientName = firstParam(name);
  const threadTitle = recipientName?.trim() ? recipientName.trim() : t('threadTitle');
  const listingId = parsePositiveInt(firstParam(listing));
  const contextType = firstParam(context_type);
  const contextId = parsePositiveInt(firstParam(context_id));

  const { data, isLoading, error, refresh } = useApi(
    () => (isNewConversation ? getOrCreateThread(safeThreadLookupId) : getThread(safeThreadLookupId)),
    [safeThreadLookupId, isNewConversation],
    { enabled: isValidId },
  );

  const [messages, setMessages] = useState<Message[]>([]);
  const [inputText, setInputText] = useState('');
  const [isSending, setIsSending] = useState(false);
  const flatListRef = useRef<FlatList<Message>>(null);
  const inputTextRef = useRef(inputText);
  inputTextRef.current = inputText;

  const enrichedMessages = useMemo(() => {
    if (!isValidId || !data?.data) return null;
    const currentUserId = authUser?.id;
    return data.data
      .map((message) => ({
        ...message,
        is_own: message.is_own ?? (currentUserId != null && message.sender_id === currentUserId),
        sender: message.sender ?? { id: message.sender_id ?? 0, first_name: null, last_name: null, avatar_url: null },
      }))
      .sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
  }, [authUser?.id, data, isValidId]);

  useEffect(() => {
    if (enrichedMessages) {
      setMessages(enrichedMessages);
    }
  }, [enrichedMessages]);

  useEffect(() => {
    if (messages.length === 0) return;
    const timer = setTimeout(() => {
      flatListRef.current?.scrollToEnd({ animated: true });
    }, 80);
    return () => clearTimeout(timer);
  }, [messages.length]);

  useEffect(() => {
    if (!isValidId || !safeThreadLookupId) return undefined;
    return subscribeToMessages(safeThreadLookupId, (incoming) => {
      setMessages((prev) => {
        if (prev.some((message) => message.id === incoming.id)) return prev;
        return [...prev, incoming].sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
      });
    });
  }, [isValidId, safeThreadLookupId, subscribeToMessages]);

  const resolvedRecipientId = useMemo(() => {
    if (!isValidId) return null;
    if (isNewConversation && safeThreadLookupId > 0) return safeThreadLookupId;
    const thread = data as (typeof data & { meta?: { conversation?: { other_user?: { id?: number } } } }) | undefined;
    const metaId = thread?.meta?.conversation?.other_user?.id;
    if (metaId) return metaId;
    const incoming = data?.data?.find((message) => !message.is_own);
    return incoming?.sender?.id ?? incoming?.sender_id ?? null;
  }, [data, isNewConversation, isValidId, safeThreadLookupId]);

  const newConversationOptions = useMemo<SendMessageOptions | undefined>(() => {
    if (!isNewConversation) return undefined;
    const options: SendMessageOptions = {};
    if (listingId) options.listing_id = listingId;
    if (contextType && contextId) {
      options.context_type = contextType;
      options.context_id = contextId;
    }
    return Object.keys(options).length > 0 ? options : undefined;
  }, [contextId, contextType, isNewConversation, listingId]);

  const handleSend = useCallback(async () => {
    const body = inputTextRef.current.trim();
    if (!body || isSending || resolvedRecipientId === null) return;

    const optimistic: Message = {
      id: Date.now(),
      body,
      sender: { id: -1, name: t('common:labels.you'), avatar_url: null },
      created_at: new Date().toISOString(),
      is_own: true,
      is_voice: false,
      audio_url: null,
      reactions: {},
      is_read: false,
    };

    setMessages((prev) => [...prev, optimistic]);
    setInputText('');
    Keyboard.dismiss();
    setIsSending(true);

    try {
      const res = newConversationOptions
        ? await sendMessage(resolvedRecipientId, body, newConversationOptions)
        : await sendMessage(resolvedRecipientId, body);
      setMessages((prev) => {
        if (prev.some((message) => message.id === res.data.id)) {
          return prev.filter((message) => message.id !== optimistic.id);
        }
        return prev.map((message) => (message.id === optimistic.id ? { ...res.data, is_own: true } : message));
      });
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      setMessages((prev) => prev.filter((message) => message.id !== optimistic.id));
      setInputText(body);
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('errors.sendFailed'), t('thread.sendFailed'));
    } finally {
      setIsSending(false);
    }
  }, [isSending, newConversationOptions, resolvedRecipientId, t]);

  if (!isValidId) {
    return (
      <ThreadShell title={t('threadTitle')} backLabel={t('thread.goBack')}>
        <CenteredState icon="alert-circle-outline" text={t('thread.invalidConversation')} primary={primary} />
      </ThreadShell>
    );
  }

  if (isLoading && !data) {
    return (
      <ThreadShell title={threadTitle} backLabel={t('thread.goBack')}>
        <LoadingSpinner />
      </ThreadShell>
    );
  }

  if (error && !data) {
    return (
      <ThreadShell title={threadTitle} backLabel={t('thread.goBack')}>
        <CenteredState icon="warning-outline" text={t('thread.loadError')} primary={primary}>
          <HeroButton variant="primary" onPress={() => void refresh()} style={{ backgroundColor: primary }}>
            <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
          </HeroButton>
        </CenteredState>
      </ThreadShell>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={threadTitle} backLabel={t('thread.goBack')} fallbackHref="/(tabs)/messages" />
      <OfflineBanner />
      <KeyboardAvoidingView
        className="flex-1"
        behavior="padding"
        keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 30}
      >
        <Surface variant="secondary" className="mx-4 mb-2 mt-3 flex-row items-center gap-3 rounded-panel-inner p-3">
          <Avatar uri={null} name={threadTitle} size={42} />
          <View className="min-w-0 flex-1">
            <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={1}>
              {threadTitle}
            </Text>
            <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
              {messages.length > 0 ? t('thread.messageCount', { count: messages.length }) : t('thread.noMessages')}
            </Text>
          </View>
          <Ionicons name="lock-closed-outline" size={16} color={theme.textMuted} />
        </Surface>

        <FlatList<Message>
          ref={flatListRef}
          data={messages}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => <MessageBubble item={item} primary={primary} theme={theme} t={t} />}
          contentContainerStyle={{
            flexGrow: 1,
            justifyContent: messages.length ? 'flex-start' : 'center',
            paddingHorizontal: 12,
            paddingVertical: 12,
          }}
          ListEmptyComponent={<EmptyThread primary={primary} text={t('thread.noMessages')} />}
          refreshControl={
            <RefreshControl
              refreshing={isLoading && messages.length > 0}
              onRefresh={refresh}
              tintColor={primary}
              colors={[primary]}
            />
          }
          showsVerticalScrollIndicator={false}
        />

        <TypingIndicator visible={false} />

        <Surface
          variant="default"
          className="flex-row items-end gap-2 border-t border-border/50 px-3 py-2.5"
          style={{ paddingBottom: Math.max(10, insets.bottom) }}
        >
          <TextInput
            className="min-h-[44px] max-h-[120px] flex-1 rounded-[22px] border border-border px-4 pb-2.5 pt-2.5 text-[15px]"
            style={{ color: theme.text, backgroundColor: theme.bg }}
            value={inputText}
            onChangeText={setInputText}
            placeholder={t('thread.inputPlaceholder')}
            placeholderTextColor={theme.textMuted}
            multiline
            maxLength={1000}
            returnKeyType="default"
            accessibilityLabel={t('thread.inputPlaceholder')}
          />
          <HeroButton
            isIconOnly
            size="lg"
            variant="primary"
            style={{ backgroundColor: primary }}
            onPress={handleSend}
            isDisabled={isSending || !inputText.trim()}
            accessibilityLabel={t('messages:send')}
          >
            {isSending ? <Spinner size="sm" /> : <Ionicons name="send" size={18} color="#fff" />}
          </HeroButton>
        </Surface>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function MessageBubble({
  item,
  primary,
  theme,
  t,
}: {
  item: Message;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string) => string;
}) {
  const isOwn = item.is_own;
  const senderName = displayName(item.sender);

  return (
    <View className={`my-1.5 flex-row items-end gap-2 ${isOwn ? 'justify-end pl-12' : 'justify-start pr-12'}`}>
      {!isOwn ? <Avatar uri={item.sender?.avatar_url ?? null} name={senderName} size={32} /> : null}
      <View
        className="max-w-[82%] rounded-[20px] px-4 pb-2.5 pt-3 shadow-sm"
        style={isOwn
          ? { backgroundColor: primary, borderBottomRightRadius: 4 }
          : { backgroundColor: theme.surface, borderBottomLeftRadius: 4, borderColor: theme.borderSubtle, borderWidth: 1 }}
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
            <Ionicons name="mic" size={16} color={isOwn ? 'rgba(255,255,255,0.9)' : theme.textSecondary} />
            <Text className={`text-[14px] italic ${isOwn ? 'text-white' : 'text-foreground'}`}>
              {t('thread.voiceMessage')}
            </Text>
          </View>
        ) : (
          <Text className={`text-[15px] leading-6 ${isOwn ? 'text-white' : 'text-foreground'}`}>
            {item.body}
          </Text>
        )}
        <Text
          className="mt-1 text-[10px]"
          style={isOwn
            ? { color: 'rgba(255,255,255,0.75)', textAlign: 'right' }
            : { color: theme.textMuted, textAlign: 'right' }}
        >
          {formatTime(item.created_at)}
        </Text>
      </View>
    </View>
  );
}

function EmptyThread({ primary, text }: { primary: string; text: string }) {
  return (
    <HeroCard variant="secondary" className="mx-4 my-8">
      <HeroCard.Body className="items-center gap-3 px-5 py-6">
        <Ionicons name="chatbubble-ellipses-outline" size={34} color={primary} />
        <Text className="text-center text-sm leading-5 text-muted-foreground">{text}</Text>
      </HeroCard.Body>
    </HeroCard>
  );
}

function ThreadShell({ title, backLabel, children }: { title: string; backLabel: string; children: React.ReactNode }) {
  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={title} backLabel={backLabel} fallbackHref="/(tabs)/messages" />
      <View className="flex-1 items-center justify-center px-6">{children}</View>
    </SafeAreaView>
  );
}

function CenteredState({
  icon,
  text,
  primary,
  children,
}: {
  icon: React.ComponentProps<typeof Ionicons>['name'];
  text: string;
  primary: string;
  children?: React.ReactNode;
}) {
  return (
    <HeroCard variant="secondary" className="w-full">
      <HeroCard.Body className="items-center gap-4 px-5 py-6">
        <Ionicons name={icon} size={34} color={primary} />
        <Text className="text-center text-sm leading-5 text-muted-foreground">{text}</Text>
        {children}
      </HeroCard.Body>
    </HeroCard>
  );
}

function firstParam(value: string | string[] | undefined): string | undefined {
  if (Array.isArray(value)) return value[0];
  return value;
}

function parsePositiveInt(value: string | undefined): number | undefined {
  if (!value) return undefined;
  const parsed = Number.parseInt(value, 10);
  return Number.isFinite(parsed) && parsed > 0 ? parsed : undefined;
}

function formatTime(iso: string): string {
  const date = new Date(iso);
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
