// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback } from 'react';
import {
  Image,
  RefreshControl,
  ScrollView,
  Share,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useLocalSearchParams } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Surface } from 'heroui-native';
import { useTranslation } from 'react-i18next';

import { getBlogPost, type BlogPost } from '@/lib/api/blog';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

const WEB_URL = 'https://app.project-nexus.ie';

export default function BlogPostScreen() {
  const { t } = useTranslation(['blog', 'common']);
  const { id } = useLocalSearchParams<{ id: string }>();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const slug = id?.trim() || '';

  const handleShare = useCallback(async (sharePost: { title: string; slug: string; excerpt: string | null }) => {
    const url = `${WEB_URL}/blog/${sharePost.slug}`;
    const message = sharePost.excerpt
      ? `${sharePost.title}\n\n${sharePost.excerpt}\n\n${url}`
      : `${sharePost.title}\n\n${url}`;
    await Share.share({ message, url });
  }, []);

  const { data, isLoading, refresh } = useApi(
    () => getBlogPost(slug),
    [slug],
    { enabled: slug.length > 0 },
  );

  const post: BlogPost | null = data?.data ?? null;

  if (!slug) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detail.title')} backLabel={t('common:back')} fallbackHref="/(modals)/blog" />
        <EmptyState
          icon="newspaper-outline"
          title={t('detail.invalidId')}
          subtitle={t('detail.invalidIdHint')}
          actionLabel={t('detail.backToBlog')}
          onAction={() => router.replace('/(modals)/blog')}
        />
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detail.title')} backLabel={t('common:back')} fallbackHref="/(modals)/blog" />
        <View className="flex-1 items-center justify-center">
          <LoadingSpinner />
        </View>
      </SafeAreaView>
    );
  }

  if (!post) {
    return (
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar title={t('detail.title')} backLabel={t('common:back')} fallbackHref="/(modals)/blog" />
        <EmptyState
          icon="newspaper-outline"
          title={t('detail.notFound')}
          subtitle={t('detail.notFoundHint')}
          actionLabel={t('detail.backToBlog')}
          onAction={() => router.replace('/(modals)/blog')}
        />
      </SafeAreaView>
    );
  }

  const publishedDate = post.published_at
    ? new Date(post.published_at).toLocaleDateString('default', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      })
    : '';

  return (
    <ModalErrorBoundary>
      <SafeAreaView className="flex-1 bg-background">
        <AppTopBar
          title={t('detail.title')}
          backLabel={t('common:back')}
          fallbackHref="/(modals)/blog"
          rightAction={{
            accessibilityLabel: t('detail.share'),
            icon: 'share-outline',
            onPress: () => handleShare(post),
          }}
        />
        <ScrollView
          contentContainerStyle={{ paddingHorizontal: 16, paddingBottom: 48 }}
          showsVerticalScrollIndicator={false}
          refreshControl={
            <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
          }
        >
          <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
            {post.cover_image ? (
              <Image
                source={{ uri: resolveImageUrl(post.cover_image) ?? post.cover_image }}
                className="h-[220px] w-full"
                resizeMode="cover"
                accessibilityLabel={post.title}
              />
            ) : (
              <View className="h-[180px] w-full items-center justify-center" style={{ backgroundColor: withAlpha(primary, 0.10) }}>
                <Ionicons name="newspaper-outline" size={42} color={primary} />
              </View>
            )}
            <HeroCard.Body className="gap-4 p-4">
              <View className="flex-row flex-wrap gap-2">
                {post.category ? (
                  <Chip size="sm" variant="secondary">
                    <Ionicons name="folder-open-outline" size={12} color={primary} />
                    <Chip.Label>{post.category}</Chip.Label>
                  </Chip>
                ) : null}
                {post.reading_time_minutes ? (
                  <Chip size="sm" variant="secondary">
                    <Ionicons name="time-outline" size={12} color={theme.textSecondary} />
                    <Chip.Label>{t('readingTime', { minutes: post.reading_time_minutes })}</Chip.Label>
                  </Chip>
                ) : null}
              </View>

              <Text className="text-2xl font-bold leading-8" style={{ color: theme.text }}>
                {post.title}
              </Text>

              {post.excerpt ? (
                <Text className="text-base leading-6" style={{ color: theme.textSecondary }}>
                  {post.excerpt}
                </Text>
              ) : null}

              <Surface variant="secondary" className="flex-row items-center gap-3 rounded-panel-inner p-3">
                <Avatar uri={post.author?.avatar ?? null} name={post.author?.name ?? '?'} size={40} />
                <View className="min-w-0 flex-1">
                  <Text className="text-sm font-semibold" style={{ color: theme.text }}>
                    {t('by', { name: post.author?.name ?? '?' })}
                  </Text>
                  {publishedDate ? (
                    <Text className="text-xs" style={{ color: theme.textSecondary }}>
                      {t('publishedOn', { date: publishedDate })}
                    </Text>
                  ) : null}
                </View>
                <HeroButton isIconOnly variant="secondary" accessibilityLabel={t('detail.share')} onPress={() => void handleShare(post)}>
                  <Ionicons name="share-outline" size={18} color={primary} />
                </HeroButton>
              </Surface>
            </HeroCard.Body>
          </HeroCard>

          {(post.tags ?? []).length > 0 ? (
            <HeroCard className="mb-4 rounded-panel p-0">
              <HeroCard.Body className="gap-3 p-4">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
                  {t('detail.tags')}
                </Text>
                <View className="flex-row flex-wrap gap-2">
                  {(post.tags ?? []).map((tag) => (
                    <Chip key={tag} size="sm" variant="secondary">
                      <Chip.Label>{tag}</Chip.Label>
                    </Chip>
                  ))}
                </View>
              </HeroCard.Body>
            </HeroCard>
          ) : null}

          <HeroCard className="rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4">
              <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>
                {t('detail.article')}
              </Text>
              {post.content ? (
                <Text className="text-base leading-7" style={{ color: theme.text }}>
                  {stripHtml(post.content)}
                </Text>
              ) : post.excerpt ? (
                <>
                  <Text className="text-base leading-7" style={{ color: theme.text }}>
                    {post.excerpt}
                  </Text>
                  <Surface variant="secondary" className="flex-row items-center gap-2 rounded-panel-inner p-3">
                    <Ionicons name="information-circle-outline" size={16} color={theme.info ?? primary} />
                    <Text className="min-w-0 flex-1 text-xs font-medium" style={{ color: theme.info ?? primary }}>
                      {t('detail.readFull')}
                    </Text>
                  </Surface>
                </>
              ) : null}
            </HeroCard.Body>
          </HeroCard>
        </ScrollView>
      </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function stripHtml(value: string): string {
  return value
    .replace(/<br\s*\/?>/gi, '\n')
    .replace(/<\/p>/gi, '\n\n')
    .replace(/<[^>]*>/g, '')
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}
