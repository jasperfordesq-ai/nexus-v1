// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import * as Haptics from 'expo-haptics';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';

import * as Sentry from '@sentry/react-native';
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
import StoryCircles from '@/components/StoryCircles';
import TenantBanner from '@/components/TenantBanner';
import { FeedItemSkeleton } from '@/components/ui/Skeleton';
import FAB from '@/components/ui/FAB';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING } from '@/lib/styles/spacing';

function extractFeedPage(response: FeedResponse) {
  if (!response?.data || !response?.meta) {
    console.error('Unexpected feed response shape:', response);
    Sentry.captureException(new Error('Unexpected feed response shape'));
    return { items: [], cursor: null, hasMore: false };
  }
  // Deduplicate — the API can return the same item across page boundaries
  const seen = new Set<string>();
  const unique = response.data.filter((item) => {
    const key = `${item.type}-${item.id}`;
    if (seen.has(key)) return false;
    seen.add(key);
    return true;
  });
  return {
    items: unique,
    cursor: response.meta.cursor ?? null,
    hasMore: response.meta.has_more ?? false,
  };
}

export default function HomeScreen() {
  const { t } = useTranslation('home');
  const { user, displayName } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  const fetchFeed = useCallback(
    (cursor: string | null) => getFeed(1, cursor),
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<FeedItemType, FeedResponse>(fetchFeed, extractFeedPage);

  const [isRefreshing, setIsRefreshing] = useState(false);
  const wasRefreshingRef = useRef(false);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    wasRefreshingRef.current = true;
    refresh();
  }, [refresh]);

  // Clear the pull-to-refresh spinner once loading finishes
  useEffect(() => {
    if (wasRefreshingRef.current && !isLoading) {
      wasRefreshingRef.current = false;
      setIsRefreshing(false);
    }
  }, [isLoading]);

  const { data: countsData } = useApi(() => getNotificationCounts());
  const unreadNotifications = countsData?.data?.total ?? 0;

  // Derive unique members from feed for story circles (exclude current user — "You" circle handles that)
  const storyMembers = useMemo(() => {
    const seen = new Set<number>();
    if (user?.id) seen.add(user.id);
    const members: { id: number; name: string; avatar?: string | null }[] = [];
    for (const item of items) {
      if (item.user_id && !seen.has(item.user_id)) {
        seen.add(item.user_id);
        members.push({ id: item.user_id, name: item.author_name || '', avatar: item.author_avatar ?? null });
      }
      if (members.length >= 10) break;
    }
    return members;
  }, [items, user?.id]);

  const handleStoryPress = useCallback((memberId: number) => {
    router.push({ pathname: '/(modals)/member-profile', params: { id: String(memberId) } });
  }, []);

  const renderItem = useCallback(({ item }: { item: FeedItemType }) => {
    return <FeedItem item={item} />;
  }, []);

  const keyExtractor = useCallback(
    (item: FeedItemType, index: number) => `${item.type}-${item.id}-${index}`,
    [],
  );

  return (
    <SafeAreaView style={styles.container}>
      <TenantBanner />
      <OfflineBanner />

      <FlatList<FeedItemType>
        data={items}
        keyExtractor={keyExtractor}
        renderItem={renderItem}
        refreshControl={
          <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} tintColor={primary} colors={[primary]} />
        }
        onEndReached={loadMore}
        onEndReachedThreshold={0.3}
        removeClippedSubviews={true}
        maxToRenderPerBatch={8}
        windowSize={5}
        ListHeaderComponent={
          <View>
            {storyMembers.length > 0 && (
              <StoryCircles members={storyMembers} onPress={handleStoryPress} />
            )}
            <View style={styles.headerRow}>
              <View>
                <Text style={styles.greetingText}>
                  {t('feed.greeting', { name: (displayName || '').split(' ')[0] || t('common:labels.friend') })} 👋
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
                <Text style={{ color: primary, ...TYPOGRAPHY.button }}>{t('common:buttons.retry')}</Text>
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

      <FAB
        icon="add"
        onPress={() => router.push('/(modals)/new-exchange')}
        position="bottom-right"
      />
    </SafeAreaView>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    list: { paddingBottom: SPACING.lg },
    headerRow: {
      flexDirection: 'row',
      alignItems: 'flex-start',
      justifyContent: 'space-between',
      paddingHorizontal: SPACING.md,
      paddingTop: SPACING.md,
      paddingBottom: SPACING.sm,
    },
    greetingText: { ...TYPOGRAPHY.h2, color: theme.text },
    subText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.textSecondary, marginTop: SPACING.xxs },
    bellButton: { position: 'relative', padding: 10 },
    bellBadge: {
      position: 'absolute',
      top: 0,
      right: 0,
      minWidth: SPACING.md,
      height: SPACING.md,
      borderRadius: SPACING.sm,
      justifyContent: 'center',
      alignItems: 'center',
      paddingHorizontal: 3,
    },
    bellBadgeText: { color: '#fff', fontSize: 9, fontWeight: '700' },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SPACING.xl },
    errorText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.error, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: 10 },
    emptyTitle: { color: theme.text, fontSize: 17, fontWeight: '600', textAlign: 'center', marginBottom: SPACING.sm },
    emptySubText: { ...TYPOGRAPHY.label, fontWeight: '400', color: theme.textSecondary, textAlign: 'center', lineHeight: 20 },
    footer: { paddingVertical: SPACING.md, alignItems: 'center' },
    endOfListText: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
  });
}
