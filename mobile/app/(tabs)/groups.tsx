// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState } from 'react';
import { FlatList, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Spinner } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getGroups, type Group, type GroupsResponse } from '@/lib/api/groups';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { withAlpha } from '@/lib/utils/color';
import OfflineBanner from '@/components/OfflineBanner';
import EmptyState from '@/components/ui/EmptyState';
import { SkeletonBox } from '@/components/ui/Skeleton';

type FilterValue = 'all' | 'public' | 'private';

function extractGroupPage(response: GroupsResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor,
    hasMore: response.meta.has_more,
  };
}

function GroupCardSkeleton() {
  return (
    <View className="bg-surface rounded-xl p-3.5 mx-4 my-1.5">
      <View className="flex-row justify-between mb-2">
        <SkeletonBox width="55%" height={16} />
        <SkeletonBox width={48} height={16} />
      </View>
      <SkeletonBox width="100%" height={12} style={{ marginBottom: 4 }} />
      <SkeletonBox width="70%" height={12} style={{ marginBottom: 10 }} />
      <SkeletonBox width={80} height={12} />
    </View>
  );
}

function GroupCard({
  item,
  primary,
  t,
  onPress,
}: {
  item: Group;
  primary: string;
  t: (key: string, opts?: Record<string, unknown>) => string;
  onPress: () => void;
}) {
  return (
    <Pressable
      className="bg-surface rounded-xl p-3.5 mx-4 my-1.5 border border-border"
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={item.name ?? ''}
    >
      {/* Name row */}
      <View className="flex-row items-center mb-1.5">
        <Ionicons
          name={item.visibility === 'private' ? 'lock-closed-outline' : 'globe-outline'}
          size={14}
          className="text-muted-foreground mr-1"
        />
        <Text className="flex-1 text-base font-bold text-foreground" numberOfLines={1}>
          {item.name}
        </Text>
        {/* Badges */}
        <View className="flex-row gap-1.5 ml-2">
          {item.is_featured ? (
            <View
              className="rounded px-1.5 py-0.5"
              style={{ backgroundColor: withAlpha(primary, 0.13) }}
            >
              <Text className="text-[11px] font-semibold" style={{ color: primary }}>
                {t('featured')}
              </Text>
            </View>
          ) : null}
          {item.is_member ? (
            <View className="bg-success/10 rounded px-1.5 py-0.5">
              <Text className="text-[11px] font-semibold text-success">
                {t('joined')}
              </Text>
            </View>
          ) : null}
        </View>
      </View>

      {/* Description */}
      {item.description ? (
        <Text className="text-sm text-muted-foreground leading-5 mb-2" numberOfLines={2}>
          {item.description}
        </Text>
      ) : null}

      {/* Meta: member count */}
      <View className="flex-row items-center gap-1">
        <Ionicons name="people-outline" size={13} className="text-muted-foreground" />
        <Text className="text-xs text-muted-foreground">
          {t('members', { count: item.member_count ?? 0 })}
        </Text>
      </View>
    </Pressable>
  );
}

export default function GroupsScreen() {
  const { t } = useTranslation('groups');
  const primary = usePrimaryColor();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);
  const [filter, setFilter] = useState<FilterValue>('all');

  const fetchGroups = useCallback(
    (cursor: string | null) => {
      const params: { search?: string; visibility?: string } = {};
      if (debouncedSearch) params.search = debouncedSearch;
      if (filter !== 'all') params.visibility = filter;
      return getGroups(cursor, params);
    },
    [debouncedSearch, filter],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Group, GroupsResponse>(fetchGroups, extractGroupPage, [debouncedSearch, filter]);

  const filterOptions: { value: FilterValue; label: string }[] = [
    { value: 'all', label: t('filter.all') },
    { value: 'public', label: t('filter.public') },
    { value: 'private', label: t('filter.private') },
  ];

  return (
    <SafeAreaView className="flex-1 bg-background">
      {/* Header */}
      <View className="flex-row items-center justify-between px-4 pt-4 pb-2">
        <Text className="text-xl font-bold text-foreground">{t('title')}</Text>
        <Pressable
          className="w-9 h-9 rounded-full items-center justify-center"
          style={{ backgroundColor: primary }}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push('/(modals)/group-detail');
          }}
          accessibilityLabel={t('newGroup')}
          accessibilityRole="button"
        >
          <Ionicons name="add" size={20} color="#fff" />
        </Pressable>
      </View>

      {/* Search bar */}
      <View className="flex-row items-center bg-surface mx-4 mb-2 rounded-xl border border-border px-3">
        <Ionicons name="search-outline" size={18} className="text-muted-foreground mr-2" />
        <TextInput
          className="flex-1 py-2.5 text-base text-foreground"
          value={search}
          onChangeText={setSearch}
          placeholder={t('searchPlaceholder')}
          returnKeyType="search"
          clearButtonMode="while-editing"
          accessibilityLabel={t('searchPlaceholder')}
        />
      </View>

      {/* Filter pills */}
      <View className="flex-row gap-2 px-4 mb-2">
        {filterOptions.map((opt) => (
          <Pressable
            key={opt.value}
            className="rounded-full border px-3.5 py-1.5"
            style={
              filter === opt.value
                ? { backgroundColor: primary, borderColor: primary }
                : { borderColor: '#d1d5db' }
            }
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              setFilter(opt.value);
            }}
            accessibilityRole="button"
            accessibilityLabel={opt.label}
          >
            <Text
              className="text-xs font-semibold"
              style={{ color: filter === opt.value ? '#fff' : '#6b7280' }}
            >
              {opt.label}
            </Text>
          </Pressable>
        ))}
      </View>

      <OfflineBanner />

      <FlatList<Group>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <GroupCard
            item={item}
            primary={primary}
            t={t}
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push({ pathname: '/(modals)/group-detail', params: { id: String(item.id) } });
            }}
          />
        )}
        refreshControl={
          <RefreshControl
            refreshing={isLoading && items.length > 0}
            onRefresh={refresh}
            tintColor={primary}
            colors={[primary]}
          />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          isLoading ? (
            <><GroupCardSkeleton /><GroupCardSkeleton /><GroupCardSkeleton /></>
          ) : error ? (
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-danger text-sm text-center mb-3">{error}</Text>
              <Pressable onPress={() => void refresh()} className="px-5 py-2.5">
                <Text className="font-semibold" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <EmptyState icon="people-outline" title={t('empty')} />
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
        contentContainerStyle={{ paddingBottom: 24 }}
      />
    </SafeAreaView>
  );
}
