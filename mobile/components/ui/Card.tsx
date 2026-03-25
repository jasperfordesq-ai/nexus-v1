// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo, useRef, useCallback } from 'react';
import {
  View,
  TouchableOpacity,
  Animated,
  type ViewProps,
  StyleSheet,
} from 'react-native';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

type CardVariant = 'elevated' | 'outlined' | 'flat';

interface CardProps extends ViewProps {
  children: React.ReactNode;
  variant?: CardVariant;
  pressable?: boolean;
  onPress?: () => void;
}

export default function Card({
  children,
  variant = 'elevated',
  pressable = false,
  onPress,
  style,
  ...rest
}: CardProps) {
  const theme = useTheme();
  const dynamicStyles = useMemo(() => makeStyles(theme), [theme]);
  const scaleAnim = useRef(new Animated.Value(1)).current;

  const handlePressIn = useCallback(() => {
    Animated.spring(scaleAnim, {
      toValue: 0.98,
      useNativeDriver: true,
      speed: 50,
      bounciness: 4,
    }).start();
  }, [scaleAnim]);

  const handlePressOut = useCallback(() => {
    Animated.spring(scaleAnim, {
      toValue: 1,
      useNativeDriver: true,
      speed: 50,
      bounciness: 4,
    }).start();
  }, [scaleAnim]);

  const variantStyle =
    variant === 'elevated'
      ? dynamicStyles.elevated
      : variant === 'outlined'
        ? dynamicStyles.outlined
        : dynamicStyles.flat;

  const cardStyle = [dynamicStyles.base, variantStyle, style];

  if (pressable) {
    return (
      <Animated.View style={{ transform: [{ scale: scaleAnim }] }}>
        <TouchableOpacity
          activeOpacity={0.8}
          onPress={onPress}
          onPressIn={handlePressIn}
          onPressOut={handlePressOut}
        >
          <View style={cardStyle} {...rest}>
            {children}
          </View>
        </TouchableOpacity>
      </Animated.View>
    );
  }

  return (
    <View style={cardStyle} {...rest}>
      {children}
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    base: {
      backgroundColor: theme.surface,
      borderRadius: 14,
      padding: 16,
    },
    elevated: {
      shadowColor: '#000',
      shadowOffset: { width: 0, height: 1 },
      shadowOpacity: 0.06,
      shadowRadius: 4,
      elevation: 2,
    },
    outlined: {
      borderWidth: 1,
      borderColor: theme.border,
    },
    flat: {},
  });
}
