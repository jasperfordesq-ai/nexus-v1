// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState, useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
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
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { ExchangeCardSkeleton } from '@/components/ui/Skeleton';

/** Extractor for cursor-based ExchangeListResponse. */
function extractExchangePage(response: ExchangeListResponse) {
  return {
    items: response.data,
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
          <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={refresh} />
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
                <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>{t('common:buttons.retry')}</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyText}>{t('empty')}</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View style={styles.footer}>
              <ActivityIndicator size="small" color={theme.textMuted} />
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
      paddingHorizontal: 16,
      paddingTop: 16,
      paddingBottom: 8,
    },
    title: { fontSize: 22, fontWeight: '700', color: theme.text },
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
      marginHorizontal: 16,
      marginBottom: 8,
      borderRadius: 10,
      borderWidth: 1,
      borderColor: theme.border,
      paddingHorizontal: 12,
    },
    searchIcon: { marginRight: 8 },
    searchInput: { flex: 1, paddingVertical: 10, fontSize: 15, color: theme.text },
    list: { paddingBottom: 24 },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyText: { color: theme.textMuted, fontSize: 14, textAlign: 'center' },
    footer: { paddingVertical: 16, alignItems: 'center' },
  });
}
