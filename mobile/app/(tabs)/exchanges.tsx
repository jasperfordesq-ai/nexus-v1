// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { FlatList, Pressable, RefreshControl, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { Spinner } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getExchanges, type Exchange, type ExchangeListResponse } from '@/lib/api/exchanges';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import ExchangeCard from '@/components/ExchangeCard';
import OfflineBanner from '@/components/OfflineBanner';
import EmptyState from '@/components/ui/EmptyState';
import { ExchangeCardSkeleton } from '@/components/ui/Skeleton';

function extractExchangePage(response: ExchangeListResponse) {
  const seen = new Set<number>();
  const unique = response.data.filter((item) => {
    if (seen.has(item.id)) return false;
    seen.add(item.id);
    return true;
  });
  return { items: unique, cursor: response.meta.cursor, hasMore: response.meta.has_more };
}

export default function ExchangesScreen() {
  const { t } = useTranslation('exchanges');
  const primary = usePrimaryColor();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);

  const fetchExchanges = useCallback(
    (cursor: string | null) => getExchanges(cursor, debouncedSearch ? { search: debouncedSearch } : undefined),
    [debouncedSearch],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Exchange, ExchangeListResponse>(fetchExchanges, extractExchangePage, [debouncedSearch]);

  const [isRefreshing, setIsRefreshing] = useState(false);
  const wasRefreshingRef = useRef(false);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    wasRefreshingRef.current = true;
    refresh();
  }, [refresh]);

  useEffect(() => {
    if (wasRefreshingRef.current && !isLoading) {
      wasRefreshingRef.current = false;
      setIsRefreshing(false);
    }
  }, [isLoading]);

  return (
    <SafeAreaView className="flex-1 bg-background">
      <View className="flex-row items-center justify-between px-4 pt-4 pb-2">
        <Text className="text-xl font-bold text-foreground">{t('title')}</Text>
        <Pressable
          className="w-9 h-9 rounded-full items-center justify-center"
          style={{ backgroundColor: primary }}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push('/(modals)/new-exchange');
          }}
          accessibilityLabel={t('newListing')}
          accessibilityRole="button"
        >
          <Ionicons name="add" size={20} color="#fff" />
        </Pressable>
      </View>

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
            <><ExchangeCardSkeleton /><ExchangeCardSkeleton /><ExchangeCardSkeleton /></>
          ) : error ? (
            <View className="flex-1 items-center justify-center p-8">
              <Text className="text-danger text-sm text-center mb-3">{error}</Text>
              <Pressable onPress={() => void refresh()} className="px-5 py-2.5">
                <Text className="font-semibold" style={{ color: primary }}>{t('common:buttons.retry')}</Text>
              </Pressable>
            </View>
          ) : (
            <EmptyState icon="swap-horizontal-outline" title={t('empty')} />
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
