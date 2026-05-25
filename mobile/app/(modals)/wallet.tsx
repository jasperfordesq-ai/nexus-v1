// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { useNavigation } from 'expo-router';
import {
  View,
  Text,
  FlatList,
  RefreshControl,
  Pressable,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { getWalletBalance, getWalletTransactions, type TransactionItem } from '@/lib/api/wallet';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

// Helpers

function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString(undefined, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    });
  } catch {
    return iso;
  }
}

// Transaction Row

function TransactionRow({
  item,
  primary,
  theme,
  t,
}: {
  item: TransactionItem;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const isCredit = item.type === 'credit';
  const sign = isCredit ? '+' : '−';
  const amountColor = isCredit ? theme.success : theme.error;
  const otherName = item.other_user?.name ?? t('system');
  const otherAvatar = item.other_user?.avatar_url ?? null;

  return (
    <Pressable
      className="flex-row items-start py-4 px-1 bg-surface rounded-xl"
      onPress={() => void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light)}
      accessibilityRole="button"
      accessibilityLabel={`${otherName}, ${sign}${(item.amount ?? 0).toFixed(1)} ${t('hrs')}`}
    >
      <Avatar
        uri={otherAvatar}
        name={otherName}
        size={44}
      />

      <View className="flex-1 ml-3 mr-2">
        <Text className="text-sm font-semibold text-foreground" numberOfLines={1}>
          {otherName}
        </Text>
        {item.description ? (
          <Text className="text-xs text-muted-foreground mt-0.5" numberOfLines={2}>
            {item.description}
          </Text>
        ) : null}
        <Text className="text-[11px] text-muted-foreground mt-1">{formatDate(item.created_at)}</Text>
      </View>

      <View className="items-end justify-center min-w-[72px]">
        <Text style={{ color: amountColor }} className="text-base font-bold">
          {sign}{(item.amount ?? 0).toFixed(1)} {t('hrs')}
        </Text>
        {item.status !== 'completed' && (
          <View style={{ borderColor: primary }} className="border rounded px-1 py-0.5 mt-1">
            <Text style={{ color: primary }} className="text-[11px] font-semibold capitalize">
              {t(`status.${item.status}`, { defaultValue: item.status })}
            </Text>
          </View>
        )}
      </View>
    </Pressable>
  );
}

// Screen

export default function WalletModal() {
  return (
    <ModalErrorBoundary>
      <WalletModalInner />
    </ModalErrorBoundary>
  );
}

function WalletModalInner() {
  const { t } = useTranslation('wallet');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const Separator = useCallback(() => <View className="h-2" />, []);
  const [isRefreshing, setIsRefreshing] = useState(false);

  const {
    data: balanceData,
    isLoading: balanceLoading,
    refresh: refreshBalance,
  } = useApi(() => getWalletBalance(), []);

  const {
    data: txData,
    isLoading: txLoading,
    error: txError,
    refresh: refreshTx,
  } = useApi(() => getWalletTransactions(undefined, 50, 'all'), []);

  const balance = balanceData?.data?.balance ?? null;
  const transactions = txData?.data ?? [];
  const isLoading = balanceLoading || txLoading;

  // Turn off manual refresh indicator once both API calls finish
  useEffect(() => {
    if (isRefreshing && !balanceLoading && !txLoading) {
      setIsRefreshing(false);
    }
  }, [isRefreshing, balanceLoading, txLoading]);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    refreshBalance();
    refreshTx();
  }, [refreshBalance, refreshTx]);

  const ListHeader = (
    <View
      className="border-2 rounded-2xl py-7 px-6 items-center bg-surface mt-5 mb-5"
      style={{ borderColor: primary }}
    >
      {balanceLoading && balance === null ? (
        <Spinner size="sm" />
      ) : (
        <>
          <Text style={{ color: primary }} className="text-5xl font-bold leading-[56px]">
            {balance !== null ? (Number(balance) || 0).toFixed(1) : '—'}
          </Text>
          <Text className="text-sm text-muted-foreground mt-1">{t('timeCredits')}</Text>
        </>
      )}
    </View>
  );

  const ListEmpty = txLoading ? null : (
    <View className="pt-16 items-center">
      {txError ? (
        <Text className="text-sm text-danger text-center">{txError}</Text>
      ) : (
        <EmptyState
          icon="wallet-outline"
          title={t('noTransactions')}
        />
      )}
    </View>
  );

  return (
    <SafeAreaView className="flex-1 bg-background">
      <FlatList<TransactionItem>
        data={transactions}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <TransactionRow item={item} primary={primary} theme={theme} t={t} />
        )}
        ListHeaderComponent={ListHeader}
        ListEmptyComponent={ListEmpty}
        ItemSeparatorComponent={Separator}
        contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 32 }}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing || isLoading}
            onRefresh={() => void handleRefresh()}
            tintColor={primary}
            colors={[primary]}
          />
        }
      />
    </SafeAreaView>
  );
}
