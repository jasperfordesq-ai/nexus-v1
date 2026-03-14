// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useMemo } from 'react';
import { View, type ViewProps, StyleSheet } from 'react-native';
import { useTheme, type Theme } from '@/lib/hooks/useTheme';

interface CardProps extends ViewProps {
  children: React.ReactNode;
}

export default function Card({ children, style, ...rest }: CardProps) {
  const theme = useTheme();
  const styles = useMemo(() => makeStyles(theme), [theme]);

  return (
    <View style={[styles.card, style]} {...rest}>
      {children}
    </View>
  );
}

function makeStyles(theme: Theme) {
  return StyleSheet.create({
    card: {
      backgroundColor: theme.surface,
      borderRadius: 14,
      padding: 16,
      shadowColor: '#000',
      shadowOffset: { width: 0, height: 1 },
      shadowOpacity: 0.06,
      shadowRadius: 4,
      elevation: 2,
    },
  });
}
