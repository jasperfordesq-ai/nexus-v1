// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Alert, FlatList, Pressable, RefreshControl, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import MarketplaceListingCard from '@/components/marketplace/MarketplaceListingCard';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import {
  deleteMarketplaceSavedSearch,
  getMarketplaceCollectionItems,
  getMarketplaceCollections,
  getMarketplaceSavedSearches,
  removeMarketplaceCollectionItem,
  type MarketplaceCollection,
  type MarketplaceCollectionItem,
  type MarketplaceSavedSearch,
} from '@/lib/api/marketplace';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

type TabKey = 'collections' | 'saved';

export default function MarketplaceCollectionsRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceCollectionsScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceCollectionsScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const { hasFeature } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [tab, setTab] = useState<TabKey>('collections');
  const [collections, setCollections] = useState<MarketplaceCollection[]>([]);
  const [savedSearches, setSavedSearches] = useState<MarketplaceSavedSearch[]>([]);
  const [selectedCollection, setSelectedCollection] = useState<MarketplaceCollection | null>(null);
  const [items, setItems] = useState<MarketplaceCollectionItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isLoadingItems, setIsLoadingItems] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!hasFeature('marketplace')) return;
    setIsLoading(true);
    setError(null);
    try {
      const [collectionResponse, savedResponse] = await Promise.all([
        getMarketplaceCollections(),
        getMarketplaceSavedSearches(),
      ]);
      setCollections(collectionResponse.data);
      setSavedSearches(savedResponse.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : t('collections.unableToLoad'));
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, [hasFeature, t]);

  useEffect(() => {
    void load();
  }, [load]);

  async function openCollection(collection: MarketplaceCollection) {
    setSelectedCollection(collection);
    setIsLoadingItems(true);
    setItems([]);
    try {
      const response = await getMarketplaceCollectionItems(collection.id, null, 50);
      setItems(response.data);
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('collections.itemsLoadFailed'));
    } finally {
      setIsLoadingItems(false);
    }
  }

  async function removeItem(item: MarketplaceCollectionItem) {
    if (!selectedCollection) return;
    try {
      await removeMarketplaceCollectionItem(selectedCollection.id, item.listing.id);
      setItems((current) => current.filter((entry) => entry.listing.id !== item.listing.id));
      setCollections((current) => current.map((collection) => collection.id === selectedCollection.id
        ? { ...collection, item_count: Math.max(0, collection.item_count - 1) }
        : collection));
      setSelectedCollection((current) => current ? { ...current, item_count: Math.max(0, current.item_count - 1) } : current);
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('collections.removeItemFailed'));
    }
  }

  async function deleteSavedSearch(search: MarketplaceSavedSearch) {
    try {
      await deleteMarketplaceSavedSearch(search.id);
      setSavedSearches((current) => current.filter((entry) => entry.id !== search.id));
    } catch {
      Alert.alert(t('common:errors.alertTitle'), t('savedSearches.deleteFailed'));
    }
  }

  function runSavedSearch(search: MarketplaceSavedSearch) {
    router.push({ pathname: '/(modals)/marketplace', params: { q: search.search_query ?? '' } } as unknown as Href);
  }

  if (!hasFeature('marketplace')) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('collections.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
        <EmptyState icon="folder-open-outline" title={t('featureGate.title')} subtitle={t('featureGate.description')} />
      </SafeAreaView>
    );
  }

  if (selectedCollection) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={selectedCollection.name} backLabel={t('common:back')} onBack={() => setSelectedCollection(null)} fallbackHref={'/(modals)/marketplace-collections' as Href} />
        <FlatList
          data={items}
          keyExtractor={(item) => `${selectedCollection.id}-${item.listing.id}`}
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
          ListHeaderComponent={
            <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
              <View className="h-1.5" style={{ backgroundColor: primary }} />
              <HeroCard.Body className="gap-3 p-4">
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{selectedCollection.name}</Text>
                {selectedCollection.description ? (
                  <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{selectedCollection.description}</Text>
                ) : null}
                <View className="flex-row flex-wrap gap-2">
                  <Chip size="sm" variant="secondary"><Chip.Label>{t('collections.count', { count: selectedCollection.item_count })}</Chip.Label></Chip>
                  <Chip size="sm" variant="secondary"><Chip.Label>{selectedCollection.is_public ? t('collections.public') : t('collections.private')}</Chip.Label></Chip>
                </View>
              </HeroCard.Body>
            </HeroCard>
          }
          renderItem={({ item }) => (
            <View>
              <MarketplaceListingCard
                item={item.listing}
                onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.listing.id) } } as unknown as Href)}
              />
              <HeroButton className="-mt-2 mb-3" variant="secondary" onPress={() => void removeItem(item)}>
                <Ionicons name="trash-outline" size={16} color={theme.error} />
                <HeroButton.Label>{t('collections.removeItem')}</HeroButton.Label>
              </HeroButton>
            </View>
          )}
          ListEmptyComponent={isLoadingItems ? <View className="py-16"><LoadingSpinner /></View> : (
            <EmptyState icon="folder-open-outline" title={t('collections.emptyItems')} subtitle={t('collections.emptyItemsHint')} />
          )}
        />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('collections.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />

      <FlatList
        data={tab === 'collections' ? collections : []}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        refreshControl={<RefreshControl refreshing={isRefreshing} onRefresh={() => { setIsRefreshing(true); void load(); }} />}
        ListHeaderComponent={
          <View>
            <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
              <View className="h-1.5" style={{ backgroundColor: primary }} />
              <HeroCard.Body className="gap-3 p-4">
                <View className="flex-row items-start gap-3">
                  <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                    <Ionicons name="folder-open-outline" size={25} color={primary} />
                  </View>
                  <View className="min-w-0 flex-1 gap-1">
                    <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('collections.eyebrow')}</Text>
                    <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('collections.title')}</Text>
                    <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('collections.subtitle')}</Text>
                  </View>
                </View>
                <View className="flex-row gap-2">
                  <HeroButton className="flex-1" variant={tab === 'collections' ? 'primary' : 'secondary'} onPress={() => setTab('collections')} style={tab === 'collections' ? { backgroundColor: primary } : undefined}>
                    <HeroButton.Label>{t('collections.collectionsTab')}</HeroButton.Label>
                  </HeroButton>
                  <HeroButton className="flex-1" variant={tab === 'saved' ? 'primary' : 'secondary'} onPress={() => setTab('saved')} style={tab === 'saved' ? { backgroundColor: primary } : undefined}>
                    <HeroButton.Label>{t('collections.savedTab')}</HeroButton.Label>
                  </HeroButton>
                </View>
                <HeroButton variant="secondary" onPress={() => router.push('/(modals)/marketplace-tools' as Href)}>
                  <Ionicons name="add-outline" size={16} color={primary} />
                  <HeroButton.Label>{t('collections.manage')}</HeroButton.Label>
                </HeroButton>
              </HeroCard.Body>
            </HeroCard>

            {tab === 'saved' ? (
              <View className="gap-3">
                {savedSearches.map((search) => (
                  <SavedSearchRow key={search.id} search={search} onRun={() => runSavedSearch(search)} onDelete={() => void deleteSavedSearch(search)} />
                ))}
                {!isLoading && savedSearches.length === 0 ? (
                  <EmptyState icon="search-outline" title={t('savedSearches.empty')} subtitle={t('savedSearches.emptyHint')} actionLabel={t('collections.manage')} onAction={() => router.push('/(modals)/marketplace-tools' as Href)} />
                ) : null}
              </View>
            ) : null}
          </View>
        }
        renderItem={({ item }) => <CollectionRow collection={item} onPress={() => void openCollection(item)} />}
        ListEmptyComponent={
          isLoading ? (
            <View className="py-16"><LoadingSpinner /></View>
          ) : tab === 'collections' ? (
            <EmptyState
              icon="folder-open-outline"
              title={error ?? t('collections.empty')}
              subtitle={t('collections.emptyHint')}
              actionLabel={error ? t('common:buttons.retry') : t('collections.manage')}
              onAction={error ? () => void load() : () => router.push('/(modals)/marketplace-tools' as Href)}
            />
          ) : null
        }
      />
    </SafeAreaView>
  );
}

