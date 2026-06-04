// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState } from 'react';
import { FlatList, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Image } from 'expo-image';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Button as HeroButton, Card as HeroCard, Chip, Separator, Spinner, Surface, Tabs } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getEvents, type Event, type EventsResponse } from '@/lib/api/events';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import { EventCardSkeleton } from '@/components/ui/Skeleton';
import NativePressable from '@/components/ui/NativePressable';

function extractEventsPage(r: EventsResponse) {
  return {
    items: r.data,
    cursor: r.meta.cursor,
    hasMore: r.meta.has_more,
  };
}

type EventTab = 'upcoming' | 'past';
type TFunction = (key: string, options?: Record<string, unknown>) => string;

export default function EventsScreen() {
  const { t } = useTranslation(['events', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [when, setWhen] = useState<EventTab>('upcoming');

  const fetcher = useCallback(
    (cursor: string | null) => getEvents(when, cursor),
    [when],
  );

  const { items, isLoading, isLoadingMore, hasMore, refresh, loadMore, error } =
    usePaginatedApi<Event, EventsResponse>(fetcher, extractEventsPage, [when]);

  function handleTabChange(tab: EventTab) {
    if (tab !== when) {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      setWhen(tab);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      {error ? (
        <View className="flex-1">
          <EventsHeader t={t} primary={primary} theme={theme} when={when} onTabChange={handleTabChange} count={items.length} isLoading={isLoading} />
          <HeroCard variant="secondary" className="mx-4 my-8">
            <HeroCard.Body className="items-center gap-4">
              <Ionicons name="warning-outline" size={30} color={primary} />
              <Text className="text-center text-sm leading-5" style={{ color: theme.textSecondary }}>{t('loadError')}</Text>
              <HeroButton variant="primary" onPress={() => void refresh()} style={{ backgroundColor: primary }}>
                <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
              </HeroButton>
            </HeroCard.Body>
          </HeroCard>
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
          ListHeaderComponent={
            <EventsHeader t={t} primary={primary} theme={theme} when={when} onTabChange={handleTabChange} count={items.length} isLoading={isLoading} />
          }
          ListEmptyComponent={
            isLoading ? (
              <><EventCardSkeleton /><EventCardSkeleton /><EventCardSkeleton /></>
            ) : (
              <HeroCard variant="secondary" className="mx-4 my-8">
                <HeroCard.Body className="items-center gap-3">
                  <Ionicons name="calendar-outline" size={34} color={primary} />
                  <Text className="text-center text-[17px] font-semibold" style={{ color: theme.text }}>
                    {t('noEvents', { when: t(when).toLowerCase() })}
                  </Text>
                  <Text className="text-center text-sm leading-5" style={{ color: theme.textSecondary }}>
                    {when === 'upcoming' ? t('emptyUpcoming') : t('emptyPast')}
                  </Text>
                </HeroCard.Body>
              </HeroCard>
            )
          }
          ListFooterComponent={
            isLoadingMore ? (
              <View className="py-4 items-center"><Spinner size="sm" /></View>
            ) : !hasMore && items.length > 0 && !isLoading ? (
              <View className="py-4 items-center">
                <Text className="text-xs" style={{ color: theme.textSecondary }}>{t('common:endOfList')}</Text>
              </View>
            ) : null
          }
          contentContainerStyle={items.length === 0 ? { flexGrow: 1, paddingBottom: 112 } : { paddingBottom: 112 }}
        />
      )}
    </SafeAreaView>
  );
}

function EventsHeader({
  t,
  primary,
  theme,
  when,
  onTabChange,
  count,
  isLoading,
}: {
  t: TFunction;
  primary: string;
  theme: Theme;
  when: EventTab;
  onTabChange: (tab: EventTab) => void;
  count: number;
  isLoading: boolean;
}) {
  return (
    <View className="gap-3 pb-2">
      <HeroCard variant="default" className="mx-4 mt-4 overflow-hidden">
        <View className="h-1 w-full" style={{ backgroundColor: '#F59E0B' }} />
        <HeroCard.Body className="gap-4 px-4 py-4">
          <View className="flex-row items-start justify-between gap-4">
            <View className="min-w-0 flex-1">
              <View className="mb-2 flex-row items-center gap-2">
                <View className="h-8 w-8 items-center justify-center rounded-full" style={{ backgroundColor: 'rgba(245, 158, 11, 0.16)' }}>
                  <Ionicons name="calendar-outline" size={18} color="#F59E0B" />
                </View>
                <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
                  {t('heroEyebrow')}
                </Text>
              </View>
              <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                {t('title')}
              </Text>
              <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                {t('subtitle')}
              </Text>
            </View>
            <HeroButton
              size="sm"
              variant="secondary"
              isIconOnly
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/new-event' as Href);
              }}
              accessibilityLabel={t('createEvent')}
            >
              <Ionicons name="add-outline" size={18} color={primary} />
            </HeroButton>
          </View>
        </HeroCard.Body>
      </HeroCard>

      <Surface variant="default" className="mx-4 gap-3 rounded-panel-inner p-3">
        <View className="flex-row items-center justify-between gap-3">
          <View className="min-w-0 flex-1">
            <Text className="text-base font-semibold" style={{ color: theme.text }}>
              {t('browse')}
            </Text>
            <Text className="mt-0.5 text-sm" style={{ color: theme.textSecondary }} numberOfLines={2}>
              {t('filtersIntro')}
            </Text>
          </View>
          <Chip size="sm" variant="soft" color="warning">
            <Ionicons name="calendar-outline" size={12} color="#F59E0B" />
            <Chip.Label>{isLoading ? t('resultsLoading') : t('resultsCount', { count })}</Chip.Label>
          </Chip>
        </View>

        <Tabs value={when} onValueChange={(value) => onTabChange(value as EventTab)} variant="secondary">
          <Tabs.List>
            <Tabs.Indicator />
            <Tabs.Trigger value="upcoming">
              <Ionicons name="sparkles-outline" size={15} color={when === 'upcoming' ? primary : theme.textMuted} />
              <Tabs.Label>{t('upcoming')}</Tabs.Label>
            </Tabs.Trigger>
            <Tabs.Trigger value="past">
              <Ionicons name="archive-outline" size={15} color={when === 'past' ? primary : theme.textMuted} />
              <Tabs.Label>{t('past')}</Tabs.Label>
            </Tabs.Trigger>
          </Tabs.List>
        </Tabs>
      </Surface>
    </View>
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
  t: TFunction;
  onPress: () => void;
}) {
  const theme = useTheme();
  const start = event.start_date ? new Date(event.start_date) : null;
  const isValidDate = start && !isNaN(start.getTime());
  const month = isValidDate ? start.toLocaleString('default', { month: 'short' }) : '-';
  const day = isValidDate ? String(start.getDate()) : '-';
  const weekday = isValidDate ? start.toLocaleString('default', { weekday: 'short' }) : '';
  const time = isValidDate ? start.toLocaleTimeString('default', { hour: '2-digit', minute: '2-digit' }) : '-';
  const coverImage = resolveImageUrl(event.cover_image);
  const accent = event.category?.color ?? '#F59E0B';
  const isCancelled = event.status === 'cancelled';

  return (
    <NativePressable
      className="mx-4 my-2"
      onPress={() => {
        onPress();
      }}
      accessibilityLabel={event.title ?? ''}
      feedback="highlight"
    >
      <HeroCard variant="default" className="w-full overflow-hidden">
        <View className="h-1 w-full" style={{ backgroundColor: accent }} />
        {coverImage ? (
          <Image source={{ uri: coverImage }} style={{ width: '100%', height: 138 }} contentFit="cover" />
        ) : null}
        <HeroCard.Body className="gap-3 px-4 py-4">
          <View className="flex-row gap-3">
            <View
              className="w-14 items-center justify-center rounded-xl border px-2 py-2"
              style={{ backgroundColor: withAlpha(accent, 0.12), borderColor: withAlpha(accent, 0.28) }}
            >
              <Text className="text-[11px] font-semibold uppercase" style={{ color: accent }}>{month}</Text>
              <Text className="text-2xl font-bold leading-7" style={{ color: theme.text }}>{day}</Text>
              {weekday ? <Text className="text-[10px]" style={{ color: theme.textSecondary }}>{weekday}</Text> : null}
            </View>

            <View className="min-w-0 flex-1 gap-2">
              <View className="flex-row flex-wrap items-center gap-2">
                {event.category ? (
                  <Chip size="sm" variant="soft" color="warning">
                    <Chip.Label>{event.category.name}</Chip.Label>
                  </Chip>
                ) : null}
                {isCancelled ? (
                  <Chip size="sm" variant="soft" color="danger">
                    <Chip.Label>{t('cancelled')}</Chip.Label>
                  </Chip>
                ) : event.is_full ? (
                  <Chip size="sm" variant="soft" color="danger">
                    <Chip.Label>{t('full')}</Chip.Label>
                  </Chip>
                ) : event.user_rsvp === 'going' ? (
                  <Chip size="sm" variant="soft" color="success">
                    <Chip.Label>{t('going')}</Chip.Label>
                  </Chip>
                ) : null}
              </View>
              <Text className="text-base font-bold leading-6" style={{ color: theme.text }} numberOfLines={2}>
                {event.title}
              </Text>
              {event.description ? (
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>
                  {event.description}
                </Text>
              ) : null}
            </View>
          </View>

          <View className="flex-row flex-wrap gap-2">
            <Surface variant="secondary" className="flex-row items-center gap-1 rounded-full px-3 py-1.5">
              <Ionicons name="time-outline" size={14} color={theme.textMuted} />
              <Text className="text-xs" style={{ color: theme.textSecondary }}>{time}</Text>
            </Surface>
            {event.location || event.is_online ? (
              <Surface variant="secondary" className="flex-row max-w-full items-center gap-1 rounded-full px-3 py-1.5">
                <Ionicons name={event.is_online ? 'videocam-outline' : 'location-outline'} size={14} color={theme.textMuted} />
                <Text className="max-w-[220px] text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                  {event.is_online ? t('online') : event.location}
                </Text>
              </Surface>
            ) : null}
          </View>
        </HeroCard.Body>

        <View className="mx-4">
          <Separator />
        </View>
        <HeroCard.Footer className="flex-row items-center justify-between gap-3 px-4 py-3">
          <View className="flex-row items-center gap-2">
            <Ionicons name="people-outline" size={16} color={theme.textMuted} />
            <Text className="text-sm" style={{ color: theme.textSecondary }}>
              {t('attendees', { going: event.rsvp_counts?.going ?? event.attendees_count ?? 0, interested: event.rsvp_counts?.interested ?? 0 })}
            </Text>
          </View>
          <Ionicons name="arrow-forward" size={17} color={primary} />
        </HeroCard.Footer>
      </HeroCard>
    </NativePressable>
  );
}
