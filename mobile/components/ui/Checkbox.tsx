// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View, Text } from 'react-native';
import { Checkbox as HeroCheckbox } from 'heroui-native';
import * as Haptics from '@/lib/haptics';

interface CheckboxProps {
  checked: boolean;
  onPress: () => void;
  label?: string;
  disabled?: boolean;
  testID?: string;
  accessibilityLabel?: string;
}

export default function Checkbox({
  checked,
  onPress,
  label,
  disabled = false,
  testID,
  accessibilityLabel,
}: CheckboxProps) {
  const handleChange = (isSelected: boolean) => {
    if (disabled) {
      return;
    }

    if (isSelected !== checked) {
      Haptics.selectionAsync().catch(() => {});
      onPress();
    }
  };

  const handleLabelPress = () => {
    if (!disabled) {
      onPress();
    }
  };

  return (
    <View className="flex-row items-center gap-2.5">
      <HeroCheckbox
        testID={testID}
        accessibilityLabel={accessibilityLabel ?? label}
        isSelected={checked}
        onSelectedChange={handleChange}
        isDisabled={disabled}
      />
      {label ? (
        <Text
          className="text-base text-foreground font-normal flex-1"
          onPress={handleLabelPress}
        >
          {label}
        </Text>
      ) : null}
    </View>
  );
}
