// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback } from 'react';
import { Alert, FlatList, Pressable, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Swipeable } from 'react-native-gesture-handler';
import * as Haptics from 'expo-haptics';
import { Spinner, Separator } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getConversations, deleteConversation, displayName, type Conversation, type ConversationListResponse } from '@/lib/api/messages';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import Avatar from '@/components/ui/Avatar';
import { ConversationSkeleton } from '@/components/ui/Skeleton';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';

function extractConversationsPage(response: ConversationListResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor ?? null,
    hasMore: response.meta.has_more,
  };
}

export default function MessagesScreen() {
  const { t } = useTranslation('messages');
  const primary = usePrimaryColor();

  const fetchConversations = useCallback((cursor: string | null) => getConversations(cursor), []);

  const { items: conversations, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Conversation, ConversationListResponse>(fetchConversations, extractConversationsPage);

  const ItemSeparator = useCallback(
    () => <Separator style={{ marginLeft: 76 }} />,
    [],
  );

  function handleDeleteConversation(conversation: Conversation) {
    Alert.alert(
      t('common:buttons.delete'),
      t('deleteConfirm', { name: displayName(conversation.other_user) }),
      [
        { text: t('common:buttons.cancel'), style: 'cancel' },
        {
          text: t('common:buttons.delete'),
          style: 'destructive',
          onPress: async () => {
            try {
              await deleteConversation(conversation.id);
              void refresh();
            } catch {
              Alert.alert(t('common:error'), t('common:errors.generic'));
            }
          },
        },
      ],
    );
  }

  function renderConversation({ item }: { item: Conversation }) {
    const lastMsg = item.last_message;
    const otherName = displayName(item.other_user);
    const lastMsgBody = lastMsg?.body ?? lastMsg?.content ?? '';
    const lastMsgIsOwn = lastMsg?.is_own ?? (lastMsg?.sender_id != null && lastMsg.sender_id === item.other_user?.id ? false : true);

    return (
      <Swipeable
        renderRightActions={() => (
          <View className="bg-danger justify-center items-center w-[72px]">
            <Ionicons name="trash-outline" size={22} color="#fff" />
          </View>
        )}
        overshootRight={false}
        onSwipeableWillOpen={() => void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium)}
        onSwipeableOpen={() => handleDeleteConversation(item)}
      >
        <Pressable
          className="flex-row items-center px-4 py-3 bg-surface"
          accessibilityRole="button"
          accessibilityLabel={`${otherName}${lastMsgBody ? `, ${lastMsgBody}` : ''}`}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push({
              pathname: '/(modals)/thread',
              params: { id: String(item.id), name: otherName },
            });
          }}
        >
          <Avatar uri={item.other_user?.avatar_url ?? null} name={otherName} size={48} />
          <View className="flex-1 ml-3">
            <View className="flex-row justify-between mb-0.5">
              <Text className="font-semibold text-foreground flex-1 mr-2" numberOfLines={1}>
                {otherName}
              </Text>
              {lastMsg ? (
                <Text className="text-xs text-muted-foreground">
                  {formatRelativeTime(lastMsg.created_at, true)}
                </Text>
              ) : null}
            </View>
            <View className="flex-row items-center">
              <Text className="text-sm text-muted-foreground flex-1" numberOfLines={1}>
                {lastMsgBody
                  ? `${lastMsgIsOwn ? `${t('thread.you')}: ` : ''}${lastMsgBody}`
                  : t('thread.noMessages')}
              </Text>
              {(item.unread_count ?? 0) > 0 ? (
                <View
                  className="min-w-[20px] h-5 rounded-full items-center justify-center px-1.5 ml-2"
                  style={{ backgroundColor: primary }}
                >
                  <Text className="text-white text-[11px] font-semibold">{item.unread_count}</Text>
                </View>
              ) : null}
            </View>
          </View>
        </Pressable>
      </Swipeable>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-surface">
      <View className="flex-row items-center justify-between px-4 pt-4 pb-2">
        <Text className="text-xl font-bold text-foreground">{t('title')}</Text>
        <Pressable
          className="w-9 h-9 rounded-full items-center justify-center"
          style={{ backgroundColor: primary }}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push('/(modals)/members');
          }}
          accessibilityLabel={t('newMessage')}
          accessibilityRole="button"
        >
          <Ionicons name="create-outline" size={18} color="#fff" />
        </Pressable>
      </View>

      <FlatList<Conversation>
        data={conversations}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderConversation}
        ItemSeparatorComponent={ItemSeparator}
        refreshControl={
          <RefreshControl
            refreshing={isLoading && conversations.length > 0}
            onRefresh={refresh}
            tintColor={primary}
            colors={[primary]}
          />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.5}
        ListEmptyComponent={
          isLoading && conversations.length === 0 ? (
            <><ConversationSkeleton /><ConversationSkeleton /><ConversationSkeleton /><ConversationSkeleton /><ConversationSkeleton /></>
          ) : error ? (
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-danger text-sm text-center mb-3">{error}</Text>
              <Pressable onPress={() => void refresh()} className="px-5 py-2.5">
                <Text className="font-semibold" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-muted-foreground text-sm text-center">{t('empty.title')}</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4 items-center"><Spinner size="sm" /></View>
          ) : !hasMore && conversations.length > 0 && !isLoading ? (
            <View className="py-4 items-center">
              <Text className="text-xs text-muted-foreground">{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={{ flexGrow: 1 }}
      />
    </SafeAreaView>
  );
}
