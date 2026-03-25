// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useRef, useCallback } from 'react';
import {
  Animated,
  Text,
  ActivityIndicator,
  StyleSheet,
  type TouchableOpacityProps,
} from 'react-native';
import * as Haptics from 'expo-haptics';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

type ButtonVariant = 'solid' | 'outline' | 'ghost';
type ButtonSize = 'sm' | 'md' | 'lg';

const SIZE_CONFIG: Record<ButtonSize, { height: number; fontSize: number }> = {
  sm: { height: 36, fontSize: 13 },
  md: { height: 44, fontSize: 15 },
  lg: { height: 52, fontSize: 17 },
};

interface ButtonProps extends TouchableOpacityProps {
  children: React.ReactNode;
  variant?: ButtonVariant;
  size?: ButtonSize;
  isLoading?: boolean;
  color?: string;
  fullWidth?: boolean;
}

export default function Button({
  children,
  variant = 'solid',
  size = 'md',
  isLoading = false,
  color,
  fullWidth = false,
  disabled,
  style,
  onPress,
  ...rest
}: ButtonProps) {
  const primary = usePrimaryColor();
  const resolvedColor = color ?? primary;
  const scaleAnim = useRef(new Animated.Value(1)).current;
  const sizeConfig = SIZE_CONFIG[size];

  const handlePressIn = useCallback(() => {
    Animated.spring(scaleAnim, {
      toValue: 0.97,
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

  const handlePress = useCallback(
    (e: any) => {
      Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
      onPress?.(e);
    },
    [onPress],
  );

  const containerStyle = [
    styles.base,
    { minHeight: sizeConfig.height },
    variant === 'solid' && { backgroundColor: resolvedColor },
    variant === 'outline' && { borderWidth: 1.5, borderColor: resolvedColor },
    variant === 'ghost' && styles.ghost,
    fullWidth && styles.fullWidth,
    (disabled || isLoading) && styles.disabled,
    style,
  ];

  const textStyle = [
    styles.text,
    { fontSize: sizeConfig.fontSize },
    variant === 'solid' && styles.textSolid,
    (variant === 'outline' || variant === 'ghost') && { color: resolvedColor },
  ];

  return (
    <Animated.View style={{ transform: [{ scale: scaleAnim }] }}>
      <Animated.View>
        <AnimatedTouchable
          style={containerStyle}
          disabled={disabled || isLoading}
          activeOpacity={0.8}
          accessibilityRole="button"
          onPressIn={handlePressIn}
          onPressOut={handlePressOut}
          onPress={handlePress}
          {...rest}
        >
          {isLoading ? (
            <ActivityIndicator color={variant === 'solid' ? '#fff' : resolvedColor} />
          ) : typeof children === 'string' ? (
            <Text style={textStyle}>{children}</Text>
          ) : (
            children
          )}
        </AnimatedTouchable>
      </Animated.View>
    </Animated.View>
  );
}

const AnimatedTouchable = Animated.createAnimatedComponent(
  require('react-native').TouchableOpacity,
);

const styles = StyleSheet.create({
  base: {
    borderRadius: 10,
    paddingVertical: 13,
    paddingHorizontal: 20,
    alignItems: 'center',
    justifyContent: 'center',
  },
  ghost: {},
  disabled: { opacity: 0.5 },
  text: { fontWeight: '600' },
  textSolid: { color: '#fff' },
  fullWidth: { width: '100%' },
});
