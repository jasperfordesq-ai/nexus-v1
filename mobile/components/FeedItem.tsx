// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo, useRef, useCallback } from 'react';
import { View, Text, TouchableOpacity, StyleSheet, Animated, Share } from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { toggleLike, toggleBookmark, type FeedItem as FeedItemType, type PollData } from '@/lib/api/feed';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import { TYPOGRAPHY } from '@/lib/styles/typography';
import { SPACING, RADIUS } from '@/lib/styles/spacing';
import Avatar from '@/components/ui/Avatar';
import Card from '@/components/ui/Card';
import ImageCarousel from '@/components/ui/ImageCarousel';
import ActionSheet from '@/components/ui/ActionSheet';
import PollCard from '@/components/PollCard';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';

interface FeedItemProps {
  item: FeedItemType;
}

export default function FeedItem({ item }: FeedItemProps) {
  const { t } = useTranslation('home');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  // Optimistic like state — initialise from server if available
  const [liked, setLiked] = useState(item.is_liked ?? false);
  const [likesCount, setLikesCount] = useState(item.likes_count ?? 0);
  const [pollData, setPollData] = useState<PollData | null | undefined>(item.poll_data);
  const [bookmarked, setBookmarked] = useState(false);
  const [actionSheetVisible, setActionSheetVisible] = useState(false);

  // Double-tap detection & single-tap navigation delay
  const lastTapRef = useRef<number>(0);
  const singleTapTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Heart overlay animation refs
  const overlayOpacity = useRef(new Animated.Value(0)).current;
  const overlayScale = useRef(new Animated.Value(0.5)).current;

  // Heart button spring animation ref
  const heartBtnScale = useRef(new Animated.Value(1)).current;

  const performLike = useCallback(async () => {
    // Optimistic update
    const wasLiked = liked;
    setLiked(!wasLiked);
    setLikesCount((n) => wasLiked ? n - 1 : n + 1);

    try {
      const result = await toggleLike(item.type, item.id);
      // Sync with server response
      setLiked(result.data.liked);
      setLikesCount(result.data.likes_count);
    } catch {
      // Revert on error
      setLiked(wasLiked);
      setLikesCount((n) => wasLiked ? n + 1 : n - 1);
    }
  }, [liked, item.type, item.id]);

  function showHeartOverlay() {
    overlayOpacity.setValue(1);
    overlayScale.setValue(0.5);

    Animated.parallel([
      Animated.spring(overlayScale, {
        toValue: 1.0,
        friction: 4,
        tension: 80,
        useNativeDriver: true,
      }),
      Animated.timing(overlayOpacity, {
        toValue: 0,
        duration: 1000,
        useNativeDriver: true,
      }),
    ]).start();
  }

  function animateHeartButton() {
    heartBtnScale.setValue(1);
    Animated.spring(heartBtnScale, {
      toValue: 1.3,
      friction: 3,
      tension: 200,
      useNativeDriver: true,
    }).start(() => {
      Animated.spring(heartBtnScale, {
        toValue: 1.0,
        friction: 3,
        tension: 200,
        useNativeDriver: true,
      }).start();
    });
  }

  /** Navigate to the appropriate detail screen based on feed item type. */
  function navigateToDetail() {
    switch (item.type) {
      case 'listing':
        router.push({ pathname: '/(modals)/exchange-detail', params: { id: String(item.id) } });
        break;
      case 'event':
        router.push({ pathname: '/(modals)/event-detail', params: { id: String(item.id) } });
        break;
      case 'job':
        router.push({ pathname: '/(modals)/job-detail', params: { id: String(item.id) } });
        break;
      case 'volunteer':
        router.push({ pathname: '/(modals)/volunteering-detail', params: { id: String(item.id) } });
        break;
      case 'goal':
        router.push({ pathname: '/(modals)/goals', params: { id: String(item.id) } });
        break;
      default:
        // post, poll, challenge, review — no dedicated detail screen yet
        break;
    }
  }

  function handleDoubleTap() {
    const now = Date.now();
    if (now - lastTapRef.current < 300) {
      // Double-tap detected — cancel pending single-tap navigation
      lastTapRef.current = 0;
      if (singleTapTimerRef.current) {
        clearTimeout(singleTapTimerRef.current);
        singleTapTimerRef.current = null;
      }
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      showHeartOverlay();
      animateHeartButton();
      // Only like if not already liked
      if (!liked) {
        void performLike();
      }
    } else {
      lastTapRef.current = now;
      // Schedule single-tap navigation — cancelled if a second tap comes within 300ms
      singleTapTimerRef.current = setTimeout(() => {
        navigateToDetail();
      }, 300);
    }
  }

  function handleLikePress() {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    animateHeartButton();
    void performLike();
  }

  const handleShare = useCallback(async () => {
    try {
      await Share.share({ message: `${item.title ?? ''}\n${item.content ?? ''}`.trim() });
    } catch {
      // User cancelled or share failed — no action needed
    }
  }, [item.title, item.content]);

  const handleSave = useCallback(async () => {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    // Optimistic update
    const wasBookmarked = bookmarked;
    setBookmarked(!wasBookmarked);
    try {
      const result = await toggleBookmark(item.type, item.id);
      setBookmarked(result.data.bookmarked);
    } catch {
      setBookmarked(wasBookmarked);
    }
  }, [bookmarked, item.type, item.id]);

  const cardLabel = `${item.author_name ?? ''}. ${item.title ?? ''}${item.content ? '. ' + item.content.slice(0, 100) : ''}`;

  return (
    <View
      style={styles.wrapper}
      accessible={true}
      accessibilityLabel={cardLabel}
      accessibilityRole="summary"
    >
      <Card style={styles.card}>
        <TouchableOpacity activeOpacity={1} onPress={handleDoubleTap}>
          {/* Author row */}
          <View style={styles.actor}>
            <Avatar uri={item.author_avatar ?? null} name={item.author_name || null} size={36} />
            <View style={styles.actorInfo}>
              <Text style={styles.actorName} numberOfLines={1}>{item.author_name ?? ''}</Text>
              <Text style={styles.time}>{item.created_at ? formatRelativeTime(item.created_at) : ''}</Text>
            </View>
            {/* Type badge */}
            <View style={styles.typeBadge}>
              <Text style={styles.typeBadgeText}>{t(`feedTypes.${item.type}`, { defaultValue: item.type })}</Text>
            </View>
            {/* More button */}
            <TouchableOpacity
              onPress={() => setActionSheetVisible(true)}
              hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
              accessibilityLabel={t('moreOptions', { defaultValue: 'More options' })}
              accessibilityRole="button"
              style={styles.moreBtn}
            >
              <Ionicons name="ellipsis-horizontal" size={20} color={theme.textMuted} />
            </TouchableOpacity>
          </View>

          {/* Content */}
          {item.title ? <Text style={styles.title}>{item.title}</Text> : null}
          {item.content && (
            <>
              <Text style={styles.body} numberOfLines={3}>{item.content}</Text>
              {item.content.length > 150 && (
                <Text style={styles.readMore}>{t('readMore')}</Text>
              )}
            </>
          )}

          {/* Multi-image carousel */}
          {item.media && item.media.filter((m) => m.media_type === 'image').length > 1 ? (
            <ImageCarousel
              images={item.media
                .filter((m) => m.media_type === 'image' && m.file_url)
                .map((m) => ({ uri: m.file_url, alt: m.alt_text ?? undefined }))}
              height={200}
            />
          ) : item.image_url ? (
            /* Single image */
            <TouchableOpacity
              activeOpacity={0.9}
              onPress={() => router.push({ pathname: '/(modals)/image-viewer', params: { uri: item.image_url ?? '', title: item.title ?? '' } })}
              accessibilityLabel={t('feedTypes.post')}
              accessibilityRole="imagebutton"
            >
              <Image source={{ uri: item.image_url }} style={styles.feedImage} contentFit="cover" />
            </TouchableOpacity>
          ) : null}

          {/* Poll */}
          {item.type === 'poll' && pollData && (
            <PollCard
              pollData={pollData}
              itemId={item.id}
              onVoted={(updated) => setPollData(updated)}
            />
          )}

          {/* Location */}
          {item.location && (
            <View style={styles.locationRow}>
              <Ionicons name="location-outline" size={13} color={theme.textMuted} />
              <Text style={styles.locationText}>{item.location}</Text>
            </View>
          )}

          {/* Heart overlay for double-tap */}
          <Animated.View
            pointerEvents="none"
            style={[
              styles.heartOverlay,
              {
                opacity: overlayOpacity,
                transform: [{ scale: overlayScale }],
              },
            ]}
          >
            <Ionicons name="heart" size={80} color={primary} />
          </Animated.View>
        </TouchableOpacity>

        {/* Actions row */}
        <View style={styles.actions}>
          <TouchableOpacity
            style={styles.actionBtn}
            onPress={handleLikePress}
            activeOpacity={0.7}
            accessibilityLabel={liked ? t('unlikePost') : t('likePost')}
            accessibilityRole="button"
          >
            <Animated.View style={{ transform: [{ scale: heartBtnScale }] }}>
              <Ionicons
                name={liked ? 'heart' : 'heart-outline'}
                size={18}
                color={liked ? primary : theme.textMuted}
              />
            </Animated.View>
            {likesCount > 0 && (
              <Text style={[styles.actionCount, liked && { color: primary }]}>
                {likesCount}
              </Text>
            )}
          </TouchableOpacity>

          {(item.comments_count ?? 0) > 0 && (
            <View style={styles.actionBtn}>
              <Ionicons name="chatbubble-outline" size={17} color={theme.textMuted} />
              <Text style={styles.actionCount}>{item.comments_count}</Text>
            </View>
          )}
        </View>
      </Card>

      {/* Post action sheet — three-dot menu only, no swipe gestures */}
      <ActionSheet
        visible={actionSheetVisible}
        onClose={() => setActionSheetVisible(false)}
        actions={[
          { label: t('share', { defaultValue: 'Share' }), icon: 'share-outline', onPress: () => void handleShare() },
          { label: bookmarked ? t('unsave', { defaultValue: 'Unsave' }) : t('save', { defaultValue: 'Save' }), icon: bookmarked ? 'bookmark' : 'bookmark-outline', onPress: () => void handleSave() },
        ]}
      />
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    wrapper: { marginHorizontal: SPACING.md, marginVertical: SPACING.sm - 2 },
    card: { gap: SPACING.sm },
    actor: { flexDirection: 'row', alignItems: 'center', gap: SPACING.sm + 2 },
    actorInfo: { flex: 1 },
    actorName: { ...TYPOGRAPHY.label, fontWeight: '600', color: theme.text },
    time: { ...TYPOGRAPHY.caption, color: theme.textMuted },
    typeBadge: { backgroundColor: theme.borderSubtle, borderRadius: RADIUS.sm, paddingHorizontal: SPACING.sm, paddingVertical: 3, marginRight: SPACING.xs },
    moreBtn: { padding: SPACING.xs },
    typeBadgeText: { fontSize: 11, fontWeight: '600', color: theme.textSecondary },
    title: { ...TYPOGRAPHY.body, fontWeight: '600', color: theme.text },
    body: { ...TYPOGRAPHY.label, color: theme.textSecondary, lineHeight: 20 },
    readMore: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted, fontWeight: '500' },
    feedImage: { width: '100%', height: 200, borderRadius: RADIUS.md },
    locationRow: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    locationText: { ...TYPOGRAPHY.caption, color: theme.textMuted },
    actions: { flexDirection: 'row', gap: SPACING.md, paddingTop: SPACING.xs, borderTopWidth: 1, borderTopColor: theme.borderSubtle },
    actionBtn: { flexDirection: 'row', alignItems: 'center', gap: 5 },
    actionCount: { ...TYPOGRAPHY.bodySmall, color: theme.textMuted },
    heartOverlay: {
      position: 'absolute',
      top: 0,
      left: 0,
      right: 0,
      bottom: 0,
      justifyContent: 'center',
      alignItems: 'center',
    },
  });
}
