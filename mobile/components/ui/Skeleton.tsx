// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useEffect, useRef } from 'react';
import {
  Animated,
  StyleSheet,
  View,
  type StyleProp,
  type ViewStyle,
} from 'react-native';

import { useTheme } from '@/lib/hooks/useTheme';
import { SPACING, RADIUS } from '@/lib/styles/spacing';

// ---------------------------------------------------------------------------
// Shared animation hook
// ---------------------------------------------------------------------------

function useShimmerAnimation(): Animated.Value {
  const opacity = useRef(new Animated.Value(0.4)).current;

  useEffect(() => {
    const animation = Animated.loop(
      Animated.sequence([
        Animated.timing(opacity, {
          toValue: 1.0,
          duration: 750,
          useNativeDriver: true,
        }),
        Animated.timing(opacity, {
          toValue: 0.4,
          duration: 750,
          useNativeDriver: true,
        }),
      ]),
    );

    animation.start();

    return () => {
      animation.stop();
    };
  }, [opacity]);

  return opacity;
}

// ---------------------------------------------------------------------------
// SkeletonBox — base primitive
// ---------------------------------------------------------------------------

interface SkeletonBoxProps {
  width?: number | `${number}%`;
  height?: number;
  borderRadius?: number;
  style?: StyleProp<ViewStyle>;
}

export function SkeletonBox({
  width = '100%',
  height = 14,
  borderRadius = RADIUS.sm,
  style,
}: SkeletonBoxProps): React.JSX.Element {
  const opacity = useShimmerAnimation();
  const theme = useTheme();

  return (
    <Animated.View
      style={[
        { backgroundColor: theme.border },
        { width: width as ViewStyle['width'], height, borderRadius, opacity },
        style,
      ]}
    />
  );
}

// ---------------------------------------------------------------------------
// FeedItemSkeleton
// ---------------------------------------------------------------------------

export function FeedItemSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();
  const theme = useTheme();
  const boxBg = theme.border;

  return (
    <View style={[styles.feedCard, { backgroundColor: theme.surface }]}>
      {/* Header row: avatar + name/time lines */}
      <View style={styles.row}>
        <Animated.View style={[styles.avatar36, { backgroundColor: boxBg, opacity }]} />
        <View style={styles.headerText}>
          <Animated.View style={[{ backgroundColor: boxBg }, { width: 120, height: 12, borderRadius: 6, opacity }]} />
          <Animated.View style={[{ backgroundColor: boxBg }, { width: 72, height: 10, borderRadius: 6, opacity }]} />
        </View>
      </View>

      {/* Title */}
      <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 16, borderRadius: 6, opacity }]} />

      {/* Body lines */}
      <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 14, borderRadius: 6, opacity }]} />
      <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 14, borderRadius: 6, opacity }]} />
      <Animated.View style={[{ backgroundColor: boxBg }, { width: '60%', height: 10, borderRadius: 6, opacity }]} />
    </View>
  );
}

// ---------------------------------------------------------------------------
// ConversationSkeleton
// ---------------------------------------------------------------------------

