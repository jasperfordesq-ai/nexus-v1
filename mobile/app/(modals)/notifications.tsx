// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useMemo, useCallback } from 'react';
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
import { useNavigation } from 'expo-router';
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
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);
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
      Alert.alert(t('common:errors.alertTitle'), t('markError'));
    } finally {
      setMarkingAll(false);
    }
  }

  function renderItem({ item }: { item: Notification }) {
    const label = item.title
      ? `${item.title}. ${item.message}`
      : item.message;

    return (
      <TouchableOpacity
        style={[styles.row, !item.is_read && styles.rowUnread]}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          void markRead(item.id).then(() => refresh()).catch(console.warn);
          navigateToLink(item.link);
        }}
        activeOpacity={0.7}
        accessibilityLabel={item.is_read ? label : t('unreadItem', { label })}
        accessibilityRole="button"
        accessibilityHint={t('itemHint')}
      >
        <View style={styles.avatarWrap}>
          <Avatar
            uri={item.actor?.avatar_url ?? null}
            name={item.actor?.name ?? '?'}
            size={42}
          />
          <View style={[styles.categoryDot, { backgroundColor: categoryColor(item.category, theme.textMuted, theme) }]} />
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
          <TouchableOpacity
            onPress={() => void handleMarkAll()}
            disabled={markingAll}
            accessibilityLabel={t('markAllRead')}
            accessibilityRole="button"
            accessibilityState={{ busy: markingAll, disabled: markingAll }}
          >
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
            <View style={styles.centered} accessibilityLabel={t('allCaughtUp')}>
              <Text style={styles.emptyTitle}>{t('allCaughtUp')}</Text>
              <Text style={styles.emptySubText}>{t('allCaughtUpSub')}</Text>
            </View>
          )
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function categoryColor(category: string, fallback: string, theme: Theme): string {
  switch (category) {
    case 'message':     return theme.info;
    case 'transaction': return theme.success;
    case 'social':      return '#8B5CF6'; // intentional purple — no equivalent token
    case 'system':      return theme.warning;
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
    emptyTitle: { color: theme.text, fontSize: 17, fontWeight: '600', textAlign: 'center', marginBottom: 8 },
    emptySubText: { color: theme.textSecondary, fontSize: 14, textAlign: 'center', lineHeight: 20 },
  });
}
