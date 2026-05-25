// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { FlatList, Pressable, RefreshControl, ScrollView, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Spinner } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { search, type SearchResult, type SearchResultType, type SearchResponse } from '@/lib/api/search';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { withAlpha } from '@/lib/utils/color';
import { SkeletonBox } from '@/components/ui/Skeleton';
import OfflineBanner from '@/components/OfflineBanner';

type FilterOption = SearchResultType | 'all';

function extractSearchPage(response: SearchResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor ?? null,
    hasMore: response.meta.has_more,
  };
}

const TYPE_ICONS: Record<SearchResultType, React.ComponentProps<typeof Ionicons>['name']> = {
  user: 'person-outline',
  listing: 'storefront-outline',
  event: 'calendar-outline',
  group: 'people-outline',
  blog_post: 'newspaper-outline',
};

function navigateToResult(item: SearchResult): void {
  switch (item.type) {
    case 'user':
      router.push({ pathname: '/(modals)/member-profile', params: { id: String(item.id) } });
      break;
    case 'listing':
      router.push({ pathname: '/(modals)/exchange-detail', params: { id: String(item.id) } });
      break;
    case 'event':
      router.push({ pathname: '/(modals)/event-detail', params: { id: String(item.id) } });
      break;
    case 'group':
      router.push({ pathname: '/(modals)/group-detail', params: { id: String(item.id) } });
      break;
    case 'blog_post':
      router.push({ pathname: '/(modals)/blog-post', params: { id: String(item.id) } });
      break;
  }
}

function SearchResultSkeleton() {
  return (
    <View className="flex-row items-center px-4 py-3 border-b border-border/50">
      <SkeletonBox width={40} height={40} borderRadius={20} />
      <View className="flex-1 ml-3 gap-1.5">
        <SkeletonBox width="65%" height={14} />
        <SkeletonBox width="40%" height={11} />
      </View>
      <SkeletonBox width={48} height={20} borderRadius={6} style={{ marginLeft: 8 }} />
    </View>
  );
}

export default function SearchScreen() {
  const { t } = useTranslation('search');
  const primary = usePrimaryColor();
  const [query, setQuery] = useState('');
  const [activeFilter, setActiveFilter] = useState<FilterOption>('all');
  const debouncedQuery = useDebounce(query, 400);

  const activeType = activeFilter === 'all' ? undefined : activeFilter;

  const fetchSearch = useCallback(
    (cursor: string | null) => {
      if (!debouncedQuery.trim()) {
        return Promise.resolve({ data: [], meta: { total: 0, has_more: false, cursor: null } } as SearchResponse);
      }
      return search(debouncedQuery, cursor, activeType);
    },
    [debouncedQuery, activeType],
  );

  const { items: results, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<SearchResult, SearchResponse>(
      fetchSearch,
      extractSearchPage,
      [debouncedQuery, activeFilter],
    );

  const filters: FilterOption[] = ['all', 'user', 'listing', 'event', 'group', 'blog_post'];

  function filterLabel(f: FilterOption): string {
    if (f === 'all') return t('filterAll');
    return t(`types.${f}`);
  }

  function renderResult({ item }: { item: SearchResult }) {
    const icon = TYPE_ICONS[item.type];
    const typeLabel = t(`types.${item.type}`);
    return (
      <Pressable
        className="flex-row items-center px-4 py-3 border-b border-border/50"
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          navigateToResult(item);
        }}
        accessibilityRole="button"
        accessibilityLabel={item.title}
      >
        <View
          className="w-10 h-10 rounded-full items-center justify-center mr-3"
          style={{ backgroundColor: withAlpha(primary, 0.09) }}
        >
          <Ionicons name={icon} size={20} color={primary} />
        </View>
        <View className="flex-1 mr-2">
          <Text className="font-semibold text-foreground" numberOfLines={1}>{item.title}</Text>
          {item.subtitle ? (
            <Text className="text-xs text-muted-foreground mt-0.5" numberOfLines={1}>{item.subtitle}</Text>
          ) : null}
        </View>
        <View className="px-2 py-0.5 rounded bg-surface border border-border">
          <Text className="text-[11px] font-semibold" style={{ color: primary }}>{typeLabel}</Text>
        </View>
      </Pressable>
    );
  }

  function renderEmpty() {
    if (isLoading && debouncedQuery.trim().length > 0 && results.length === 0) {
      return (
        <>
          <SearchResultSkeleton />
          <SearchResultSkeleton />
          <SearchResultSkeleton />
          <SearchResultSkeleton />
        </>
      );
    }
    if (debouncedQuery.trim().length === 0) {
      return (
        <View className="flex-1 items-center justify-center p-12">
          <Ionicons name="search-outline" size={40} className="text-muted-foreground mb-3" />
          <Text className="text-muted-foreground text-sm text-center">{t('startTyping')}</Text>
        </View>
      );
    }
    if (error) {
      return (
        <View className="flex-1 items-center justify-center p-8">
          <Text className="text-muted-foreground text-sm text-center">{t('error')}</Text>
        </View>
      );
    }
    return (
      <View className="flex-1 items-center justify-center p-8">
        <Text className="text-muted-foreground text-sm text-center">{t('empty')}</Text>
      </View>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      {/* Header */}
      <View className="px-4 pt-4 pb-2">
        <Text className="text-xl font-bold text-foreground">{t('title')}</Text>
      </View>

      {/* Search input */}
      <View className="flex-row items-center bg-surface mx-4 mb-2 rounded-xl border border-border px-3">
        <Ionicons name="search-outline" size={18} className="text-muted-foreground mr-2" />
        <TextInput
          className="flex-1 py-2.5 text-base text-foreground"
          value={query}
          onChangeText={setQuery}
          placeholder={t('placeholder')}
          returnKeyType="search"
          clearButtonMode="while-editing"
          autoCorrect={false}
          accessibilityLabel={t('placeholder')}
        />
        {isLoading && query.trim().length > 0 ? (
          <Ionicons name="sync-outline" size={16} className="text-muted-foreground" />
        ) : null}
      </View>

      {/* Type filter pills */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 10, gap: 8, flexDirection: 'row' }}
      >
        {filters.map((f) => {
          const active = activeFilter === f;
          return (
            <Pressable
              key={f}
              className="rounded-full border px-3.5 py-1.5"
              style={
                active
                  ? { backgroundColor: primary, borderColor: primary }
                  : { borderColor: '#d1d5db' }
              }
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                setActiveFilter(f);
              }}
              accessibilityRole="button"
              accessibilityState={{ selected: active }}
            >
              <Text className="text-xs font-semibold" style={{ color: active ? '#fff' : '#6b7280' }}>
                {filterLabel(f)}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>

      <OfflineBanner />

      {/* Results */}
      <FlatList<SearchResult>
        data={results}
        keyExtractor={(item) => `${item.type}-${item.id}`}
        renderItem={renderResult}
        ListEmptyComponent={renderEmpty}
        onEndReached={loadMore}
        onEndReachedThreshold={0.3}
        refreshControl={
          <RefreshControl
            refreshing={isLoading && results.length > 0}
            onRefresh={refresh}
            tintColor={primary}
            colors={[primary]}
          />
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4 items-center"><Spinner size="sm" /></View>
          ) : !hasMore && results.length > 0 && !isLoading ? (
            <View className="py-4 items-center">
              <Text className="text-xs text-muted-foreground">{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={results.length === 0 ? { flex: 1 } : { paddingBottom: 24 }}
        keyboardShouldPersistTaps="handled"
      />
    </SafeAreaView>
  );
}