export function ConversationSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();
  const theme = useTheme();
  const boxBg = theme.border;

  return (
    <View style={[styles.conversationRow, { backgroundColor: theme.surface }]}>
      <Animated.View style={[styles.avatar48, { backgroundColor: boxBg, opacity }]} />
      <View style={styles.conversationText}>
        <Animated.View style={[{ backgroundColor: boxBg }, { width: 120, height: 13, borderRadius: 6, opacity }]} />
        <Animated.View style={[{ backgroundColor: boxBg }, { width: '60%', height: 11, borderRadius: 6, opacity }]} />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// EventCardSkeleton
// ---------------------------------------------------------------------------

export function EventCardSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();
  const theme = useTheme();
  const boxBg = theme.border;

  return (
    <View style={[styles.eventRow, { backgroundColor: theme.surface }]}>
      {/* Date badge */}
      <Animated.View style={[styles.dateBadge, { backgroundColor: boxBg, opacity }]} />

      {/* Content */}
      <View style={styles.eventContent}>
        {/* Title */}
        <Animated.View style={[{ backgroundColor: boxBg }, { width: '80%', height: 16, borderRadius: 6, opacity }]} />
        {/* Meta row 1 */}
        <Animated.View style={[{ backgroundColor: boxBg }, { width: '60%', height: 12, borderRadius: 6, opacity }]} />
        {/* Meta row 2 */}
        <Animated.View style={[{ backgroundColor: boxBg }, { width: '45%', height: 12, borderRadius: 6, opacity }]} />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// ExchangeCardSkeleton
// ---------------------------------------------------------------------------

export function ExchangeCardSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();
  const theme = useTheme();
  const boxBg = theme.border;

  return (
    <View style={[styles.exchangeCard, { backgroundColor: theme.surface }]}>
      {/* Title */}
      <Animated.View style={[{ backgroundColor: boxBg }, { width: '75%', height: 16, borderRadius: 6, opacity }]} />
      {/* Description line */}
      <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 12, borderRadius: 6, opacity }]} />
      {/* Meta row: type + credits */}
      <View style={styles.row}>
        <Animated.View style={[{ backgroundColor: boxBg }, { width: 60, height: 12, borderRadius: 6, opacity }]} />
        <Animated.View style={[{ backgroundColor: boxBg }, { width: 40, height: 12, borderRadius: 6, opacity }]} />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// ProfileSkeleton
// ---------------------------------------------------------------------------

export function ProfileSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();
  const theme = useTheme();
  const boxBg = theme.border;

  return (
    <View style={styles.profileContainer}>
      <View style={styles.profileCenter}>
        <Animated.View style={[styles.profileAvatar, { backgroundColor: boxBg, opacity }]} />
        <Animated.View style={[{ backgroundColor: boxBg }, { width: 120, height: 16, borderRadius: 8, opacity, marginTop: 12 }]} />
        <Animated.View style={[{ backgroundColor: boxBg }, { width: 80, height: 12, borderRadius: 6, opacity, marginTop: 6 }]} />
      </View>
      <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 90, borderRadius: 14, opacity, marginTop: 24 }]} />
      <View style={styles.profileActions}>
        <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 46, borderRadius: 10, opacity }]} />
        <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 46, borderRadius: 10, opacity }]} />
        <Animated.View style={[{ backgroundColor: boxBg }, { width: '100%', height: 46, borderRadius: 10, opacity }]} />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

const styles = StyleSheet.create({
  // FeedItemSkeleton
  feedCard: {
    borderRadius: RADIUS.lg,
    padding: RADIUS.lg,
    marginHorizontal: SPACING.md,
    marginVertical: SPACING.sm - 2,
    gap: SPACING.sm,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: SPACING.sm + 2,
  },
  avatar36: {
    width: 36,
    height: 36,
    borderRadius: 18,
  },
  headerText: {
    flex: 1,
    gap: SPACING.sm - 2,
  },

  // ConversationSkeleton
  conversationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: SPACING.md,
    paddingVertical: SPACING.sm + 4,
    gap: SPACING.sm + 4,
  },
  avatar48: {
    width: 48,
    height: 48,
    borderRadius: 24,
  },
  conversationText: {
    flex: 1,
    gap: SPACING.sm,
  },

  // EventCardSkeleton
  eventRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: SPACING.sm + 4,
    padding: RADIUS.lg,
    borderRadius: RADIUS.lg,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  dateBadge: {
    width: 48,
    height: 52,
    borderRadius: SPACING.sm,
  },
  eventContent: {
    flex: 1,
    gap: SPACING.sm,
  },

  // ExchangeCardSkeleton
  exchangeCard: {
    borderRadius: RADIUS.lg,
    padding: RADIUS.lg,
    marginHorizontal: SPACING.md,
    marginVertical: SPACING.sm - 2,
    gap: SPACING.sm + 2,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },

  // ProfileSkeleton
  profileContainer: { paddingHorizontal: SPACING.lg, paddingTop: SPACING.xl },
  profileCenter: { alignItems: 'center' },
  profileAvatar: { width: 88, height: 88, borderRadius: 44 },
  profileActions: { gap: SPACING.sm + 4, marginTop: SPACING.lg },
});
