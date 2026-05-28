// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useCallback } from 'react';
import { FlatList, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface, Tabs } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { search, type SearchResult, type SearchResultType, type SearchResponse } from '@/lib/api/search';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
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

const TYPE_TONES: Record<SearchResultType, string> = {
  user: '#10B981',
  listing: '#6366F1',
  event: '#F59E0B',
  group: '#8B5CF6',
  blog_post: '#0EA5E9',
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
    case 'blog_post': {
      const slug = getBlogSlug(item);
      router.push({ pathname: '/(modals)/blog-post', params: { id: slug } });
      break;
    }
  }
}

function getBlogSlug(item: SearchResult): string {
  if (item.url) {
    const cleanUrl = item.url.split('?')[0]?.replace(/\/$/, '') ?? '';
    const slug = cleanUrl.split('/').filter(Boolean).pop();
    if (slug) return slug;
  }
  return String(item.id);
}

function SearchResultSkeleton() {
  return (
    <HeroCard variant="default" className="mx-4 my-2 rounded-panel p-0">
      <HeroCard.Body className="flex-row items-center gap-3 p-4">
      <SkeletonBox width={40} height={40} borderRadius={20} />
        <View className="flex-1 gap-1.5">
        <SkeletonBox width="65%" height={14} />
        <SkeletonBox width="40%" height={11} />
      </View>
      <SkeletonBox width={48} height={20} borderRadius={6} style={{ marginLeft: 8 }} />
      </HeroCard.Body>
    </HeroCard>
  );
}

export default function SearchScreen() {
  const { t } = useTranslation(['search', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
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
    const tone = TYPE_TONES[item.type];
    return (
      <Pressable
        className="mx-4 my-2"
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          navigateToResult(item);
        }}
        accessibilityRole="button"
        accessibilityLabel={item.title}
      >
        <HeroCard variant="default" className="overflow-hidden rounded-panel p-0">
          <View className="h-1 w-full" style={{ backgroundColor: tone }} />
          <HeroCard.Body className="flex-row items-center gap-3 p-4">
            {item.type === 'user' ? (
              <Avatar uri={item.avatar} name={item.title} size={44} />
            ) : (
              <View
                className="size-11 items-center justify-center rounded-2xl"
                style={{ backgroundColor: withAlpha(tone, 0.14) }}
              >
                <Ionicons name={icon} size={21} color={tone} />
              </View>
            )}
            <View className="min-w-0 flex-1 gap-1">
              <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>{item.title}</Text>
              {item.subtitle ? (
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>{item.subtitle}</Text>
              ) : null}
            </View>
            <View className="items-end gap-2">
              <Chip size="sm" variant="secondary">
                <Chip.Label>{typeLabel}</Chip.Label>
              </Chip>
              <Ionicons name="arrow-forward" size={17} color={primary} />
            </View>
          </HeroCard.Body>
        </HeroCard>
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
        <View className="px-4 py-8">
          <EmptyState
            icon="search-outline"
            title={t('initialTitle')}
            subtitle={t('startTyping')}
          />
        </View>
      );
    }
    if (error) {
      return (
        <View className="px-4 py-8">
          <EmptyState
            icon="warning-outline"
            title={t('errorTitle')}
            subtitle={t('error')}
            actionLabel={t('common:buttons.retry')}
            onAction={() => void refresh()}
          />
        </View>
      );
    }
    return (
      <View className="px-4 py-8">
        <EmptyState
          icon="search-outline"
          title={t('empty')}
          subtitle={t('emptyHint', { query: debouncedQuery })}
        />
      </View>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />

      <OfflineBanner />

      {/* Results */}
      <FlatList<SearchResult>
        data={results}
        keyExtractor={(item) => `${item.type}-${item.id}`}
        renderItem={renderResult}
        ListEmptyComponent={renderEmpty}
        onEndReached={() => { if (hasMore) void loadMore(); }}
        onEndReachedThreshold={0.3}
        refreshControl={
          <RefreshControl
            refreshing={isLoading && results.length > 0}
            onRefresh={() => void refresh()}
            tintColor={primary}
            colors={[primary]}
          />
        }
        ListHeaderComponent={
          <SearchHeader
            query={query}
            setQuery={setQuery}
            isLoading={isLoading}
            activeFilter={activeFilter}
            setActiveFilter={setActiveFilter}
            filters={filters}
            filterLabel={filterLabel}
            resultCount={results.length}
            hasQuery={debouncedQuery.trim().length > 0}
            primary={primary}
            theme={theme}
            t={t}
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
        contentContainerStyle={results.length === 0 ? { flexGrow: 1, paddingBottom: 24 } : { paddingBottom: 24 }}
        keyboardShouldPersistTaps="handled"
      />
    </SafeAreaView>
  );
}

