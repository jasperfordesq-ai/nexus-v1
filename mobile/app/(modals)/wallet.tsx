// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigation } from 'expo-router';
import {
  View,
  Text,
  FlatList,
  RefreshControl,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
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
  styles,
  t,
}: {
  item: TransactionItem;
  primary: string;
  theme: Theme;
  styles: ReturnType<typeof makeStyles>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  const isCredit = item.type === 'credit';
  const sign = isCredit ? '+' : '\u2212';
  const amountColor = isCredit ? theme.success : theme.error;
  const otherName = item.other_user?.name ?? t('system');
  const otherAvatar = item.other_user?.avatar_url ?? null;

  return (
    <TouchableOpacity
      style={styles.row}
      activeOpacity={0.8}
      onPress={() => void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light)}
      accessibilityRole="button"
      accessibilityLabel={`${otherName}, ${sign}${(item.amount ?? 0).toFixed(1)} ${t('hrs')}`}
    >
      <Avatar
        uri={otherAvatar}
        name={otherName}
        size={44}
      />

      <View style={styles.rowBody}>
        <Text style={styles.rowName} numberOfLines={1}>
          {otherName}
        </Text>
        {item.description ? (
          <Text style={styles.rowDesc} numberOfLines={2}>
            {item.description}
          </Text>
        ) : null}
        <Text style={styles.rowDate}>{formatDate(item.created_at)}</Text>
      </View>

      <View style={styles.rowRight}>
        <Text style={[styles.rowAmount, { color: amountColor }]}>
          {sign}{(item.amount ?? 0).toFixed(1)} {t('hrs')}
        </Text>
        {item.status !== 'completed' && (
          <View style={[styles.statusBadge, { borderColor: primary }]}>
            <Text style={[styles.statusText, { color: primary }]}>
              {t(`status.${item.status}`, { defaultValue: item.status })}
            </Text>
          </View>
        )}
      </View>
    </TouchableOpacity>
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
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);
  const Separator = useCallback(() => <View style={styles.separator} />, [styles]);
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
    <View style={[styles.balanceCard, { borderColor: primary }]}>
      {balanceLoading && balance === null ? (
        <ActivityIndicator color={primary} />
      ) : (
        <>
          <Text style={[styles.balanceValue, { color: primary }]}>
            {balance !== null ? (Number(balance) || 0).toFixed(1) : '\u2014'}
          </Text>
          <Text style={styles.balanceLabel}>{t('timeCredits')}</Text>
        </>
      )}
    </View>
  );

  const ListEmpty = txLoading ? null : (
    <View style={styles.emptyWrap}>
      {txError ? (
        <Text style={styles.errorText}>{txError}</Text>
      ) : (
        <EmptyState
          icon="wallet-outline"
          title={t('noTransactions')}
        />
      )}
    </View>
  );

  return (
    <SafeAreaView style={styles.container}>
      <FlatList<TransactionItem>
        data={transactions}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <TransactionRow item={item} primary={primary} theme={theme} styles={styles} t={t} />
        )}
        ListHeaderComponent={ListHeader}
        ListEmptyComponent={ListEmpty}
        ItemSeparatorComponent={Separator}
        contentContainerStyle={styles.listContent}
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

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    listContent: { paddingHorizontal: SPACING.md, paddingBottom: SPACING.xl },
    balanceCard: {
      borderWidth: 2,
      borderRadius: SPACING.md,
      paddingVertical: 28,
      paddingHorizontal: SPACING.lg,
      alignItems: 'center',
      backgroundColor: theme.surface,
      marginTop: 20,
      marginBottom: SPACING.lg,
    },
    balanceValue: { fontSize: 48, fontWeight: '700', lineHeight: 56 },
    balanceLabel: { ...TYPOGRAPHY.label, color: theme.textSecondary, marginTop: 4 },
    row: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      paddingVertical: RADIUS.lg,
      paddingHorizontal: 4,
      backgroundColor: theme.surface,
      borderRadius: 12,
    },
    rowBody: { flex: 1, marginLeft: 12, marginRight: SPACING.sm },
    rowName: { ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text },
    rowDesc: { ...TYPOGRAPHY.bodySmall, color: theme.textSecondary, marginTop: 2 },
    rowDate: { ...TYPOGRAPHY.caption, color: theme.textMuted, marginTop: 4 },
    rowRight: { alignItems: 'flex-end', justifyContent: 'center', minWidth: 72 },
    rowAmount: { fontSize: 16, fontWeight: '700' },
    statusBadge: {
      borderWidth: 1,
      borderRadius: 4,
      paddingHorizontal: RADIUS.sm,
      paddingVertical: 2,
      marginTop: 4,
    },
    statusText: { ...TYPOGRAPHY.badge, fontWeight: '600', textTransform: 'capitalize' },
    separator: { height: SPACING.sm },
    emptyWrap: { paddingTop: SPACING.xxl, alignItems: 'center' },
    errorText: { ...TYPOGRAPHY.label, color: theme.error, textAlign: 'center' },
  });
}
