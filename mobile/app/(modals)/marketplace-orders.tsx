// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { FlatList, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import {
  getMarketplaceOrders,
  marketplaceHasMore,
  marketplaceNextCursor,
  type MarketplaceOrder,
} from '@/lib/api/marketplace';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

type OrderMode = 'purchases' | 'sales';

export default function MarketplaceOrdersRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceOrdersScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceOrdersScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [mode, setMode] = useState<OrderMode>('purchases');
  const orders = usePaginatedApi<MarketplaceOrder, Awaited<ReturnType<typeof getMarketplaceOrders>>>(
    (cursor) => getMarketplaceOrders(mode, cursor),
    (response) => ({
      items: response.data,
      cursor: marketplaceNextCursor(response),
      hasMore: marketplaceHasMore(response),
    }),
    [mode],
  );

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('orders.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
      <FlatList
        data={orders.items}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        ListHeaderComponent={
          <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
            <View className="h-1.5" style={{ backgroundColor: primary }} />
            <HeroCard.Body className="gap-4 p-4">
              <View className="flex-row items-start gap-3">
                <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                  <Ionicons name="receipt-outline" size={25} color={primary} />
                </View>
                <View className="min-w-0 flex-1">
                  <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('orders.eyebrow')}</Text>
                  <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('orders.title')}</Text>
                  <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('orders.subtitle')}</Text>
                </View>
              </View>
              <View className="flex-row gap-2">
                <HeroButton className="flex-1" variant={mode === 'purchases' ? 'primary' : 'secondary'} onPress={() => setMode('purchases')} style={mode === 'purchases' ? { backgroundColor: primary } : undefined}>
                  <HeroButton.Label>{t('orders.purchases')}</HeroButton.Label>
                </HeroButton>
                <HeroButton className="flex-1" variant={mode === 'sales' ? 'primary' : 'secondary'} onPress={() => setMode('sales')} style={mode === 'sales' ? { backgroundColor: primary } : undefined}>
                  <HeroButton.Label>{t('orders.sales')}</HeroButton.Label>
                </HeroButton>
              </View>
            </HeroCard.Body>
          </HeroCard>
        }
        renderItem={({ item }) => <OrderCard item={item} />}
        ListEmptyComponent={
          orders.isLoading ? (
            <View className="py-16"><LoadingSpinner /></View>
          ) : (
            <EmptyState icon="receipt-outline" title={orders.error ?? t('orders.empty')} subtitle={t('orders.emptyHint')} />
          )
        }
        ListFooterComponent={
          orders.isLoadingMore ? (
            <LoadingSpinner />
          ) : orders.hasMore ? (
            <HeroButton variant="secondary" onPress={orders.loadMore}>
              <HeroButton.Label>{t('loadMore')}</HeroButton.Label>
            </HeroButton>
          ) : null
        }
        onEndReached={orders.loadMore}
        onEndReachedThreshold={0.35}
      />
    </SafeAreaView>
  );
}

function OrderCard({ item }: { item: MarketplaceOrder }) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const total = `${item.currency || 'EUR'} ${Number(item.total_price).toLocaleString()}`;
  return (
    <HeroCard className="mb-3 rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start justify-between gap-3">
          <View className="min-w-0 flex-1">
            <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>{item.listing?.title ?? item.order_number}</Text>
            <Text className="text-sm" style={{ color: theme.textSecondary }}>{total}</Text>
          </View>
          <Chip size="sm" variant="secondary"><Chip.Label>{t(`orders.status.${item.status}`, { defaultValue: item.status })}</Chip.Label></Chip>
        </View>
        <Text className="text-xs" style={{ color: theme.textMuted }}>{t('orders.number', { number: item.order_number })}</Text>
        {item.listing?.id ? (
          <HeroButton size="sm" variant="secondary" onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(item.listing?.id) } } as unknown as Href)}>
            <Ionicons name="open-outline" size={14} color={primary} />
            <HeroButton.Label>{t('actions.view')}</HeroButton.Label>
          </HeroButton>
        ) : null}
      </HeroCard.Body>
    </HeroCard>
  );
}
