// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View } from 'react-native';
import { Spinner } from 'heroui-native';
import type { SpinnerSize } from 'heroui-native';

interface LoadingSpinnerProps {
  size?: 'small' | 'large';
  fullScreen?: boolean;
}

const SIZE_MAP: Record<'small' | 'large', SpinnerSize> = {
  small: 'sm',
  large: 'lg',
};

export default function LoadingSpinner({ size = 'large', fullScreen = false }: LoadingSpinnerProps) {
  return (
    <View
      className={
        fullScreen
          ? 'absolute inset-0 z-10 items-center justify-center bg-background/80'
          : 'flex-1 items-center justify-center p-10'
      }
    >
      <Spinner size={SIZE_MAP[size]} />
    </View>
  );
}
