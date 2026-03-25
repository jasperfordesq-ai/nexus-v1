// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { View } from 'react-native';
import { useTheme } from '@/lib/hooks/useTheme';

interface DividerProps {
  spacing?: number;
  color?: string;
}

export default function Divider({ spacing = 16, color }: DividerProps) {
  const theme = useTheme();

  return (
    <View
      style={{
        height: 1,
        backgroundColor: color ?? theme.border,
        marginVertical: spacing,
      }}
    />
  );
}
