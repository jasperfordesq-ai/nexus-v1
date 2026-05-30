// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View, Text } from 'react-native';
import { Switch } from 'heroui-native';
import * as Haptics from '@/lib/haptics';

interface ToggleProps {
  value: boolean;
  onValueChange: (value: boolean) => void;
  disabled?: boolean;
  label?: string;
  accessibilityLabel?: string;
  size?: 'sm' | 'md';
}

export default function Toggle({ value, onValueChange, disabled = false, label, accessibilityLabel, size = 'md' }: ToggleProps) {
  const handleChange = (isSelected: boolean) => {
    Haptics.selectionAsync().catch(() => {});
    onValueChange(isSelected);
  };

  const switchEl = (
    <Switch
      isSelected={value}
      onSelectedChange={handleChange}
      isDisabled={disabled}
      accessibilityLabel={accessibilityLabel ?? label}
      className={size === 'sm' ? 'scale-75' : undefined}
    />
  );

  if (!label) {
    return <View className={disabled ? 'opacity-50' : undefined}>{switchEl}</View>;
  }

  return (
    <View className={`flex-row items-center justify-between${disabled ? ' opacity-50' : ''}`}>
      <Text className="text-base font-medium text-foreground flex-1 mr-3">{label}</Text>
      {switchEl}
    </View>
  );
}
