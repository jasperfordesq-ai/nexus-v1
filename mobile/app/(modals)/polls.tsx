// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useRef, useState } from 'react';
import { FlatList, RefreshControl, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import PollCard from '@/components/PollCard';
import { getFeed, getFeedAuthor, type FeedItem, type FeedResponse } from '@/lib/api/feed';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';

function extractPollsPage(response: FeedResponse) {
  if (!response?.data || !response?.meta) {
    return { items: [], cursor: null, hasMore: false };
  }

  const seen = new Set<number>();
  const polls = response.data.filter((item) => {
    if (item.type !== 'poll' || !item.poll_data || seen.has(item.id)) return false;
    seen.add(item.id);
    return true;
  });

  return {
    items: polls,
    cursor: response.meta.cursor ?? null,
    hasMore: response.meta.has_more ?? false,
  };
}

export default function PollsScreen() {
  const { t } = useTranslation(['home', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [isRefreshing, setIsRefreshing] = useState(false);
  const wasRefreshingRef = useRef(false);

  const fetchPolls = useCallback(
    (cursor: string | null) => getFeed(1, cursor, { filter: 'polls', mode: 'recent', perPage: 20 }),
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<FeedItem, FeedResponse>(fetchPolls, extractPollsPage, []);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    wasRefreshingRef.current = true;
    refresh();
  }, [refresh]);

  useEffect(() => {
    if (wasRefreshingRef.current && !isLoading && isRefreshing) {
      wasRefreshingRef.current = false;
      setIsRefreshing(false);
    }
  }, [isLoading, isRefreshing]);

  const renderItem = useCallback(
    ({ item }: { item: FeedItem }) => (
      <PollFeedCard item={item} primary={primary} theme={theme} t={t} />
    ),
    [primary, theme, t],
  );

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('pollsScreen.title')} backLabel={t('common:back')} fallbackHref="/(tabs)/home" />

        <FlatList
          data={items}
          keyExtractor={(item) => `poll-${item.id}`}
          renderItem={renderItem}
          refreshControl={
            <RefreshControl refreshing={isRefreshing} onRefresh={handleRefresh} tintColor={primary} colors={[primary]} />
          }
          onEndReached={loadMore}
          onEndReachedThreshold={0.3}
          ListHeaderComponent={
            <View className="px-4 pb-3">
              <HeroCard className="overflow-hidden rounded-panel p-0">
                <View className="h-1.5" style={{ backgroundColor: primary }} />
                <HeroCard.Body className="gap-3 p-4">
                  <View className="flex-row items-center gap-3">
                    <View className="size-12 items-center justify-center rounded-2xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                      <Ionicons name="stats-chart-outline" size={24} color={primary} />
                    </View>
                    <View className="min-w-0 flex-1">
                      <Text className="text-xs font-semibold uppercase text-muted-foreground">
                        {t('pollsScreen.heroEyebrow')}
                      </Text>
                      <Text className="text-2xl font-bold text-foreground" numberOfLines={1}>
                        {t('pollsScreen.title')}
                      </Text>
                    </View>
                  </View>
                  <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
                    {t('pollsScreen.subtitle')}
                  </Text>
                </HeroCard.Body>
              </HeroCard>
            </View>
          }
          ListEmptyComponent={
            isLoading ? (
              <LoadingSpinner />
            ) : error ? (
              <HeroCard variant="secondary" className="mx-4 my-6">
                <HeroCard.Body className="items-center gap-4">
                  <Ionicons name="cloud-offline-outline" size={30} color={primary} />
                  <Text className="text-center text-base font-semibold text-foreground">
                    {t('pollsScreen.errorTitle')}
                  </Text>
                  <Text className="text-center text-sm text-danger">{error}</Text>
                  <HeroButton variant="primary" onPress={() => void refresh()} style={{ backgroundColor: primary }}>
                    <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
                  </HeroButton>
                </HeroCard.Body>
              </HeroCard>
            ) : (
              <EmptyState
                icon="stats-chart-outline"
                title={t('pollsScreen.emptyTitle')}
                subtitle={t('pollsScreen.emptySubtitle')}
              />
            )
          }
          ListFooterComponent={
            isLoadingMore ? (
              <View className="items-center py-4">
                <Spinner size="sm" />
              </View>
            ) : !hasMore && items.length > 0 && !isLoading ? (
              <View className="items-center py-4">
                <Text className="text-xs text-muted-foreground">{t('common:endOfList')}</Text>
              </View>
            ) : null
          }
          contentContainerStyle={{ paddingBottom: 28 }}
        />
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function PollFeedCard({
  item,
  primary,
  theme,
  t,
}: {
  item: FeedItem;
  primary: string;
  theme: ReturnType<typeof useTheme>;
  t: (key: string, opts?: Record<string, unknown>) => string;
}) {
  if (!item.poll_data) return null;

  const author = getFeedAuthor(item, t('common:labels.member'));
  const voteCount = item.poll_data.total_votes ?? 0;

  return (
    <HeroCard variant="default" className="mx-4 mb-3 overflow-hidden rounded-panel p-0">
      <HeroCard.Body className="gap-4 p-4">
        <View className="flex-row items-start justify-between gap-3">
          <View className="min-w-0 flex-1 gap-1">
            <Text className="text-base font-semibold text-foreground" numberOfLines={2}>
              {t('pollsScreen.feedItemTitle', { title: item.title || item.poll_data.question })}
            </Text>
            <Text className="text-xs text-muted-foreground" numberOfLines={1}>
              {author.name}
            </Text>
          </View>
          <Chip size="sm" variant={item.poll_data.is_active ? 'secondary' : 'soft'} color={item.poll_data.is_active ? 'accent' : 'default'}>
            <Ionicons name={item.poll_data.is_active ? 'radio-button-on-outline' : 'lock-closed-outline'} size={12} color={primary} />
            <Chip.Label>{item.poll_data.is_active ? t('pollsScreen.statusOpen') : t('pollsScreen.statusClosed')}</Chip.Label>
          </Chip>
        </View>

        <Surface variant="secondary" className="rounded-panel-inner p-3">
          <PollCard pollData={item.poll_data} itemId={item.id} />
        </Surface>

        <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
          {t('pollsScreen.totalVotes', { count: voteCount })}
        </Text>
      </HeroCard.Body>
    </HeroCard>
  );
}
