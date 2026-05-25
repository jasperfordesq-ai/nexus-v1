// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Separator } from 'heroui-native';

interface DividerProps {
  spacing?: number;
  color?: string;
}

export default function Divider({ spacing = 16, color }: DividerProps) {
  return (
    <Separator
      style={[
        { marginVertical: spacing },
        color ? { backgroundColor: color } : undefined,
      ]}
    />
  );
}