function SearchHeader({
  query,
  setQuery,
  isLoading,
  activeFilter,
  setActiveFilter,
  filters,
  filterLabel,
  resultCount,
  hasQuery,
  primary,
  theme,
  t,
}: {
  query: string;
  setQuery: (value: string) => void;
  isLoading: boolean;
  activeFilter: FilterOption;
  setActiveFilter: (value: FilterOption) => void;
  filters: FilterOption[];
  filterLabel: (filter: FilterOption) => string;
  resultCount: number;
  hasQuery: boolean;
  primary: string;
  theme: Theme;
  t: (key: string, options?: Record<string, unknown>) => string;
}) {
  return (
    <View className="gap-3 pb-2">
      <HeroCard variant="default" className="mx-4 overflow-hidden rounded-panel p-0">
        <View className="h-1 w-full" style={{ backgroundColor: '#10B981' }} />
        <HeroCard.Body className="gap-4 p-4">
          <View className="flex-row items-start gap-3">
            <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha('#10B981', 0.14) }}>
              <Ionicons name="search-outline" size={24} color="#10B981" />
            </View>
            <View className="min-w-0 flex-1">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('heroEyebrow')}</Text>
              <Text className="mt-1 text-2xl font-bold leading-8" style={{ color: theme.text }}>{t('title')}</Text>
              <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>{t('subtitle')}</Text>
            </View>
          </View>
          {hasQuery ? (
            <Chip size="sm" variant="soft" color="success" className="self-start">
              <Ionicons name="sparkles-outline" size={12} color="#10B981" />
              <Chip.Label>{isLoading ? t('searching') : t('resultsCount', { count: resultCount })}</Chip.Label>
            </Chip>
          ) : null}
        </HeroCard.Body>
      </HeroCard>

      <Surface variant="default" className="mx-4 gap-3 rounded-panel-inner p-3">
        <View className="flex-row items-center gap-2 rounded-panel-inner border px-3 py-2" style={{ borderColor: withAlpha(primary, 0.30), backgroundColor: theme.surface }}>
          <Ionicons name="search-outline" size={18} color={theme.textMuted} />
          <TextInput
            className="min-h-10 flex-1 text-base"
            style={{ color: theme.text }}
            value={query}
            onChangeText={setQuery}
            placeholder={t('placeholder')}
            placeholderTextColor={theme.textMuted}
            returnKeyType="search"
            clearButtonMode="while-editing"
            autoCorrect={false}
            accessibilityLabel={t('placeholder')}
          />
          {query.trim().length > 0 ? (
            <HeroButton isIconOnly variant="ghost" accessibilityLabel={t('clearSearch')} onPress={() => setQuery('')}>
              <Ionicons name={isLoading ? 'sync-outline' : 'close-outline'} size={18} color={theme.textMuted} />
            </HeroButton>
          ) : null}
        </View>

        <Tabs value={activeFilter} onValueChange={(value) => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          setActiveFilter(value as FilterOption);
        }} variant="secondary">
          <Tabs.List>
            <Tabs.Indicator />
            {filters.map((filter) => {
              const icon = filter === 'all' ? 'apps-outline' : TYPE_ICONS[filter];
              return (
                <Tabs.Trigger key={filter} value={filter}>
                  <Ionicons name={icon} size={15} color={activeFilter === filter ? primary : theme.textMuted} />
                  <Tabs.Label>{filterLabel(filter)}</Tabs.Label>
                </Tabs.Trigger>
              );
            })}
          </Tabs.List>
        </Tabs>
      </Surface>
    </View>
  );
}
