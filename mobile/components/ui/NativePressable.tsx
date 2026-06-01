// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { useCallback } from 'react';
import type { AccessibilityRole, GestureResponderEvent, StyleProp, ViewStyle } from 'react-native';
import { PressableFeedback } from 'heroui-native';

import * as Haptics from '@/lib/haptics';

type NativePressableFeedback = 'scale' | 'highlight' | 'ripple' | 'none';

interface NativePressableProps {
  children: React.ReactNode;
  onPress?: (event: GestureResponderEvent) => void;
  onLongPress?: (event: GestureResponderEvent) => void;
  disabled?: boolean;
  feedback?: NativePressableFeedback;
  haptics?: boolean;
  accessibilityLabel?: string;
  accessibilityRole?: AccessibilityRole;
  testID?: string;
  className?: string;
  contentClassName?: string;
  style?: StyleProp<ViewStyle>;
}

export default function NativePressable({
  children,
  onPress,
  onLongPress,
  disabled = false,
  feedback = 'scale',
  haptics = true,
  accessibilityLabel,
  accessibilityRole = 'button',
  testID,
  className,
  contentClassName,
  style,
}: NativePressableProps) {
  const handlePress = useCallback((event: GestureResponderEvent) => {
    if (disabled) return;
    if (haptics) {
      void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    }
    onPress?.(event);
  }, [disabled, haptics, onPress]);

  const useScale = feedback !== 'none';

  return (
    <PressableFeedback
      accessibilityLabel={accessibilityLabel}
      accessibilityRole={accessibilityRole}
      animation={useScale ? false : 'disable-all'}
      className={`overflow-hidden ${className ?? ''}`}
      isDisabled={disabled}
      onLongPress={onLongPress}
      onPress={handlePress}
      style={style}
      testID={testID}
    >
      {useScale ? (
        <PressableFeedback.Scale className={contentClassName}>
          {children}
        </PressableFeedback.Scale>
      ) : (
        children
      )}
      {feedback === 'highlight' ? <PressableFeedback.Highlight /> : null}
      {feedback === 'ripple' ? <PressableFeedback.Ripple /> : null}
    </PressableFeedback>
  );
}
