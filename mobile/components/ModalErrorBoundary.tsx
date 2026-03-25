// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Appearance, Text, TouchableOpacity, View } from 'react-native';
import { router } from 'expo-router';

interface Props {
  children: React.ReactNode;
}

interface State {
  hasError: boolean;
}

/**
 * Returns dark-mode-aware colors using Appearance API.
 * Uses Appearance.getColorScheme() instead of the useColorScheme hook
 * because class components cannot use hooks.
 */
function getErrorColors() {
  const isDark = Appearance.getColorScheme() === 'dark';
  return {
    bg: isDark ? '#0F0F0F' : '#FFFFFF',
    text: isDark ? '#F2F2F7' : '#1a1a1a',
    buttonBg: '#006FEE',
    buttonText: '#FFFFFF',
  };
}

/**
 * Lightweight error boundary for modal screens.
 * Uses Appearance API for dark mode support since class components cannot use hooks.
 * On error, shows a "Something went wrong" message with a "Go Back" button.
 */
export default class ModalErrorBoundary extends React.Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(): State {
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo): void {
    console.error('ModalErrorBoundary caught:', error, info.componentStack);
  }

  render(): React.ReactNode {
    if (this.state.hasError) {
      const colors = getErrorColors();
      return (
        <View
          style={{
            flex: 1,
            alignItems: 'center',
            justifyContent: 'center',
            padding: 24,
            backgroundColor: colors.bg,
          }}
        >
          <Text
            style={{
              fontSize: 16,
              fontWeight: '600',
              color: colors.text,
              marginBottom: 16,
              textAlign: 'center',
            }}
          >
            Something went wrong
          </Text>
          <TouchableOpacity
            style={{
              paddingVertical: 10,
              paddingHorizontal: 24,
              backgroundColor: colors.buttonBg,
              borderRadius: 8,
            }}
            onPress={() => router.back()}
            accessibilityRole="button"
            accessibilityLabel="Go Back"
          >
            <Text style={{ fontSize: 14, fontWeight: '600', color: colors.buttonText }}>Go Back</Text>
          </TouchableOpacity>
        </View>
      );
    }

    return this.props.children;
  }
}
