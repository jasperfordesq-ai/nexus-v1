// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Feature Error Boundary
 * Catches errors in feature sections without crashing the entire app
 */

import { Component, type ReactNode, type ErrorInfo } from 'react';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import i18n from 'i18next';
import { logError } from '@/lib/logger';

interface FeatureErrorBoundaryProps {
  children: ReactNode;
  featureName: string;
  fallback?: ReactNode;
  onRetry?: () => void;
}

interface FeatureErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
}

export class FeatureErrorBoundary extends Component<
  FeatureErrorBoundaryProps,
  FeatureErrorBoundaryState
> {
  constructor(props: FeatureErrorBoundaryProps) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
    };
  }

  static getDerivedStateFromError(error: Error): Partial<FeatureErrorBoundaryState> {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    // Dev console only — logError now forwards to Sentry in production, so
    // calling it here in prod too would double-report alongside the explicit
    // captureSentryException below.
    if (import.meta.env.DEV) {
      logError(`Feature error in ${this.props.featureName}`, { error, errorInfo });
    }

    // Production crash visibility (consent-gated — captureSentryException no-ops
    // unless the user granted analytics consent). Mirrors ErrorBoundary.tsx so a
    // render crash in an authenticated feature route (Wallet, Listings, Events,
    // Groups, Messages — the ~169 <FeatureErrorBoundary> wraps) surfaces in
    // Sentry instead of vanishing. The feature tag makes crashes filterable per
    // module.
    void import('@/lib/sentry').then(({ captureSentryException }) => {
      captureSentryException(error, {
        feature: this.props.featureName,
        componentStack: errorInfo.componentStack ?? undefined,
        source: 'FeatureErrorBoundary',
      });
    });
  }

  handleRetry = () => {
    this.setState({ hasError: false, error: null });
    this.props.onRetry?.();
  };

  render() {
    if (this.state.hasError) {
      if (this.props.fallback) {
        return this.props.fallback;
      }

      return (
        <div className="p-4">
          <div role="alert" className="rounded-lg border border-theme-default bg-theme-surface/90 p-6 text-center shadow-sm">
            <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-500/20 mb-4">
              <AlertTriangle className="w-6 h-6 text-amber-400" aria-hidden="true" />
            </div>
            <h3 className="text-lg font-semibold text-theme-primary mb-2">
              {i18n.t('error_boundary.title', { ns: 'common' })}
            </h3>
            <p className="text-theme-muted text-sm mb-4">
              {i18n.t('feature_error.load_failed', { ns: 'common', feature: this.props.featureName })}
            </p>
            <button
              type="button"
              className="inline-flex items-center justify-center gap-2 rounded-lg bg-theme-elevated px-4 py-2.5 text-sm font-semibold text-theme-primary transition hover:bg-theme-muted/10 focus:outline-none focus:ring-2 focus:ring-theme-focus"
              onClick={this.handleRetry}
            >
              <RefreshCw className="w-4 h-4" aria-hidden="true" />
              {i18n.t('error_boundary.try_again', { ns: 'common' })}
            </button>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}

export default FeatureErrorBoundary;
