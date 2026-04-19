// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import HttpBackend from 'i18next-http-backend';

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

const formatMissingKey = (key: string) => {
  if (import.meta.env.DEV && !loggedMissingKeys.has(key)) {
    loggedMissingKeys.add(key);
    console.error(`[i18n] Missing translation key: ${key}`);
  }

  return import.meta.env.DEV ? `${DEV_MISSING_KEY_PREFIX} ${key}` : key;
};

i18n
  .use(HttpBackend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'en',
    supportedLngs: SUPPORTED_LOCALE_CODES as unknown as string[],
    nonExplicitSupportedLngs: false,
    load: 'currentOnly',
    cleanCode: true,
    appendNamespaceToMissingKey: import.meta.env.DEV,
    parseMissingKeyHandler: formatMissingKey,
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
      'social', 'volunteering', 'explore', 'broker',
      'api_controllers_1', 'api_controllers_2', 'api_controllers_3',
      'svc_notifications', 'svc_notifications_2',
    ],
    debug: import.meta.env.DEV,

    interpolation: {
      escapeValue: false, // React already escapes values
    },

    backend: {
      loadPath: '/locales/{{lng}}/{{ns}}.json',
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
