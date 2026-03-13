// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';
import HttpBackend from 'i18next-http-backend';

i18n
  .use(HttpBackend)
  .use(LanguageDetector)
  .use(initReactI18next)
  .init({
    fallbackLng: 'en',
    // supportedLngs is intentionally not set here — tenant-aware filtering is handled
    // in LanguageSwitcher which reads from TenantContext. fallbackLng: 'en' ensures
    // graceful fallback for any unsupported locale.
    defaultNS: 'common',
    ns: [
      'common', 'events', 'groups', 'profile', 'wallet',
      'exchanges', 'group_exchanges', 'feed', 'notifications',
      'search_page', 'onboarding', 'gamification', 'blog',
      'community', 'utility', 'public', 'legal', 'about',
      'federation', 'connections', 'chat',
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

export default i18n;
