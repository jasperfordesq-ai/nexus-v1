// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo, useCallback } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { search, type SearchResult, type SearchResultType, type SearchResponse } from '@/lib/api/search';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { SkeletonBox } from '@/components/ui/Skeleton';
import OfflineBanner from '@/components/OfflineBanner';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

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

/** Inline skeleton for a search result row. */
function SearchResultSkeleton({ theme }: { theme: Theme }) {
  return (
    <View style={{
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: 16,
      paddingVertical: 12,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: theme.borderSubtle,
    }}>
      <SkeletonBox width={40} height={40} borderRadius={20} />
      <View style={{ flex: 1, marginLeft: 12, gap: 6 }}>
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
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
      <TouchableOpacity
        style={styles.resultRow}
        onPress={() => {
          void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
          navigateToResult(item);
        }}
        activeOpacity={0.75}
        accessibilityRole="button"
        accessibilityLabel={item.title}
      >
        <View style={[styles.iconWrap, { backgroundColor: withAlpha(primary, 0.09) }]}>
          <Ionicons name={icon} size={20} color={primary} />
        </View>
        <View style={styles.resultText}>
          <Text style={styles.resultTitle} numberOfLines={1}>{item.title}</Text>
          {item.subtitle ? (
            <Text style={styles.resultSubtitle} numberOfLines={1}>{item.subtitle}</Text>
          ) : null}
        </View>
        <View style={styles.typePill}>
          <Text style={[styles.typeLabel, { color: primary }]}>{typeLabel}</Text>
        </View>
      </TouchableOpacity>
    );
  }

  function renderEmpty() {
    if (isLoading && debouncedQuery.trim().length > 0 && results.length === 0) {
      return (
        <>
          <SearchResultSkeleton theme={theme} />
          <SearchResultSkeleton theme={theme} />
          <SearchResultSkeleton theme={theme} />
          <SearchResultSkeleton theme={theme} />
        </>
      );
    }
    if (debouncedQuery.trim().length === 0) {
      return (
        <View style={styles.centered}>
          <Ionicons name="search-outline" size={40} color={theme.textMuted} style={{ marginBottom: 12 }} />
          <Text style={styles.emptyText}>{t('startTyping')}</Text>
        </View>
      );
    }
    if (error) {
      return (
        <View style={styles.centered}>
          <Text style={styles.errorText}>{t('error')}</Text>
        </View>
      );
    }
    return (
      <View style={styles.centered}>
        <Text style={styles.emptyText}>{t('empty')}</Text>
      </View>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>{t('title')}</Text>
      </View>

      {/* Search input */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          value={query}
          onChangeText={setQuery}
          placeholder={t('placeholder')}
          placeholderTextColor={theme.textMuted}
          returnKeyType="search"
          clearButtonMode="while-editing"
          autoCorrect={false}
          accessibilityLabel={t('placeholder')}
        />
        {isLoading && query.trim().length > 0 ? (
          <Ionicons name="sync-outline" size={16} color={theme.textMuted} />
        ) : null}
      </View>

      {/* Type filter pills */}
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.filterRow}
      >
        {filters.map((f) => {
          const active = activeFilter === f;
          return (
            <TouchableOpacity
              key={f}
              style={[
                styles.filterPill,
                active
                  ? { backgroundColor: primary, borderColor: primary }
                  : { backgroundColor: theme.surface, borderColor: theme.border },
              ]}
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                setActiveFilter(f);
              }}
              activeOpacity={0.75}
              accessibilityRole="button"
              accessibilityState={{ selected: active }}
            >
              {/* '#fff' = contrast on primary */}
              <Text style={[styles.filterText, active ? { color: '#fff' } : { color: theme.textSecondary }]}>
                {filterLabel(f)}
              </Text>
            </TouchableOpacity>
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
            <View style={styles.footer}>
              <ActivityIndicator size="small" color={theme.textMuted} />
            </View>
          ) : !hasMore && results.length > 0 && !isLoading ? (
            <View style={styles.footer}>
              <Text style={styles.endOfListText}>{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={results.length === 0 ? styles.listEmptyContainer : styles.list}
        keyboardShouldPersistTaps="handled"
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    header: {
      paddingHorizontal: SPACING.md,
      paddingTop: SPACING.md,
      paddingBottom: SPACING.sm,
    },
    title: { ...TYPOGRAPHY.h2, color: theme.text },
    searchContainer: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      marginHorizontal: SPACING.md,
      marginBottom: SPACING.sm,
      borderRadius: RADIUS.md,
      borderWidth: 1,
      borderColor: theme.border,
      paddingHorizontal: 12,
    },
    searchIcon: { marginRight: SPACING.sm },
    searchInput: { flex: 1, paddingVertical: 10, ...TYPOGRAPHY.body, color: theme.text },
    filterRow: {
      paddingHorizontal: SPACING.md,
      paddingBottom: 10,
      gap: SPACING.sm,
      flexDirection: 'row',
    },
    filterPill: {
      borderRadius: RADIUS.xl,
      borderWidth: 1,
      paddingHorizontal: 14,
      paddingVertical: 6,
    },
    filterText: { ...TYPOGRAPHY.buttonSmall },
    list: { paddingBottom: SPACING.lg },
    listEmptyContainer: { flex: 1 },
    resultRow: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: SPACING.md,
      paddingVertical: 12,
      borderBottomWidth: StyleSheet.hairlineWidth,
      borderBottomColor: theme.borderSubtle,
    },
    iconWrap: {
      width: 40,
      height: 40,
      borderRadius: RADIUS.xl,
      justifyContent: 'center',
      alignItems: 'center',
      marginRight: 12,
    },
    resultText: { flex: 1, marginRight: SPACING.sm },
    resultTitle: { ...TYPOGRAPHY.button, color: theme.text },
    resultSubtitle: { ...TYPOGRAPHY.bodySmall, color: theme.textSecondary, marginTop: SPACING.xxs },
    typePill: {
      paddingHorizontal: SPACING.sm,
      paddingVertical: 3,
      borderRadius: RADIUS.sm,
      backgroundColor: theme.surface,
      borderWidth: 1,
      borderColor: theme.border,
    },
    typeLabel: { fontSize: 11, fontWeight: '600' },
    centered: {
      flex: 1,
      justifyContent: 'center',
      alignItems: 'center',
      padding: SPACING.xxl,
    },
    emptyText: { ...TYPOGRAPHY.body, color: theme.textMuted, textAlign: 'center' },
    errorText: { ...TYPOGRAPHY.body, color: theme.error, textAlign: 'center' },
    footer: { paddingVertical: SPACING.md, alignItems: 'center' as const },
    endOfListText: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
  });
}
