// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Alert, Linking, ScrollView, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface, Text } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import AppTopBar from '@/components/ui/AppTopBar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import {
  getMarketplaceSellerBalance,
  getMarketplaceSellerPayouts,
  getMarketplaceStripeOnboardingStatus,
  startMarketplaceStripeOnboarding,
  type MarketplaceSellerBalance,
  type MarketplaceSellerPayout,
  type MarketplaceStripeOnboardingStatus,
} from '@/lib/api/marketplace';
import { usePrimaryColor, useTenant } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

export default function MarketplaceStripeOnboardingRoute() {
  return (
    <ModalErrorBoundary>
      <MarketplaceStripeOnboardingScreen />
    </ModalErrorBoundary>
  );
}

function MarketplaceStripeOnboardingScreen() {
  const { t } = useTranslation(['marketplace', 'common']);
  const { hasFeature } = useTenant();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [status, setStatus] = useState<MarketplaceStripeOnboardingStatus | null>(null);
  const [balance, setBalance] = useState<MarketplaceSellerBalance | null>(null);
  const [payouts, setPayouts] = useState<MarketplaceSellerPayout[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isStarting, setIsStarting] = useState(false);

  async function load() {
    setIsLoading(true);
    try {
      const [statusResponse, balanceResponse, payoutsResponse] = await Promise.all([
        getMarketplaceStripeOnboardingStatus(),
        getMarketplaceSellerBalance(),
        getMarketplaceSellerPayouts(1, 5),
      ]);
      setStatus(statusResponse.data);
      setBalance(balanceResponse.data);
      setPayouts(payoutsResponse.data);
    } catch {
      setStatus(null);
      setBalance(null);
      setPayouts([]);
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    void load();
  }, []);

  async function start() {
    setIsStarting(true);
    try {
      const response = await startMarketplaceStripeOnboarding();
      const url = response.data.onboarding_url ?? response.data.url;
      if (!url) throw new Error(t('stripeOnboarding.startFailed'));
      await Linking.openURL(url);
    } catch (err) {
      Alert.alert(t('common:errors.alertTitle'), err instanceof Error ? err.message : t('stripeOnboarding.startFailed'));
    } finally {
      setIsStarting(false);
    }
  }

  if (!hasFeature('marketplace')) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('stripeOnboarding.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace-my-listings' as Href} />
        <View className="flex-1 items-center justify-center px-6">
          <Text style={{ color: theme.textSecondary }}>{t('featureGate.description')}</Text>
        </View>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('stripeOnboarding.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace-my-listings' as Href} />
        <View className="flex-1 items-center justify-center"><LoadingSpinner /></View>
      </SafeAreaView>
    );
  }

  const complete = Boolean(status?.stripe_onboarding_complete);

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('stripeOnboarding.title')} backLabel={t('common:back')} fallbackHref={'/(modals)/marketplace-my-listings' as Href} />
      <ScrollView contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 132 }}>
        <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: complete ? theme.success : primary }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(complete ? theme.success : primary, 0.14) }}>
                <Ionicons name={complete ? 'shield-checkmark-outline' : 'card-outline'} size={25} color={complete ? theme.success : primary} />
              </View>
              <View className="min-w-0 flex-1 gap-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('stripeOnboarding.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{complete ? t('stripeOnboarding.completeTitle') : t('stripeOnboarding.title')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{complete ? t('stripeOnboarding.completeSubtitle') : t('stripeOnboarding.subtitle')}</Text>
              </View>
            </View>
            <View className="flex-row flex-wrap gap-2">
              <StatusChip label={t('stripeOnboarding.charges')} enabled={Boolean(status?.charges_enabled)} />
              <StatusChip label={t('stripeOnboarding.payouts')} enabled={Boolean(status?.payouts_enabled)} />
              <StatusChip label={t('stripeOnboarding.details')} enabled={Boolean(status?.details_submitted)} />
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="mb-3 rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <ChecklistRow icon="business-outline" title={t('stripeOnboarding.needBank')} subtitle={t('stripeOnboarding.needBankHint')} />
            <ChecklistRow icon="id-card-outline" title={t('stripeOnboarding.needIdentity')} subtitle={t('stripeOnboarding.needIdentityHint')} />
            <ChecklistRow icon="lock-closed-outline" title={t('stripeOnboarding.secure')} subtitle={t('stripeOnboarding.secureHint')} />
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="mb-3 rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <View className="flex-row items-center justify-between gap-3">
              <View className="min-w-0 flex-1">
                <Text className="text-base font-bold" style={{ color: theme.text }}>{t('stripeOnboarding.balanceTitle')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('stripeOnboarding.balanceSubtitle')}</Text>
              </View>
              <Ionicons name="wallet-outline" size={22} color={primary} />
            </View>
            <View className="flex-row flex-wrap gap-2">
              <BalanceTile label={t('stripeOnboarding.pending')} value={formatMoney(balance?.pending, balance?.currency)} tone={theme.warning} />
              <BalanceTile label={t('stripeOnboarding.available')} value={formatMoney(balance?.available, balance?.currency)} tone={theme.success} />
              <BalanceTile label={t('stripeOnboarding.totalEarned')} value={formatMoney(balance?.total_earned, balance?.currency)} tone={primary} />
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="mb-3 rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <View className="flex-row items-center justify-between gap-3">
              <View className="min-w-0 flex-1">
                <Text className="text-base font-bold" style={{ color: theme.text }}>{t('stripeOnboarding.payoutHistory')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('stripeOnboarding.payoutHistoryHint')}</Text>
              </View>
              <Ionicons name="receipt-outline" size={22} color={primary} />
            </View>
            {payouts.length === 0 ? (
              <Surface variant="secondary" className="rounded-panel-inner p-3">
                <Text className="text-sm" style={{ color: theme.textSecondary }}>{t('stripeOnboarding.noPayouts')}</Text>
              </Surface>
            ) : (
              <View className="gap-2">
                {payouts.map((payout) => (
                  <PayoutRow key={payout.id} payout={payout} />
                ))}
              </View>
            )}
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <HeroButton variant="primary" onPress={() => void start()} isDisabled={isStarting || complete} style={{ backgroundColor: complete ? theme.success : primary }}>
              <Ionicons name={complete ? 'checkmark-circle-outline' : 'open-outline'} size={17} color="#fff" />
              <HeroButton.Label>{complete ? t('stripeOnboarding.completeButton') : t('stripeOnboarding.start')}</HeroButton.Label>
            </HeroButton>
            <HeroButton variant="secondary" onPress={() => void load()} isDisabled={isStarting}>
              <Ionicons name="refresh-outline" size={17} color={primary} />
              <HeroButton.Label>{t('stripeOnboarding.checkStatus')}</HeroButton.Label>
            </HeroButton>
            <HeroButton variant="secondary" onPress={() => router.replace('/(modals)/marketplace-my-listings' as Href)}>
              <Ionicons name="albums-outline" size={17} color={primary} />
              <HeroButton.Label>{t('stripeOnboarding.goListings')}</HeroButton.Label>
            </HeroButton>
          </HeroCard.Body>
        </HeroCard>
      </ScrollView>
    </SafeAreaView>
  );
}

