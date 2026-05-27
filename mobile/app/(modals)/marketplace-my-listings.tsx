// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Alert, FlatList, RefreshControl, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import MarketplaceListingCard from '@/components/marketplace/MarketplaceListingCard';
import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import {
  deleteMarketplaceListing,
  getMarketplaceDashboard,
  getMerchantOnboardingStatus,
  getMyMarketplaceListings,
  marketplaceHasMore,
  marketplaceNextCursor,
  renewMarketplaceListing,
  type MarketplaceDashboard,
  type MarketplaceListingItem,
} from '@/lib/api/marketplace';
import { useApi } from '@/lib/hooks/useApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { storage } from '@/lib/storage';
import { withAlpha } from '@/lib/utils/color';

type ListingTab = 'active' | 'draft' | 'sold' | 'expired';

const LISTING_TABS: ListingTab[] = ['active', 'draft', 'sold', 'expired'];
const ONBOARDING_DISMISS_KEY = 'nx_merchant_onboarding_dismissed';

export default function MarketplaceMyListingsRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceMyListingsScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceMyListingsScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState<ListingTab>('active');
  const [onboardingDismissed, setOnboardingDismissed] = useState(true);
  const dashboard = useApi(() => getMarketplaceDashboard(), [], { enabled: true });
  const onboarding = useApi(() => getMerchantOnboardingStatus(), [], { enabled: true });
  const list = usePaginatedApi<MarketplaceListingItem, Awaited<ReturnType<typeof getMyMarketplaceListings>>>(
    (cursor) => getMyMarketplaceListings(cursor, user?.id, activeTab),
    (response) => ({
      items: response.data,
      cursor: marketplaceNextCursor(response),
      hasMore: marketplaceHasMore(response),
    }),
    [user?.id, activeTab],
  );

  const stats = dashboard.data?.data ?? {};
  const showOnboardingNudge = !onboardingDismissed && onboarding.data?.data && !onboarding.data.data.onboarding_completed;

  useEffect(() => {
    let mounted = true;
    storage.get(ONBOARDING_DISMISS_KEY).then((value) => {
      if (mounted) setOnboardingDismissed(value === '1');
    });
    return () => {
      mounted = false;
    };
  }, []);

  function dismissOnboardingNudge() {
    setOnboardingDismissed(true);
    void storage.set(ONBOARDING_DISMISS_KEY, '1');
  }

  async function removeListing(item: MarketplaceListingItem) {
    Alert.alert(t('owner.deleteTitle'), t('owner.deleteMessage'), [
      { text: t('common:buttons.cancel'), style: 'cancel' },
      {
        text: t('common:buttons.delete'),
        style: 'destructive',
        onPress: async () => {
          try {
            await deleteMarketplaceListing(item.id);
            list.refresh();
          } catch (err) {
            Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('owner.deleteFailed'));
          }
        },
      },
    ]);
  }

  async function renew(item: MarketplaceListingItem) {
    try {
      await renewMarketplaceListing(item.id);
      list.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('owner.renewFailed'));
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar
        title={t('myListings.title')}
        backLabel={t('common:back')}
        fallbackHref={'/(modals)/marketplace' as Href}
        rightAction={{
          accessibilityLabel: t('actions.sell'),
          icon: 'add-outline',
          onPress: () => router.push('/(modals)/new-marketplace-listing' as Href),
        }}
      />
      <FlatList
        data={list.items}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        refreshControl={<RefreshControl refreshing={list.isLoading && list.items.length > 0} onRefresh={list.refresh} />}
        ListHeaderComponent={
          <>
            <DashboardCard stats={stats} primary={primary} activeTab={activeTab} onTabChange={setActiveTab} />
            {showOnboardingNudge ? <OnboardingNudge primary={primary} onDismiss={dismissOnboardingNudge} /> : null}
          </>
        }
        renderItem={({ item }) => (
          <View>
            <MarketplaceListingCard item={item} onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.id) } } as unknown as Href)} />
            <View className="mb-3 flex-row gap-2">
              <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => router.push({ pathname: '/(modals)/edit-marketplace-listing', params: { id: String(item.id) } } as unknown as Href)}>
                <Ionicons name="create-outline" size={14} color={primary} />
                <HeroButton.Label>{t('owner.edit')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => void renew(item)}>
                <Ionicons name="refresh-outline" size={14} color={primary} />
                <HeroButton.Label>{t('owner.renew')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" size="sm" variant="danger" onPress={() => void removeListing(item)}>
                <Ionicons name="trash-outline" size={14} color="#fff" />
                <HeroButton.Label>{t('owner.delete')}</HeroButton.Label>
              </HeroButton>
            </View>
          </View>
        )}
        ListEmptyComponent={
          list.isLoading ? (
            <View className="py-16">
              <LoadingSpinner />
            </View>
          ) : (
            <EmptyState
              icon="albums-outline"
              title={list.error ?? t(`myListings.emptyState.${activeTab}.title`)}
              subtitle={t(`myListings.emptyState.${activeTab}.subtitle`)}
              actionLabel={activeTab === 'active' || activeTab === 'draft' ? t('actions.sell') : undefined}
              onAction={activeTab === 'active' || activeTab === 'draft' ? () => router.push('/(modals)/new-marketplace-listing' as Href) : undefined}
            />
          )
        }
        ListFooterComponent={
          list.isLoadingMore ? (
            <LoadingSpinner />
          ) : list.hasMore ? (
            <HeroButton variant="secondary" onPress={list.loadMore}>
              <HeroButton.Label>{t('loadMore')}</HeroButton.Label>
            </HeroButton>
          ) : null
        }
        onEndReached={list.loadMore}
        onEndReachedThreshold={0.35}
      />
    </SafeAreaView>
  );
}

