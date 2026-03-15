// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useMemo } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

import { useTranslation } from 'react-i18next';

import { toggleLike, type FeedItem as FeedItemType } from '@/lib/api/feed';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';
import Avatar from '@/components/ui/Avatar';
import Card from '@/components/ui/Card';
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

  return (
    <View style={styles.wrapper}>
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
          <Text style={styles.body} numberOfLines={3}>{item.content}</Text>
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
            accessibilityLabel={liked ? t('feedTypes.unlike') : t('feedTypes.like')}
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
    locationRow: { flexDirection: 'row', alignItems: 'center', gap: 3 },
    locationText: { fontSize: 12, color: theme.textMuted },
    actions: { flexDirection: 'row', gap: 16, paddingTop: 4, borderTopWidth: 1, borderTopColor: theme.borderSubtle },
    actionBtn: { flexDirection: 'row', alignItems: 'center', gap: 5 },
    actionCount: { fontSize: 13, color: theme.textMuted },
  });
}
