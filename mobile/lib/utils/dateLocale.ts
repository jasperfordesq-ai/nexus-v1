// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

// Import the i18next singleton directly — NOT '@/lib/i18n'. The app entry
// initialises that module anyway, and importing it here would drag the full
// i18next init into every unit test that renders a date (react-i18next is
// mocked in tests, so the init crashes the suite).
import i18n from 'i18next';

/**
 * Locale for Date/Intl formatting that follows the language the user picked
 * in Settings rather than the device locale.
 *
 * Passing 'default' (or nothing) to toLocaleDateString/Intl.DateTimeFormat
 * uses the OS locale — so a user who switched the app to Spanish would still
 * see English/German dates. Always pass this instead.
 */
export function dateLocale(): string {
  return i18n.language || 'en';
}
