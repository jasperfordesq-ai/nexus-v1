// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo, useCallback } from 'react';
import { View, Text, Image, TouchableOpacity, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { toggleLike, type FeedItem as FeedItemType, type PollData } from '@/lib/api/feed';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import Card from '@/components/ui/Card';
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
  const [likesCount, setLikesCount] = useState(item.likes_count);
  const [isTruncated, setIsTruncated] = useState(false);
  const [pollData, setPollData] = useState<PollData | null | undefined>(item.poll_data);

  const onTextLayout = useCallback((e: { nativeEvent: { lines: unknown[] } }) => {
    // numberOfLines={3} means if there are more than 3 lines the text is truncated
    setIsTruncated(e.nativeEvent.lines.length >= 3);
  }, []);

  async function handleLike() {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
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
  }

  const cardLabel = `${item.author_name}. ${item.title}${item.content ? '. ' + item.content.slice(0, 100) : ''}`;

  return (
    <View
      style={styles.wrapper}
      accessible={true}
      accessibilityLabel={cardLabel}
      accessibilityRole="summary"
    >
      <Card style={styles.card}>
        {/* Author row */}
        <View style={styles.actor}>
          <Avatar uri={item.author_avatar} name={item.author_name} size={36} />
          <View style={styles.actorInfo}>
            <Text style={styles.actorName}>{item.author_name}</Text>
            <Text style={styles.time}>{formatRelativeTime(item.created_at)}</Text>
          </View>
          {/* Type badge */}
          <View style={styles.typeBadge}>
            <Text style={styles.typeBadgeText}>{t(`feedTypes.${item.type}`, { defaultValue: item.type })}</Text>
          </View>
        </View>

        {/* Content */}
        <Text style={styles.title}>{item.title}</Text>
        {item.content && (
          <>
            <Text style={styles.body} numberOfLines={3} onTextLayout={onTextLayout}>{item.content}</Text>
            {(isTruncated || item.content.length > 100) && (
              <Text style={styles.readMore}>{t('readMore')}</Text>
            )}
          </>
        )}

        {/* Image */}
        {item.image_url && (
          <TouchableOpacity
            activeOpacity={0.9}
            onPress={() => router.push({ pathname: '/(modals)/image-viewer', params: { uri: item.image_url!, title: item.title } })}
            accessibilityLabel={t('feedTypes.post')}
            accessibilityRole="imagebutton"
          >
            <Image source={{ uri: item.image_url }} style={styles.feedImage} resizeMode="cover" />
          </TouchableOpacity>
        )}

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

        {/* Actions row */}
        <View style={styles.actions}>
          <TouchableOpacity
            style={styles.actionBtn}
            onPress={handleLike}
            activeOpacity={0.7}
            accessibilityLabel={liked ? t('unlikePost') : t('likePost')}
            accessibilityRole="button"
          >
            <Ionicons
              name={liked ? 'heart' : 'heart-outline'}
              size={18}
              color={liked ? primary : theme.textMuted}
            />
            {likesCount > 0 && (
              <Text style={[styles.actionCount, liked && { color: primary }]}>
                {likesCount}
              </Text>
            )}
          </TouchableOpacity>

          {item.comments_count > 0 && (
            <View style={styles.actionBtn}>
              <Ionicons name="chatbubble-outline" size={17} color={theme.textMuted} />
              <Text style={styles.actionCount}>{item.comments_count}</Text>
            </View>
          )}
        </View>
      </Card>
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    wrapper: { marginHorizontal: 16, marginVertical: 6 },
    card: { gap: 8 },
    actor: { flexDirection: 'row', alignItems: 'center', gap: 10 },
    actorInfo: { flex: 1 },
    actorName: { fontSize: 14, fontWeight: '600', color: theme.text },
    time: { fontSize: 12, color: theme.textMuted },
    typeBadge: { backgroundColor: theme.borderSubtle, borderRadius: 6, paddingHorizontal: 8, paddingVertical: 3 },
    typeBadgeText: { fontSize: 11, fontWeight: '600', color: theme.textSecondary },
    title: { fontSize: 15, fontWeight: '600', color: theme.text },
    body: { fontSize: 14, color: theme.textSecondary, lineHeight: 20 },
    readMore: { fontSize: 13, color: theme.textMuted, fontWeight: '500' },
    feedImage: { width: '100%', height: 200, borderRadius: 10 },
    locationRow: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    locationText: { fontSize: 12, color: theme.textMuted },
    actions: { flexDirection: 'row', gap: 16, paddingTop: 4, borderTopWidth: 1, borderTopColor: theme.borderSubtle },
    actionBtn: { flexDirection: 'row', alignItems: 'center', gap: 5 },
    actionCount: { fontSize: 13, color: theme.textMuted },
  });
}
