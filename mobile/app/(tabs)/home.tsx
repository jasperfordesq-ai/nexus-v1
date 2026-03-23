// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useMemo } from 'react';
import * as Haptics from 'expo-haptics';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import { useTranslation } from 'react-i18next';
import { getFeed, type FeedItem as FeedItemType, type FeedResponse } from '@/lib/api/feed';
import { getNotificationCounts } from '@/lib/api/notifications';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { useApi } from '@/lib/hooks/useApi';
import { useAuth } from '@/lib/hooks/useAuth';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import FeedItem from '@/components/FeedItem';
import OfflineBanner from '@/components/OfflineBanner';
import TenantBanner from '@/components/TenantBanner';
import { FeedItemSkeleton } from '@/components/ui/Skeleton';

function extractFeedPage(response: FeedResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor ?? null,
    hasMore: response.meta.has_more,
  };
}

export default function HomeScreen() {
  const { t } = useTranslation('home');
  const { displayName } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const fetchFeed = useCallback(
    (cursor: string | null) => getFeed(1, cursor),
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<FeedItemType, FeedResponse>(fetchFeed, extractFeedPage);

  const { data: countsData } = useApi(() => getNotificationCounts());
  const unreadNotifications = countsData?.data?.total ?? 0;

  return (
    <SafeAreaView style={styles.container}>
      <TenantBanner />
      <OfflineBanner />

      <FlatList<FeedItemType>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => <FeedItem item={item} />}
        refreshControl={
          <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={() => void refresh()} tintColor={primary} colors={[primary]} />
        }
        onEndReached={loadMore}
        onEndReachedThreshold={0.3}
        ListHeaderComponent={
          <View style={styles.headerRow}>
            <View>
              <Text style={styles.greetingText}>
                {t('feed.greeting', { name: displayName.split(' ')[0] || t('common:labels.friend') })} 👋
              </Text>
              <Text style={styles.subText}>{t('feed.subtitle')}</Text>
            </View>
            <TouchableOpacity
              onPress={() => {
                void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                router.push('/(modals)/notifications');
              }}
              style={styles.bellButton}
              activeOpacity={0.7}
              accessibilityLabel={t('notifications.title')}
              accessibilityRole="button"
            >
              <Ionicons name="notifications-outline" size={24} color={theme.text} />
              {unreadNotifications > 0 && (
                <View style={[styles.bellBadge, { backgroundColor: primary }]}>
                  <Text style={styles.bellBadgeText}>
                    {unreadNotifications > 9 ? '9+' : unreadNotifications}
                  </Text>
                </View>
              )}
            </TouchableOpacity>
          </View>
        }
        ListEmptyComponent={
          isLoading ? (
            <>
              <FeedItemSkeleton />
              <FeedItemSkeleton />
              <FeedItemSkeleton />
            </>
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
                <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>{t('common:buttons.retry')}</Text>
              </TouchableOpacity>
            </View>
          ) : (
            <View style={styles.centered}>
              <Text style={styles.emptyTitle}>{t('feed.emptyTitle')}</Text>
              <Text style={styles.emptySubText}>{t('feed.emptySubtitle')}</Text>
            </View>
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View style={styles.footer}>
              <ActivityIndicator size="small" color={theme.textSecondary} />
            </View>
          ) : !hasMore && items.length > 0 && !isLoading ? (
            <View style={styles.footer}>
              <Text style={styles.endOfListText}>{t('common:endOfList')}</Text>
            </View>
          ) : null
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    list: { paddingBottom: 24 },
    headerRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      justifyContent: 'space-between',
      paddingHorizontal: 16,
      paddingTop: 16,
      paddingBottom: 8,
    },
    greetingText: { fontSize: 22, fontWeight: '700', color: theme.text },
    subText: { fontSize: 14, color: theme.textSecondary, marginTop: 2 },
    bellButton: { position: 'relative', padding: 10 },
    bellBadge: {
      position: 'absolute',
      top: 0,
      right: 0,
      minWidth: 16,
      height: 16,
      borderRadius: 8,
      justifyContent: 'center',
      alignItems: 'center',
      paddingHorizontal: 3,
    },
    bellBadgeText: { color: '#fff', fontSize: 9, fontWeight: '700' },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: 32 },
    errorText: { color: theme.error, fontSize: 14, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyTitle: { color: theme.text, fontSize: 17, fontWeight: '600', textAlign: 'center', marginBottom: 8 },
    emptySubText: { color: theme.textSecondary, fontSize: 14, textAlign: 'center', lineHeight: 20 },
    footer: { paddingVertical: 16, alignItems: 'center' },
    endOfListText: { fontSize: 13, color: theme.textMuted },
  });
}
