// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useCallback } from 'react';
import {
  FlatList,
  View,
  Text,
  TouchableOpacity,
  RefreshControl,
  StyleSheet,
  SafeAreaView,
} from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getConversations, type Conversation } from '@/lib/api/messages';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import { ConversationSkeleton } from '@/components/ui/Skeleton';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';

export default function MessagesScreen() {
  const { t } = useTranslation('messages');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const { data, isLoading, error, refresh } = useApi(() => getConversations());
  const Separator = useCallback(() => <View style={styles.separator} />, [styles]);

  const conversations = data?.data ?? [];

  function renderConversation({ item }: { item: Conversation }) {
    const lastMsg = item.last_message;
    return (
      <TouchableOpacity
        style={styles.row}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel={`${item.other_user.name}${lastMsg ? `, ${lastMsg.body}` : ''}`}
        onPress={() =>
          router.push({
            pathname: '/(modals)/thread',
            params: { id: String(item.id), name: item.other_user.name },
          })
        }
      >
        <Avatar
          uri={item.other_user.avatar_url}
          name={item.other_user.name}
          size={48}
        />
        <View style={styles.rowContent}>
          <View style={styles.rowHeader}>
            <Text style={styles.userName} numberOfLines={1}>
              {item.other_user.name}
            </Text>
            {lastMsg && (
              <Text style={styles.time}>
                {formatRelativeTime(lastMsg.created_at, true)}
              </Text>
            )}
          </View>
          <View style={styles.rowFooter}>
            <Text style={styles.lastMessage} numberOfLines={1}>
              {lastMsg
                ? `${lastMsg.is_own ? `${t('thread.you')}: ` : ''}${lastMsg.body}`
                : t('thread.noMessages')}
            </Text>
            {item.unread_count > 0 && (
              <View style={[styles.badge, { backgroundColor: primary }]}>
                <Text style={styles.badgeText}>{item.unread_count}</Text>
              </View>
            )}
          </View>
        </View>
      </TouchableOpacity>
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
          <Ionicons name="create-outline" size={18} color="#fff" />
        </TouchableOpacity>
      </View>

      <FlatList<Conversation>
        data={conversations}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderConversation}
        ItemSeparatorComponent={Separator}
        refreshControl={
          <RefreshControl refreshing={isLoading && conversations.length > 0} onRefresh={refresh} />
        }
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
                <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>{t('common:buttons.retry')}</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>{t('empty.title')}</Text>
            </View>
          )
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
      paddingHorizontal: 16,
      paddingTop: 16,
      paddingBottom: 8,
    },
    title: { fontSize: 22, fontWeight: '700', color: theme.text },
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
      paddingHorizontal: 16,
      paddingVertical: 12,
      backgroundColor: theme.surface,
    },
    rowContent: { flex: 1, marginLeft: 12 },
    rowHeader: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 2 },
    userName: { fontSize: 15, fontWeight: '600', color: theme.text, flex: 1, marginRight: 8 },
    time: { fontSize: 12, color: theme.textMuted },
    rowFooter: { flexDirection: 'row', alignItems: 'center' },
    lastMessage: { fontSize: 13, color: theme.textSecondary, flex: 1 },
    badge: {
      minWidth: 20,
      height: 20,
      borderRadius: 10,
      justifyContent: 'center',
      alignItems: 'center',
      paddingHorizontal: 5,
      marginLeft: 8,
    },
    badgeText: { color: '#fff', fontSize: 11, fontWeight: '600' },
    separator: { height: 1, backgroundColor: theme.borderSubtle, marginLeft: 76 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyText: { color: theme.textSecondary, fontSize: 14, textAlign: 'center' },
  });
}
