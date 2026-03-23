// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Text, TouchableOpacity, View } from 'react-native';
import * as Sentry from '@sentry/react-native';
import { useTranslation } from 'react-i18next';

import { useTheme } from '@/lib/hooks/useTheme';
import { usePrimaryColor } from '@/lib/hooks/useTenant';

interface Props {
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

/** Functional fallback UI that can use hooks for theme-aware colors. */
function ErrorFallback({ onReset }: { onReset: () => void }) {
  const { t } = useTranslation('common');
  const theme = useTheme();
  const primary = usePrimaryColor();

  return (
    <View
      style={{
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        padding: 24,
        backgroundColor: theme.bg,
      }}
    >
      <Text
        style={{
          fontSize: 16,
          fontWeight: '600',
          color: theme.text,
          marginBottom: 16,
          textAlign: 'center',
        }}
      >
        {t('errors.generic')}
      </Text>
      <TouchableOpacity
        style={{
          paddingVertical: 10,
          paddingHorizontal: 24,
          backgroundColor: primary,
          borderRadius: 8,
        }}
        onPress={onReset}
        accessibilityRole="button"
        accessibilityLabel={t('buttons.retry')}
      >
        <Text style={{ fontSize: 14, fontWeight: '600', color: '#fff' /* contrast on primary */ }}>{t('buttons.retry')}</Text>
      </TouchableOpacity>
    </View>
  );
}

/**
 * Class-based error boundary — React hooks (including useTranslation) cannot
 * be used in class components. The ErrorFallback functional component above
 * handles i18n. The class itself uses no user-visible strings.
 */
export default class ErrorBoundary extends React.Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: React.ErrorInfo): void {
    // Log to an error reporting service in the future
    console.error('[ErrorBoundary] Caught error:', error, info.componentStack);
    Sentry.captureException(error, { extra: { componentStack: info.componentStack } });
  }

  handleReset = (): void => {
    this.setState({ hasError: false, error: null });
  };

  render(): React.ReactNode {
    if (this.state.hasError) {
      if (this.props.fallback) {
        return this.props.fallback;
      }

      return <ErrorFallback onReset={this.handleReset} />;
    }

    return this.props.children;
  }
}
