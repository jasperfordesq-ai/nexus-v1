// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo, useCallback } from 'react';
import {
  FlatList,
  View,
  Text,
  TouchableOpacity,
  RefreshControl,
  StyleSheet,
  SafeAreaView,
  Alert,
} from 'react-native';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import {
  getNotifications,
  markAllRead,
  markRead,
  type Notification,
} from '@/lib/api/notifications';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { navigateToLink } from '@/lib/utils/navigateToLink';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';

export default function NotificationsScreen() {
  const { t } = useTranslation('notifications');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const Separator = useCallback(() => <View style={styles.separator} />, [styles]);
  const [markingAll, setMarkingAll] = useState(false);

  const { data, isLoading, error, refresh } = useApi(() => getNotifications());
  const notifications = data?.data ?? [];
  const unreadCount = notifications.filter((n) => !n.is_read).length;

  async function handleMarkAll() {
    setMarkingAll(true);
    try {
      await markAllRead();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      refresh();
    } catch {
      Alert.alert('Error', t('markError'));
    } finally {
      setMarkingAll(false);
    }
  }

  function renderItem({ item }: { item: Notification }) {
    return (
      <TouchableOpacity
        style={[styles.row, !item.is_read && styles.rowUnread]}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          void markRead(item.id).then(() => refresh());
          navigateToLink(item.link);
        }}
        activeOpacity={0.7}
      >
        <View style={styles.avatarWrap}>
          <Avatar
            uri={item.actor?.avatar_url ?? null}
            name={item.actor?.name ?? '?'}
            size={42}
          />
          <View style={[styles.categoryDot, { backgroundColor: categoryColor(item.category, theme.textMuted) }]} />
        </View>

        <View style={styles.content}>
          {item.title && <Text style={styles.title} numberOfLines={1}>{item.title}</Text>}
          <Text style={styles.message} numberOfLines={2}>{item.message}</Text>
          <Text style={styles.time}>
            {(Date.now() - new Date(item.created_at).getTime()) < 60_000
              ? t('justNow')
              : formatRelativeTime(item.created_at)}
          </Text>
        </View>

        {!item.is_read && <View style={[styles.unreadDot, { backgroundColor: primary }]} />}
      </TouchableOpacity>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <Text style={styles.heading}>{t('title')}</Text>
          {unreadCount > 0 && (
            <View style={[styles.badge, { backgroundColor: primary }]}>
              <Text style={styles.badgeText}>{unreadCount}</Text>
            </View>
          )}
        </View>
        {unreadCount > 0 && (
          <TouchableOpacity onPress={() => void handleMarkAll()} disabled={markingAll}>
            <Text style={[styles.markAll, { color: primary }]}>
              {markingAll ? t('marking') : t('markAllRead')}
            </Text>
          </TouchableOpacity>
        )}
      </View>

      <FlatList<Notification>
        data={notifications}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        ItemSeparatorComponent={Separator}
        refreshControl={
          <RefreshControl refreshing={isLoading && notifications.length > 0} onRefresh={refresh} />
        }
        ListEmptyComponent={
          isLoading ? (
            <LoadingSpinner />
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
                <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>{t('common:buttons.retry')}</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>{t('allCaughtUp')}</Text>
            </View>
          )
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function categoryColor(category: string, fallback: string): string {
  switch (category) {
    case 'message':     return '#3B82F6';
    case 'transaction': return '#10B981';
    case 'social':      return '#8B5CF6';
    case 'system':      return '#F59E0B';
    default:            return fallback;
  }
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
    headerLeft: { flexDirection: 'row', alignItems: 'center', gap: 8 },
    heading: { fontSize: 22, fontWeight: '700', color: theme.text },
    badge: {
      minWidth: 20,
      height: 20,
      borderRadius: 10,
      justifyContent: 'center',
      alignItems: 'center',
      paddingHorizontal: 5,
    },
    badgeText: { color: '#fff', fontSize: 11, fontWeight: '700' },
    markAll: { fontSize: 14, fontWeight: '600' },
    list: { flexGrow: 1 },
    row: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      paddingHorizontal: 16,
      paddingVertical: 12,
      gap: 12,
    },
    rowUnread: { backgroundColor: theme.bg },
    avatarWrap: { position: 'relative' },
    categoryDot: {
      position: 'absolute',
      bottom: 0,
      right: 0,
      width: 10,
      height: 10,
      borderRadius: 5,
      borderWidth: 1.5,
      borderColor: theme.surface,
    },
    content: { flex: 1 },
    title: { fontSize: 14, fontWeight: '700', color: theme.text, marginBottom: 2 },
    message: { fontSize: 14, color: theme.textSecondary, lineHeight: 20 },
    time: { fontSize: 12, color: theme.textMuted, marginTop: 4 },
    unreadDot: {
      width: 8,
      height: 8,
      borderRadius: 4,
      marginTop: 6,
      flexShrink: 0,
    },
    separator: { height: 1, backgroundColor: theme.borderSubtle },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 40 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyText: { color: theme.textSecondary, fontSize: 15, textAlign: 'center' },
  });
}
