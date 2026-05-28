// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Alert, FlatList, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import {
  acceptMarketplaceOffer,
  acceptMarketplaceCounterOffer,
  counterMarketplaceOffer,
  declineMarketplaceOffer,
  getMarketplaceOffers,
  marketplaceHasMore,
  marketplaceNextCursor,
  withdrawMarketplaceOffer,
  type MarketplaceOffer,
} from '@/lib/api/marketplace';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
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
  const params = useLocalSearchParams<{ mode?: string | string[] }>();
  const { hasFeature } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [mode, setMode] = useState<OfferMode>(normalizeOfferMode(firstParam(params.mode)));
  const offers = usePaginatedApi<MarketplaceOffer, Awaited<ReturnType<typeof getMarketplaceOffers>>>(
    (cursor) => getMarketplaceOffers(mode, cursor),
    (response) => ({
      items: response.data,
      cursor: marketplaceNextCursor(response),
      hasMore: marketplaceHasMore(response),
    }),
    [mode],
  );

  if (!hasFeature('marketplace')) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('offers.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace' as Href} />
        <EmptyState icon="hand-left-outline" title={t('featureGate.title')} subtitle={t('featureGate.description')} />
      </SafeAreaView>
    );
  }

  async function action(kind: 'accept' | 'decline' | 'withdraw' | 'acceptCounter', offer: MarketplaceOffer) {
    try {
      if (kind === 'accept') await acceptMarketplaceOffer(offer.id);
      if (kind === 'decline') await declineMarketplaceOffer(offer.id);
      if (kind === 'withdraw') await withdrawMarketplaceOffer(offer.id);
      if (kind === 'acceptCounter') await acceptMarketplaceCounterOffer(offer.id);
      offers.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('offers.actionFailed'));
    }
  }

  async function counter(offer: MarketplaceOffer, amount: number, message?: string | null) {
    try {
      await counterMarketplaceOffer(offer.id, { amount, message });
      offers.refresh();
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('offers.counterFailed'));
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
          <OfferCard offer={item} mode={mode} onAction={action} onCounter={counter} />
        )}
        ListEmptyComponent={
          offers.isLoading ? (
            <View className="py-16">
              <LoadingSpinner />
            </View>
          ) : (
            <EmptyState
              icon={mode === 'sent' ? 'send-outline' : 'archive-outline'}
              title={offers.error ?? (mode === 'sent' ? t('offers.emptySent') : t('offers.emptyReceived'))}
              subtitle={mode === 'sent' ? t('offers.emptySentHint') : t('offers.emptyReceivedHint')}
            />
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

function firstParam(value?: string | string[]): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

function normalizeOfferMode(value?: string): OfferMode {
  return value === 'received' ? 'received' : 'sent';
}

function OfferCard({
  offer,
  mode,
  onAction,
  onCounter,
}: {
  offer: MarketplaceOffer;
  mode: OfferMode;
  onAction: (kind: 'accept' | 'decline' | 'withdraw' | 'acceptCounter', offer: MarketplaceOffer) => void;
  onCounter: (offer: MarketplaceOffer, amount: number, message?: string | null) => void;
}) {
  const { t } = useTranslation('marketplace');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [isCountering, setIsCountering] = useState(false);
  const [counterAmount, setCounterAmount] = useState('');
  const [counterMessage, setCounterMessage] = useState('');
  const listingId = offer.listing?.id;
  const amount = `${offer.currency || 'EUR'} ${Number(offer.amount).toLocaleString()}`;

  function submitCounter() {
    const value = Number(counterAmount);
    if (!Number.isFinite(value) || value <= 0) {
      Alert.alert(t('offers.amountRequired'), t('offers.amountRequired'));
      return;
    }
    onCounter(offer, value, counterMessage.trim() || null);
    setIsCountering(false);
    setCounterAmount('');
    setCounterMessage('');
  }

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
        {offer.status === 'countered' && offer.counter_amount ? (
          <Surface variant="secondary" className="gap-1 rounded-panel-inner p-3">
            <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('offers.countered')}</Text>
            <Text className="text-base font-bold" style={{ color: theme.text }}>
              {offer.currency || 'EUR'} {Number(offer.counter_amount).toLocaleString()}
            </Text>
            {offer.counter_message ? <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{offer.counter_message}</Text> : null}
          </Surface>
        ) : null}
        {isCountering ? (
          <Surface variant="secondary" className="gap-3 rounded-panel-inner p-3">
            <View className="gap-2">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('offers.counterAmount')}</Text>
              <TextInput
                className="min-h-12 rounded-panel-inner border px-3 text-sm"
                style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg }}
                placeholder={t('offers.amountPlaceholder')}
                placeholderTextColor={theme.textMuted}
                keyboardType="decimal-pad"
                value={counterAmount}
                onChangeText={setCounterAmount}
              />
            </View>
            <View className="gap-2">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('offers.counterMessage')}</Text>
              <TextInput
                className="min-h-20 rounded-panel-inner border px-3 py-2 text-sm"
                style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg, textAlignVertical: 'top' }}
                placeholder={t('offers.counterMessagePlaceholder')}
                placeholderTextColor={theme.textMuted}
                multiline
                value={counterMessage}
                onChangeText={setCounterMessage}
              />
            </View>
            <View className="flex-row gap-2">
              <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => setIsCountering(false)}>
                <HeroButton.Label>{t('offers.cancelCounter')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" size="sm" variant="primary" onPress={submitCounter} style={{ backgroundColor: primary }}>
                <HeroButton.Label>{t('offers.sendCounter')}</HeroButton.Label>
              </HeroButton>
            </View>
          </Surface>
        ) : null}
        <View className="flex-row flex-wrap gap-2">
          {listingId ? (
            <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => router.push({ pathname: '/(modals)/marketplace-detail', params: { id: String(listingId) } } as unknown as Href)} style={{ minWidth: '46%' }}>
              <Ionicons name="open-outline" size={14} color={primary} />
              <HeroButton.Label>{t('actions.view')}</HeroButton.Label>
            </HeroButton>
          ) : null}
          {mode === 'received' && offer.status === 'pending' ? (
            <>
              <HeroButton className="flex-1" size="sm" variant="primary" onPress={() => onAction('accept', offer)} style={{ minWidth: '46%', backgroundColor: theme.success }}>
                <HeroButton.Label>{t('offers.accept')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" size="sm" variant="danger" onPress={() => onAction('decline', offer)} style={{ minWidth: '46%' }}>
                <HeroButton.Label>{t('offers.decline')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" size="sm" variant="secondary" onPress={() => setIsCountering(true)} style={{ minWidth: '46%' }}>
                <HeroButton.Label>{t('offers.counter')}</HeroButton.Label>
              </HeroButton>
            </>
          ) : null}
          {mode === 'sent' && offer.status === 'pending' ? (
            <HeroButton className="flex-1" size="sm" variant="danger" onPress={() => onAction('withdraw', offer)} style={{ minWidth: '46%' }}>
              <HeroButton.Label>{t('offers.withdraw')}</HeroButton.Label>
            </HeroButton>
          ) : null}
          {mode === 'sent' && offer.status === 'countered' ? (
            <>
              <HeroButton className="flex-1" size="sm" variant="primary" onPress={() => onAction('acceptCounter', offer)} style={{ minWidth: '46%', backgroundColor: theme.success }}>
                <HeroButton.Label>{t('offers.acceptCounter')}</HeroButton.Label>
              </HeroButton>
              <HeroButton className="flex-1" size="sm" variant="danger" onPress={() => onAction('decline', offer)} style={{ minWidth: '46%' }}>
                <HeroButton.Label>{t('offers.decline')}</HeroButton.Label>
              </HeroButton>
            </>
          ) : null}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}
