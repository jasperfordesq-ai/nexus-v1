import { Card } from '@heroui/react';
import { Skeleton, Spinner } from '@/components/ui';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Loading Screen Component
 * Full-page loading indicator
 */

import { motion } from '@/lib/motion';

import i18n from 'i18next';

interface LoadingScreenProps {
  message?: string;
}

export function LoadingScreen({ message }: LoadingScreenProps) {
  const displayMessage = message ?? (
    i18n.isInitialized && i18n.hasLoadedNamespace('common')
      ? i18n.t('loading', { ns: 'common' })
      : 'Loading...'
  );
  return (
    <div
      className="min-h-screen flex items-center justify-center"
      role="status"
      aria-live="polite"
      aria-busy="true"
      aria-label={displayMessage}
    >
      <motion.div
        initial={{ opacity: 0, scale: 0.9 }}
        animate={{ opacity: 1, scale: 1 }}
        className="relative z-10 w-full max-w-sm px-4"
      >
        <Card className="rounded-lg border border-theme-default bg-theme-surface/80 shadow-xl">
          <Card.Content className="items-center px-6 py-8 text-center">
            <motion.div
              animate={{ rotate: 360 }}
              transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}
              className="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500/20 to-cyan-500/20"
              aria-hidden="true"
            >
              <Spinner size="lg" />
            </motion.div>
            <p className="text-sm font-medium text-theme-secondary">{displayMessage}</p>
            <div className="mt-5 w-full space-y-2" aria-hidden="true">
              <Skeleton className="mx-auto h-2.5 w-3/4 rounded-full" />
              <Skeleton className="mx-auto h-2.5 w-1/2 rounded-full" />
            </div>
            <span className="sr-only">{displayMessage}</span>
          </Card.Content>
        </Card>
      </motion.div>
    </div>
  );
}

export default LoadingScreen;
