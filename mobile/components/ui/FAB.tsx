// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useRef, useCallback } from 'react';
import {
  Animated,
  StyleSheet,
  TouchableWithoutFeedback,
  View,
} from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import * as Haptics from 'expo-haptics';

import { usePrimaryColor } from '@/lib/hooks/useTenant';

type IoniconName = React.ComponentProps<typeof Ionicons>['name'];

interface FABProps {
  icon?: IoniconName;
  onPress: () => void;
  color?: string;
  position?: 'bottom-right' | 'bottom-center';
}

export default function FAB({
  icon = 'add',
  onPress,
  color,
  position = 'bottom-right',
}: FABProps) {
  const primary = usePrimaryColor();
  const bgColor = color ?? primary;
  const scale = useRef(new Animated.Value(1)).current;

  const handlePressIn = useCallback(() => {
    Animated.spring(scale, {
      toValue: 0.9,
      useNativeDriver: true,
    }).start();
  }, [scale]);

  const handlePressOut = useCallback(() => {
    Animated.spring(scale, {
      toValue: 1,
      friction: 3,
      tension: 40,
      useNativeDriver: true,
    }).start();
  }, [scale]);

  const handlePress = useCallback(() => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    onPress();
  }, [onPress]);

  const positionStyle =
    position === 'bottom-center'
      ? styles.bottomCenter
      : styles.bottomRight;

  return (
    <View style={[styles.wrapper, positionStyle]} pointerEvents="box-none">
      <TouchableWithoutFeedback
        onPressIn={handlePressIn}
        onPressOut={handlePressOut}
        onPress={handlePress}
        accessibilityRole="button"
        accessibilityLabel="Action button"
      >
        <Animated.View
          style={[
            styles.fab,
            { backgroundColor: bgColor, transform: [{ scale }] },
          ]}
        >
          <Ionicons name={icon} size={28} color="#fff" />
        </Animated.View>
      </TouchableWithoutFeedback>
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
