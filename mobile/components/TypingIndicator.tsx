// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef } from 'react';
import { Animated, StyleSheet } from 'react-native';

import { useTheme } from '@/lib/hooks/useTheme';

interface TypingIndicatorProps {
  visible: boolean;
}

const DOT_SIZE = 8;
const DOT_GAP = 4;
const BOUNCE_HEIGHT = -6;
const STAGGER_DELAY = 150;

export default function TypingIndicator({ visible }: TypingIndicatorProps) {
  const theme = useTheme();
  const containerOpacity = useRef(new Animated.Value(0)).current;
  const dot1 = useRef(new Animated.Value(0)).current;
  const dot2 = useRef(new Animated.Value(0)).current;
  const dot3 = useRef(new Animated.Value(0)).current;
  const loopRef = useRef<Animated.CompositeAnimation | null>(null);

  useEffect(() => {
    if (visible) {
      // Fade in
      Animated.timing(containerOpacity, {
        toValue: 1,
        duration: 200,
        useNativeDriver: true,
      }).start();

      // Bouncing dots loop
      const makeBounce = (dot: Animated.Value) =>
        Animated.sequence([
          Animated.timing(dot, {
            toValue: BOUNCE_HEIGHT,
            duration: 300,
            useNativeDriver: true,
          }),
          Animated.timing(dot, {
            toValue: 0,
            duration: 300,
            useNativeDriver: true,
          }),
        ]);

      const loop = Animated.loop(
        Animated.stagger(STAGGER_DELAY, [
          makeBounce(dot1),
          makeBounce(dot2),
          makeBounce(dot3),
        ]),
      );
      loopRef.current = loop;
      loop.start();
    } else {
      // Fade out
      Animated.timing(containerOpacity, {
        toValue: 0,
        duration: 200,
        useNativeDriver: true,
      }).start();

      // Stop loop
      if (loopRef.current) {
        loopRef.current.stop();
        loopRef.current = null;
      }
      dot1.setValue(0);
      dot2.setValue(0);
      dot3.setValue(0);
    }

    return () => {
      if (loopRef.current) {
        loopRef.current.stop();
        loopRef.current = null;
      }
    };
  }, [visible, containerOpacity, dot1, dot2, dot3]);

  return (
    <Animated.View
      style={[
        styles.container,
        { backgroundColor: theme.surface, opacity: containerOpacity },
      ]}
      pointerEvents="none"
    >
      {[dot1, dot2, dot3].map((dot, i) => (
        <Animated.View
          key={i}
          style={[
            styles.dot,
            {
              backgroundColor: theme.textMuted,
              marginLeft: i > 0 ? DOT_GAP : 0,
              transform: [{ translateY: dot }],
            },
          ]}
        />
      ))}
    </Animated.View>
  );
}

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 8,
    marginLeft: 12,
    marginBottom: 4,
  },
  dot: {
    width: DOT_SIZE,
    height: DOT_SIZE,
    borderRadius: DOT_SIZE / 2,
  },
});
