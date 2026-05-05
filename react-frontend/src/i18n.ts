// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import ChainedBackend from 'i18next-chained-backend';
import HttpBackend from 'i18next-http-backend';
import LocalStorageBackend from 'i18next-localstorage-backend';
import { captureMessage } from '@sentry/react';

export const SUPPORTED_LOCALE_CODES = [
  'ar',
  'de',
  'en',
  'es',
  'fr',
  'ga',
  'it',
  'ja',
  'nl',
  'pl',
  'pt',
] as const;

const DEV_MISSING_KEY_PREFIX = '[missing]';
const loggedMissingKeys = new Set<string>();
const thrownMissingKeys = new Set<string>();
const sentryReportedKeys = new Set<string>();
const STRICT_MISSING_KEY_STORAGE_KEY = 'nexus_i18n_strict_missing_keys';
const localeBackends = import.meta.env.DEV ? [HttpBackend] : [LocalStorageBackend, HttpBackend];
const localeBackendOptions = import.meta.env.DEV
  ? [
      {
        // Dev must not reuse stale namespace payloads from localStorage. Missing-key
        // fixes should be visible on reload without clearing browser storage.
        loadPath: `/locales/{{lng}}/{{ns}}.json?v=${Date.now()}`,
      },
    ]
  : [
      {
        expirationTime: 60 * 60 * 1000,
        prefix: `i18n_${__BUILD_COMMIT__}_`,
      },
      {
        loadPath: `/locales/{{lng}}/{{ns}}.json?v=${__BUILD_COMMIT__}`,
      },
    ];

const isStrictMissingKeyMode = () => {
  if (!import.meta.env.DEV) return false;
  if (import.meta.env.VITE_I18N_STRICT_MISSING === '1') return true;

  try {
    return window.localStorage.getItem(STRICT_MISSING_KEY_STORAGE_KEY) === '1';
  } catch {
    return false;
  }
};

const reportMissingKey = (identifier: string) => {
  if (import.meta.env.DEV && !loggedMissingKeys.has(identifier)) {
    loggedMissingKeys.add(identifier);
    console.error(`[i18n] Missing translation key: ${identifier}`);
  }

  if (isStrictMissingKeyMode() && !thrownMissingKeys.has(identifier)) {
    thrownMissingKeys.add(identifier);
    queueMicrotask(() => {
      throw new Error(`[i18n] Missing translation key: ${identifier}`);
    });
  }

  // Production: report to Sentry once per session so missing keys surface as alerts
  if (!import.meta.env.DEV && !sentryReportedKeys.has(identifier)) {
    sentryReportedKeys.add(identifier);
    captureMessage(`[i18n] Missing translation key: ${identifier}`, 'warning');
  }
};

const formatMissingKey = (key: string, defaultValue?: string) => {
  // Honor caller-supplied defaultValue when present — i18next's own fallback
  // is suppressed once parseMissingKeyHandler is set, so forward it explicitly.
  if (typeof defaultValue === 'string' && defaultValue.length > 0 && defaultValue !== key) {
    return defaultValue;
  }
  return import.meta.env.DEV ? `${DEV_MISSING_KEY_PREFIX} ${key}` : key;
};

i18n
  .use(ChainedBackend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'en',
    supportedLngs: SUPPORTED_LOCALE_CODES as unknown as string[],
    nonExplicitSupportedLngs: false,
    load: 'currentOnly',
    cleanCode: true,
    saveMissing: import.meta.env.DEV,
    appendNamespaceToMissingKey: import.meta.env.DEV,
    parseMissingKeyHandler: formatMissingKey,
    missingKeyHandler: (lng, ns, key) => {
      const localeList = Array.isArray(lng) ? lng.join(',') : lng || 'unknown';
      reportMissingKey(`${ns}:${key} [${localeList}]`);
    },
    // Tenant-aware filtering still happens in LanguageSwitcher. supportedLngs keeps
    // detection and fallback behavior constrained to the locales we actually ship.
    defaultNS: 'common',
    ns: [
      'common', 'events', 'groups', 'profile', 'wallet',
      'exchanges', 'group_exchanges', 'feed', 'notifications',
      'search_page', 'onboarding', 'gamification', 'blog',
      'community', 'utility', 'public', 'legal', 'about',
      'federation', 'connections', 'chat', 'activity', 'admin', 'stories',
      'admin_dashboard', 'admin_nav', 'auth', 'dashboard',
      'emails', 'emails_listings', 'emails_misc', 'emails_notifications',
      'errors', 'goals', 'ideation', 'jobs', 'kb',
      'listings', 'marketplace', 'matches', 'messages', 'polls', 'settings',
      'social', 'volunteering', 'explore', 'broker', 'caring_community',
      'municipality_survey', 'civic_digest', 'municipality_feedback',
      'success_stories', 'pilot_inquiry', 'project_announcements', 'project_announcements_admin',
      'api_controllers_1', 'api_controllers_2', 'api_controllers_3',
      'svc_notifications', 'svc_notifications_2',
    ],
    debug: import.meta.env.DEV,

    interpolation: {
      escapeValue: false, // React already escapes values
    },

    backend: {
      backends: localeBackends,
      backendOptions: localeBackendOptions,
    },

    detection: {
      // Check localStorage first, then browser language, then <html lang>
      order: ['localStorage', 'navigator', 'htmlTag'],
      caches: ['localStorage'],
      lookupLocalStorage: 'nexus_language',
    },
  });

/** Languages that use right-to-left script direction. */
const RTL_LANGUAGES = new Set(['ar']);

const applyLangAttributes = (lng: string) => {
  document.documentElement.lang = lng;
  document.documentElement.dir = RTL_LANGUAGES.has(lng) ? 'rtl' : 'ltr';
};

// Keep <html lang="..."> and dir in sync with the active language for accessibility & SEO
i18n.on('languageChanged', applyLangAttributes);

// Apply on initial load so first paint is correct (before any languageChanged fires)
i18n.on('initialized', () => applyLangAttributes(i18n.language || 'en'));

export default i18n;
