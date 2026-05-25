// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import {
  FlatList,
  View,
  Text,
  TextInput,
  Pressable,
  RefreshControl,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import {
  getOpportunities,
  type VolunteerOpportunity,
  type VolunteeringResponse,
} from '@/lib/api/volunteering';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// ---------------------------------------------------------------------------
// Inline card component
// ---------------------------------------------------------------------------

function OpportunityCard({
  item,
  primary,
  theme,
  t,
  onPress,
}: {
  item: VolunteerOpportunity;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  const statusColor =
    item.status === 'open'
      ? theme.success
      : item.status === 'filled'
        ? theme.warning
        : theme.textMuted;

  const deadlineStr = item.deadline
    ? t('deadline', { date: new Date(item.deadline).toLocaleDateString('default', { month: 'short', day: 'numeric', year: 'numeric' }) })
    : null;

  const visibleSkills = (item.skills_needed ?? []).slice(0, 3);

  return (
    <Pressable
      className="bg-surface rounded-2xl p-4 mb-3 border border-border/50 gap-2"
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={item.title}
    >
      {/* Title row */}
      <View className="flex-row items-start gap-2">
        <Text className="flex-1 text-sm font-semibold text-foreground" numberOfLines={2}>{item.title}</Text>
        <View style={{ backgroundColor: statusColor + '22' }} className="rounded px-2 py-0.5 self-start">
          <Text style={{ color: statusColor }} className="text-[11px] font-semibold">
            {t(`status.${item.status}`)}
          </Text>
        </View>
      </View>

      {/* Organisation */}
      {item.organisation ? (
        <Text className="text-xs text-muted-foreground" numberOfLines={1}>{item.organisation.name}</Text>
      ) : null}

      {/* Meta row: location / remote, hours */}
      <View className="flex-row flex-wrap gap-2 items-center">
        {item.is_remote ? (
          <View style={{ backgroundColor: withAlpha(primary, 0.10) }} className="rounded px-2 py-0.5">
            <Text style={{ color: primary }} className="text-[11px] font-semibold">{t('remote')}</Text>
          </View>
        ) : item.location ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name="location-outline" size={13} color={theme.textMuted} />
            <Text className="text-[11px] text-muted-foreground" numberOfLines={1}>{item.location}</Text>
          </View>
        ) : null}

        {item.hours_per_week !== null ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name="time-outline" size={13} color={theme.textMuted} />
            <Text className="text-[11px] text-muted-foreground">{t('hoursPerWeek', { hours: item.hours_per_week })}</Text>
          </View>
        ) : null}

        {deadlineStr ? (
          <View className="flex-row items-center gap-1">
            <Ionicons name="calendar-outline" size={13} color={theme.textMuted} />
            <Text className="text-[11px] text-muted-foreground">{deadlineStr}</Text>
          </View>
        ) : null}
      </View>

      {/* Skills */}
      {visibleSkills.length > 0 ? (
        <View className="flex-row flex-wrap gap-1.5">
          {visibleSkills.map((skill) => (
            <View key={skill} className="rounded px-2 py-0.5 border border-border bg-background">
              <Text className="text-[11px] text-muted-foreground">{skill}</Text>
            </View>
          ))}
          {(item.skills_needed ?? []).length > 3 ? (
            <View className="rounded px-2 py-0.5 border border-border bg-background">
              <Text className="text-[11px] text-muted-foreground">+{item.skills_needed.length - 3}</Text>
            </View>
          ) : null}
        </View>
      ) : null}
    </Pressable>
  );
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

export default function VolunteeringScreen() {
  const { t } = useTranslation('volunteering');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const [search, setSearch] = useState('');
  const [committedSearch, setCommittedSearch] = useState('');
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  function handleSearchChange(text: string) {
    setSearch(text);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setCommittedSearch(text.trim());
    }, 400);
  }

  function handleClear() {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    setSearch('');
    setCommittedSearch('');
  }

  // Clean up debounce timer on unmount
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const fetchFn = useCallback(
    (cursor: string | null) => getOpportunities(cursor, committedSearch || undefined),
    [committedSearch],
  );

  const extractor = useCallback(
    (response: VolunteeringResponse) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<VolunteerOpportunity, VolunteeringResponse>(fetchFn, extractor, [committedSearch]);

  const renderItem = useCallback(
    ({ item }: { item: VolunteerOpportunity }) => (
      <OpportunityCard
        item={item}
        primary={primary}
        theme={theme}
        t={t}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          router.push({
            pathname: '/(modals)/volunteering-detail',
            params: { id: String(item.id) },
          });
        }}
      />
    ),
    [primary, theme, t],
  );

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      {/* Search bar */}
      <View className="flex-row items-center mx-4 my-3 px-3 h-[42px] bg-surface rounded-xl gap-2">
        <Ionicons name="search-outline" size={18} color={theme.textMuted} />
        <TextInput
          className="flex-1 text-sm py-0"
          style={{ color: theme.text }}
          placeholder={t('searchPlaceholder')}
          placeholderTextColor={theme.textMuted}
          value={search}
          onChangeText={handleSearchChange}
          returnKeyType="search"
          clearButtonMode="never"
          autoCorrect={false}
          autoCapitalize="none"
          accessibilityLabel={t('searchPlaceholder')}
        />
        {search.length > 0 && (
          <Pressable
            onPress={handleClear}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
            accessibilityLabel={t('common:actions.clear', 'Clear search')}
            accessibilityRole="button"
          >
            <Ionicons name="close-circle" size={18} color={theme.textMuted} />
          </Pressable>
        )}
      </View>

      <FlatList<VolunteerOpportunity>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        onEndReached={hasMore ? loadMore : undefined}
        onEndReachedThreshold={0.3}
        refreshControl={
          <RefreshControl
            refreshing={isLoading && items.length > 0}
            onRefresh={refresh}
            tintColor={primary}
          />
        }
        ListEmptyComponent={
          isLoading ? (
            <LoadingSpinner />
          ) : error ? (
            <View className="flex-1 justify-center items-center p-10">
              <Text className="text-sm text-danger text-center">{error}</Text>
              <Pressable onPress={refresh} className="mt-3">
                <Text style={{ color: primary }} className="text-sm font-semibold">{t('common:actions.retry', 'Retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <EmptyState
              icon="heart-outline"
              title={t('empty')}
            />
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4">
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={{ flexGrow: 1, paddingHorizontal: 16, paddingBottom: 32 }}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
