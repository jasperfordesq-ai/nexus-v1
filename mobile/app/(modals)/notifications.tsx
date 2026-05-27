// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import {
  Alert,
  FlatList,
  Pressable,
  RefreshControl,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
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
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { navigateToLink } from '@/lib/utils/navigateToLink';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function NotificationsScreen() {
  const { t } = useTranslation('notifications');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [markingAll, setMarkingAll] = useState(false);

  const { data, isLoading, error, refresh } = useApi(() => getNotifications());
  const notifications = data?.data ?? [];
  const unreadCount = notifications.filter((n) => !n.is_read).length;

  function handleMarkAll() {
    Alert.alert(
      t('common:buttons.confirm'),
      t('confirmMarkAllRead'),
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

  function handleNotificationPress(item: Notification) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    void markRead(item.id).then(() => refresh()).catch(console.warn);
    navigateToLink(item.link ?? null);
  }

  function renderHeader() {
    return (
      <View className="px-4 pb-3">
        <HeroCard className="overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                <Ionicons name="notifications-outline" size={24} color={primary} />
              </View>
              <View className="min-w-0 flex-1 gap-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
                  {t('eyebrow')}
                </Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>
                  {t('title')}
                </Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
                  {unreadCount > 0 ? t('unreadSummary', { count: unreadCount }) : t('allCaughtUpSub')}
                </Text>
              </View>
              {unreadCount > 0 ? (
                <Chip size="sm" variant="secondary">
                  <Chip.Label>{t('unreadCount', { count: unreadCount })}</Chip.Label>
                </Chip>
              ) : null}
            </View>

            {unreadCount > 0 ? (
              <HeroButton
                variant="primary"
                style={{ backgroundColor: primary }}
                onPress={() => void handleMarkAll()}
                isDisabled={markingAll}
                accessibilityLabel={t('markAllRead')}
              >
                <Ionicons name="checkmark-done-outline" size={17} color="#fff" />
                <HeroButton.Label>{markingAll ? t('marking') : t('markAllRead')}</HeroButton.Label>
              </HeroButton>
            ) : null}
          </HeroCard.Body>
        </HeroCard>
      </View>
    );
  }

  function renderItem({ item }: { item: Notification }) {
    const label = item.title ? `${item.title}. ${item.message}` : item.message;
    const categoryTint = categoryColor(item.category, theme.textMuted, theme);

    return (
      <Pressable
        className="mx-4 mb-3"
        onPress={() => handleNotificationPress(item)}
        accessibilityLabel={item.is_read ? label : t('unreadItem', { label })}
        accessibilityRole="button"
        accessibilityHint={t('itemHint')}
      >
        <HeroCard className={`overflow-hidden rounded-panel p-0 ${!item.is_read ? 'border border-primary/30' : ''}`}>
          {!item.is_read ? <View className="h-1.5" style={{ backgroundColor: primary }} /> : null}
          <HeroCard.Body className="gap-3 p-4">
            <View className="flex-row items-start gap-3">
              <View className="relative">
                <Avatar uri={item.actor?.avatar_url ?? null} name={item.actor?.name ?? '?'} size={44} />
                <View
                  className="absolute bottom-0 right-0 size-3 rounded-full border-[1.5px]"
                  style={{ backgroundColor: categoryTint, borderColor: theme.surface }}
                />
              </View>

              <View className="min-w-0 flex-1 gap-2">
                <View className="flex-row items-start gap-2">
                  <View className="min-w-0 flex-1">
                    {item.title ? (
                      <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>
                        {item.title}
                      </Text>
                    ) : null}
                    <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
                      {item.message}
                    </Text>
                  </View>
                  {!item.is_read ? (
                    <View className="mt-1 size-2.5 rounded-full" style={{ backgroundColor: primary }} />
                  ) : null}
                </View>

                <View className="flex-row flex-wrap items-center gap-2">
                  <Chip size="sm" variant="secondary">
                    <Ionicons name={categoryIcon(item.category)} size={12} color={categoryTint} />
                    <Chip.Label>{categoryLabel(item.category, t)}</Chip.Label>
                  </Chip>
                  <Chip size="sm" variant="secondary">
                    <Ionicons name="time-outline" size={12} color={theme.textSecondary} />
                    <Chip.Label>
                      {(Date.now() - new Date(item.created_at).getTime()) < 60_000
                        ? t('justNow')
                        : formatRelativeTime(item.created_at)}
                    </Chip.Label>
                  </Chip>
                </View>
              </View>
            </View>
          </HeroCard.Body>
        </HeroCard>
      </Pressable>
    );
  }

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />

        <FlatList<Notification>
          data={notifications}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderItem}
          ListHeaderComponent={renderHeader}
          refreshControl={
            <RefreshControl refreshing={isLoading && notifications.length > 0} onRefresh={refresh} />
          }
          ListEmptyComponent={
            isLoading ? (
              <LoadingSpinner />
            ) : error ? (
              <Surface variant="secondary" className="mx-4 rounded-panel p-6">
                <View className="items-center gap-3">
                  <Ionicons name="warning-outline" size={34} color={theme.error} />
                  <Text className="text-center text-sm" style={{ color: theme.text }}>{error}</Text>
                  <HeroButton variant="secondary" onPress={() => void refresh()}>
                    <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
                  </HeroButton>
                </View>
              </Surface>
            ) : (
              <EmptyState
                icon="notifications-off-outline"
                title={t('allCaughtUp')}
                subtitle={t('allCaughtUpSub')}
              />
            )
          }
          contentContainerStyle={{ flexGrow: 1, paddingBottom: 24 }}
        />
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function categoryLabel(category: string | undefined | null, t: (key: string) => string): string {
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
    other: t('category.other'),
  };
  return category ? labels[category] ?? category : labels.other;
}

function categoryIcon(category: string | undefined | null): React.ComponentProps<typeof Ionicons>['name'] {
  switch (category) {
    case 'message': return 'chatbubble-outline';
    case 'transaction': return 'swap-horizontal-outline';
    case 'social': return 'heart-outline';
    case 'system': return 'settings-outline';
    case 'event': return 'calendar-outline';
    case 'group': return 'people-outline';
    case 'listing': return 'pricetag-outline';
    case 'connection': return 'person-add-outline';
    case 'mention': return 'at-outline';
    default: return 'notifications-outline';
  }
}

function categoryColor(category: string | undefined | null, fallback: string, theme: Theme): string {
  const info = theme.info ?? '#3b82f6';
  const success = theme.success ?? '#22c55e';
  const warning = theme.warning ?? '#f59e0b';
  switch (category) {
    case 'message': return info;
    case 'transaction': return success;
    case 'social': return '#8B5CF6';
    case 'system': return warning;
    case 'event': return '#F59E0B';
    case 'group': return '#06B6D4';
    case 'listing': return '#10B981';
    case 'connection': return '#EC4899';
    case 'mention': return '#6366F1';
    default: return fallback;
  }
}
