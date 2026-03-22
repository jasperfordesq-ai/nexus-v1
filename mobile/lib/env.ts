// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Environment variable validation.
 * Called once at app startup to warn about missing configuration.
 * In development, logs warnings to console.
 * In production, sends warnings to Sentry if configured.
 */

const PRODUCTION_API_URL = 'https://api.project-nexus.ie';
const isDev = process.env.NODE_ENV === 'development';

/**
 * Validate all expected environment variables at app startup.
 * Logs warnings for missing or misconfigured values.
 */
export function validateEnv(): void {
  const apiUrl = process.env.EXPO_PUBLIC_API_URL;
  const defaultTenant = process.env.EXPO_PUBLIC_DEFAULT_TENANT;
  const sentryDsn = process.env.EXPO_PUBLIC_SENTRY_DSN;

  // --- EXPO_PUBLIC_API_URL ---
  if (!apiUrl) {
    warn('EXPO_PUBLIC_API_URL is not set. API calls will fail.');
  } else {
    // Warn if the production URL is being used in a development build
    if (isDev && apiUrl === PRODUCTION_API_URL) {
      warn(
        `EXPO_PUBLIC_API_URL points to production (${PRODUCTION_API_URL}) in a development build. ` +
          'Use http://10.0.2.2:8090 (Android emulator) or http://localhost:8090 (iOS simulator) instead.'
      );
    }

    // Warn about trailing slash — a common misconfiguration that causes double-slash URLs
    if (apiUrl.endsWith('/')) {
      warn(
        `EXPO_PUBLIC_API_URL has a trailing slash ("${apiUrl}"). ` +
          'Remove it to avoid double-slash URLs in API requests.'
      );
    }
  }

  // --- EXPO_PUBLIC_DEFAULT_TENANT ---
  if (!defaultTenant || defaultTenant.trim() === '') {
    warn(
      'EXPO_PUBLIC_DEFAULT_TENANT is not set. ' +
        'The app will not know which tenant to load on first launch.'
    );
  }

  // --- EXPO_PUBLIC_SENTRY_DSN ---
  if (!sentryDsn) {
    note(
      'EXPO_PUBLIC_SENTRY_DSN is not set. Crash reporting (Sentry) is disabled. ' +
        'Set this in .env.local for production builds.'
    );
  }
}

/** Log a warning — visible in development, silent in production. */
function warn(message: string): void {
  if (isDev) {
    console.warn(`[env] WARNING: ${message}`);
  }
}

/** Log an informational note — always visible in development. */
function note(message: string): void {
  if (isDev) {
    console.log(`[env] NOTE: ${message}`);
  }
}
