// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import {
  FlatList,
  View,
  Text,
  Pressable,
  RefreshControl,
  Alert,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from '@/lib/haptics';
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
import Badge from '@/components/ui/Badge';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { navigateToLink } from '@/lib/utils/navigateToLink';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function NotificationsScreen() {
  const { t } = useTranslation('notifications');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  // Keep useTheme for categoryDot backgroundColor (categoryColor uses theme tokens)
  // and categoryDot borderColor (theme.surface) — these can't be className'd.
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const Separator = useCallback(() => <View className="h-px bg-border/50" />, []);
  const [markingAll, setMarkingAll] = useState(false);

  const { data, isLoading, error, refresh } = useApi(() => getNotifications());
  const notifications = data?.data ?? [];
  const unreadCount = notifications.filter((n) => !n.is_read).length;

  function handleMarkAll() {
    Alert.alert(
      t('common:buttons.confirm'),
      t('markAllConfirm'),
      [
        { text: t('common:no'), style: 'cancel' },
        {
          text: t('common:yes'),
          onPress: async () => {
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
          },
        },
      ],
    );
  }

  function renderItem({ item }: { item: Notification }) {
    const label = item.title
      ? `${item.title}. ${item.message}`
      : item.message;

    return (
      <Pressable
        className={`flex-row items-start px-4 py-3 gap-3${!item.is_read ? ' bg-background' : ''}`}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          void markRead(item.id).then(() => refresh()).catch(console.warn);
          navigateToLink(item.link ?? null);
        }}
        accessibilityLabel={item.is_read ? label : t('unreadItem', { label })}
        accessibilityRole="button"
        accessibilityHint={t('itemHint')}
      >
        <View className="relative">
          <Avatar
            uri={item.actor?.avatar_url ?? null}
            name={item.actor?.name ?? '?'}
            size={42}
          />
          <View
            className="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-[1.5px]"
            style={{
              backgroundColor: categoryColor(item.category, theme.textMuted, theme),
              borderColor: theme.surface,
            }}
          />
        </View>

        <View className="flex-1">
          {item.title && <Text className="text-sm font-bold text-foreground mb-0.5" numberOfLines={1}>{item.title}</Text>}
          <Text className="text-sm text-muted-foreground leading-5" numberOfLines={2}>{item.message}</Text>
          <View className="flex-row items-center gap-2 mt-1">
            <Text className="text-[12px] text-muted-foreground">
              {(Date.now() - new Date(item.created_at).getTime()) < 60_000
                ? t('justNow')
                : formatRelativeTime(item.created_at)}
            </Text>
            {item.category ? (
              <Badge
                label={categoryLabel(item.category, t)}
                color={categoryColor(item.category, theme.textMuted, theme)}
                size="sm"
                variant="outline"
              />
            ) : null}
          </View>
        </View>

        {!item.is_read && (
          <View
            className="w-2 h-2 rounded-full mt-1.5 shrink-0"
            style={{ backgroundColor: primary }}
          />
        )}
      </Pressable>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-surface" edges={['bottom']}>
      {/* Header */}
      <View className="flex-row items-center justify-between px-4 pt-4 pb-2">
        <View className="flex-row items-center gap-2">
          <Text className="text-xl font-bold text-foreground">{t('title')}</Text>
          {unreadCount > 0 && (
            <View
              className="min-w-5 h-5 rounded-md justify-center items-center px-1.5"
              style={{ backgroundColor: primary }}
            >
              <Text className="text-white text-[11px] font-bold">{unreadCount}</Text>
            </View>
          )}
        </View>
        {unreadCount > 0 && (
          <Pressable
            onPress={() => void handleMarkAll()}
            disabled={markingAll}
            accessibilityLabel={t('markAllRead')}
            accessibilityRole="button"
            accessibilityState={{ busy: markingAll, disabled: markingAll }}
          >
            <Text className="text-sm font-semibold" style={{ color: primary }}>
              {markingAll ? t('marking') : t('markAllRead')}
            </Text>
          </Pressable>
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
            <View className="flex-1 justify-center items-center p-10">
              <Text className="text-sm text-danger text-center mb-3">{error}</Text>
              <Pressable onPress={() => void refresh()} className="px-5 py-2">
                <Text className="font-semibold text-[15px]" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <EmptyState
              icon="notifications-off-outline"
              title={t('allCaughtUp')}
              subtitle={t('allCaughtUpSub')}
            />
          )
        }
        contentContainerStyle={{ flexGrow: 1 }}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function categoryLabel(category: string, t: (key: string) => string): string {
  const labels: Record<string, string> = {
    message: t('category.message'),
    transaction: t('category.transaction'),
    social: t('category.social'),
    system: t('category.system'),
    event: t('category.event'),
    group: t('category.group'),
    listing: t('category.listing'),
    connection: t('category.connection'),
    mention: t('category.mention'),
  };
  return labels[category] ?? category;
}

function categoryColor(category: string | undefined | null, fallback: string, theme: Theme): string {
  switch (category) {
    case 'message':     return theme.info;
    case 'transaction': return theme.success;
    case 'social':      return '#8B5CF6'; // intentional purple — no equivalent token
    case 'system':      return theme.warning;
    case 'event':       return '#F59E0B';
    case 'group':       return '#06B6D4';
    case 'listing':     return '#10B981';
    case 'connection':  return '#EC4899';
    case 'mention':     return '#6366F1';
    default:            return fallback;
  }
}
