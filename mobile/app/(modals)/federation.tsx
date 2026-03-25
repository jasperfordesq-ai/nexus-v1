// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import {
  getFederationPartners,
  getFederationStats,
  type FederatedTenant,
} from '@/lib/api/federation';
import { useApi } from '@/lib/hooks/useApi';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function FederationScreen() {
  const { t } = useTranslation('federation');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const { data: statsData, isLoading: statsLoading } = useApi(
    () => getFederationStats(),
    [],
  );

  const stats = statsData?.data ?? null;

  const fetchPartners = useCallback(
    (cursor: string | null) => getFederationPartners(cursor),
    [],
  );

  const extractPartners = useCallback(
    (response: Awaited<ReturnType<typeof getFederationPartners>>) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const {
    items: partners,
    isLoading: partnersLoading,
    isLoadingMore,
    hasMore,
    loadMore,
    refresh,
  } = usePaginatedApi(fetchPartners, extractPartners);

  const renderPartner = useCallback(
    ({ item }: { item: FederatedTenant }) => {
      const connectedDate = new Date(item.connected_since).toLocaleDateString('default', {
        month: 'long',
        year: 'numeric',
      });
      return (
        <TouchableOpacity
          style={styles.partnerCard}
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push({
              pathname: '/(modals)/federation-partner',
              params: { id: String(item.id) },
            });
          }}
          activeOpacity={0.75}
          accessibilityRole="button"
          accessibilityLabel={item.name}
        >
          <Avatar uri={item.logo} name={item.name} size={48} />
          <View style={styles.partnerInfo}>
            <Text style={styles.partnerName} numberOfLines={1}>
              {item.name}
            </Text>
            {item.location ? (
              <View style={styles.metaRow}>
                <Ionicons name="location-outline" size={13} color={theme.textSecondary} />
                <Text style={styles.metaText} numberOfLines={1}>
                  {item.location}
                </Text>
              </View>
            ) : null}
            <View style={styles.metaRow}>
              <Ionicons name="people-outline" size={13} color={theme.textSecondary} />
              <Text style={styles.metaText}>
                {(item.member_count ?? 0).toLocaleString()}
              </Text>
            </View>
            <Text style={styles.connectedSince}>
              {t('connectedSince', { date: connectedDate })}
            </Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
        </TouchableOpacity>
      );
    },
    [styles, t, theme.textSecondary, theme.textMuted],
  );

  const listHeader = useMemo(() => {
    if (statsLoading) {
      return (
        <View style={styles.statsCard}>
          <ActivityIndicator color={primary} />
        </View>
      );
    }
    if (!stats) return null;
    return (
      <View style={styles.statsCard}>
        <StatColumn
          value={stats.partner_count ?? 0}
          label={t('stats.partners', { count: stats.partner_count ?? 0 })}
          primary={primary}
          theme={theme}
        />
        <View style={styles.statDivider} />
        <StatColumn
          value={stats.federated_members ?? 0}
          label={t('stats.members', { count: stats.federated_members ?? 0 })}
          primary={primary}
          theme={theme}
        />
        <View style={styles.statDivider} />
        <StatColumn
          value={stats.cross_community_exchanges ?? 0}
          label={t('stats.exchanges', { count: stats.cross_community_exchanges ?? 0 })}
          primary={primary}
          theme={theme}
        />
      </View>
    );
  }, [stats, statsLoading, primary, t, theme, styles]);

  if (partnersLoading) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView style={styles.container} edges={['bottom']}>
      <FlatList
        data={partners}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderPartner}
        contentContainerStyle={styles.listContent}
        ListHeaderComponent={
          <>
            {listHeader}
            <Text style={styles.sectionTitle}>{t('partners')}</Text>
          </>
        }
        ListEmptyComponent={
          <EmptyState
            icon="globe-outline"
            title={t('empty')}
          />
        }
        ListFooterComponent={
          isLoadingMore ? (
            <ActivityIndicator color={primary} style={{ marginVertical: 16 }} />
          ) : null
        }
        onEndReached={hasMore ? loadMore : undefined}
        onEndReachedThreshold={0.3}
        onRefresh={refresh}
        refreshing={partnersLoading && partners.length > 0}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function StatColumn({
  value,
  label,
  primary,
  theme,
}: {
  value: number;
  label: string;
  primary: string;
  theme: Theme;
}) {
  return (
    <View style={{ flex: 1, alignItems: 'center', gap: SPACING.xs }}>
      <Text style={{ ...TYPOGRAPHY.h2, color: primary }}>
        {value.toLocaleString()}
      </Text>
      <Text
        style={{ fontSize: 11, color: theme.textSecondary, textAlign: 'center' }}
        numberOfLines={2}
      >
        {label}
      </Text>
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    listContent: { padding: SPACING.md, paddingBottom: SPACING.xxl },
    statsCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: SPACING.md,
      padding: SPACING.md,
      marginBottom: SPACING.xl - 12,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    statDivider: {
      width: 1,
      height: 40,
      backgroundColor: theme.border,
      marginHorizontal: SPACING.sm,
    },
    sectionTitle: {
      ...TYPOGRAPHY.caption,
      fontWeight: '700',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: SPACING.sm + 4,
    },
    partnerCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: RADIUS.lg,
      padding: RADIUS.lg,
      marginBottom: SPACING.sm + 2,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      gap: SPACING.sm + 4,
    },
    partnerInfo: {
      flex: 1,
      gap: 3,
    },
    partnerName: {
      ...TYPOGRAPHY.body,
      fontWeight: '600',
      color: theme.text,
    },
    metaRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: SPACING.xs,
    },
    metaText: {
      ...TYPOGRAPHY.caption,
      color: theme.textSecondary,
      flex: 1,
    },
    connectedSince: {
      fontSize: 11,
      color: theme.textMuted,
      marginTop: SPACING.xxs,
    },
    // emptyText removed — now handled by EmptyState component
  });
}