function DashboardCard({
  stats,
  primary,
  activeTab,
  onTabChange,
}: {
  stats: MarketplaceDashboard;
  primary: string;
  activeTab: ListingTab;
  onTabChange: (tab: ListingTab) => void;
}) {
  const { t } = useTranslation('marketplace');
  const theme = useTheme();
  return (
    <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: primary }} />
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start gap-3">
          <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name="storefront-outline" size={25} color={primary} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('myListings.eyebrow')}</Text>
            <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('myListings.title')}</Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('myListings.subtitle')}</Text>
          </View>
        </View>
        <View className="flex-row flex-wrap gap-2">
          <Chip size="sm" variant="secondary"><Chip.Label>{t('myListings.active', { count: stats.active_listings ?? 0 })}</Chip.Label></Chip>
          <Chip size="sm" variant="secondary"><Chip.Label>{t('myListings.sold', { count: stats.sold_listings ?? 0 })}</Chip.Label></Chip>
          <Chip size="sm" variant="secondary"><Chip.Label>{t('myListings.views', { count: stats.total_views ?? stats.views_30d ?? 0 })}</Chip.Label></Chip>
          <Chip size="sm" variant="secondary"><Chip.Label>{t('myListings.offers', { count: stats.pending_offers ?? 0 })}</Chip.Label></Chip>
        </View>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
          {LISTING_TABS.map((tab) => (
            <HeroButton
              key={tab}
              size="sm"
              variant={activeTab === tab ? 'primary' : 'secondary'}
              onPress={() => onTabChange(tab)}
              style={activeTab === tab ? { backgroundColor: primary } : undefined}
            >
              <HeroButton.Label>{t(`myListings.tabs.${tab}`, { count: listingTabCount(stats, tab) })}</HeroButton.Label>
            </HeroButton>
          ))}
        </ScrollView>
        <View className="flex-row gap-2">
          <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-merchant-onboarding' as Href)}>
            <Ionicons name="storefront-outline" size={16} color={primary} />
            <HeroButton.Label>{t('myListings.onboarding')}</HeroButton.Label>
          </HeroButton>
          <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-stripe-onboarding' as Href)}>
            <Ionicons name="card-outline" size={16} color={primary} />
            <HeroButton.Label>{t('myListings.payments')}</HeroButton.Label>
          </HeroButton>
        </View>
        <View className="flex-row gap-2">
          <HeroButton className="flex-1" variant="secondary" onPress={() => router.push({ pathname: '/(modals)/marketplace-orders', params: { mode: 'sales' } } as unknown as Href)}>
            <Ionicons name="receipt-outline" size={16} color={primary} />
            <HeroButton.Label>{t('myListings.salesOrders')}</HeroButton.Label>
          </HeroButton>
          <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-offers' as Href)}>
            <Ionicons name="chatbubbles-outline" size={16} color={primary} />
            <HeroButton.Label>{t('myListings.offersCta')}</HeroButton.Label>
          </HeroButton>
        </View>
        <View className="flex-row gap-2">
          <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-tools' as Href)}>
            <Ionicons name="construct-outline" size={16} color={primary} />
            <HeroButton.Label>{t('myListings.sellerTools')}</HeroButton.Label>
          </HeroButton>
          <HeroButton className="flex-1" variant="secondary" onPress={() => router.push('/(modals)/marketplace-shipping-options' as Href)}>
            <Ionicons name="car-outline" size={16} color={primary} />
            <HeroButton.Label>{t('myListings.shipping')}</HeroButton.Label>
          </HeroButton>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function OnboardingNudge({ primary, onDismiss }: { primary: string; onDismiss: () => void }) {
  const { t } = useTranslation('marketplace');
  const theme = useTheme();
  return (
    <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: primary }} />
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start gap-3">
          <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
            <Ionicons name="ribbon-outline" size={24} color={primary} />
          </View>
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-base font-bold" style={{ color: theme.text }}>{t('myListings.onboardingNudge.title')}</Text>
            <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('myListings.onboardingNudge.subtitle')}</Text>
          </View>
        </View>
        <View className="flex-row gap-2">
          <HeroButton className="flex-1" variant="primary" onPress={() => router.push('/(modals)/marketplace-merchant-onboarding' as Href)} style={{ backgroundColor: primary }}>
            <Ionicons name="storefront-outline" size={16} color="#fff" />
            <HeroButton.Label>{t('myListings.onboardingNudge.start')}</HeroButton.Label>
          </HeroButton>
          <HeroButton className="flex-1" variant="secondary" onPress={onDismiss}>
            <HeroButton.Label>{t('myListings.onboardingNudge.dismiss')}</HeroButton.Label>
          </HeroButton>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function listingTabCount(stats: MarketplaceDashboard, tab: ListingTab): number {
  if (tab === 'active') return stats.active_listings ?? 0;
  if (tab === 'draft') return stats.draft_listings ?? 0;
  if (tab === 'sold') return stats.sold_listings ?? 0;
  return stats.expired_listings ?? 0;
}
