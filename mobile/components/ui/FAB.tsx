// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useCallback } from 'react';
import { Pressable, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from '@/lib/haptics';
import Animated, { useSharedValue, useAnimatedStyle, withSpring } from 'react-native-reanimated';
import { useTranslation } from 'react-i18next';

import { usePrimaryColor } from '@/lib/hooks/useTenant';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface FABProps {
  icon?: IoniconName;
  onPress: () => void;
  color?: string;
  position?: 'bottom-right' | 'bottom-center';
  accessibilityLabel?: string;
}

const AnimatedPressable = Animated.createAnimatedComponent(Pressable);

export default function FAB({ icon = 'add', onPress, color, position = 'bottom-right', accessibilityLabel }: FABProps) {
  const { t } = useTranslation('common');
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

  const wrapperClass = position === 'bottom-center'
    ? 'absolute z-10 bottom-4 left-0 right-0 items-center'
    : 'absolute z-10 bottom-4 right-4';

  return (
    <View className={wrapperClass} pointerEvents="box-none">
      <AnimatedPressable
        onPressIn={handlePressIn}
        onPressOut={handlePressOut}
        onPress={handlePress}
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel ?? t('aria.actionButton')}
        className="w-14 h-14 rounded-full items-center justify-center"
        style={[
          { backgroundColor: bgColor, elevation: 6, shadowColor: '#000', shadowOffset: { width: 0, height: 3 }, shadowOpacity: 0.27, shadowRadius: 4.65 },
          animatedStyle,
        ]}
      >
        <Ionicons name={icon} size={28} color="#fff" />
      </AnimatedPressable>
    </View>
  );
}
