// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState, useCallback } from 'react';
import {
  View,
  Text,
  ScrollView,
  Pressable,
  RefreshControl,
  Share,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import * as Haptics from 'expo-haptics';
import { Spinner } from 'heroui-native';

import { getExchange, type Exchange } from '@/lib/api/exchanges';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { useAuth } from '@/lib/hooks/useAuth';
import { APP_URL } from '@/lib/constants';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function ExchangeDetailModal() {
  return (
    <ModalErrorBoundary>
      <ExchangeDetailModalInner />
    </ModalErrorBoundary>
  );
}

function ExchangeDetailModalInner() {
  const { t } = useTranslation('exchanges');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const { user: currentUser } = useAuth();
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const exchangeId = Number(id);
  const safeExchangeId = isNaN(exchangeId) || exchangeId <= 0 ? 0 : exchangeId;

  const { data, isLoading, error, refresh } = useApi(
    () => getExchange(safeExchangeId),
    [safeExchangeId],
    { enabled: safeExchangeId > 0 },
  );

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    refresh();
    // refresh() triggers a state update that re-runs the fetch effect;
    // isLoading will become true then false once data arrives.
    // Use a short timer to clear the refreshing indicator since refresh()
    // is synchronous (just bumps a counter).
    setTimeout(() => setIsRefreshing(false), 1200);
  }, [refresh]);

  const handleAction = useCallback(
    (recipientId: number, recipientName: string) => {
      if (isSubmitting) return;
      setIsSubmitting(true);
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      router.push({
        pathname: '/(modals)/thread',
        params: { recipientId: String(recipientId), name: recipientName },
      });
      // Reset after navigation begins so the button is re-enabled if user returns
      setTimeout(() => setIsSubmitting(false), 600);
    },
    [isSubmitting],
  );

  if (isNaN(exchangeId) || exchangeId <= 0) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center p-6">
        <Text className="text-xs text-danger mb-4">{t('detail.invalidId')}</Text>
        <Pressable onPress={() => router.back()}>
          <Text className="text-sm font-semibold" style={{ color: primary }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  // Support both { data: Exchange } wrapper and bare Exchange responses
  const exchange: Exchange | undefined = (data as { data?: Exchange })?.data ?? (data as Exchange | null) ?? undefined;

  if (isLoading) return <LoadingSpinner />;

  if (error || !exchange) {
    return (
      <SafeAreaView className="flex-1 justify-center items-center p-6">
        <Text className="text-xs text-danger mb-4">{error ?? t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()}>
          <Text className="text-sm font-semibold" style={{ color: primary }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  // Guard against missing user object — API may return exchange without nested user
  const exchangeUser = exchange.user ?? { id: 0, name: '?', avatar_url: null };

  async function handleShare() {
    try {
      await Share.share({
        title: exchange!.title,
        message: `${exchange!.title}\n${APP_URL}/listings/${exchange!.id}`,
        url: `${APP_URL}/listings/${exchange!.id}`,
      });
    } catch {
      // User cancelled or share failed — silently ignore
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-surface" edges={['bottom']}>
      <ScrollView
        contentContainerStyle={{ padding: 20 }}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={() => void handleRefresh()}
            tintColor={primary}
            colors={[primary]}
          />
        }
      >
        {/* Top row: type badge + share */}
        <View className="flex-row items-center justify-between mb-3">
          <View
            className={exchange.type === 'offer' ? 'bg-success/10 self-start rounded px-2.5 py-1' : 'bg-info/10 self-start rounded px-2.5 py-1'}
          >
            <Text className="text-xs font-semibold text-muted-foreground">
              {exchange.type === 'offer' ? t('offering') : t('requesting')}
            </Text>
          </View>
          <Pressable
            onPress={() => void handleShare()}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
            hitSlop={{ top: 10, bottom: 10, left: 10, right: 10 }}
          >
            <Ionicons name="share-outline" size={22} color={theme.textSecondary} />
          </Pressable>
        </View>

        <Text className="text-xl font-bold text-foreground mb-3">{exchange.title ?? ''}</Text>
        <Text className="text-sm text-muted-foreground mb-5">{exchange.description ?? ''}</Text>

        {/* Time estimate */}
        {(exchange.hours_estimate ?? 0) > 0 && (
          <View style={{ borderWidth: 2, borderRadius: 12, padding: 16, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 20, borderColor: primary }}>
            <Text className="text-xs text-muted-foreground">{t('detail.timeEstimate')}</Text>
            <Text style={{ fontSize: 24, fontWeight: '700', color: primary }}>
              {t('detail.hours', { count: exchange.hours_estimate ?? 0 })}
            </Text>
          </View>
        )}

        {/* Posted by */}
        <Pressable
          className="flex-row items-center mb-6"
          onPress={() => {
            if (exchangeUser.id > 0) {
              router.push({ pathname: '/(modals)/member-profile', params: { id: String(exchangeUser.id) } });
            }
          }}
          accessibilityRole="button"
          accessibilityLabel={exchangeUser.name}
        >
          <Avatar uri={exchangeUser.avatar_url} name={exchangeUser.name} size={40} />
          <View style={{ marginLeft: 12 }}>
            <Text className="text-xs text-muted-foreground">{t('detail.postedBy')}</Text>
            <Text className="text-sm font-semibold text-foreground">{exchangeUser.name}</Text>
          </View>
        </Pressable>

        {/* Action -- hidden if you are the poster */}
        {currentUser?.id !== exchangeUser.id && exchangeUser.id > 0 && (
          <Pressable
            style={[{ borderRadius: 12, paddingVertical: 14, alignItems: 'center', backgroundColor: primary }, isSubmitting && { opacity: 0.7 }]}
            disabled={isSubmitting}
            accessibilityLabel={
              exchange.type === 'offer' ? t('detail.requestService') : t('detail.offerHelp')
            }
            accessibilityRole="button"
            onPress={() => handleAction(exchangeUser.id, exchangeUser.name)}
          >
            {isSubmitting ? (
              <Spinner size="sm" />
            ) : (
              <Text style={{ color: '#fff', fontSize: 16, fontWeight: '600' }}>
                {exchange.type === 'offer' ? t('detail.requestService') : t('detail.offerHelp')}
              </Text>
            )}
          </Pressable>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}
