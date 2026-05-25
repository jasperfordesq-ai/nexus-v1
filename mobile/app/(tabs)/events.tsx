// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback, useMemo } from 'react';
import {
  ActivityIndicator,
  View,
  Text,
  FlatList,
  StyleSheet,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getEvents, type Event, type EventsResponse } from '@/lib/api/events';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import EmptyState from '@/components/ui/EmptyState';
import { EventCardSkeleton } from '@/components/ui/Skeleton';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

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
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const [when, setWhen] = useState<'upcoming' | 'past'>('upcoming');

  const fetcher = useCallback(
    (cursor: string | null) => getEvents(when, cursor),
    [when],
  );

  const {
    items,
    isLoading,
    isLoadingMore,
    hasMore,
    refresh,
    loadMore,
    error,
  } = usePaginatedApi<Event, EventsResponse>(fetcher, extractEventsPage, [when]);

  function handleTabChange(tab: 'upcoming' | 'past') {
    if (tab !== when) {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      setWhen(tab);
    }
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Segment tabs */}
      <View style={styles.tabs}>
        {(['upcoming', 'past'] as const).map((tab) => (
          <TouchableOpacity
            key={tab}
            style={[styles.tab, when === tab && { borderBottomColor: primary, borderBottomWidth: 2 }]}
            onPress={() => handleTabChange(tab)}
            activeOpacity={0.8}
            accessibilityRole="tab"
            accessibilityState={{ selected: when === tab }}
          >
            <Text style={[styles.tabText, when === tab && { color: primary, fontWeight: '700' }]}>
              {t(tab)}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {error ? (
        <View style={styles.center}>
          <Text style={styles.errorText}>{t('loadError')}</Text>
          <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
            <Text style={{ color: primary }}>{t('common:buttons.retry')}</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <FlatList
          data={items}
          keyExtractor={(item) => String(item.id)}
          renderItem={({ item }) => (
            <EventCard
              event={item}
              primary={primary}
              theme={theme}
              t={t}
              cardStyles={styles}
              onPress={() =>
                router.push({ pathname: '/(modals)/event-detail', params: { id: String(item.id) } })
              }
            />
          )}
          refreshControl={
            <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />
          }
          onEndReached={() => { if (hasMore) void loadMore(); }}
          onEndReachedThreshold={0.4}
          ListEmptyComponent={
            isLoading ? (
              <>
                <EventCardSkeleton />
                <EventCardSkeleton />
                <EventCardSkeleton />
              </>
            ) : (
              <EmptyState
                icon="calendar-outline"
                title={t('noEvents', { when: t(when).toLowerCase() })}
              />
            )
          }
          ListFooterComponent={
            isLoadingMore ? (
              <View style={styles.footer}>
                <ActivityIndicator size="small" color={theme.textMuted} />
              </View>
            ) : !hasMore && items.length > 0 && !isLoading ? (
              <View style={styles.footer}>
                <Text style={styles.endOfListText}>{t('common:endOfList')}</Text>
              </View>
            ) : null
          }
          contentContainerStyle={items.length === 0 ? { flex: 1 } : { paddingBottom: 24 }}
        />
      )}
    </SafeAreaView>
  );
}

type Styles = ReturnType<typeof makeStyles>;

function EventCard({
  event,
  primary,
  theme,
  t,
  cardStyles,
  onPress,
}: {
  event: Event;
  primary: string;
  theme: Theme;
  t: (key: string, options?: Record<string, unknown>) => string;
  cardStyles: Styles;
  onPress: () => void;
}) {
  const start = event.start_date ? new Date(event.start_date) : null;
  const isValidDate = start && !isNaN(start.getTime());
  const month = isValidDate ? start.toLocaleString('default', { month: 'short' }) : '—';
  const day = isValidDate ? start.getDate() : '—';
  const time = isValidDate ? start.toLocaleTimeString('default', { hour: '2-digit', minute: '2-digit' }) : '—';

  return (
    <TouchableOpacity
      style={cardStyles.card}
      onPress={() => {
        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
        onPress();
      }}
      activeOpacity={0.8}
      accessibilityRole="button"
      accessibilityLabel={event.title ?? ''}
    >
      {/* Date badge */}
      {/* 8% opacity variant for light background */}
      <View style={[cardStyles.dateBadge, { backgroundColor: withAlpha(primary, 0.08) }]}>
        <Text style={[cardStyles.dateMonth, { color: primary }]}>{month}</Text>
        <Text style={[cardStyles.dateDay, { color: primary }]}>{day}</Text>
      </View>

      <View style={cardStyles.cardContent}>
        <Text style={cardStyles.cardTitle} numberOfLines={2}>{event.title}</Text>

        <View style={cardStyles.metaRow}>
          <Ionicons name="time-outline" size={13} color={theme.textMuted} />
          <Text style={cardStyles.metaText}>{time}</Text>
        </View>

        {event.location ? (
          <View style={cardStyles.metaRow}>
            <Ionicons name={event.is_online ? 'videocam-outline' : 'location-outline'} size={13} color={theme.textMuted} />
            <Text style={cardStyles.metaText} numberOfLines={1}>
              {event.is_online ? t('online') : event.location}
            </Text>
          </View>
        ) : event.is_online ? (
          <View style={cardStyles.metaRow}>
            <Ionicons name="videocam-outline" size={13} color={theme.textMuted} />
            <Text style={cardStyles.metaText}>{t('online')}</Text>
          </View>
        ) : null}

        {/* RSVP + category row */}
        <View style={cardStyles.footerRow}>
          <View style={cardStyles.rsvpPill}>
            <Ionicons name="people-outline" size={12} color={theme.textSecondary} />
            <Text style={cardStyles.rsvpText}>{t('goingCount', { count: event.rsvp_counts?.going ?? 0 })}</Text>
          </View>

          {event.category && (
            <View style={[cardStyles.categoryPill, { backgroundColor: withAlpha(event.category.color ?? primary, 0.13) }]}>
              <Text style={[cardStyles.categoryText, { color: event.category.color ?? primary }]}>
                {event.category.name}
              </Text>
            </View>
          )}

          {event.user_rsvp === 'going' && (
            <View style={[cardStyles.rsvpBadge, { backgroundColor: withAlpha(primary, 0.13) }]}>
              <Text style={[cardStyles.rsvpBadgeText, { color: primary }]}>{t('going')}</Text>
            </View>
          )}
        </View>
      </View>

      <Ionicons name="chevron-forward" size={16} color={theme.textMuted} style={{ alignSelf: 'center' }} />
    </TouchableOpacity>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    tabs: { flexDirection: 'row', backgroundColor: theme.surface, borderBottomWidth: 1, borderBottomColor: theme.borderSubtle },
    tab: { flex: 1, paddingVertical: 14, alignItems: 'center' },
    tabText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.textSecondary },
    card: {
      flexDirection: 'row',
      backgroundColor: theme.surface,
      marginHorizontal: SPACING.md,
      marginTop: 12,
      borderRadius: RADIUS.lg,
      padding: 14,
      gap: 12,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    dateBadge: {
      width: SPACING.xxl,
      height: 52,
      borderRadius: RADIUS.md,
      alignItems: 'center',
      justifyContent: 'center',
    },
    dateMonth: { fontSize: 11, fontWeight: '600', textTransform: 'uppercase' },
    dateDay: { ...TYPOGRAPHY.h2, lineHeight: 26 },
    cardContent: { flex: 1, gap: SPACING.xs },
    cardTitle: { ...TYPOGRAPHY.button, color: theme.text, marginBottom: SPACING.xxs },
    metaRow: { flexDirection: 'row', alignItems: 'center', gap: SPACING.xs },
    metaText: { ...TYPOGRAPHY.caption, color: theme.textSecondary, flex: 1 },
    footerRow: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm, marginTop: SPACING.xs, flexWrap: 'wrap' },
    rsvpPill: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    rsvpText: { fontSize: 11, color: theme.textSecondary },
    categoryPill: { borderRadius: RADIUS.sm, paddingHorizontal: 7, paddingVertical: SPACING.xxs },
    categoryText: { fontSize: 11, fontWeight: '600' },
    rsvpBadge: { borderRadius: RADIUS.sm, paddingHorizontal: 7, paddingVertical: SPACING.xxs },
    rsvpBadgeText: { fontSize: 11, fontWeight: '600' },
    emptyText: { ...TYPOGRAPHY.body, color: theme.textMuted },
    errorText: { ...TYPOGRAPHY.body, color: theme.textMuted, marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    footer: { paddingVertical: SPACING.md, alignItems: 'center' as const },
    endOfListText: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
  });
}
