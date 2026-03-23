// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo } from 'react';
import {
  ActivityIndicator,
  FlatList,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
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
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';

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
                {item.member_count.toLocaleString()}
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
          value={stats.partner_count}
          label={t('stats.partners', { count: stats.partner_count })}
          primary={primary}
          theme={theme}
        />
        <View style={styles.statDivider} />
        <StatColumn
          value={stats.federated_members}
          label={t('stats.members', { count: stats.federated_members })}
          primary={primary}
          theme={theme}
        />
        <View style={styles.statDivider} />
        <StatColumn
          value={stats.cross_community_exchanges}
          label={t('stats.exchanges', { count: stats.cross_community_exchanges })}
          primary={primary}
          theme={theme}
        />
      </View>
    );
  }, [stats, statsLoading, primary, t, theme, styles]);

  if (partnersLoading) {
    return (
      <SafeAreaView style={styles.center}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
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
          <Text style={styles.emptyText}>{t('empty')}</Text>
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
    <View style={{ flex: 1, alignItems: 'center', gap: 4 }}>
      <Text style={{ fontSize: 22, fontWeight: '700', color: primary }}>
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
    listContent: { padding: 16, paddingBottom: 48 },
    statsCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: 16,
      padding: 16,
      marginBottom: 20,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    statDivider: {
      width: 1,
      height: 40,
      backgroundColor: theme.border,
      marginHorizontal: 8,
    },
    sectionTitle: {
      fontSize: 12,
      fontWeight: '700',
      color: theme.textSecondary,
      textTransform: 'uppercase',
      letterSpacing: 0.6,
      marginBottom: 12,
    },
    partnerCard: {
      flexDirection: 'row',
      alignItems: 'center',
      backgroundColor: theme.surface,
      borderRadius: 14,
      padding: 14,
      marginBottom: 10,
      borderWidth: 1,
      borderColor: theme.borderSubtle,
      gap: 12,
    },
    partnerInfo: {
      flex: 1,
      gap: 3,
    },
    partnerName: {
      fontSize: 15,
      fontWeight: '600',
      color: theme.text,
    },
    metaRow: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
    },
    metaText: {
      fontSize: 12,
      color: theme.textSecondary,
      flex: 1,
    },
    connectedSince: {
      fontSize: 11,
      color: theme.textMuted,
      marginTop: 2,
    },
    emptyText: {
      fontSize: 14,
      color: theme.textMuted,
      textAlign: 'center',
      marginTop: 32,
    },
  });
}
