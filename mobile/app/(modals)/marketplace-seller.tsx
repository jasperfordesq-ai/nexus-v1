// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { FlatList, Image, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';
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

type SellerTab = 'listings' | 'reviews';

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
  const [tab, setTab] = useState<SellerTab>('listings');
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
  const profile = seller.data?.data ?? null;

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
        data={tab === 'listings' ? listings.items : []}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        ListHeaderComponent={
          seller.isLoading ? (
            <View className="py-8"><LoadingSpinner /></View>
          ) : profile ? (
            <>
              <SellerHeader profile={profile} />
              <SellerTabs
                selected={tab}
                listingsCount={listings.items.length}
                reviewsCount={profile.total_ratings ?? 0}
                onSelect={setTab}
              />
            </>
          ) : (
            <EmptyState icon="storefront-outline" title={seller.error ?? t('seller.notFound')} subtitle={t('seller.notFoundHint')} />
          )
        }
        renderItem={({ item }) => (
          <MarketplaceListingCard item={item} onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.id) } } as unknown as Href)} />
        )}
        ListEmptyComponent={
          tab === 'reviews' ? (
            <ReviewsPlaceholder />
          ) : listings.isLoading ? (
            <View className="py-16"><LoadingSpinner /></View>
          ) : (
            <EmptyState icon="bag-handle-outline" title={listings.error ?? t('seller.empty')} subtitle={t('seller.emptyHint')} />
          )
        }
        onEndReached={tab === 'listings' ? listings.loadMore : undefined}
        onEndReachedThreshold={0.35}
      />
    </SafeAreaView>
  );
}

function SellerTabs({
  selected,
  listingsCount,
  reviewsCount,
  onSelect,
}: {
  selected: SellerTab;
  listingsCount: number;
  reviewsCount: number;
  onSelect: (tab: SellerTab) => void;
}) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();

  return (
    <View className="mb-3 flex-row gap-2">
      {(['listings', 'reviews'] as SellerTab[]).map((tab) => (
        <HeroButton
          key={tab}
          className="flex-1"
          variant={selected === tab ? 'primary' : 'secondary'}
          onPress={() => onSelect(tab)}
          style={selected === tab ? { backgroundColor: primary } : undefined}
        >
          <HeroButton.Label>{t(`seller.${tab}Tab`)}</HeroButton.Label>
          <Chip size="sm" variant="secondary">
            <Chip.Label>{String(tab === 'listings' ? listingsCount : reviewsCount)}</Chip.Label>
          </Chip>
        </HeroButton>
      ))}
    </View>
  );
}

function ReviewsPlaceholder() {
  const { t } = useTranslation('marketplace');
  return (
    <EmptyState
      icon="star-outline"
      title={t('seller.reviewsTitle')}
      subtitle={t('seller.reviewsHint')}
    />
  );
}

function SellerHeader({ profile }: { profile: MarketplaceSellerProfile }) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const memberSince = profile.member_since ? new Date(profile.member_since).toLocaleDateString(undefined, { month: 'short', year: 'numeric' }) : null;
  const joinedMarketplace = profile.joined_marketplace_at ? new Date(profile.joined_marketplace_at).toLocaleDateString(undefined, { month: 'short', year: 'numeric' }) : null;
  const trustScore = typeof profile.community_trust_score === 'number' ? Math.max(0, Math.min(100, profile.community_trust_score)) : null;
  return (
    <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
      {profile.cover_image_url ? (
        <Image source={{ uri: profile.cover_image_url }} className="h-32 w-full" resizeMode="cover" accessibilityLabel={t('seller.coverAlt', { name: profile.display_name })} />
      ) : (
        <View className="h-1.5" style={{ backgroundColor: primary }} />
      )}
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start gap-4">
          <Avatar uri={profile.avatar_url} name={profile.display_name} size={72} />
          <View className="min-w-0 flex-1 gap-2">
            <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('seller.eyebrow')}</Text>
            <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }} numberOfLines={2}>{profile.display_name}</Text>
            <View className="flex-row flex-wrap gap-2">
              <Chip size="sm" variant="secondary"><Chip.Label>{t(`seller.sellerType.${profile.seller_type}`, { defaultValue: profile.seller_type })}</Chip.Label></Chip>
              {profile.business_verified || profile.is_community_endorsed ? (
                <Chip size="sm" variant="secondary" style={{ backgroundColor: withAlpha(theme.success, 0.15) }}>
                  <Ionicons name="shield-checkmark-outline" size={12} color={theme.success} />
                  <Chip.Label style={{ color: theme.success }}>{t('seller.verified')}</Chip.Label>
                </Chip>
              ) : null}
            </View>
            {profile.bio ? <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={4}>{profile.bio}</Text> : null}
            {memberSince ? (
              <Text className="text-xs" style={{ color: theme.textSecondary }}>
                {t('seller.memberSince', { date: memberSince })}
              </Text>
            ) : null}
          </View>
        </View>

        {trustScore !== null && trustScore > 0 ? (
          <Surface variant="secondary" className="rounded-panel-inner p-3">
            <View className="flex-row items-center justify-between gap-3">
              <View className="min-w-0 flex-1">
                <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>{t('seller.communityTrust')}</Text>
                <View className="mt-1 flex-row items-center gap-1">
                  {[1, 2, 3, 4, 5].map((level) => (
                    <Ionicons key={level} name={level <= Math.round(trustScore / 20) ? 'star' : 'star-outline'} size={16} color={theme.warning} />
                  ))}
                </View>
              </View>
              <Text className="text-xl font-bold" style={{ color: theme.text }}>{t('seller.trustScore', { score: trustScore })}</Text>
            </View>
          </Surface>
        ) : null}

        <View className="flex-row flex-wrap gap-2">
          <SellerStat icon="bag-check-outline" label={t('seller.totalSales')} value={String(profile.total_sales ?? 0)} tone={primary} />
          <SellerStat icon="star-outline" label={t('seller.avgRating')} value={profile.avg_rating !== null && profile.avg_rating !== undefined ? profile.avg_rating.toFixed(1) : t('seller.na')} tone={theme.warning} />
          <SellerStat icon="time-outline" label={t('seller.responseTime')} value={profile.response_time_avg || t('seller.na')} tone="#14b8a6" />
          <SellerStat icon="storefront-outline" label={t('seller.activeListings')} value={String(profile.active_listings ?? 0)} tone="#8b5cf6" />
        </View>

        {joinedMarketplace ? (
          <Text className="text-xs" style={{ color: theme.textSecondary }}>
            {t('seller.joinedMarketplace', { date: joinedMarketplace })}
          </Text>
        ) : null}

        <HeroButton variant="primary" onPress={() => router.push({ pathname: '/(tabs)/messages', params: { to: String(profile.user_id), name: profile.display_name } } as unknown as Href)} style={{ backgroundColor: primary }}>
          <Ionicons name="chatbubble-outline" size={17} color="#fff" />
          <HeroButton.Label>{t('seller.message')}</HeroButton.Label>
        </HeroButton>
      </HeroCard.Body>
    </HeroCard>
  );
}

function SellerStat({ icon, label, value, tone }: { icon: React.ComponentProps<typeof Ionicons>['name']; label: string; value: string; tone: string }) {
  const theme = useTheme();
  return (
    <Surface variant="secondary" className="w-[48%] rounded-panel-inner p-3">
      <View className="mb-2 size-8 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(tone, 0.14) }}>
        <Ionicons name={icon} size={16} color={tone} />
      </View>
      <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={1}>{value}</Text>
      <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={2}>{label}</Text>
    </Surface>
  );
}
