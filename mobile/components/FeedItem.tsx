// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useRef, useState } from 'react';
import { Animated, Platform, Pressable, Share, Text, View } from 'react-native';
import { Image } from 'expo-image';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { Button as HeroButton, Card as HeroCard, Chip, Separator, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';

import { useTranslation } from 'react-i18next';

import { getFeedAuthor, toggleBookmark, toggleLike, type FeedItem as FeedItemType, type PollData } from '@/lib/api/feed';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { resolveImageUrl } from '@/lib/utils/resolveImageUrl';
import Avatar from '@/components/ui/Avatar';
import ImageCarousel from '@/components/ui/ImageCarousel';
import ActionSheet from '@/components/ui/ActionSheet';
import PollCard from '@/components/PollCard';
import { formatRelativeTime } from '@/lib/utils/formatRelativeTime';

interface FeedItemProps {
  item: FeedItemType;
  disableDetailNavigation?: boolean;
}

type ChipColor = 'accent' | 'default' | 'success' | 'warning' | 'danger';

const TYPE_CONFIG: Record<
  FeedItemType['type'],
  {
    chipColor: ChipColor;
    icon: keyof typeof Ionicons.glyphMap;
    strip: readonly [string, string, string];
  }
> = {
  post: {
    chipColor: 'default',
    icon: 'chatbox-ellipses-outline',
    strip: ['#D4D4D8', '#E4E4E7', '#D4D4D8'],
  },
  listing: {
    chipColor: 'accent',
    icon: 'swap-horizontal-outline',
    strip: ['#6366F1', '#3B82F6', '#6366F1'],
  },
  event: {
    chipColor: 'success',
    icon: 'calendar-outline',
    strip: ['#10B981', '#22C55E', '#10B981'],
  },
  poll: {
    chipColor: 'warning',
    icon: 'stats-chart-outline',
    strip: ['#F59E0B', '#F97316', '#F59E0B'],
  },
  goal: {
    chipColor: 'accent',
    icon: 'flag-outline',
    strip: ['#A855F7', '#EC4899', '#A855F7'],
  },
  review: {
    chipColor: 'warning',
    icon: 'star-outline',
    strip: ['#F59E0B', '#EAB308', '#F59E0B'],
  },
  job: {
    chipColor: 'accent',
    icon: 'briefcase-outline',
    strip: ['#3B82F6', '#06B6D4', '#3B82F6'],
  },
  challenge: {
    chipColor: 'accent',
    icon: 'trophy-outline',
    strip: ['#8B5CF6', '#A855F7', '#8B5CF6'],
  },
  volunteer: {
    chipColor: 'success',
    icon: 'heart-outline',
    strip: ['#22C55E', '#10B981', '#22C55E'],
  },
  blog: {
    chipColor: 'accent',
    icon: 'book-outline',
    strip: ['#0EA5E9', '#3B82F6', '#0EA5E9'],
  },
  discussion: {
    chipColor: 'accent',
    icon: 'people-outline',
    strip: ['#D946EF', '#A855F7', '#D946EF'],
  },
  resource: {
    chipColor: 'accent',
    icon: 'library-outline',
    strip: ['#14B8A6', '#06B6D4', '#14B8A6'],
  },
  badge_earned: {
    chipColor: 'warning',
    icon: 'ribbon-outline',
    strip: ['#EAB308', '#F59E0B', '#EAB308'],
  },
  level_up: {
    chipColor: 'success',
    icon: 'flash-outline',
    strip: ['#10B981', '#14B8A6', '#10B981'],
  },
};

const COMMENTABLE_TYPES = new Set<FeedItemType['type']>([
  'post',
  'listing',
  'event',
  'poll',
  'goal',
  'job',
  'challenge',
  'volunteer',
  'review',
  'blog',
  'discussion',
  'resource',
]);

const BOOKMARKABLE_TYPES = new Set<FeedItemType['type']>([
  'post',
  'listing',
  'event',
  'job',
  'blog',
  'discussion',
]);

function getTypeColor(type: FeedItemType['type']): ChipColor {
  return TYPE_CONFIG[type].chipColor;
}

function getTypeIcon(type: FeedItemType['type']): keyof typeof Ionicons.glyphMap {
  return TYPE_CONFIG[type].icon;
}

function getDetailTarget(item: FeedItemType) {
  switch (item.type) {
    case 'listing':
      return { pathname: '/(modals)/exchange-detail', params: { id: String(item.id) }, labelKey: 'detail.listing' };
    case 'event':
      return { pathname: '/(modals)/event-detail', params: { id: String(item.id) }, labelKey: 'detail.event' };
    case 'job':
      return { pathname: '/(modals)/job-detail', params: { id: String(item.id) }, labelKey: 'detail.job' };
    case 'volunteer':
      return { pathname: '/(modals)/volunteering-detail', params: { id: String(item.id) }, labelKey: 'detail.volunteer' };
    case 'goal':
      return { pathname: '/(modals)/goals', params: { id: String(item.id) }, labelKey: 'detail.goal' };
    case 'review':
      return item.receiver ? { pathname: '/(modals)/member-profile', params: { id: String(item.receiver.id) }, labelKey: 'detail.profile' } : null;
    case 'blog':
      return item.slug ? { pathname: '/(modals)/blog-post', params: { id: item.slug }, labelKey: 'detail.blog' } : null;
    case 'post':
    case 'poll':
    case 'challenge':
    case 'discussion':
    case 'resource':
    case 'badge_earned':
    case 'level_up':
      return { pathname: '/(modals)/feed-item-detail', params: { id: String(item.id), type: item.type }, labelKey: 'detail.post' };
    default:
      return null;
  }
}

export default function FeedItem({ item, disableDetailNavigation = false }: FeedItemProps) {
  const { t } = useTranslation('home');
  const primary = usePrimaryColor();
  const theme = useTheme();

  const [liked, setLiked] = useState(item.is_liked ?? false);
  const [likesCount, setLikesCount] = useState(item.likes_count ?? 0);
  const [pollData, setPollData] = useState<PollData | null | undefined>(item.poll_data);
  const [bookmarked, setBookmarked] = useState(item.is_bookmarked ?? false);
  const [actionSheetVisible, setActionSheetVisible] = useState(false);

  const lastTapRef = useRef<number>(0);
  const singleTapTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const overlayOpacity = useRef(new Animated.Value(0)).current;
  const overlayScale = useRef(new Animated.Value(0.5)).current;
  const heartBtnScale = useRef(new Animated.Value(1)).current;

  const performLike = useCallback(async () => {
    const wasLiked = liked;
    setLiked(!wasLiked);
    setLikesCount((n) => (wasLiked ? n - 1 : n + 1));

    try {
      const result = await toggleLike(item.type, item.id);
      setLiked(result.data.liked);
      setLikesCount(result.data.likes_count);
    } catch {
      setLiked(wasLiked);
      setLikesCount((n) => (wasLiked ? n + 1 : n - 1));
    }
  }, [liked, item.id, item.type]);

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

  function navigateToDetail() {
    if (disableDetailNavigation) return;
    const detailTarget = getDetailTarget(item);
    if (detailTarget) {
      router.push({ pathname: detailTarget.pathname as never, params: detailTarget.params });
    }
  }

  function handleDoubleTap() {
    const now = Date.now();
    if (now - lastTapRef.current < 300) {
      lastTapRef.current = 0;
      if (singleTapTimerRef.current) {
        clearTimeout(singleTapTimerRef.current);
        singleTapTimerRef.current = null;
      }
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
      showHeartOverlay();
      animateHeartButton();
      if (!liked) {
        void performLike();
      }
    } else {
      lastTapRef.current = now;
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
      // User cancelled or share failed.
    }
  }, [item.content, item.title]);

  const handleSave = useCallback(async () => {
    void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    const wasBookmarked = bookmarked;
    setBookmarked(!wasBookmarked);
    try {
      const result = await toggleBookmark(item.type, item.id);
      setBookmarked(result.data.bookmarked);
    } catch {
      setBookmarked(wasBookmarked);
    }
  }, [bookmarked, item.id, item.type]);

  const typeConfig = TYPE_CONFIG[item.type];
  const imageItems = item.media
    ?.filter((media) => media.media_type === 'image' && media.file_url)
    .sort((a, b) => a.display_order - b.display_order)
    .map((media) => ({ uri: resolveImageUrl(media.file_url) ?? media.file_url, alt: media.alt_text ?? undefined }));
  const videoItems = item.media?.filter((media) => media.media_type === 'video' && media.file_url) ?? [];
  const imageUrl = resolveImageUrl(item.image_url);
  const author = getFeedAuthor(item, t('stories.member'));
  const authorName = author.name;
  const detailTarget = disableDetailNavigation ? null : getDetailTarget(item);
  const isCommentable = COMMENTABLE_TYPES.has(item.type);
  const canBookmark = BOOKMARKABLE_TYPES.has(item.type);
  const reactionTotal = item.reactions?.total ?? likesCount;
  const linkPreview = item.link_previews?.[0];
  const linkPreviewImageUrl = resolveImageUrl(linkPreview?.image_url);

  const cardLabel = `${authorName}. ${item.title ?? ''}${item.content ? '. ' + item.content.slice(0, 100) : ''}`;

  return (
    <View className="mx-4 my-2" accessible accessibilityLabel={cardLabel} accessibilityRole="summary">
      <HeroCard variant="default" className="overflow-hidden">
        <View
          className="h-1 w-full"
          style={[
            { backgroundColor: typeConfig.strip[1] },
            Platform.OS === 'web'
              ? ({
                  backgroundImage: `linear-gradient(90deg, ${typeConfig.strip[0]}, ${typeConfig.strip[1]}, ${typeConfig.strip[2]})`,
                } as object)
              : null,
          ]}
        />
        <Pressable onPress={handleDoubleTap}>
          <HeroCard.Header className="flex-row items-center gap-3 px-4 pb-2 pt-4">
            <Avatar uri={author.avatar} name={authorName} size={40} />
            <View className="min-w-0 flex-1">
              <Text className="text-sm font-semibold" style={{ color: theme.text }} numberOfLines={1}>
                {authorName}
              </Text>
              <Text className="text-xs" style={{ color: theme.textSecondary }}>
                {item.created_at ? formatRelativeTime(item.created_at) : ''}
              </Text>
            </View>
            {item.type !== 'post' ? (
              <Chip size="sm" variant="soft" color={getTypeColor(item.type)}>
                <Ionicons name={getTypeIcon(item.type)} size={12} color={typeConfig.strip[1]} />
                <Chip.Label>{t(`feedTypes.${item.type}`, { defaultValue: item.type })}</Chip.Label>
              </Chip>
            ) : null}
            {item.is_official ? (
              <Chip size="sm" variant="soft" color="accent">
                <Chip.Label>{t('official')}</Chip.Label>
              </Chip>
            ) : null}
            <HeroButton
              isIconOnly
              size="sm"
              variant="ghost"
              onPress={() => setActionSheetVisible(true)}
              accessibilityLabel={t('moreOptions')}
            >
              <Ionicons name="ellipsis-horizontal" size={20} color={theme.textMuted} />
            </HeroButton>
          </HeroCard.Header>

          <HeroCard.Body className="gap-3 px-4 pb-4 pt-1">
            {item.type === 'badge_earned' || item.type === 'level_up' ? (
              <Surface variant="secondary" className="items-center gap-2 rounded-panel-inner p-5">
                <View className="h-16 w-16 items-center justify-center rounded-full" style={{ backgroundColor: primary }}>
                  <Ionicons name={item.type === 'badge_earned' ? 'ribbon' : 'flash'} size={34} color="#fff" />
                </View>
                <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
                  {item.type === 'badge_earned' ? t('milestone.badgeUnlocked') : t('milestone.levelReached')}
                </Text>
                <Text className="text-center text-base font-bold" style={{ color: theme.text }}>
                  {item.type === 'badge_earned'
                    ? t('milestone.badgeMessage', { name: authorName, badge: item.badge_name || item.title || '' })
                    : t('milestone.levelMessage', { name: authorName, level: item.new_level || item.title || '' })}
                </Text>
              </Surface>
            ) : null}

            {item.title ? (
              <Text className="text-base font-bold leading-6" style={{ color: theme.text }}>
                {item.title}
              </Text>
            ) : null}
            {item.content ? (
              <View className="gap-1">
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={item.content_truncated ? 4 : 5}>
                  {item.content}
                </Text>
                {item.content.length > 150 || item.content_truncated ? (
                  <HeroButton
                    size="sm"
                    variant="ghost"
                    isDisabled={!detailTarget}
                    className="self-start px-0"
                    onPress={navigateToDetail}
                    accessibilityLabel={t('readMore')}
                  >
                    <HeroButton.Label style={{ color: detailTarget ? primary : theme.textMuted }}>
                      {t('readMore')}
                    </HeroButton.Label>
                  </HeroButton>
                ) : null}
              </View>
            ) : null}

            {imageItems && imageItems.length > 0 ? (
              <View className="overflow-hidden rounded-panel-inner">
                <ImageCarousel images={imageItems} height={210} />
              </View>
            ) : imageUrl ? (
              <Pressable
                onPress={() =>
                  router.push({ pathname: '/(modals)/image-viewer', params: { uri: imageUrl, title: item.title ?? '' } })
                }
                accessibilityLabel={t('feedTypes.post')}
                accessibilityRole="imagebutton"
              >
                <Image source={{ uri: imageUrl }} style={{ width: '100%', height: 210, borderRadius: 14 }} contentFit="cover" />
              </Pressable>
            ) : null}

            {videoItems.length > 0 ? (
              <Surface variant="secondary" className="flex-row items-center gap-3 rounded-panel-inner p-4">
                <Ionicons name="play-circle-outline" size={28} color={primary} />
                <View className="min-w-0 flex-1">
                  <Text className="font-semibold" style={{ color: theme.text }}>{t('media.video')}</Text>
                  <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                    {videoItems[0]?.alt_text || t('media.videoDescription')}
                  </Text>
                </View>
              </Surface>
            ) : null}

            {linkPreview ? (
              <Surface variant="secondary" className="overflow-hidden rounded-panel-inner">
                {linkPreviewImageUrl ? (
                  <Image source={{ uri: linkPreviewImageUrl }} style={{ width: '100%', height: 120 }} contentFit="cover" />
                ) : null}
                <View className="gap-1 p-3">
                  <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>{linkPreview.domain || linkPreview.site_name}</Text>
                  <Text className="font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                    {linkPreview.title || linkPreview.url}
                  </Text>
                  {linkPreview.description ? (
                    <Text className="text-sm" style={{ color: theme.textSecondary }} numberOfLines={2}>
                      {linkPreview.description}
                    </Text>
                  ) : null}
                </View>
              </Surface>
            ) : null}

            {item.type === 'poll' && pollData ? (
              <PollCard pollData={pollData} itemId={item.id} onVoted={(updated) => setPollData(updated)} />
            ) : null}

            {item.location ? (
              <View className="flex-row items-center gap-1">
                <Ionicons name="location-outline" size={14} color={theme.textMuted} />
                <Text className="text-xs" style={{ color: theme.textSecondary }} numberOfLines={1}>
                  {item.location}
                </Text>
              </View>
            ) : null}

            {detailTarget ? (
              <HeroButton size="sm" variant="secondary" onPress={navigateToDetail}>
                <Ionicons name={getTypeIcon(item.type)} size={16} color={primary} />
                <HeroButton.Label>{t(detailTarget.labelKey)}</HeroButton.Label>
                <Ionicons name="arrow-forward" size={15} color={primary} />
              </HeroButton>
            ) : null}
          </HeroCard.Body>

          <Animated.View
            pointerEvents="none"
            style={{
              position: 'absolute',
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              justifyContent: 'center',
              alignItems: 'center',
              opacity: overlayOpacity,
              transform: [{ scale: overlayScale }],
            }}
          >
            <Ionicons name="heart" size={80} color={primary} />
          </Animated.View>
        </Pressable>

        <View className="mx-4">
          <Separator />
        </View>

        {reactionTotal > 0 || (item.comments_count ?? 0) > 0 || (item.views_count ?? 0) > 0 || (item.share_count ?? 0) > 0 ? (
          <View className="flex-row items-center justify-between px-4 pt-3">
            <View className="flex-row flex-wrap items-center gap-3">
              {reactionTotal > 0 ? (
                <Text className="text-xs" style={{ color: theme.textSecondary }}>
                  {t('stats.reactions', { count: reactionTotal })}
                </Text>
              ) : null}
              {(item.views_count ?? 0) > 0 ? (
                <Text className="text-xs" style={{ color: theme.textSecondary }}>
                  {t('stats.views', { count: item.views_count })}
                </Text>
              ) : null}
              {(item.share_count ?? 0) > 0 ? (
                <Text className="text-xs" style={{ color: theme.textSecondary }}>
                  {t('stats.shares', { count: item.share_count })}
                </Text>
              ) : null}
            </View>
            {(item.comments_count ?? 0) > 0 ? (
              <Text className="text-xs" style={{ color: theme.textSecondary }}>
                {t('stats.comments', { count: item.comments_count })}
              </Text>
            ) : null}
          </View>
        ) : null}

        <HeroCard.Footer className="flex-row items-center gap-2 px-4 py-3">
          <HeroButton
            size="sm"
            variant={liked ? 'secondary' : 'ghost'}
            onPress={handleLikePress}
            accessibilityLabel={liked ? t('unlikePost') : t('likePost')}
          >
            <Animated.View style={{ transform: [{ scale: heartBtnScale }] }}>
              <Ionicons name={liked ? 'heart' : 'heart-outline'} size={18} color={liked ? primary : theme.textMuted} />
            </Animated.View>
            {likesCount > 0 ? (
              <HeroButton.Label style={{ color: liked ? primary : theme.textMuted }}>{likesCount}</HeroButton.Label>
            ) : null}
          </HeroButton>

          {isCommentable ? (
            <HeroButton size="sm" variant="ghost" onPress={navigateToDetail}>
              <Ionicons name="chatbubble-outline" size={17} color={theme.textMuted} />
              <HeroButton.Label style={{ color: theme.textMuted }}>{t('comment')}</HeroButton.Label>
            </HeroButton>
          ) : null}

          <HeroButton size="sm" variant="ghost" onPress={() => void handleShare()}>
            <Ionicons name="share-social-outline" size={17} color={theme.textMuted} />
            <HeroButton.Label style={{ color: theme.textMuted }}>{t('share')}</HeroButton.Label>
          </HeroButton>

          {canBookmark ? (
            <HeroButton size="sm" variant={bookmarked ? 'secondary' : 'ghost'} onPress={() => void handleSave()}>
              <Ionicons name={bookmarked ? 'bookmark' : 'bookmark-outline'} size={17} color={bookmarked ? primary : theme.textMuted} />
            </HeroButton>
          ) : null}
        </HeroCard.Footer>
      </HeroCard>

      <ActionSheet
        visible={actionSheetVisible}
        onClose={() => setActionSheetVisible(false)}
        actions={[
          { label: t('share'), icon: 'share-outline', onPress: () => void handleShare() },
          ...(canBookmark ? [{ label: bookmarked ? t('unsave') : t('save'), icon: bookmarked ? 'bookmark' : 'bookmark-outline', onPress: () => void handleSave() }] : []),
          ...(detailTarget ? [{ label: t(detailTarget.labelKey), icon: 'arrow-forward-outline', onPress: navigateToDetail }] : []),
        ]}
      />
    </View>
  );
}
