// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect } from 'react';
import {
  FlatList,
  Text,
  Pressable,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';
import { Spinner } from 'heroui-native';

import {
  getFederationPartners,
  getFederationStats,
  type FederatedTenant,
} from '@/lib/api/federation';
import { useApi } from '@/lib/hooks/useApi';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

export default function FederationScreen() {
  const { t } = useTranslation('federation');
  const navigation = useNavigation();
  const primary = usePrimaryColor();
  const theme = useTheme();

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
        <Pressable
          className="flex-row items-center bg-surface rounded-xl p-4 mb-2.5 border border-border/50 gap-3.5"
          onPress={() => {
            void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
            router.push({
              pathname: '/(modals)/federation-partner',
              params: { id: String(item.id) },
            });
          }}
          accessibilityRole="button"
          accessibilityLabel={item.name}
        >
          <Avatar uri={item.logo} name={item.name} size={48} />
          <View className="flex-1 gap-0.5">
            <Text className="text-sm font-semibold text-foreground" numberOfLines={1}>
              {item.name}
            </Text>
            {item.location ? (
              <View className="flex-row items-center gap-1">
                <Ionicons name="location-outline" size={13} color={theme.textSecondary} />
                <Text className="text-xs text-muted-foreground flex-1" numberOfLines={1}>
                  {item.location}
                </Text>
              </View>
            ) : null}
            <View className="flex-row items-center gap-1">
              <Ionicons name="people-outline" size={13} color={theme.textSecondary} />
              <Text className="text-xs text-muted-foreground">
                {(item.member_count ?? 0).toLocaleString()}
              </Text>
            </View>
            <Text style={{ fontSize: 11, color: theme.textMuted, marginTop: 2 }}>
              {t('connectedSince', { date: connectedDate })}
            </Text>
          </View>
          <Ionicons name="chevron-forward" size={18} color={theme.textMuted} />
        </Pressable>
      );
    },
    [t, theme.textSecondary, theme.textMuted],
  );

  const listHeader = (() => {
    if (statsLoading) {
      return (
        <View className="flex-row items-center bg-surface rounded-xl p-4 mb-4 border border-border/50 justify-center">
          <Spinner size="sm" />
        </View>
      );
    }
    if (!stats) return null;
    return (
      <View className="flex-row items-center bg-surface rounded-xl p-4 mb-4 border border-border/50">
        <StatColumn
          value={stats.partner_count ?? 0}
          label={t('stats.partners', { count: stats.partner_count ?? 0 })}
          primary={primary}
          theme={theme}
        />
        <View style={{ width: 1, height: 40, backgroundColor: theme.border, marginHorizontal: 8 }} />
        <StatColumn
          value={stats.federated_members ?? 0}
          label={t('stats.members', { count: stats.federated_members ?? 0 })}
          primary={primary}
          theme={theme}
        />
        <View style={{ width: 1, height: 40, backgroundColor: theme.border, marginHorizontal: 8 }} />
        <StatColumn
          value={stats.cross_community_exchanges ?? 0}
          label={t('stats.exchanges', { count: stats.cross_community_exchanges ?? 0 })}
          primary={primary}
          theme={theme}
        />
      </View>
    );
  })();

  if (partnersLoading) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <FlatList
        data={partners}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderPartner}
        contentContainerStyle={{ padding: 16, paddingBottom: 48 }}
        ListHeaderComponent={
          <>
            {listHeader}
            <Text className="text-xs font-bold text-muted-foreground uppercase tracking-wider mb-3">
              {t('partners')}
            </Text>
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
            <View style={{ marginVertical: 16, alignItems: 'center' }}>
              <Spinner size="sm" />
            </View>
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
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <View style={{ flex: 1, alignItems: 'center', gap: 4 }}>
      <Text style={{ fontSize: 20, fontWeight: '700', color: primary }}>
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
