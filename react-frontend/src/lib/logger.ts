// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Development Logger
 * Only logs in development mode to keep production console clean
 */

const isDev = import.meta.env.DEV;

/**
 * Log error messages.
 *
 * Dev: prints to the console. Production: forwards to Sentry so error-level logs
 * scattered across the app are not silently lost (warn/info/debug stay dev-only).
 * The forward is consent-gated — the capture helpers no-op unless the user
 * granted analytics consent (see lib/sentry.ts). The Sentry module is imported
 * lazily so this widely-imported logger keeps no eager Sentry dependency.
 */
export function logError(message: string, error?: unknown): void {
  if (isDev) {
    console.error(`[Error] ${message}`, error || '');
    return;
  }

  void import('@/lib/sentry')
    .then(({ captureSentryException, captureSentryMessage }) => {
      if (error instanceof Error) {
        captureSentryException(error, { source: 'logger', message });
      } else {
        captureSentryMessage(
          message,
          'error',
          error !== undefined ? { detail: error } : undefined,
        );
      }
    })
    .catch(() => {
      /* never let logging throw */
    });
}

/**
 * Log warning messages (only in development)
 */
export function logWarn(message: string, data?: unknown): void {
  if (isDev) {
    console.warn(`[Warn] ${message}`, data || '');
  }
}

/**
 * Log info messages (only in development)
 */
export function logInfo(message: string, data?: unknown): void {
  if (isDev) {
    console.info(`[Info] ${message}`, data || '');
  }
}

/**
 * Log debug messages (only in development)
 */
export function logDebug(message: string, data?: unknown): void {
  if (isDev) {
    console.log(`[Debug] ${message}`, data || '');
  }
}

export const logger = {
  error: logError,
  warn: logWarn,
  info: logInfo,
  debug: logDebug,
};

export default logger;
