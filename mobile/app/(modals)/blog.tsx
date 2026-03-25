// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo } from 'react';
import {
  FlatList,
  Image,
  RefreshControl,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, useNavigation } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import { useTranslation } from 'react-i18next';

import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import { getBlogPosts, type BlogPost, type BlogListResponse } from '@/lib/api/blog';
import EmptyState from '@/components/ui/EmptyState';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
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
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

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
    <SafeAreaView style={styles.container} edges={['bottom']}>
      <FlatList<BlogPost>
        data={items}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <TouchableOpacity
            style={styles.card}
            activeOpacity={0.8}
            onPress={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push({ pathname: '/(modals)/blog-post', params: { id: item.slug } });
            }}
          >
            {item.cover_image ? (
              <Image source={{ uri: item.cover_image }} style={styles.cover} resizeMode="cover" />
            ) : (
              <View style={[styles.cover, styles.coverPlaceholder]}>
                <Ionicons name="newspaper-outline" size={32} color={theme.textMuted} />
              </View>
            )}
            <View style={styles.cardBody}>
              {item.category ? (
                <View style={[styles.categoryPill, { backgroundColor: withAlpha(primary, 0.13) }]}>
                  <Text style={[styles.categoryText, { color: primary }]}>{item.category}</Text>
                </View>
              ) : null}
              <Text style={styles.postTitle} numberOfLines={2}>{item.title}</Text>
              {item.excerpt ? (
                <Text style={styles.excerpt} numberOfLines={2}>{item.excerpt}</Text>
              ) : null}
              <View style={styles.meta}>
                <Text style={styles.metaText}>{item.author?.name ?? ''}</Text>
                {item.reading_time_minutes ? (
                  <Text style={styles.metaText}>
                    {t('readingTime', { minutes: item.reading_time_minutes })}
                  </Text>
                ) : null}
              </View>
            </View>
          </TouchableOpacity>
        )}
        refreshControl={
          <RefreshControl refreshing={isLoading && items.length > 0} onRefresh={refresh} tintColor={primary} colors={[primary]} />
        }
        onEndReached={() => { if (hasMore) loadMore(); }}
        onEndReachedThreshold={0.3}
        ListEmptyComponent={
          isLoading ? (
            <View style={styles.centered}>
              <LoadingSpinner />
            </View>
          ) : error ? (
            <View style={styles.centered}>
              <Text style={styles.errorText}>{error}</Text>
              <TouchableOpacity onPress={() => void refresh()} style={styles.retryBtn}>
                <Text style={{ color: primary, fontWeight: '600', fontSize: 15 }}>
                  {t('common:buttons.retry')}
                </Text>
              </TouchableOpacity>
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
            <View style={styles.footer}>
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={styles.list}
      />
    </SafeAreaView>
    </ModalErrorBoundary>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    container: { flex: 1, backgroundColor: theme.bg },
    list: { paddingBottom: SPACING.lg },
    card: {
      backgroundColor: theme.surface,
      marginHorizontal: SPACING.md,
      marginTop: 12,
      borderRadius: RADIUS.lg,
      overflow: 'hidden',
      borderWidth: 1,
      borderColor: theme.borderSubtle,
    },
    cover: { width: '100%', height: 160 },
    coverPlaceholder: {
      backgroundColor: theme.surface,
      justifyContent: 'center',
      alignItems: 'center',
    },
    cardBody: { padding: RADIUS.lg },
    categoryPill: {
      alignSelf: 'flex-start',
      borderRadius: RADIUS.sm,
      paddingHorizontal: SPACING.sm,
      paddingVertical: 2,
      marginBottom: SPACING.sm,
    },
    categoryText: { fontSize: 11, fontWeight: '600' },
    postTitle: { fontSize: 16, fontWeight: '700', color: theme.text, marginBottom: RADIUS.sm },
    excerpt: { ...TYPOGRAPHY.label, color: theme.textSecondary, lineHeight: 20, marginBottom: RADIUS.md },
    meta: { flexDirection: 'row', gap: 12 },
    metaText: { ...TYPOGRAPHY.caption, color: theme.textMuted },
    centered: { flex: 1, justifyContent: 'center', alignItems: 'center', padding: SPACING.xl },
    errorText: { ...TYPOGRAPHY.label, color: theme.error, textAlign: 'center', marginBottom: 12 },
    retryBtn: { paddingHorizontal: 20, paddingVertical: RADIUS.md },
    footer: { paddingVertical: SPACING.md, alignItems: 'center' },
  });
}
