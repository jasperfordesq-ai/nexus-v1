// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback, useEffect, useRef } from 'react';
import {
  View,
  Text,
  FlatList,
  StyleSheet,
  SafeAreaView,
  TouchableOpacity,
  RefreshControl,
} from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import { getEvents, type Event, type EventsResponse } from '@/lib/api/events';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { EventCardSkeleton } from '@/components/ui/Skeleton';

export default function EventsScreen() {
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = makeStyles(theme);
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
  } = usePaginatedApi<Event, EventsResponse>(fetcher, (r) => ({
    items: r.data,
    cursor: r.meta.cursor,
    hasMore: r.meta.has_more,
  }));

  // Re-fetch when the tab filter changes.
  // Skip the initial mount (usePaginatedApi already fetches on mount).
  const isFirstMount = useRef(true);
  useEffect(() => {
    if (isFirstMount.current) {
      isFirstMount.current = false;
      return;
    }
    refresh();
  }, [when]); // eslint-disable-line react-hooks/exhaustive-deps

  function handleTabChange(tab: 'upcoming' | 'past') {
    if (tab !== when) setWhen(tab);
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
          >
            <Text style={[styles.tabText, when === tab && { color: primary, fontWeight: '700' }]}>
              {tab === 'upcoming' ? 'Upcoming' : 'Past'}
            </Text>
          </TouchableOpacity>
        ))}
      </View>

      {error ? (
        <View style={styles.center}>
          <Text style={styles.errorText}>Could not load events.</Text>
          <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
            <Text style={{ color: primary }}>Retry</Text>
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
              onPress={() =>
                router.push({ pathname: '/(modals)/event-detail', params: { id: String(item.id) } })
              }
            />
          )}
          refreshControl={
            <RefreshControl refreshing={isLoading} onRefresh={() => void refresh()} tintColor={primary} />
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
              <View style={styles.center}>
                <Text style={styles.emptyText}>No {when} events.</Text>
              </View>
            )
          }
          ListFooterComponent={isLoadingMore ? <View style={{ marginVertical: 16 }}><LoadingSpinner size="small" /></View> : null}
          contentContainerStyle={items.length === 0 ? { flex: 1 } : { paddingBottom: 24 }}
        />
      )}
    </SafeAreaView>
  );
}

function EventCard({
  event,
  primary,
  theme,
  onPress,
}: {
  event: Event;
  primary: string;
  theme: Theme;
  onPress: () => void;
}) {
  const cardStyles = makeStyles(theme);
  const start = new Date(event.start_date);
  const month = start.toLocaleString('default', { month: 'short' });
  const day = start.getDate();
  const time = start.toLocaleTimeString('default', { hour: '2-digit', minute: '2-digit' });

  return (
    <TouchableOpacity style={cardStyles.card} onPress={onPress} activeOpacity={0.8}>
      {/* Date badge */}
      <View style={[cardStyles.dateBadge, { backgroundColor: primary + '15' }]}>
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
              {event.is_online ? 'Online' : event.location}
            </Text>
          </View>
        ) : event.is_online ? (
          <View style={cardStyles.metaRow}>
            <Ionicons name="videocam-outline" size={13} color={theme.textMuted} />
            <Text style={cardStyles.metaText}>Online</Text>
          </View>
        ) : null}

        {/* RSVP + category row */}
        <View style={cardStyles.footerRow}>
          <View style={cardStyles.rsvpPill}>
            <Ionicons name="people-outline" size={12} color={theme.textSecondary} />
            <Text style={cardStyles.rsvpText}>{event.rsvp_counts.going} going</Text>
          </View>

          {event.category && (
            <View style={[cardStyles.categoryPill, { backgroundColor: (event.category.color ?? primary) + '20' }]}>
              <Text style={[cardStyles.categoryText, { color: event.category.color ?? primary }]}>
                {event.category.name}
              </Text>
            </View>
          )}

          {event.user_rsvp === 'going' && (
            <View style={[cardStyles.rsvpBadge, { backgroundColor: primary + '20' }]}>
              <Text style={[cardStyles.rsvpBadgeText, { color: primary }]}>Going</Text>
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
    tabText: { fontSize: 14, color: theme.textSecondary },
    card: {
      flexDirection: 'row',
      backgroundColor: theme.surface,
      marginHorizontal: 16,
      marginTop: 12,
      borderRadius: 14,
      padding: 14,
      gap: 12,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    dateBadge: {
      width: 48,
      height: 52,
      borderRadius: 10,
      alignItems: 'center',
      justifyContent: 'center',
    },
    dateMonth: { fontSize: 11, fontWeight: '600', textTransform: 'uppercase' },
    dateDay: { fontSize: 22, fontWeight: '700', lineHeight: 26 },
    cardContent: { flex: 1, gap: 4 },
    cardTitle: { fontSize: 15, fontWeight: '600', color: theme.text, marginBottom: 2 },
    metaRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
    metaText: { fontSize: 12, color: theme.textSecondary, flex: 1 },
    footerRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4, flexWrap: 'wrap' },
    rsvpPill: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    rsvpText: { fontSize: 11, color: theme.textSecondary },
    categoryPill: { borderRadius: 6, paddingHorizontal: 7, paddingVertical: 2 },
    categoryText: { fontSize: 11, fontWeight: '600' },
    rsvpBadge: { borderRadius: 6, paddingHorizontal: 7, paddingVertical: 2 },
    rsvpBadgeText: { fontSize: 11, fontWeight: '600' },
    emptyText: { fontSize: 15, color: theme.textMuted },
    errorText: { fontSize: 15, color: theme.textMuted, marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
  });
}
