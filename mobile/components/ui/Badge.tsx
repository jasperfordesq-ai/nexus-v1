// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

type BadgeSize = 'sm' | 'md';
type BadgeVariant = 'solid' | 'outline';

interface BadgeProps {
  label: string;
  color?: string;
  size?: BadgeSize;
  variant?: BadgeVariant;
}

const SIZE_CONFIG: Record<BadgeSize, { height: number; fontSize: number; paddingHorizontal: number }> = {
  sm: { height: 20, fontSize: 10, paddingHorizontal: 6 },
  md: { height: 26, fontSize: 12, paddingHorizontal: 10 },
};

export default function Badge({
  label,
  color,
  size = 'md',
  variant = 'solid',
}: BadgeProps) {
  const primary = usePrimaryColor();
  const resolvedColor = color ?? primary;
  const config = SIZE_CONFIG[size];

  return (
    <View
      style={[
        styles.base,
        {
          height: config.height,
          paddingHorizontal: config.paddingHorizontal,
        },
        variant === 'solid' && { backgroundColor: resolvedColor },
        variant === 'outline' && {
          borderWidth: 1,
          borderColor: resolvedColor,
          backgroundColor: 'transparent',
        },
      ]}
    >
      <Text
        style={[
          styles.text,
          { fontSize: config.fontSize },
          variant === 'solid' && { color: '#FFFFFF' },
          variant === 'outline' && { color: resolvedColor },
        ]}
        numberOfLines={1}
      >
        {label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  base: {
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    alignSelf: 'flex-start',
  },
  text: {
    fontWeight: '600',
  },
});
