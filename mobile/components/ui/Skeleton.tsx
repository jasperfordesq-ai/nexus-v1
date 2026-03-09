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
  borderRadius = 6,
  style,
}: SkeletonBoxProps): React.JSX.Element {
  const opacity = useShimmerAnimation();

  return (
    <Animated.View
      style={[
        styles.box,
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

  return (
    <View style={styles.feedCard}>
      {/* Header row: avatar + name/time lines */}
      <View style={styles.row}>
        <Animated.View style={[styles.avatar36, { opacity }]} />
        <View style={styles.headerText}>
          <Animated.View style={[styles.box, { width: 120, height: 12, borderRadius: 6, opacity }]} />
          <Animated.View style={[styles.box, { width: 72, height: 10, borderRadius: 6, opacity }]} />
        </View>
      </View>

      {/* Title */}
      <Animated.View style={[styles.box, { width: '100%', height: 16, borderRadius: 6, opacity }]} />

      {/* Body lines */}
      <Animated.View style={[styles.box, { width: '100%', height: 14, borderRadius: 6, opacity }]} />
      <Animated.View style={[styles.box, { width: '100%', height: 14, borderRadius: 6, opacity }]} />
      <Animated.View style={[styles.box, { width: '60%', height: 10, borderRadius: 6, opacity }]} />
    </View>
  );
}

// ---------------------------------------------------------------------------
// ConversationSkeleton
// ---------------------------------------------------------------------------

export function ConversationSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();

  return (
    <View style={styles.conversationRow}>
      <Animated.View style={[styles.avatar48, { opacity }]} />
      <View style={styles.conversationText}>
        <Animated.View style={[styles.box, { width: 120, height: 13, borderRadius: 6, opacity }]} />
        <Animated.View style={[styles.box, { width: '60%', height: 11, borderRadius: 6, opacity }]} />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// EventCardSkeleton
// ---------------------------------------------------------------------------

export function EventCardSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();

  return (
    <View style={styles.eventRow}>
      {/* Date badge */}
      <Animated.View style={[styles.dateBadge, { opacity }]} />

      {/* Content */}
      <View style={styles.eventContent}>
        {/* Title */}
        <Animated.View style={[styles.box, { width: '80%', height: 16, borderRadius: 6, opacity }]} />
        {/* Meta row 1 */}
        <Animated.View style={[styles.box, { width: '60%', height: 12, borderRadius: 6, opacity }]} />
        {/* Meta row 2 */}
        <Animated.View style={[styles.box, { width: '45%', height: 12, borderRadius: 6, opacity }]} />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// ProfileSkeleton
// ---------------------------------------------------------------------------

export function ProfileSkeleton(): React.JSX.Element {
  const opacity = useShimmerAnimation();
  return (
    <View style={styles.profileContainer}>
      <View style={styles.profileCenter}>
        <Animated.View style={[styles.profileAvatar, { opacity }]} />
        <Animated.View style={[styles.box, { width: 120, height: 16, borderRadius: 8, opacity, marginTop: 12 }]} />
        <Animated.View style={[styles.box, { width: 80, height: 12, borderRadius: 6, opacity, marginTop: 6 }]} />
      </View>
      <Animated.View style={[styles.box, { width: '100%', height: 90, borderRadius: 14, opacity, marginTop: 24 }]} />
      <View style={styles.profileActions}>
        <Animated.View style={[styles.box, { width: '100%', height: 46, borderRadius: 10, opacity }]} />
        <Animated.View style={[styles.box, { width: '100%', height: 46, borderRadius: 10, opacity }]} />
        <Animated.View style={[styles.box, { width: '100%', height: 46, borderRadius: 10, opacity }]} />
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// Styles
// ---------------------------------------------------------------------------

const styles = StyleSheet.create({
  box: {
    backgroundColor: '#D1D5DB',
  },

  // FeedItemSkeleton
  feedCard: {
    backgroundColor: '#fff',
    borderRadius: 14,
    padding: 14,
    gap: 8,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  avatar36: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: '#D1D5DB',
  },
  headerText: {
    flex: 1,
    gap: 6,
  },

  // ConversationSkeleton
  conversationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    gap: 12,
    backgroundColor: '#fff',
  },
  avatar48: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#D1D5DB',
  },
  conversationText: {
    flex: 1,
    gap: 8,
  },

  // EventCardSkeleton
  eventRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 12,
    padding: 14,
    backgroundColor: '#fff',
    borderRadius: 14,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.06,
    shadowRadius: 4,
    elevation: 2,
  },
  dateBadge: {
    width: 48,
    height: 52,
    borderRadius: 8,
    backgroundColor: '#D1D5DB',
  },
  eventContent: {
    flex: 1,
    gap: 8,
  },

  // ProfileSkeleton
  profileContainer: { paddingHorizontal: 24, paddingTop: 32 },
  profileCenter: { alignItems: 'center' },
  profileAvatar: { width: 88, height: 88, borderRadius: 44, backgroundColor: '#D1D5DB' },
  profileActions: { gap: 12, marginTop: 24 },
});
