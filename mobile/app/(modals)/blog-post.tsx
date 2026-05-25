// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useMemo, useCallback } from 'react';
import {
  Image,
  RefreshControl,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useLocalSearchParams, router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import { getBlogPost, type BlogPost } from '@/lib/api/blog';
import { useApi } from '@/lib/hooks/useApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
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
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.invalidId')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  if (isLoading) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <LoadingSpinner />
      </SafeAreaView>
    );
  }

  if (!post) {
    return (
      <SafeAreaView style={styles.center} edges={['bottom']}>
        <Text style={styles.errorText}>{t('detail.notFound')}</Text>
        <TouchableOpacity onPress={() => router.back()} style={{ marginTop: 12 }}>
          <Text style={{ color: primary, fontSize: 15, fontWeight: '600' }}>{t('detail.goBack')}</Text>
        </TouchableOpacity>
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
    <SafeAreaView style={styles.container} edges={['bottom']}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={isLoading} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
      >
        {/* Cover image */}
        {post.cover_image ? (
          <Image
            source={{ uri: post.cover_image }}
            style={styles.coverImage}
            resizeMode="cover"
            accessibilityLabel={post.title}
          />
        ) : null}

        {/* Title */}
        <Text style={styles.title}>{post.title}</Text>

        {/* Author row */}
        <View style={styles.authorRow}>
          <Avatar uri={post.author?.avatar ?? null} name={post.author?.name ?? '?'} size={36} />
          <View style={styles.authorMeta}>
            <Text style={styles.authorName}>{t('by', { name: post.author?.name ?? '?' })}</Text>
            <Text style={styles.dateText}>{publishedDate}</Text>
          </View>
          {post.reading_time_minutes ? (
            <View style={styles.readingTimeBadge}>
              <Ionicons name="time-outline" size={13} color={theme.textSecondary} />
              <Text style={styles.readingTimeText}>
                {t('readingTime', { minutes: post.reading_time_minutes })}
              </Text>
            </View>
          ) : null}
          <TouchableOpacity
            onPress={() => void handleShare(post)}
            style={styles.shareButton}
            activeOpacity={0.7}
            accessibilityLabel={t('detail.share')}
            accessibilityRole="button"
          >
            <Ionicons name="share-outline" size={22} color={primary} />
          </TouchableOpacity>
        </View>

        {/* Category */}
        {post.category ? (
          <View style={[styles.categoryPill, { backgroundColor: withAlpha(primary, 0.13) }]}>
            <Text style={[styles.categoryText, { color: primary }]}>{post.category}</Text>
          </View>
        ) : null}

        {/* Tags */}
        {(post.tags ?? []).length > 0 ? (
          <View style={styles.tagsRow}>
            {(post.tags ?? []).map((tag) => (
              <View key={tag} style={styles.tagPill}>
                <Text style={styles.tagText}>{tag}</Text>
              </View>
            ))}
          </View>
        ) : null}

        {/* Content */}
        {post.content ? (
          <Text style={styles.content_body}>{post.content}</Text>
        ) : post.excerpt ? (
          <View style={styles.excerptWrap}>
            <Text style={styles.excerpt}>{post.excerpt}</Text>
            <View style={[styles.previewNote, { backgroundColor: theme.infoBg }]}>
              <Ionicons name="information-circle-outline" size={15} color={theme.info} />
              <Text style={[styles.previewNoteText, { color: theme.info }]}>
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

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
    content: { paddingBottom: SPACING.xxl },
    coverImage: {
      width: '100%',
      height: 200,
      backgroundColor: theme.surface,
    },
    title: {
      ...TYPOGRAPHY.h2,
      color: theme.text,
      paddingHorizontal: 20,
      paddingTop: 20,
      marginBottom: SPACING.md,
      lineHeight: 30,
    },
    authorRow: {
      flexDirection: 'row',
      alignItems: 'center',
      paddingHorizontal: 20,
      marginBottom: SPACING.md,
      gap: RADIUS.md,
    },
    authorMeta: { flex: 1 },
    authorName: { ...TYPOGRAPHY.label, fontWeight: '600', color: theme.text },
    dateText: { ...TYPOGRAPHY.caption, color: theme.textMuted, marginTop: 2 },
    readingTimeBadge: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 4,
      backgroundColor: theme.surface,
      borderRadius: 8,
      borderWidth: 1,
      borderColor: theme.border,
      paddingHorizontal: 8,
      paddingVertical: 4,
    },
    readingTimeText: { ...TYPOGRAPHY.caption, color: theme.textSecondary },
    shareButton: { padding: 4 },
    categoryPill: {
      alignSelf: 'flex-start',
      borderRadius: SPACING.sm,
      paddingHorizontal: 10,
      paddingVertical: 4,
      marginHorizontal: 20,
      marginBottom: 12,
    },
    categoryText: { ...TYPOGRAPHY.caption, fontWeight: '600' },
    tagsRow: {
      flexDirection: 'row',
      flexWrap: 'wrap',
      paddingHorizontal: 20,
      gap: SPACING.sm,
      marginBottom: 20,
    },
    tagPill: {
      borderRadius: RADIUS.sm,
      backgroundColor: theme.surface,
      borderWidth: 1,
      borderColor: theme.border,
      paddingHorizontal: SPACING.sm,
      paddingVertical: 4,
    },
    tagText: { ...TYPOGRAPHY.caption, color: theme.textSecondary },
    content_body: {
      fontSize: 16,
      color: theme.text,
      lineHeight: 26,
      paddingHorizontal: 20,
    },
    excerptWrap: { paddingHorizontal: 20 },
    excerpt: {
      fontSize: 16,
      color: theme.text,
      lineHeight: 26,
      marginBottom: 16,
    },
    previewNote: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 6,
      borderRadius: 8,
      padding: 12,
    },
    previewNoteText: { ...TYPOGRAPHY.bodySmall, fontWeight: '500', flex: 1 },
    errorText: { ...TYPOGRAPHY.body, color: theme.textMuted },
  });
}
