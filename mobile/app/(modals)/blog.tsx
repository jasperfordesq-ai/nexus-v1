// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect } from 'react';
import {
  FlatList,
  Image,
  Pressable,
  RefreshControl,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { getBlogPosts, type BlogPost, type BlogListResponse } from '@/lib/api/blog';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { withAlpha } from '@/lib/utils/color';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

function extractBlogPage(response: BlogListResponse) {
  return {
    items: response.data,
    cursor: response.meta.cursor,
    hasMore: response.meta.has_more,
  };
}

export default function BlogScreen() {
  const { t } = useTranslation('blog');
  const navigation = useNavigation();
  const primary = usePrimaryColor();

  useEffect(() => {
    navigation.setOptions({ title: t('title') });
  }, [navigation, t]);

  const fetchPosts = useCallback(
    (cursor: string | null) => getBlogPosts(cursor),
    [],
  );

  const { items, isLoading, isLoadingMore, error, hasMore, loadMore, refresh } =
    usePaginatedApi<BlogPost, BlogListResponse>(fetchPosts, extractBlogPage, []);

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <FlatList<BlogPost>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <Pressable
            className="bg-surface mx-4 mt-3 rounded-xl overflow-hidden border border-border/50"
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push({ pathname: '/(modals)/blog-post', params: { id: item.slug } });
            }}
          >
            {item.cover_image ? (
              <Image source={{ uri: item.cover_image }} className="w-full h-40" resizeMode="cover" />
            ) : (
              <View className="w-full h-40 bg-surface justify-center items-center">
                <Ionicons name="newspaper-outline" size={32} className="text-muted-foreground" />
              </View>
            )}
            <View className="p-4">
              {item.category ? (
                <View
                  className="self-start rounded px-2 py-0.5 mb-2"
                  style={{ backgroundColor: withAlpha(primary, 0.13) }}
                >
                  <Text className="text-[11px] font-semibold" style={{ color: primary }}>{item.category}</Text>
                </View>
              ) : null}
              <Text className="text-base font-bold text-foreground mb-1" numberOfLines={2}>{item.title}</Text>
              {item.excerpt ? (
                <Text className="text-sm text-muted-foreground leading-5 mb-2" numberOfLines={2}>{item.excerpt}</Text>
              ) : null}
              <View className="flex-row gap-3">
                <Text className="text-[12px] text-muted-foreground">{item.author?.name ?? ''}</Text>
                {item.reading_time_minutes ? (
                  <Text className="text-[12px] text-muted-foreground">
                    {t('readingTime', { minutes: item.reading_time_minutes })}
                  </Text>
                ) : null}
              </View>
            </View>
          </Pressable>
        )}
        refreshControl={
          <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          isLoading ? (
            <View className="flex-1 justify-center items-center p-8">
              <LoadingSpinner />
            </View>
          ) : error ? (
            <View className="flex-1 justify-center items-center p-8">
              <Text className="text-sm text-danger text-center mb-3">{error}</Text>
              <Pressable onPress={() => void refresh()} className="px-5 py-2">
                <Text className="font-semibold text-[15px]" style={{ color: primary }}>
                  {t('common:buttons.retry')}
                </Text>
              </Pressable>
            </View>
          ) : (
            <EmptyState
              icon="newspaper-outline"
              title={t('empty')}
            />
          )
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4 items-center">
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={{ paddingBottom: 24 }}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
