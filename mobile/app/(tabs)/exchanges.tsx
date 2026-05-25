// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState, useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
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
import { getExchanges, type Exchange, type ExchangeListResponse } from '@/lib/api/exchanges';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import ExchangeCard from '@/components/ExchangeCard';
import OfflineBanner from '@/components/OfflineBanner';
import EmptyState from '@/components/ui/EmptyState';
import { ExchangeCardSkeleton } from '@/components/ui/Skeleton';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

/** Extractor for cursor-based ExchangeListResponse with deduplication. */
function extractExchangePage(response: ExchangeListResponse) {
  // Deduplicate — the API can return the same item across page boundaries
  const seen = new Set<number>();
  const unique = response.data.filter((item) => {
    if (seen.has(item.id)) return false;
    seen.add(item.id);
    return true;
  });
  return {
    items: unique,
    cursor: response.meta.cursor,
    hasMore: response.meta.has_more,
  };
}

export default function ExchangesScreen() {
  const { t } = useTranslation('exchanges');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);

  // Build a stable fetch function that includes the current search term and
  // decodes the page number from the cursor string.
  const fetchExchanges = useCallback(
    (cursor: string | null) => getExchanges(cursor, debouncedSearch ? { search: debouncedSearch } : undefined),
    // Re-create (and therefore reset pagination) whenever the debounced search term changes.
    [debouncedSearch],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Exchange, ExchangeListResponse>(fetchExchanges, extractExchangePage, [debouncedSearch]);

  // Dedicated pull-to-refresh state (mirrors home.tsx pattern)
  const [isRefreshing, setIsRefreshing] = useState(false);
  const wasRefreshingRef = useRef(false);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    wasRefreshingRef.current = true;
    refresh();
  }, [refresh]);

  // Clear the pull-to-refresh spinner once loading finishes
  useEffect(() => {
    if (wasRefreshingRef.current && !isLoading) {
      wasRefreshingRef.current = false;
      setIsRefreshing(false);
    }
  }, [isLoading]);

  return (
    <SafeAreaView style={styles.container}>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>{t('title')}</Text>
        <TouchableOpacity
          style={[styles.newButton, { backgroundColor: primary }]}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push('/(modals)/new-exchange');
          }}
          activeOpacity={0.8}
          accessibilityLabel={t('newListing')}
          accessibilityRole="button"
        >
          {/* #fff = contrast on primary */}
          <Ionicons name="add" size={20} color="#fff" />
        </TouchableOpacity>
      </View>

      {/* Search bar */}
      <View style={styles.searchContainer}>
        <Ionicons name="search-outline" size={18} color={theme.textMuted} style={styles.searchIcon} />
        <TextInput
          style={styles.searchInput}
          value={search}
          onChangeText={setSearch}
          placeholder={t('searchPlaceholder')}
          placeholderTextColor={theme.textMuted}
          returnKeyType="search"
          clearButtonMode="while-editing"
          accessibilityLabel={t('searchPlaceholder')}
        />
      </View>

      <OfflineBanner />

      <FlatList<Exchange>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <ExchangeCard exchange={item} />}
        refreshControl={
          <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} tintColor={primary} colors={[primary]} />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          isLoading ? (
            <>
              <ExchangeCardSkeleton />
              <ExchangeCardSkeleton />
              <ExchangeCardSkeleton />
            </>
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
                <Text style={{ color: primary, ...TYPOGRAPHY.button }}>{t('common:buttons.retry')}</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <EmptyState
              icon="swap-horizontal-outline"
              title={t('empty')}
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
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    header: {
      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',
      paddingHorizontal: SPACING.md,
      paddingTop: SPACING.md,
      paddingBottom: SPACING.sm,
    },
    title: { ...TYPOGRAPHY.h2, color: theme.text },
    newButton: {
      width: 36,
      height: 36,
      borderRadius: 18,
      justifyContent: 'center',
      alignItems: 'center',
    },
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
    list: { paddingBottom: SPACING.lg },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SPACING.xl },
    errorText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.error, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.textMuted, textAlign: 'center' },
    footer: { paddingVertical: SPACING.md, alignItems: 'center' },
    endOfListText: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
  });
}
