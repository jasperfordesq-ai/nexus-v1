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
 * Log error messages (only in development)
 */
export function logError(message: string, error?: unknown): void {
  if (isDev) {
    console.error(`[Error] ${message}`, error || '');
  }
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
