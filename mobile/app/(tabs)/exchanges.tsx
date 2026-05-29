// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { FlatList, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface, Tabs } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getExchanges, type Exchange, type ExchangeListResponse, type ExchangeType } from '@/lib/api/exchanges';
import { useDebounce } from '@/lib/hooks/useDebounce';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import ExchangeCard from '@/components/ExchangeCard';
import OfflineBanner from '@/components/OfflineBanner';
import Input from '@/components/ui/Input';
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
  const { t } = useTranslation(['exchanges', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [search, setSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<'all' | ExchangeType>('all');
  const debouncedSearch = useDebounce(search, 400);

  const fetchExchanges = useCallback(
    (cursor: string | null) => getExchanges(cursor, {
      ...(debouncedSearch ? { search: debouncedSearch } : {}),
      ...(typeFilter !== 'all' ? { type: typeFilter } : {}),
    }),
    [debouncedSearch, typeFilter],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<Exchange, ExchangeListResponse>(fetchExchanges, extractExchangePage, [debouncedSearch, typeFilter]);

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

  const hasActiveFilters = Boolean(search.trim()) || typeFilter !== 'all';

  return (
    <SafeAreaView className="flex-1 bg-background">
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
        ListHeaderComponent={
          <View className="gap-3 pb-2">
            <HeroCard variant="default" className="mx-4 mt-4 overflow-hidden">
              <View className="h-1 w-full" style={{ backgroundColor: '#10B981' }} />
              <HeroCard.Body className="gap-4 px-4 py-4">
                <View className="flex-row items-start justify-between gap-4">
                  <View className="min-w-0 flex-1">
                    <View className="mb-2 flex-row items-center gap-2">
                      <View className="h-8 w-8 items-center justify-center rounded-full" style={{ backgroundColor: 'rgba(16, 185, 129, 0.16)' }}>
                        <Ionicons name="list-outline" size={18} color="#10B981" />
                      </View>
                      <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
                        {t('heroEyebrow')}
                      </Text>
                    </View>
                    <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                      {t('title')}
                    </Text>
                    <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                      {t('subtitle')}
                    </Text>
                  </View>
                  <HeroButton
                    isIconOnly
                    size="md"
                    variant="primary"
                    style={{ backgroundColor: primary }}
                    onPress={() => {
                      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                      router.push('/(modals)/new-exchange');
                    }}
                    accessibilityLabel={t('newListing')}
                  >
                    <Ionicons name="add" size={22} color="#fff" />
                  </HeroButton>
                </View>
              </HeroCard.Body>
            </HeroCard>

            <Surface variant="default" className="mx-4 gap-3 rounded-panel-inner p-3">
              <View className="flex-row items-center justify-between gap-3">
                <View className="min-w-0 flex-1">
                  <Text className="text-base font-semibold" style={{ color: theme.text }}>
                    {t('browse')}
                  </Text>
                  <Text className="mt-0.5 text-sm" style={{ color: theme.textSecondary }} numberOfLines={2}>
                    {t('filtersIntro')}
                  </Text>
                </View>
                <Chip size="sm" variant="soft" color="success">
                  <Ionicons name="swap-horizontal-outline" size={12} color="#10B981" />
                  <Chip.Label>{isLoading ? t('resultsLoading') : t('resultsCount', { count: items.length })}</Chip.Label>
                </Chip>
              </View>

              <Input
                value={search}
                onChangeText={setSearch}
                placeholder={t('searchPlaceholder')}
                placeholderTextColor={theme.textMuted}
                returnKeyType="search"
                clearButtonMode="while-editing"
                accessibilityLabel={t('searchPlaceholder')}
                style={{ color: theme.text }}
                leftIcon={<Ionicons name="search-outline" size={18} color={theme.textMuted} />}
                rightIcon={search ? (
                  <HeroButton isIconOnly size="sm" variant="ghost" onPress={() => setSearch('')} accessibilityLabel={t('clearSearch')}>
                    <Ionicons name="close-circle-outline" size={18} color={theme.textMuted} />
                  </HeroButton>
                ) : null}
              />

              <Tabs value={typeFilter} onValueChange={(value) => setTypeFilter(value as 'all' | ExchangeType)} variant="secondary">
                <Tabs.List>
                  <Tabs.Indicator />
                  <Tabs.Trigger value="all">
                    <Ionicons name="apps-outline" size={15} color={typeFilter === 'all' ? primary : theme.textMuted} />
                    <Tabs.Label>{t('filterAllTypes')}</Tabs.Label>
                  </Tabs.Trigger>
                  <Tabs.Trigger value="offer">
                    <Ionicons name="gift-outline" size={15} color={typeFilter === 'offer' ? '#10B981' : theme.textMuted} />
                    <Tabs.Label>{t('offer')}</Tabs.Label>
                  </Tabs.Trigger>
                  <Tabs.Trigger value="request">
                    <Ionicons name="hand-left-outline" size={15} color={typeFilter === 'request' ? '#F59E0B' : theme.textMuted} />
                    <Tabs.Label>{t('request')}</Tabs.Label>
                  </Tabs.Trigger>
                </Tabs.List>
              </Tabs>

              {hasActiveFilters ? (
                <HeroButton
                  size="sm"
                  variant="ghost"
                  onPress={() => {
                    setSearch('');
                    setTypeFilter('all');
                  }}
                >
                  <Ionicons name="close-outline" size={16} color={theme.textMuted} />
                  <HeroButton.Label style={{ color: theme.textMuted }}>{t('clearFilters')}</HeroButton.Label>
                </HeroButton>
              ) : null}
            </Surface>
          </View>
        }
        ListEmptyComponent={
          isLoading ? (
            <><ExchangeCardSkeleton /><ExchangeCardSkeleton /><ExchangeCardSkeleton /></>
          ) : error ? (
            <HeroCard variant="secondary" className="mx-4 my-8">
              <HeroCard.Body className="items-center gap-4">
                <Ionicons name="warning-outline" size={30} color={primary} />
                <Text className="text-center text-sm leading-5 text-danger">{error}</Text>
                <HeroButton variant="primary" onPress={() => void refresh()} style={{ backgroundColor: primary }}>
                  <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
                </HeroButton>
              </HeroCard.Body>
            </HeroCard>
          ) : (
            <HeroCard variant="secondary" className="mx-4 my-8">
              <HeroCard.Body className="items-center gap-3">
                <Ionicons name="search-outline" size={34} color={primary} />
                <Text className="text-center text-[17px] font-semibold" style={{ color: theme.text }}>
                  {t('empty')}
                </Text>
                <Text className="text-center text-sm leading-5" style={{ color: theme.textSecondary }}>
                  {hasActiveFilters ? t('emptySubtitle') : t('emptyNoListings')}
                </Text>
                {hasActiveFilters ? (
                  <HeroButton size="sm" variant="secondary" onPress={() => {
                    setSearch('');
                    setTypeFilter('all');
                  }}>
                    <HeroButton.Label>{t('clearFilters')}</HeroButton.Label>
                  </HeroButton>
                ) : (
                  <HeroButton size="sm" variant="primary" style={{ backgroundColor: primary }} onPress={() => router.push('/(modals)/new-exchange')}>
                    <Ionicons name="add" size={16} color="#fff" />
                    <HeroButton.Label>{t('newListing')}</HeroButton.Label>
                  </HeroButton>
                )}
              </HeroCard.Body>
            </HeroCard>
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
