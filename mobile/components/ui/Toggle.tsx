// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useRef } from 'react';
import {
  Animated,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import * as Haptics from 'expo-haptics';

import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';

interface ToggleProps {
  value: boolean;
  onValueChange: (value: boolean) => void;
  disabled?: boolean;
  label?: string;
  size?: 'sm' | 'md';
}

const SIZES = {
  md: { trackWidth: 50, trackHeight: 28, thumbSize: 24 },
  sm: { trackWidth: 40, trackHeight: 22, thumbSize: 18 },
} as const;

export default function Toggle({
  value,
  onValueChange,
  disabled = false,
  label,
  size = 'md',
}: ToggleProps) {
  const theme = useTheme();
  const primary = usePrimaryColor();
  const dims = SIZES[size];

  const thumbAnim = useRef(new Animated.Value(value ? 1 : 0)).current;
  const trackColorAnim = useRef(new Animated.Value(value ? 1 : 0)).current;

  useEffect(() => {
    Animated.timing(thumbAnim, {
      toValue: value ? 1 : 0,
      duration: 200,
      useNativeDriver: false,
    }).start();
    Animated.timing(trackColorAnim, {
      toValue: value ? 1 : 0,
      duration: 200,
      useNativeDriver: false,
    }).start();
  }, [value, thumbAnim, trackColorAnim]);

  const thumbPadding = (dims.trackHeight - dims.thumbSize) / 2;
  const thumbTranslateX = thumbAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [thumbPadding, dims.trackWidth - dims.thumbSize - thumbPadding],
  });

  const trackBackgroundColor = trackColorAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [theme.border, primary],
  });

  function handlePress() {
    if (disabled) return;
    void Haptics.selectionAsync();
    onValueChange(!value);
  }

  const track = (
    <Pressable
      onPress={handlePress}
      disabled={disabled}
      accessibilityRole="switch"
      accessibilityState={{ checked: value, disabled }}
    >
      <Animated.View
        style={[
          styles.track,
          {
            width: dims.trackWidth,
            height: dims.trackHeight,
            borderRadius: dims.trackHeight / 2,
            backgroundColor: trackBackgroundColor,
          },
        ]}
      >
        <Animated.View
          style={[
            styles.thumb,
            {
              width: dims.thumbSize,
              height: dims.thumbSize,
              borderRadius: dims.thumbSize / 2,
              transform: [{ translateX: thumbTranslateX }],
            },
          ]}
        />
      </Animated.View>
    </Pressable>
  );

  if (!label) {
    return (
      <View style={[disabled && styles.disabled]}>
        {track}
      </View>
    );
  }

  return (
    <View style={[styles.row, disabled && styles.disabled]}>
      <Text style={[styles.label, { color: theme.text }]}>{label}</Text>
      {track}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  label: {
    fontSize: 15,
    fontWeight: '500',
    flex: 1,
    marginRight: 12,
  },
  track: {
    justifyContent: 'center',
  },
  thumb: {
    backgroundColor: '#fff',
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 1 },
    shadowOpacity: 0.18,
    shadowRadius: 2,
    elevation: 3,
  },
  disabled: {
    opacity: 0.5,
  },
});
