// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
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
import { router } from 'expo-router';

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

export default function NotificationsScreen() {
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = makeStyles(theme);
  const [markingAll, setMarkingAll] = useState(false);

  const { data, isLoading, error, refresh } = useApi(() => getNotifications());
  const notifications = data?.data ?? [];
  const unreadCount = notifications.filter((n) => !n.is_read).length;

  async function handleMarkAll() {
    setMarkingAll(true);
    try {
      await markAllRead();
      refresh();
    } catch {
      Alert.alert('Error', 'Could not mark notifications as read.');
    } finally {
      setMarkingAll(false);
    }
  }

  function renderItem({ item }: { item: Notification }) {
    return (
      <TouchableOpacity
        style={[styles.row, !item.is_read && styles.rowUnread]}
        onPress={() => {
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
          <Text style={styles.time}>{formatRelativeTime(item.created_at)}</Text>
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
          <Text style={styles.heading}>Notifications</Text>
          {unreadCount > 0 && (
            <View style={[styles.badge, { backgroundColor: primary }]}>
              <Text style={styles.badgeText}>{unreadCount}</Text>
            </View>
          )}
        </View>
        {unreadCount > 0 && (
          <TouchableOpacity onPress={handleMarkAll} disabled={markingAll}>
            <Text style={[styles.markAll, { color: primary }]}>
              {markingAll ? 'Marking…' : 'Mark all read'}
            </Text>
          </TouchableOpacity>
        )}
      </View>

      <FlatList<Notification>
        data={notifications}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        ItemSeparatorComponent={() => <View style={styles.separator} />}
        refreshControl={
          <RefreshControl refreshing={isLoading && notifications.length > 0} onRefresh={refresh} />
        }
        ListEmptyComponent={
          isLoading ? (
            <LoadingSpinner />
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>You&apos;re all caught up!</Text>
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

/**
 * Maps a web-format deep-link (e.g. /exchanges/123) to the appropriate
 * mobile screen and navigates to it.
 */
function navigateToLink(link: string | null): void {
  if (!link) return;
  const match = link.match(/^\/([^/]+)(?:\/(\d+))?/);
  if (!match) return;
  const [, section, id] = match;
  switch (section) {
    case 'exchanges':
      if (id) router.push({ pathname: '/(modals)/exchange-detail', params: { id } });
      break;
    case 'events':
      if (id) router.push({ pathname: '/(modals)/event-detail', params: { id } });
      break;
    case 'members':
      if (id) router.push({ pathname: '/(modals)/member-profile', params: { id } });
      break;
    case 'messages':
      if (id) router.push({ pathname: '/(modals)/thread', params: { id } });
      else router.push('/(tabs)/messages');
      break;
    default:
      break;
  }
}

function formatRelativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diff / 60_000);
  if (minutes < 1) return 'just now';
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  return new Date(iso).toLocaleDateString();
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
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center' },
    emptyText: { color: theme.textSecondary, fontSize: 15, textAlign: 'center' },
  });
}
