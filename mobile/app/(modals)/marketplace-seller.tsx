// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { FlatList, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Card as HeroCard, Chip, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import MarketplaceListingCard from '@/components/marketplace/MarketplaceListingCard';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import {
  getMarketplaceSeller,
  getMarketplaceSellerListings,
  marketplaceHasMore,
  marketplaceNextCursor,
  type MarketplaceListingItem,
  type MarketplaceSellerProfile,
} from '@/lib/api/marketplace';
import { useApi } from '@/lib/hooks/useApi';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

export default function MarketplaceSellerRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceSellerScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceSellerScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const params = useLocalSearchParams<{ id?: string }>();
  const sellerId = Number(params.id);
  const safeId = Number.isFinite(sellerId) && sellerId > 0 ? sellerId : 0;
  const seller = useApi(() => getMarketplaceSeller(safeId), [safeId], { enabled: safeId > 0 });
  const listings = usePaginatedApi<MarketplaceListingItem, Awaited<ReturnType<typeof getMarketplaceSellerListings>>>(
    (cursor) => getMarketplaceSellerListings(safeId, cursor),
    (response) => ({
      items: response.data,
      cursor: marketplaceNextCursor(response),
      hasMore: marketplaceHasMore(response),
    }),
    [safeId],
  );

  if (!safeId) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('seller.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
        <EmptyState icon="storefront-outline" title={t('seller.notFound')} subtitle={t('seller.notFoundHint')} />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('seller.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
      <FlatList
        data={listings.items}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        ListHeaderComponent={
          seller.isLoading ? (
            <View className="py-8"><LoadingSpinner /></View>
          ) : seller.data?.data ? (
            <SellerHeader profile={seller.data.data} />
          ) : (
            <EmptyState icon="storefront-outline" title={seller.error ?? t('seller.notFound')} subtitle={t('seller.notFoundHint')} />
          )
        }
        renderItem={({ item }) => (
          <MarketplaceListingCard item={item} onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.id) } } as unknown as Href)} />
        )}
        ListEmptyComponent={
          listings.isLoading ? (
            <View className="py-16"><LoadingSpinner /></View>
          ) : (
            <EmptyState icon="bag-handle-outline" title={listings.error ?? t('seller.empty')} subtitle={t('seller.emptyHint')} />
          )
        }
        onEndReached={listings.loadMore}
        onEndReachedThreshold={0.35}
      />
    </SafeAreaView>
  );
}

function SellerHeader({ profile }: { profile: MarketplaceSellerProfile }) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  return (
    <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: primary }} />
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start gap-4">
          <Avatar uri={profile.avatar_url} name={profile.display_name} size={72} />
          <View className="min-w-0 flex-1 gap-2">
            <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('seller.eyebrow')}</Text>
            <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }} numberOfLines={2}>{profile.display_name}</Text>
            {profile.bio ? <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={4}>{profile.bio}</Text> : null}
          </View>
        </View>
        <View className="flex-row flex-wrap gap-2">
          <Chip size="sm" variant="secondary"><Ionicons name="star-outline" size={12} color={theme.warning} /><Chip.Label>{t('seller.rating', { rating: profile.avg_rating ?? 0 })}</Chip.Label></Chip>
          <Chip size="sm" variant="secondary"><Ionicons name="bag-check-outline" size={12} color={primary} /><Chip.Label>{t('seller.sales', { count: profile.total_sales ?? 0 })}</Chip.Label></Chip>
          <Chip size="sm" variant="secondary"><Ionicons name="storefront-outline" size={12} color={primary} /><Chip.Label>{t('seller.active', { count: profile.active_listings ?? 0 })}</Chip.Label></Chip>
          {profile.business_verified ? <Chip size="sm" variant="secondary"><Ionicons name="shield-checkmark-outline" size={12} color={theme.success} /><Chip.Label>{t('seller.verified')}</Chip.Label></Chip> : null}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}
