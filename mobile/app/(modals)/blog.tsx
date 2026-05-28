// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useState } from 'react';
import {
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  Text,
  TextInput,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { getBlogPosts, type BlogPost, type BlogListResponse } from '@/lib/api/blog';
import AppTopBar from '@/components/ui/AppTopBar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

function extractBlogPage(response: BlogListResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor,
    hasMore: response.meta.has_more,
  };
}

export default function BlogScreen() {
  const { t } = useTranslation(['blog', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [search, setSearch] = useState('');
  const [committedSearch, setCommittedSearch] = useState('');

  const fetchPosts = useCallback(
    (cursor: string | null) => getBlogPosts(cursor, committedSearch.trim() || undefined),
    [committedSearch],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<BlogPost, BlogListResponse>(fetchPosts, extractBlogPage, [committedSearch]);

  function submitSearch() {
    setCommittedSearch(search.trim());
  }

  function clearSearch() {
    setSearch('');
    setCommittedSearch('');
  }

  function openPost(item: BlogPost) {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    router.push({ pathname: '/(modals)/blog-post', params: { id: item.slug } });
  }

  function renderHeader() {
    return (
      <View className="px-4 pb-3">
        <HeroCard className="mb-3 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: primary }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-12 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
                <Ionicons name="book-outline" size={24} color={primary} />
              </View>
              <View className="min-w-0 flex-1 gap-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
                  {t('heroEyebrow')}
                </Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>
                  {t('title')}
                </Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>
                  {t('subtitle')}
                </Text>
              </View>
              {items.length > 0 ? (
                <Chip size="sm" variant="secondary">
                  <Chip.Label>{t('postsCount', { count: items.length })}</Chip.Label>
                </Chip>
              ) : null}
            </View>
          </HeroCard.Body>
        </HeroCard>

        <Surface variant="secondary" className="mb-3 flex-row items-center gap-2 rounded-panel-inner px-3 py-2.5">
          <Ionicons name="search-outline" size={18} color={theme.textMuted} />
          <TextInput
            className="min-w-0 flex-1 py-0 text-sm"
            style={{ color: theme.text }}
            placeholder={t('searchPlaceholder')}
            placeholderTextColor={theme.textMuted}
            value={search}
            onChangeText={setSearch}
            onSubmitEditing={submitSearch}
            returnKeyType="search"
          />
          {search.length > 0 ? (
            <HeroButton isIconOnly size="sm" variant="ghost" accessibilityLabel={t('clearSearch')} onPress={clearSearch}>
              <Ionicons name="close-circle-outline" size={18} color={theme.textMuted} />
            </HeroButton>
          ) : null}
          <HeroButton size="sm" variant="primary" style={{ backgroundColor: primary }} onPress={submitSearch} accessibilityLabel={t('searchAction')}>
            <HeroButton.Label>{t('searchAction')}</HeroButton.Label>
          </HeroButton>
        </Surface>
      </View>
    );
  }

  function renderPost({ item, index }: { item: BlogPost; index: number }) {
    const imageHeight = index === 0 && !committedSearch ? 190 : 150;
    const coverImage = resolveImageUrl(item.cover_image);
    const publishedLabel = item.published_at
      ? t('publishedOn', { date: new Date(item.published_at).toLocaleDateString() })
      : null;

    return (
      <Pressable
        className="mx-4 mb-3"
        onPress={() => openPost(item)}
        accessibilityRole="button"
        accessibilityLabel={item.title}
      >
        <HeroCard className="overflow-hidden rounded-panel p-0">
          {coverImage ? (
            <Image source={{ uri: coverImage }} className="w-full" style={{ height: imageHeight }} resizeMode="cover" />
          ) : (
            <View className="w-full items-center justify-center" style={{ height: imageHeight, backgroundColor: withAlpha(primary, 0.10) }}>
              <Ionicons name="newspaper-outline" size={36} color={primary} />
            </View>
          )}
          <HeroCard.Body className="gap-3 p-4">
            <View className="flex-row flex-wrap items-center gap-2">
              {item.category ? (
                <Chip size="sm" variant="secondary">
                  <Ionicons name="folder-open-outline" size={12} color={primary} />
                  <Chip.Label>{item.category}</Chip.Label>
                </Chip>
              ) : null}
              {item.reading_time_minutes ? (
                <Chip size="sm" variant="secondary">
                  <Ionicons name="time-outline" size={12} color={theme.textSecondary} />
                  <Chip.Label>{t('readingTime', { minutes: item.reading_time_minutes })}</Chip.Label>
                </Chip>
              ) : null}
            </View>

            <Text className="text-lg font-bold leading-6" style={{ color: theme.text }} numberOfLines={2}>
              {item.title}
            </Text>
            {item.excerpt ? (
              <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
                {item.excerpt}
              </Text>
            ) : null}

            <View className="flex-row flex-wrap items-center gap-2">
              {item.author?.name ? (
                <Chip size="sm" variant="secondary">
                  <Ionicons name="person-outline" size={12} color={theme.textSecondary} />
                  <Chip.Label>{t('by', { name: item.author.name })}</Chip.Label>
                </Chip>
              ) : null}
              {publishedLabel ? (
                <Chip size="sm" variant="secondary">
                  <Ionicons name="calendar-outline" size={12} color={theme.textSecondary} />
                  <Chip.Label>{publishedLabel}</Chip.Label>
                </Chip>
              ) : null}
            </View>
          </HeroCard.Body>
        </HeroCard>
      </Pressable>
    );
  }

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('title')} backLabel={t('common:back')} fallbackHref="/(tabs)/profile" />
        <FlatList<BlogPost>
          data={items}
          keyExtractor={(item) => String(item.id)}
          renderItem={renderPost}
          ListHeaderComponent={renderHeader}
          refreshControl={
            <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={refresh} tintColor={primary} colors={[primary]} />
          }
          onEndReached={() => { if (hasMore) loadMore(); }}
          onEndReachedThreshold={0.3}
          ListEmptyComponent={
            isLoading ? (
              <View className="items-center p-8">
                <LoadingSpinner />
              </View>
            ) : error ? (
              <Surface variant="secondary" className="mx-4 rounded-panel p-6">
                <View className="items-center gap-3">
                  <Ionicons name="warning-outline" size={34} color={theme.error} />
                  <Text className="text-center text-sm" style={{ color: theme.text }}>{error}</Text>
                  <HeroButton variant="secondary" onPress={() => void refresh()}>
                    <HeroButton.Label>{t('common:buttons.retry')}</HeroButton.Label>
                  </HeroButton>
                </View>
              </Surface>
            ) : (
              <EmptyState
                icon="newspaper-outline"
                title={committedSearch ? t('emptyFiltered') : t('empty')}
                subtitle={committedSearch ? t('emptyFilteredHint') : t('emptyHint')}
              />
            )
          }
          ListFooterComponent={
            isLoadingMore ? (
              <View className="items-center py-4">
                <LoadingSpinner />
              </View>
            ) : hasMore ? (
              <View className="px-4 py-3">
                <HeroButton variant="secondary" onPress={loadMore}>
                  <HeroButton.Label>{t('loadMore')}</HeroButton.Label>
                </HeroButton>
              </View>
            ) : null
          }
          contentContainerStyle={{ paddingBottom: 24 }}
        />
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}
