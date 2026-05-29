// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback } from 'react';
import { FlatList, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Card as HeroCard, Chip, Surface } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import {
  getActivityDashboard,
  type ActivityDashboard,
  type ActivityItem,
} from '@/lib/api/activity';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

const ACTIVITY_ICONS: Record<string, IoniconName> = {
  exchange: 'swap-horizontal-outline',
  gave_hours: 'arrow-up-outline',
  received_hours: 'arrow-down-outline',
  listing: 'list-outline',
  connection: 'people-outline',
  event: 'calendar-outline',
  message: 'chatbubble-outline',
  review: 'star-outline',
  post: 'chatbox-ellipses-outline',
  comment: 'chatbubble-ellipses-outline',
};

function formatDate(value: string) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  return date.toLocaleDateString('default', { day: 'numeric', month: 'short' });
}

function emptyDashboard(): ActivityDashboard {
  return {
    timeline: [],
    hours_summary: {
      hours_given: 0,
      hours_received: 0,
      transactions_given: 0,
      transactions_received: 0,
      net_balance: 0,
    },
    connection_stats: {
      total_connections: 0,
      pending_requests: 0,
      groups_joined: 0,
    },
    engagement: {
      posts_count: 0,
      comments_count: 0,
      likes_given: 0,
      likes_received: 0,
    },
    skills_breakdown: {
      skills: [],
      offering_count: 0,
      requesting_count: 0,
    },
    monthly_hours: [],
  };
}

export default function ActivityScreen() {
  const { t } = useTranslation(['home', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { data, isLoading, error, refresh } = useApi(() => getActivityDashboard());
  const dashboard = data?.data ?? emptyDashboard();

  const renderItem = useCallback(
    ({ item }: { item: ActivityItem }) => (
      <Surface variant="secondary" className="mx-4 my-2 rounded-panel-inner p-3">
        <View className="flex-row items-start gap-3">
          <View className="size-10 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name={ACTIVITY_ICONS[item.activity_type] ?? 'pulse-outline'} size={19} color={primary} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={2}>
              {item.description}
            </Text>
            <Text className="text-xs" style={{ color: theme.textMuted }}>
              {formatDate(item.created_at)}
            </Text>
          </View>
        </View>
      </Surface>
    ),
    [primary, theme.text, theme.textMuted],
  );

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('activity.title')} backLabel={t('common:back')} fallbackHref="/(tabs)/home" />
        <FlatList<ActivityItem>
          data={dashboard.timeline}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderItem}
          refreshControl={<RefreshControl refreshing={false} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />}
          contentContainerStyle={{ paddingBottom: 40 }}
          ListHeaderComponent={
            <View className="gap-3 pb-2">
              <HeroCard variant="default" className="mx-4 overflow-hidden rounded-panel p-0">
                <View className="h-1 w-full" style={{ backgroundColor: primary }} />
                <HeroCard.Body className="gap-4 p-4">
                  <View className="flex-row items-start gap-3">
                    <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                      <Ionicons name="pulse-outline" size={25} color={primary} />
                    </View>
                    <View className="min-w-0 flex-1">
                      <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                        {t('activity.title')}
                      </Text>
                      <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                        {t('activity.subtitle')}
                      </Text>
                    </View>
                  </View>
                  <Chip size="sm" variant="secondary" className="self-start">
                    <Ionicons name="analytics-outline" size={12} color={primary} />
                    <Chip.Label>{t('activity.netBalance', { count: dashboard.hours_summary.net_balance })}</Chip.Label>
                  </Chip>
                </HeroCard.Body>
              </HeroCard>

              <View className="mx-4 flex-row flex-wrap gap-3">
                <StatTile icon="arrow-up-outline" label={t('activity.hoursGiven')} value={dashboard.hours_summary.hours_given} tone="#22c55e" />
                <StatTile icon="arrow-down-outline" label={t('activity.hoursReceived')} value={dashboard.hours_summary.hours_received} tone="#6366f1" />
                <StatTile icon="people-outline" label={t('activity.connections')} value={dashboard.connection_stats.total_connections} tone="#14b8a6" />
                <StatTile icon="people-circle-outline" label={t('activity.groupsJoined')} value={dashboard.connection_stats.groups_joined} tone="#f59e0b" />
                <StatTile icon="chatbox-ellipses-outline" label={t('activity.posts')} value={dashboard.engagement.posts_count} tone="#f43f5e" />
                <StatTile icon="construct-outline" label={t('activity.skills')} value={dashboard.skills_breakdown.skills.length} tone="#8b5cf6" />
              </View>

              {dashboard.skills_breakdown.skills.length > 0 ? (
                <HeroCard variant="default" className="mx-4 rounded-panel p-0">
                  <HeroCard.Body className="gap-3 p-4">
                    <Text className="text-lg font-bold" style={{ color: theme.text }}>
                      {t('activity.skills')}
                    </Text>
                    <View className="flex-row flex-wrap gap-2">
                      {dashboard.skills_breakdown.skills.slice(0, 8).map((skill) => (
                        <Chip key={skill.skill_name} size="sm" variant="secondary">
                          <Chip.Label>{skill.skill_name}</Chip.Label>
                        </Chip>
                      ))}
                    </View>
                  </HeroCard.Body>
                </HeroCard>
              ) : null}

              <Text className="mx-4 mt-2 text-lg font-bold" style={{ color: theme.text }}>
                {t('activity.recent')}
              </Text>
            </View>
          }
          ListEmptyComponent={
            isLoading ? (
              <View className="items-center justify-center py-14">
                <LoadingSpinner />
              </View>
            ) : (
              <View className="px-4 py-8">
                <EmptyState
                  icon={error ? 'warning-outline' : 'pulse-outline'}
                  title={error ? t('common:errors.generic') : t('activity.emptyTitle')}
                  subtitle={error ? String(error) : t('activity.emptySubtitle')}
                  actionLabel={error ? t('common:buttons.retry') : undefined}
                  onAction={error ? () => void refresh() : undefined}
                />
              </View>
            )
          }
        />
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function StatTile({
  icon,
  label,
  value,
  tone,
}: {
  icon: IoniconName;
  label: string;
  value: number;
  tone: string;
}) {
  const theme = useTheme();

  return (
    <Surface variant="secondary" className="min-w-[46%] flex-1 gap-2 rounded-panel-inner p-4">
      <View className="size-9 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(tone, 0.14) }}>
        <Ionicons name={icon} size={18} color={tone} />
      </View>
      <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
        {value}
      </Text>
      <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }} numberOfLines={2}>
        {label}
      </Text>
    </Surface>
  );
}
