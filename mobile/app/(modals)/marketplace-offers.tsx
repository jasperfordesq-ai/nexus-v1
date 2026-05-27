// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Alert, FlatList, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import {
  acceptMarketplaceOffer,
  declineMarketplaceOffer,
  getMarketplaceOffers,
  marketplaceHasMore,
  marketplaceNextCursor,
  withdrawMarketplaceOffer,
  type MarketplaceOffer,
} from '@/lib/api/marketplace';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

type OfferMode = 'sent' | 'received';

export default function MarketplaceOffersRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceOffersScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceOffersScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [mode, setMode] = useState<OfferMode>('received');
  const offers = usePaginatedApi<MarketplaceOffer, Awaited<ReturnType<typeof getMarketplaceOffers>>>(
    (cursor) => getMarketplaceOffers(mode, cursor),
    (response) => ({
      items: response.data,
      cursor: marketplaceNextCursor(response),
      hasMore: marketplaceHasMore(response),
    }),
    [mode],
  );

  async function action(kind: 'accept' | 'decline' | 'withdraw', offer: MarketplaceOffer) {
    try {
      if (kind === 'accept') await acceptMarketplaceOffer(offer.id);
      if (kind === 'decline') await declineMarketplaceOffer(offer.id);
      if (kind === 'withdraw') await withdrawMarketplaceOffer(offer.id);
      offers.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('offers.actionFailed'));
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('offers.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
      <FlatList
        data={offers.items}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}
        ListHeaderComponent={
          <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
            <View className="h-1.5" style={{ backgroundColor: primary }} />
            <HeroCard.Body className="gap-4 p-4">
              <View className="flex-row items-start gap-3">
                <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                  <Ionicons name="hand-left-outline" size={25} color={primary} />
                </View>
                <View className="min-w-0 flex-1">
                  <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('offers.eyebrow')}</Text>
                  <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('offers.title')}</Text>
                  <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('offers.subtitle')}</Text>
                </View>
              </View>
              <View className="flex-row gap-2">
                <HeroButton className="flex-1" variant={mode === 'received' ? 'primary' : 'secondary'} onPress={() => setMode('received')} style={mode === 'received' ? { backgroundColor: primary } : undefined}>
                  <HeroButton.Label>{t('offers.received')}</HeroButton.Label>
                </HeroButton>
                <HeroButton className="flex-1" variant={mode === 'sent' ? 'primary' : 'secondary'} onPress={() => setMode('sent')} style={mode === 'sent' ? { backgroundColor: primary } : undefined}>
                  <HeroButton.Label>{t('offers.sentTab')}</HeroButton.Label>
                </HeroButton>
              </View>
            </HeroCard.Body>
          </HeroCard>
        }
        renderItem={({ item }) => (
          <OfferCard offer={item} mode={mode} onAction={action} />
        )}
        ListEmptyComponent={
          offers.isLoading ? (
            <View className="py-16">
              <LoadingSpinner />
            </View>
          ) : (
            <EmptyState icon="hand-left-outline" title={offers.error ?? t('offers.empty')} subtitle={t('offers.emptyHint')} />
          )
        }
        ListFooterComponent={
          offers.isLoadingMore ? (
            <LoadingSpinner />
          ) : offers.hasMore ? (
            <HeroButton variant="secondary" onPress={offers.loadMore}>
              <HeroButton.Label>{t('loadMore')}</HeroButton.Label>
            </HeroButton>
          ) : null
        }
        onEndReached={offers.loadMore}
        onEndReachedThreshold={0.35}
      />
    </SafeAreaView>
  );
}

function OfferCard({ offer, mode, onAction }: { offer: MarketplaceOffer; mode: OfferMode; onAction: (kind: 'accept' | 'decline' | 'withdraw', offer: MarketplaceOffer) => void }) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const listingId = offer.listing?.id;
  const amount = `${offer.currency || 'EUR'} ${Number(offer.amount).toLocaleString()}`;
  return (
    <HeroCard className="mb-3 rounded-panel p-0">
      <HeroCard.Body className="gap-3 p-4">
        <View className="flex-row items-start justify-between gap-3">
          <View className="min-w-0 flex-1">
            <Text className="text-base font-bold" style={{ color: theme.text }} numberOfLines={2}>{offer.listing?.title ?? t('offers.listing')}</Text>
            <Text className="text-sm" style={{ color: theme.textSecondary }}>{amount}</Text>
          </View>
          <Chip size="sm" variant="secondary">
            <Chip.Label>{t(`offers.status.${offer.status}`)}</Chip.Label>
          </Chip>
        </View>
        {offer.message ? <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{offer.message}</Text> : null}
        <View className="flex-row gap-2">
          {listingId ? (
            <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(listingId) } } as unknown as Href)}>
              <Ionicons name="open-outline" size={14} color={primary} />
              <HeroButton.Label>{t('actions.view')}</HeroButton.Label>
            </HeroButton>
          ) : null}
          {mode === 'received' && offer.status === 'pending' ? (
            <>
              <HeroButton className="flex-1" size="sm" variant="primary" onPress={() => onAction('accept', offer)} style={{ backgroundColor: theme.success }}>
                <HeroButton.Label>{t('offers.accept')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" size="sm" variant="danger" onPress={() => onAction('decline', offer)}>
                <HeroButton.Label>{t('offers.decline')}</HeroButton.Label>
              </HeroButton>
            </>
          ) : null}
          {mode === 'sent' && offer.status === 'pending' ? (
            <HeroButton className="flex-1" size="sm" variant="danger" onPress={() => onAction('withdraw', offer)}>
              <HeroButton.Label>{t('offers.withdraw')}</HeroButton.Label>
            </HeroButton>
          ) : null}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}
