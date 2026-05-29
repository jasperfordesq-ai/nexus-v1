// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { type StyleProp, type ViewStyle } from 'react-native';
import { Button as HeroButton, Card as HeroCard } from 'heroui-native';
import * as Haptics from '@/lib/haptics';

type CardVariant = 'elevated' | 'outlined' | 'flat';

// Surface variants accepted by HeroUI Card.Root (extends Surface)
const VARIANT_MAP: Record<CardVariant, 'default' | 'secondary' | 'transparent'> = {
  elevated: 'default',
  outlined: 'secondary',
  flat: 'transparent',
};

interface CardProps {
  children: React.ReactNode;
  variant?: CardVariant;
  pressable?: boolean;
  onPress?: () => void;
  style?: StyleProp<ViewStyle>;
  className?: string;
}

export default function Card({
  children,
  variant = 'elevated',
  pressable = false,
  onPress,
  style,
  className,
}: CardProps) {
  const heroVariant = VARIANT_MAP[variant];

  const handlePress = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light).catch(() => {});
    onPress?.();
  };

  if (pressable) {
    return (
      <HeroButton variant="ghost" feedbackVariant="scale" className="p-0" onPress={handlePress}>
        <HeroCard variant={heroVariant} style={style} className={className}>
          <HeroCard.Body>{children}</HeroCard.Body>
        </HeroCard>
      </HeroButton>
    );
  }

  return (
    <HeroCard variant={heroVariant} style={style} className={className}>
      <HeroCard.Body>{children}</HeroCard.Body>
    </HeroCard>
  );
}
