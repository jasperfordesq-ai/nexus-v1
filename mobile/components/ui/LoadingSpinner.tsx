// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View, ActivityIndicator, StyleSheet } from 'react-native';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';

interface LoadingSpinnerProps {
  size?: 'small' | 'large';
  fullScreen?: boolean;
}

export default function LoadingSpinner({ size = 'large', fullScreen = false }: LoadingSpinnerProps) {
  const primary = usePrimaryColor();
  const theme = useTheme();

  return (
    <View style={[styles.container, fullScreen && [styles.fullScreen, { backgroundColor: theme.bg + 'CC' }]]}>
      <ActivityIndicator size={size} color={primary} />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  fullScreen: {
    position: 'absolute',
    inset: 0,
    zIndex: 10,
  },
});
