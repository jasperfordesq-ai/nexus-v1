// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Appearance, Pressable, Text, View } from 'react-native';
import { router } from 'expo-router';
import i18n from 'i18next';

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
 * On error, shows a translated recovery message with a translated back action.
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
      const title = i18n.t('errors.boundaryTitle', { ns: 'common' });
      const goBack = i18n.t('buttons.back', { ns: 'common' });
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
            {title}
          </Text>
          <Pressable
            style={{
              paddingVertical: 10,
              paddingHorizontal: 24,
              backgroundColor: colors.buttonBg,
              borderRadius: 8,
            }}
            onPress={() => router.back()}
            accessibilityRole="button"
            accessibilityLabel={goBack}
          >
            <Text style={{ fontSize: 14, fontWeight: '600', color: colors.buttonText }}>{goBack}</Text>
          </Pressable>
        </View>
      );
    }

    return this.props.children;
  }
}
