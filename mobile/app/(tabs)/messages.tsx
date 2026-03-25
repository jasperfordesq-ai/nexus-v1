// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useCallback } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  View,
  Text,
  TouchableOpacity,
  RefreshControl,
  StyleSheet,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Swipeable } from 'react-native-gesture-handler';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getConversations, deleteConversation, displayName, type Conversation, type ConversationListResponse } from '@/lib/api/messages';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import { ConversationSkeleton } from '@/components/ui/Skeleton';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

/** Extractor for cursor-based ConversationListResponse. */
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
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const fetchConversations = useCallback(
    (cursor: string | null) => getConversations(cursor),
    [],
  );

  const { items: conversations, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Conversation, ConversationListResponse>(fetchConversations, extractConversationsPage);

  const Separator = useCallback(() => <View style={styles.separator} />, [styles]);

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

  function renderRightActions() {
    return (
      <TouchableOpacity
        style={styles.swipeDeleteBtn}
        onPress={() => {
          // onPress is handled per-item below via the Swipeable wrapper
        }}
        activeOpacity={0.8}
        accessibilityLabel={t('common:buttons.delete')}
        accessibilityRole="button"
      >
        <Ionicons name="trash-outline" size={22} color="#fff" />
      </TouchableOpacity>
    );
  }

  function renderConversation({ item }: { item: Conversation }) {
    const lastMsg = item.last_message;
    const otherName = displayName(item.other_user);
    // API returns `content` (not `body`) in conversation list last_message
    const lastMsgBody = lastMsg?.body ?? lastMsg?.content ?? '';
    // `is_own` may not be present — fall back to checking sender_id
    const lastMsgIsOwn = lastMsg?.is_own ?? (lastMsg?.sender_id != null && lastMsg.sender_id === item.other_user?.id ? false : true);
    return (
      <Swipeable
        renderRightActions={renderRightActions}
        overshootRight={false}
        onSwipeableWillOpen={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
        }}
        onSwipeableOpen={() => {
          handleDeleteConversation(item);
        }}
      >
        <TouchableOpacity
          style={styles.row}
          activeOpacity={0.7}
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
          <Avatar
            uri={item.other_user?.avatar_url ?? null}
            name={otherName}
            size={48}
          />
          <View style={styles.rowContent}>
            <View style={styles.rowHeader}>
              <Text style={styles.userName} numberOfLines={1}>
                {otherName}
              </Text>
              {lastMsg && (
                <Text style={styles.time}>
                  {formatRelativeTime(lastMsg.created_at, true)}
                </Text>
              )}
            </View>
            <View style={styles.rowFooter}>
              <Text style={styles.lastMessage} numberOfLines={1}>
                {lastMsgBody
                  ? `${lastMsgIsOwn ? `${t('thread.you')}: ` : ''}${lastMsgBody}`
                  : t('thread.noMessages')}
              </Text>
              {(item.unread_count ?? 0) > 0 && (
                <View style={[styles.badge, { backgroundColor: primary }]}>
                  <Text style={styles.badgeText}>{item.unread_count}</Text>
                </View>
              )}
            </View>
          </View>
        </TouchableOpacity>
      </Swipeable>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>{t('title')}</Text>
        <TouchableOpacity
          style={[styles.composeButton, { backgroundColor: primary }]}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push('/(modals)/members');
          }}
          activeOpacity={0.8}
          accessibilityLabel={t('newMessage')}
          accessibilityRole="button"
        >
          {/* #fff = contrast on primary */}
          <Ionicons name="create-outline" size={18} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList<Conversation>
        data={conversations}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderConversation}
        ItemSeparatorComponent={Separator}
        refreshControl={
          <RefreshControl refreshing={isLoading && conversations.length > 0} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.5}
        ListEmptyComponent={
          isLoading && conversations.length === 0 ? (
            <>
              <ConversationSkeleton />
              <ConversationSkeleton />
              <ConversationSkeleton />
              <ConversationSkeleton />
              <ConversationSkeleton />
            </>
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
                <Text style={{ color: primary, ...TYPOGRAPHY.button }}>{t('common:buttons.retry')}</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>{t('empty.title')}</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View style={styles.footer}>
              <ActivityIndicator size="small" color={theme.textMuted} />
            </View>
          ) : !hasMore && conversations.length > 0 && !isLoading ? (
            <View style={styles.footer}>
              <Text style={styles.endOfListText}>{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.surface },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingHorizontal: SPACING.md,
      paddingTop: SPACING.md,
      paddingBottom: SPACING.sm,
    },
    title: { ...TYPOGRAPHY.h2, color: theme.text },
    composeButton: {
      width: 36,
      height: 36,
      borderRadius: 18,
      justifyContent: 'center',
      alignItems: 'center',
    },
    list: { flexGrow: 1 },
    row: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: SPACING.md,
      paddingVertical: 12,
      backgroundColor: theme.surface,
    },
    rowContent: { flex: 1, marginLeft: 12 },
    rowHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: SPACING.xxs },
    userName: { ...TYPOGRAPHY.button, color: theme.text, flex: 1, marginRight: SPACING.sm },
    time: { ...TYPOGRAPHY.caption, color: theme.textMuted },
    rowFooter: { flexDirection: 'row', alignItems: 'center' },
    lastMessage: { ...TYPOGRAPHY.bodySmall, color: theme.textSecondary, flex: 1 },
    badge: {
      minWidth: 20,
      height: 20,
      borderRadius: RADIUS.md,
      justifyContent: 'center',
      alignItems: 'center',
      paddingHorizontal: 5,
      marginLeft: SPACING.sm,
    },
    badgeText: { color: '#fff', fontSize: 11, fontWeight: '600' },
    separator: { height: 1, backgroundColor: theme.borderSubtle, marginLeft: 76 },
    swipeDeleteBtn: {
      backgroundColor: '#dc2626',
      justifyContent: 'center',
      alignItems: 'center',
      width: 72,
    },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SPACING.xl },
    errorText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.error, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.textSecondary, textAlign: 'center' },
    footer: { paddingVertical: SPACING.md, alignItems: 'center' },
    endOfListText: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
  });
}
