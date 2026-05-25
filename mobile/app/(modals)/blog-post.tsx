// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useCallback } from 'react';
import {
  Image,
  Pressable,
  RefreshControl,
  ScrollView,
  Share,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { getBlogPost, type BlogPost } from '@/lib/api/blog';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import Avatar from '@/components/ui/Avatar';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function BlogPostScreen() {
  const { t } = useTranslation('blog');
  const navigation = useNavigation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  // Keep useTheme only for non-className-able props: tintColor/colors on RefreshControl,
  // and the info/infoBg tokens which have no Tailwind equivalent here.
  const theme = useTheme();

  const handleShare = useCallback(async (sharePost: { title: string; slug: string; excerpt: string | null }) => {
    const url = `${WEB_URL}/blog/${sharePost.slug}`;
    const message = sharePost.excerpt
      ? `${sharePost.title}\n\n${sharePost.excerpt}\n\n${url}`
      : `${sharePost.title}\n\n${url}`;
    await Share.share({ message, url });
  }, []);

  useEffect(() => {
    navigation.setOptions({ title: t('detail.title') });
  }, [navigation, t]);

  const slug = id?.trim() || '';

  const { data, isLoading, refresh } = useApi(
    () => getBlogPost(slug),
    [slug],
    { enabled: slug.length > 0 },
  );

  const post: BlogPost | null = data?.data ?? null;

  if (!slug) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.invalidId')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text className="text-[15px] font-semibold" style={{ color: primary }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!post) {
    return (
      <SafeAreaView className="flex-1 items-center justify-center" edges={['bottom']}>
        <Text className="text-sm text-muted-foreground">{t('detail.notFound')}</Text>
        <Pressable onPress={() => router.back()} className="mt-3">
          <Text className="text-[15px] font-semibold" style={{ color: primary }}>{t('detail.goBack')}</Text>
        </Pressable>
      </SafeAreaView>
    );
  }

  const publishedDate = post.published_at ? new Date(post.published_at).toLocaleDateString('default', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }) : '';

  return (
    <ModalErrorBoundary>
    <SafeAreaView className="flex-1 bg-background" edges={['bottom']}>
      <ScrollView
        contentContainerStyle={{ paddingBottom: 48 }}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Cover image */}
        {post.cover_image ? (
          <Image
            source={{ uri: post.cover_image }}
            className="w-full h-[200px] bg-surface"
            resizeMode="cover"
            accessibilityLabel={post.title}
          />
        ) : null}

        {/* Title */}
        <Text className="text-xl font-bold text-foreground px-5 pt-5 mb-4 leading-[30px]">{post.title}</Text>

        {/* Author row */}
        <View className="flex-row items-center px-5 mb-4 gap-2">
          <Avatar uri={post.author?.avatar ?? null} name={post.author?.name ?? '?'} size={36} />
          <View className="flex-1">
            <Text className="text-sm font-semibold text-foreground">{t('by', { name: post.author?.name ?? '?' })}</Text>
            <Text className="text-[12px] text-muted-foreground mt-0.5">{publishedDate}</Text>
          </View>
          {post.reading_time_minutes ? (
            <View className="flex-row items-center gap-1 bg-surface rounded-lg border border-border px-2 py-1">
              <Ionicons name="time-outline" size={13} color={theme.textSecondary} />
              <Text className="text-[12px] text-muted-foreground">
                {t('readingTime', { minutes: post.reading_time_minutes })}
              </Text>
            </View>
          ) : null}
          <Pressable
            onPress={() => void handleShare(post)}
            className="p-1"
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </Pressable>
        </View>

        {/* Category */}
        {post.category ? (
          <View
            className="self-start rounded px-2.5 py-1 mx-5 mb-3"
            style={{ backgroundColor: withAlpha(primary, 0.13) }}
          >
            <Text className="text-[12px] font-semibold" style={{ color: primary }}>{post.category}</Text>
          </View>
        ) : null}

        {/* Tags */}
        {(post.tags ?? []).length > 0 ? (
          <View className="flex-row flex-wrap px-5 gap-2 mb-5">
            {(post.tags ?? []).map((tag) => (
              <View key={tag} className="rounded bg-surface border border-border px-2 py-1">
                <Text className="text-[12px] text-muted-foreground">{tag}</Text>
              </View>
            ))}
          </View>
        ) : null}

        {/* Content */}
        {post.content ? (
          <Text className="text-base text-foreground leading-[26px] px-5">{post.content}</Text>
        ) : post.excerpt ? (
          <View className="px-5">
            <Text className="text-base text-foreground leading-[26px] mb-4">{post.excerpt}</Text>
            <View
              className="flex-row items-center gap-1.5 rounded-lg p-3"
              style={{ backgroundColor: theme.infoBg }}
            >
              <Ionicons name="information-circle-outline" size={15} color={theme.info} />
              <Text className="text-xs font-medium flex-1" style={{ color: theme.info }}>
                {t('detail.readFull')}
              </Text>
            </View>
          </View>
        ) : null}
      </ScrollView>
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}
