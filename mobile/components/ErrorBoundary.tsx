// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { Text, TouchableOpacity, View } from 'react-native';
import * as Sentry from '@sentry/react-native';

interface Props {
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

/**
 * Fallback UI — must NOT use any context hooks (useTheme, useTenant, etc.)
 * because the ErrorBoundary sits OUTSIDE all providers in the component tree.
 * Uses hardcoded colors to guarantee it always renders.
 */
function ErrorFallback({ onReset }: { onReset: () => void }) {
  return (
    <View
      style={{
        flex: 1,
        alignItems: 'center',
        justifyContent: 'center',
        padding: 24,
        backgroundColor: '#fff',
      }}
    >
      <Text
        style={{
          fontSize: 16,
          fontWeight: '600',
          color: '#1a1a1a',
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
          backgroundColor: '#006FEE',
          borderRadius: 8,
        }}
        onPress={onReset}
        accessibilityRole="button"
        accessibilityLabel="Retry"
      >
        <Text style={{ fontSize: 14, fontWeight: '600', color: '#fff' }}>Retry</Text>
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
    if (__DEV__) {
      console.error('ErrorBoundary caught:', error, info.componentStack);
    } else {
      Sentry.captureException(error, {
        extra: { componentStack: info.componentStack },
      });
    }
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
