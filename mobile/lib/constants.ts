// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import Constants from 'expo-constants';

/**
 * Base URL for the Project NEXUS PHP API.
 * Configured via EXPO_PUBLIC_API_URL in .env.local
 * Falls back to production URL.
 */
export const API_BASE_URL: string =
  (Constants.expoConfig?.extra?.apiUrl as string | undefined) ??
  process.env.EXPO_PUBLIC_API_URL ??
  'https://api.project-nexus.ie';

/**
 * Default tenant slug used when no tenant is selected.
 * Configurable via EXPO_PUBLIC_DEFAULT_TENANT.
 */
export const DEFAULT_TENANT: string =
  process.env.EXPO_PUBLIC_DEFAULT_TENANT ?? 'hour-timebank';

/** Secure storage keys */
export const STORAGE_KEYS = {
  AUTH_TOKEN: 'nexus_auth_token',
  REFRESH_TOKEN: 'nexus_refresh_token',
  TENANT_SLUG: 'nexus_tenant_slug',
  USER_DATA: 'nexus_user_data',
} as const;

/** App-wide timing constants */
export const TIMEOUTS = {
  /** API request timeout in milliseconds */
  API_REQUEST: 15_000,
} as const;

/** API path prefix for all v2 endpoints */
export const API_V2 = '/api/v2';
