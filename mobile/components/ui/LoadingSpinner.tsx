// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { View, ActivityIndicator, StyleSheet } from 'react-native';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

interface LoadingSpinnerProps {
  size?: 'small' | 'large';
  fullScreen?: boolean;
}

export default function LoadingSpinner({ size = 'large', fullScreen = false }: LoadingSpinnerProps) {
  const primary = usePrimaryColor();

  return (
    <View style={[styles.container, fullScreen && styles.fullScreen]}>
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
    backgroundColor: 'rgba(255,255,255,0.8)',
    zIndex: 10,
  },
});
