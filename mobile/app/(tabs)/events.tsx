// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { FlatList, Pressable, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Spinner } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getEvents, type Event, type EventsResponse } from '@/lib/api/events';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { withAlpha } from '@/lib/utils/color';
import EmptyState from '@/components/ui/EmptyState';
import { EventCardSkeleton } from '@/components/ui/Skeleton';

function extractEventsPage(r: EventsResponse) {
  return {
    items: r.data,
    cursor: r.meta.cursor,
    hasMore: r.meta.has_more,
  };
}

export default function EventsScreen() {
  const { t } = useTranslation('events');
  const primary = usePrimaryColor();
  const [when, setWhen] = useState<'upcoming' | 'past'>('upcoming');

  const fetcher = useCallback(
    (cursor: string | null) => getEvents(when, cursor),
    [when],
  );

  const { items, isLoading, isLoadingMore, hasMore, refresh, loadMore, error } =
    usePaginatedApi<Event, EventsResponse>(fetcher, extractEventsPage, [when]);

  function handleTabChange(tab: 'upcoming' | 'past') {
    if (tab !== when) {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      setWhen(tab);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      {/* Segment tabs */}
      <View className="flex-row bg-surface border-b border-border">
        {(['upcoming', 'past'] as const).map((tab) => (
          <Pressable
            key={tab}
            className="flex-1 py-3.5 items-center"
            style={when === tab ? { borderBottomWidth: 2, borderBottomColor: primary } : undefined}
            onPress={() => handleTabChange(tab)}
            accessibilityRole="tab"
            accessibilityState={{ selected: when === tab }}
          >
            <Text
              className="text-sm"
              style={when === tab ? { color: primary, fontWeight: '700' } : { color: '#6b7280' }}
            >
              {t(tab)}
            </Text>
          </Pressable>
        ))}
      </View>

      {error ? (
        <View className="flex-1 items-center justify-center p-8">
          <Text className="text-muted-foreground text-sm text-center mb-3">{t('loadError')}</Text>
          <Pressable onPress={() => void refresh()} className="px-5 py-2.5">
            <Text className="font-semibold" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
          </Pressable>
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <EventCard
              event={item}
              primary={primary}
              t={t}
              onPress={() =>
                router.push({ pathname: '/(modals)/event-detail', params: { id: String(item.id) } })
              }
            />
          )}
          refreshControl={
            <RefreshControl
              refreshing={isLoading && items.length > 0}
              onRefresh={() => void refresh()}
              tintColor={primary}
              colors={[primary]}
            />
          }
          onEndReached={() => { if (hasMore) void loadMore(); }}
          onEndReachedThreshold={0.4}
          ListEmptyComponent={
            isLoading ? (
              <><EventCardSkeleton /><EventCardSkeleton /><EventCardSkeleton /></>
            ) : (
              <EmptyState
                icon="calendar-outline"
                title={t('noEvents', { when: t(when).toLowerCase() })}
              />
            )
          }
          ListFooterComponent={
            isLoadingMore ? (
              <View className="py-4 items-center"><Spinner size="sm" /></View>
            ) : !hasMore && items.length > 0 && !isLoading ? (
              <View className="py-4 items-center">
                <Text className="text-xs text-muted-foreground">{t('common:endOfList')}</Text>
              </View>
            ) : null
          }
          contentContainerStyle={items.length === 0 ? { flex: 1 } : { paddingBottom: 24 }}
        />
      )}
    </SafeAreaView>
  );
}

function EventCard({
  event,
  primary,
  t,
  onPress,
}: {
  event: Event;
  primary: string;
  t: (key: string, options?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  const start = event.start_date ? new Date(event.start_date) : null;
  const isValidDate = start && !isNaN(start.getTime());
  const month = isValidDate ? start.toLocaleString('default', { month: 'short' }) : '—';
  const day = isValidDate ? start.getDate() : '—';
  const time = isValidDate ? start.toLocaleTimeString('default', { hour: '2-digit', minute: '2-digit' }) : '—';

  return (
    <Pressable
      className="flex-row bg-surface mx-4 mt-3 rounded-xl p-3.5 gap-3 border border-border"
      onPress={() => {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        onPress();
      }}
      accessibilityRole="button"
      accessibilityLabel={event.title ?? ''}
    >
      {/* Date badge */}
      <View
        className="w-12 h-[52px] rounded-lg items-center justify-center"
        style={{ backgroundColor: withAlpha(primary, 0.08) }}
      >
        <Text className="text-[11px] font-semibold uppercase" style={{ color: primary }}>{month}</Text>
        <Text className="text-2xl font-bold leading-7" style={{ color: primary }}>{day}</Text>
      </View>

      <View className="flex-1 gap-1">
        <Text className="font-bold text-foreground mb-0.5" numberOfLines={2}>{event.title}</Text>

        <View className="flex-row items-center gap-1">
          <Ionicons name="time-outline" size={13} className="text-muted-foreground" />
          <Text className="text-[12px] text-muted-foreground flex-1">{time}</Text>
        </View>

        {event.location ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name={event.is_online ? 'videocam-outline' : 'location-outline'} size={13} className="text-muted-foreground" />
            <Text className="text-[12px] text-muted-foreground flex-1" numberOfLines={1}>
              {event.is_online ? t('online') : event.location}
            </Text>
          </View>
        ) : event.is_online ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name="videocam-outline" size={13} className="text-muted-foreground" />
            <Text className="text-[12px] text-muted-foreground">{t('online')}</Text>
          </View>
        ) : null}

        {/* RSVP + category row */}
        <View className="flex-row items-center gap-2 mt-1 flex-wrap">
          <View className="flex-row items-center gap-0.5">
            <Ionicons name="people-outline" size={12} className="text-muted-foreground" />
            <Text className="text-[11px] text-muted-foreground">{t('goingCount', { count: event.rsvp_counts?.going ?? 0 })}</Text>
          </View>

          {event.category ? (
            <View
              className="rounded px-1.5 py-0.5"
              style={{ backgroundColor: withAlpha(event.category.color ?? primary, 0.13) }}
            >
              <Text className="text-[11px] font-semibold" style={{ color: event.category.color ?? primary }}>
                {event.category.name}
              </Text>
            </View>
          ) : null}

          {event.user_rsvp === 'going' ? (
            <View
              className="rounded px-1.5 py-0.5"
              style={{ backgroundColor: withAlpha(primary, 0.13) }}
            >
              <Text className="text-[11px] font-semibold" style={{ color: primary }}>{t('going')}</Text>
            </View>
          ) : null}
        </View>
      </View>

      <Ionicons name="chevron-forward" size={16} className="text-muted-foreground self-center" />
    </Pressable>
  );
}
