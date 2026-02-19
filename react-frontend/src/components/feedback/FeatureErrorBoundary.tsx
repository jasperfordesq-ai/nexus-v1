// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Feature Error Boundary
 * Catches errors in feature sections without crashing the entire app
 */

import { Component, type ReactNode, type ErrorInfo } from 'react';
import { motion } from 'framer-motion';
import { AlertTriangle, RefreshCw } from 'lucide-react';
import { Button } from '@heroui/react';
import { GlassCard } from '@/components/ui';
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
    logError(`Feature error in ${this.props.featureName}`, { error, errorInfo });
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
        <motion.div
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          className="p-4"
        >
          <GlassCard className="p-6 text-center">
            <div className="inline-flex items-center justify-center w-12 h-12 rounded-full bg-amber-500/20 mb-4">
              <AlertTriangle className="w-6 h-6 text-amber-400" />
            </div>
            <h3 className="text-lg font-semibold text-theme-primary mb-2">
              Something went wrong
            </h3>
            <p className="text-theme-muted text-sm mb-4">
              We couldn&apos;t load {this.props.featureName}. This section may be temporarily unavailable.
            </p>
            <Button
              variant="flat"
              className="bg-theme-elevated text-theme-primary"
              startContent={<RefreshCw className="w-4 h-4" />}
              onPress={this.handleRetry}
            >
              Try Again
            </Button>
          </GlassCard>
        </motion.div>
      );
    }

    return this.props.children;
  }
}

export default FeatureErrorBoundary;
