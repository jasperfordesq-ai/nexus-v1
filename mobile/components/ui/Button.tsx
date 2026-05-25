// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { ActivityIndicator, View, type ViewStyle, type StyleProp } from 'react-native';
import { Button as HeroButton } from 'heroui-native';
import * as Haptics from '@/lib/haptics';

// Legacy variant names kept for backwards compatibility with 32 importing screens.
// 'solid' is mapped to HeroUI's 'primary'.
type LegacyVariant = 'solid' | 'outline' | 'ghost' | 'secondary' | 'danger';
type ButtonSize = 'sm' | 'md' | 'lg';

interface ButtonProps {
  children: React.ReactNode;
  variant?: LegacyVariant;
  size?: ButtonSize;
  isLoading?: boolean;
  /** Custom tenant color — applied via inline style override. */
  color?: string;
  fullWidth?: boolean;
  disabled?: boolean;
  onPress?: () => void;
  style?: StyleProp<ViewStyle>;
  className?: string;
  accessibilityLabel?: string;
  testID?: string;
}

const VARIANT_MAP: Record<LegacyVariant, 'primary' | 'outline' | 'ghost' | 'secondary' | 'danger'> = {
  solid: 'primary',
  outline: 'outline',
  ghost: 'ghost',
  secondary: 'secondary',
  danger: 'danger',
};

export default function Button({
  children,
  variant = 'solid',
  size = 'md',
  isLoading = false,
  color,
  fullWidth = false,
  disabled = false,
  onPress,
  style,
  className,
  accessibilityLabel,
  testID,
}: ButtonProps) {
  const handlePress = () => {
    void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    onPress?.();
  };

  const wrapperStyle: StyleProp<ViewStyle> = [
    fullWidth && { width: '100%' },
    color && variant === 'solid' && { backgroundColor: color },
    style,
  ];

  return (
    <HeroButton
      variant={VARIANT_MAP[variant]}
      size={size}
      isDisabled={disabled || isLoading}
      onPress={handlePress}
      style={wrapperStyle}
      className={className}
      accessibilityLabel={accessibilityLabel}
      testID={testID}
    >
      {isLoading ? (
        <View className="flex-row items-center gap-2">
          <ActivityIndicator color={variant === 'solid' ? '#fff' : color ?? '#818cf8'} />
        </View>
      ) : typeof children === 'string' ? (
        <HeroButton.Label>{children}</HeroButton.Label>
      ) : (
        children
      )}
    </HeroButton>
  );
}