function CollectionRow({ collection, onPress }: { collection: MarketplaceCollection; onPress: () => void }) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  return (
    <Pressable accessibilityRole="button" accessibilityLabel={collection.name} onPress={onPress}>
      <HeroCard className="mb-3 rounded-panel p-0">
        <HeroCard.Body className="gap-2 p-4">
          <View className="flex-row items-center gap-3">
            <View className="size-11 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
              <Ionicons name="folder-outline" size={21} color={primary} />
            </View>
            <View className="min-w-0 flex-1">
              <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>{collection.name}</Text>
              {collection.description ? <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={2}>{collection.description}</Text> : null}
            </View>
            <Ionicons name="chevron-forward-outline" size={18} color={theme.textMuted} />
          </View>
          <View className="flex-row flex-wrap gap-2">
            <Chip size="sm" variant="secondary"><Chip.Label>{t('collections.count', { count: collection.item_count })}</Chip.Label></Chip>
            <Chip size="sm" variant="secondary"><Chip.Label>{collection.is_public ? t('collections.public') : t('collections.private')}</Chip.Label></Chip>
          </View>
        </HeroCard.Body>
      </HeroCard>
    </Pressable>
  );
}

function SavedSearchRow({
  search,
  onRun,
  onDelete,
}: {
  search: MarketplaceSavedSearch;
  onRun: () => void;
  onDelete: () => void;
}) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  return (
    <HeroCard className="rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-center gap-3">
          <View className="size-11 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name="search-outline" size={21} color={primary} />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>{search.name}</Text>
            <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>{search.search_query || t('savedSearches.anything')}</Text>
          </View>
        </View>
        <View className="flex-row gap-2">
          <HeroButton className="flex-1" variant="primary" onPress={onRun} style={{ backgroundColor: primary }}>
            <Ionicons name="play-outline" size={16} color="#fff" />
            <HeroButton.Label>{t('savedSearches.run')}</HeroButton.Label>
          </HeroButton>
          <HeroButton className="flex-1" variant="secondary" onPress={onDelete}>
            <Ionicons name="trash-outline" size={16} color={theme.error} />
            <HeroButton.Label>{t('tools.delete')}</HeroButton.Label>
          </HeroButton>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}
