// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Loading Screen Component
 * Full-page loading indicator
 */

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
      <div className="relative z-10 w-full max-w-sm px-4">
        <div className="rounded-lg border border-theme-default bg-theme-surface/80 px-6 py-8 text-center shadow-xl">
          <div
            className="mx-auto mb-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-theme-elevated"
            aria-hidden="true"
          >
            <span className="h-8 w-8 animate-spin rounded-full border-3 border-theme-muted/30 border-t-theme-accent" />
          </div>
          <p className="text-sm font-medium text-theme-secondary">{displayMessage}</p>
          <div className="mt-5 w-full space-y-2" aria-hidden="true">
            <div className="mx-auto h-2.5 w-3/4 animate-pulse rounded-full bg-theme-elevated" />
            <div className="mx-auto h-2.5 w-1/2 animate-pulse rounded-full bg-theme-elevated" />
          </div>
          <span className="sr-only">{displayMessage}</span>
        </div>
      </div>
    </div>
  );
}

export default LoadingScreen;
