// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useCallback } from 'react';
import { Pressable, View, StyleSheet } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';
import Animated, { useSharedValue, useAnimatedStyle, withSpring } from 'react-native-reanimated';

import { usePrimaryColor } from '@/lib/hooks/useTenant';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface FABProps {
  icon?: IoniconName;
  onPress: () => void;
  color?: string;
  position?: 'bottom-right' | 'bottom-center';
}

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

export default function FAB({ icon = 'add', onPress, color, position = 'bottom-right' }: FABProps) {
  const primary = usePrimaryColor();
  const bgColor = color ?? primary;
  const scale = useSharedValue(1);

  const animatedStyle = useAnimatedStyle(() => ({
    transform: [{ scale: scale.value }],
  }));

  const handlePressIn = useCallback(() => {
    scale.value = withSpring(0.9, { stiffness: 300, damping: 20 });
  }, [scale]);

  const handlePressOut = useCallback(() => {
    scale.value = withSpring(1, { stiffness: 200, damping: 15 });
  }, [scale]);

  const handlePress = useCallback(() => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium).catch(() => {});
    onPress();
  }, [onPress]);

  const positionStyle = position === 'bottom-center' ? styles.bottomCenter : styles.bottomRight;

  return (
    <View style={[styles.wrapper, positionStyle]} pointerEvents="box-none">
      <AnimatedPressable
        onPressIn={handlePressIn}
        onPressOut={handlePressOut}
        onPress={handlePress}
        accessibilityRole="button"
        accessibilityLabel="Action button"
        style={[styles.fab, { backgroundColor: bgColor }, animatedStyle]}
      >
        <Ionicons name={icon} size={28} color="#fff" />
      </AnimatedPressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    position: 'absolute',
    zIndex: 10,
  },
  bottomRight: {
    right: 16,
    bottom: 16,
  },
  bottomCenter: {
    bottom: 16,
    left: 0,
    right: 0,
    alignItems: 'center',
  },
  fab: {
    width: 56,
    height: 56,
    borderRadius: 28,
    justifyContent: 'center',
    alignItems: 'center',
    elevation: 6,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 3 },
    shadowOpacity: 0.27,
    shadowRadius: 4.65,
  },
});