function formatMoney(value?: number | null, currency?: string | null) {
  const amount = Number(value ?? 0);
  return `${(currency || 'EUR').toUpperCase()} ${amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function StatusChip({ label, enabled }: { label: string; enabled: boolean }) {
  const theme = useTheme();
  return (
    <Chip size="sm" variant="secondary">
      <Ionicons name={enabled ? 'checkmark-circle-outline' : 'ellipse-outline'} size={12} color={enabled ? theme.success : theme.textMuted} />
      <Chip.Label>{label}</Chip.Label>
    </Chip>
  );
}

function BalanceTile({ label, value, tone }: { label: string; value: string; tone: string }) {
  const theme = useTheme();
  return (
    <Surface variant="secondary" className="min-w-[46%] flex-1 rounded-panel-inner p-3">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{label}</Text>
      <Text className="mt-1 text-lg font-bold" style={{ color: tone }}>{value}</Text>
    </Surface>
  );
}

function PayoutRow({ payout }: { payout: MarketplaceSellerPayout }) {
  const { t } = useTranslation('marketplace');
  const theme = useTheme();
  return (
    <Surface variant="secondary" className="rounded-panel-inner p-3">
      <View className="flex-row items-start justify-between gap-3">
        <View className="min-w-0 flex-1">
          <Text className="text-sm font-bold" style={{ color: theme.text }}>
            {t('stripeOnboarding.payoutOrder', { order: payout.order_id })}
          </Text>
          <Text className="mt-1 text-xs" style={{ color: theme.textSecondary }}>
            {payout.created_at ? new Date(payout.created_at).toLocaleDateString() : t('stripeOnboarding.dateUnknown')}
          </Text>
        </View>
        <View className="items-end">
          <Text className="text-sm font-bold" style={{ color: theme.text }}>{formatMoney(payout.seller_payout, payout.currency)}</Text>
          <Chip size="sm" variant="secondary"><Chip.Label>{t(`stripeOnboarding.payoutStatus.${payout.payout_status}`, { defaultValue: payout.payout_status })}</Chip.Label></Chip>
        </View>
      </View>
    </Surface>
  );
}

function ChecklistRow({ icon, title, subtitle }: { icon: React.ComponentProps<typeof Ionicons>['name']; title: string; subtitle: string }) {
  const primary = usePrimaryColor();
  const theme = useTheme();
  return (
    <Surface variant="secondary" className="flex-row gap-3 rounded-panel-inner p-3">
      <View className="size-10 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
        <Ionicons name={icon} size={19} color={primary} />
      </View>
      <View className="min-w-0 flex-1">
        <Text className="text-sm font-bold" style={{ color: theme.text }}>{title}</Text>
        <Text className="mt-1 text-xs leading-4" style={{ color: theme.textSecondary }}>{subtitle}</Text>
      </View>
    </Surface>
  );
}
