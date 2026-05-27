// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Alert, FlatList, RefreshControl, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import MarketplaceListingCard from '@/components/marketplace/MarketplaceListingCard';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import {
  getMarketplaceListings,
  marketplaceHasMore,
  marketplaceNextCursor,
  saveMarketplaceListing,
  unsaveMarketplaceListing,
  type MarketplaceCondition,
  type MarketplaceListingItem,
} from '@/lib/api/marketplace';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

const CONDITION_FILTERS: Array<MarketplaceCondition | ''> = ['', 'new', 'like_new', 'good', 'fair'];

export default function MarketplaceCategoryRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceCategoryScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceCategoryScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const { hasFeature } = useTenant();
  const params = useLocalSearchParams<{ id?: string; name?: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const categoryId = Number(params.id);
  const safeCategoryId = Number.isFinite(categoryId) && categoryId > 0 ? categoryId : 0;
  const categoryName = typeof params.name === 'string' && params.name.trim() ? params.name : t('category.title');
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [condition, setCondition] = useState<MarketplaceCondition | ''>('');
  const [listings, setListings] = useState<MarketplaceListingItem[]>([]);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query.trim()), 300);
    return () => clearTimeout(timer);
  }, [query]);

  const fetchListings = useCallback(async (append = false) => {
    if (!hasFeature('marketplace') || !safeCategoryId) return;
    if (append) setIsLoadingMore(true);
    else setIsLoading(true);
    setError(null);

    try {
      const response = await getMarketplaceListings({
        q: debouncedQuery,
        category_id: safeCategoryId,
        condition,
        cursor: append ? cursor : null,
        limit: 20,
        sort: 'newest',
      });
      setCursor(marketplaceNextCursor(response));
      setHasMore(marketplaceHasMore(response));
      setListings((current) => append ? [...current, ...response.data] : response.data);
    } catch (err) {
      if (!append) {
        setError(err instanceof Error ? err.message : t('category.unableToLoad'));
      } else {
        Alert.alert(t('common:errors.alertTitle'), t('category.loadMoreFailed'));
      }
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
      setIsLoadingMore(false);
    }
  }, [condition, cursor, debouncedQuery, hasFeature, safeCategoryId, t]);

  useEffect(() => {
    void fetchListings(false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debouncedQuery, condition, safeCategoryId]);

  async function toggleSave(item: MarketplaceListingItem) {
    const nextSaved = !item.is_saved;
    setListings((current) => current.map((listing) => listing.id === item.id ? { ...listing, is_saved: nextSaved } : listing));
    try {
      if (nextSaved) await saveMarketplaceListing(item.id);
      else await unsaveMarketplaceListing(item.id);
    } catch {
      setListings((current) => current.map((listing) => listing.id === item.id ? item : listing));
      Alert.alert(t('common:errors.alertTitle'), t('common.save_failed'));
    }
  }

  if (!hasFeature('marketplace') || !safeCategoryId) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('category.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
        <EmptyState icon="grid-outline" title={t('category.notFound')} subtitle={t('category.notFoundHint')} />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={categoryName} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />

      <FlatList
        data={listings}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={() => { setIsRefreshing(true); void fetchListings(false); }} />}
        ListHeaderComponent={
          <View>
            <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
              <View className="h-1.5" style={{ backgroundColor: primary }} />
              <HeroCard.Body className="gap-3 p-4">
                <View className="flex-row items-start gap-3">
                  <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                    <Ionicons name="grid-outline" size={25} color={primary} />
                  </View>
                  <View className="min-w-0 flex-1 gap-1">
                    <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('category.eyebrow')}</Text>
                    <Text className="text-2xl font-bold" style={{ color: theme.text }}>{categoryName}</Text>
                    <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('category.subtitle')}</Text>
                  </View>
                </View>
              </HeroCard.Body>
            </HeroCard>

            <Surface variant="secondary" className="mb-3 rounded-panel px-3 py-3">
              <View className="flex-row items-center gap-2">
                <Ionicons name="search-outline" size={18} color={theme.textMuted} />
                <TextInput
                  className="min-h-10 flex-1 text-sm"
                  style={{ color: theme.text }}
                  placeholder={t('search.placeholder')}
                  placeholderTextColor={theme.textMuted}
                  value={query}
                  onChangeText={setQuery}
                  returnKeyType="search"
                />
              </View>
            </Surface>

            <View className="mb-3 flex-row flex-wrap gap-2">
              {CONDITION_FILTERS.map((value) => (
                <HeroButton
                  key={value || 'all'}
                  size="sm"
                  variant={condition === value ? 'primary' : 'secondary'}
                  onPress={() => setCondition(value)}
                  style={condition === value ? { backgroundColor: primary } : undefined}
                >
                  <HeroButton.Label>{value ? t(`condition.${value}`) : t('category.allConditions')}</HeroButton.Label>
                </HeroButton>
              ))}
            </View>
          </View>
        }
        renderItem={({ item }) => (
          <MarketplaceListingCard
            item={item}
            onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.id) } } as unknown as Href)}
            onSavePress={() => void toggleSave(item)}
          />
        )}
        ListEmptyComponent={
          isLoading ? (
            <View className="py-16"><LoadingSpinner /></View>
          ) : (
            <EmptyState
              icon="grid-outline"
              title={error ?? t('category.emptyTitle')}
              subtitle={t('category.emptySubtitle')}
              actionLabel={error ? t('common:buttons.retry') : t('actions.browse')}
              onAction={error ? () => void fetchListings(false) : () => router.replace('/(modals)/marketplace' as Href)}
            />
          )
        }
        onEndReached={() => {
          if (hasMore && !isLoadingMore) void fetchListings(true);
        }}
        onEndReachedThreshold={0.35}
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4"><LoadingSpinner /></View>
          ) : hasMore ? (
            <HeroButton variant="secondary" onPress={() => void fetchListings(true)}>
              <HeroButton.Label>{t('loadMore')}</HeroButton.Label>
            </HeroButton>
          ) : null
        }
      />
    </SafeAreaView>
  );
}
