// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import * as Localization from 'expo-localization';

// --- English ---
import enCommon from '../locales/en/common.json';
import enAuth from '../locales/en/auth.json';
import enMessages from '../locales/en/messages.json';
import enHome from '../locales/en/home.json';
import enMembers from '../locales/en/members.json';

// --- Irish (Gaeilge) ---
import gaCommon from '../locales/ga/common.json';
import gaAuth from '../locales/ga/auth.json';
import gaMessages from '../locales/ga/messages.json';
import gaHome from '../locales/ga/home.json';
import gaMembers from '../locales/ga/members.json';

// --- German ---
import deCommon from '../locales/de/common.json';
import deAuth from '../locales/de/auth.json';
import deMessages from '../locales/de/messages.json';
import deHome from '../locales/de/home.json';
import deMembers from '../locales/de/members.json';

// --- French ---
import frCommon from '../locales/fr/common.json';
import frAuth from '../locales/fr/auth.json';
import frMessages from '../locales/fr/messages.json';
import frHome from '../locales/fr/home.json';
import frMembers from '../locales/fr/members.json';

// --- Italian ---
import itCommon from '../locales/it/common.json';
import itAuth from '../locales/it/auth.json';
import itMessages from '../locales/it/messages.json';
import itHome from '../locales/it/home.json';
import itMembers from '../locales/it/members.json';

// --- Portuguese ---
import ptCommon from '../locales/pt/common.json';
import ptAuth from '../locales/pt/auth.json';
import ptMessages from '../locales/pt/messages.json';
import ptHome from '../locales/pt/home.json';
import ptMembers from '../locales/pt/members.json';

// --- Spanish ---
import esCommon from '../locales/es/common.json';
import esAuth from '../locales/es/auth.json';
import esMessages from '../locales/es/messages.json';
import esHome from '../locales/es/home.json';
import esMembers from '../locales/es/members.json';

// Detect device locale, fall back to 'en'
const deviceLocale = Localization.getLocales()[0]?.languageCode ?? 'en';
const supportedLanguages = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es'];
const detectedLanguage = supportedLanguages.includes(deviceLocale) ? deviceLocale : 'en';

i18n
  .use(initReactI18next)
  .init({
    resources: {
      en: { common: enCommon, auth: enAuth, messages: enMessages, home: enHome, members: enMembers },
      ga: { common: gaCommon, auth: gaAuth, messages: gaMessages, home: gaHome, members: gaMembers },
      de: { common: deCommon, auth: deAuth, messages: deMessages, home: deHome, members: deMembers },
      fr: { common: frCommon, auth: frAuth, messages: frMessages, home: frHome, members: frMembers },
      it: { common: itCommon, auth: itAuth, messages: itMessages, home: itHome, members: itMembers },
      pt: { common: ptCommon, auth: ptAuth, messages: ptMessages, home: ptHome, members: ptMembers },
      es: { common: esCommon, auth: esAuth, messages: esMessages, home: esHome, members: esMembers },
    },
    lng: detectedLanguage,
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: ['common', 'auth', 'messages', 'home', 'members'],
    interpolation: {
      escapeValue: false, // React Native handles XSS
    },
    compatibilityJSON: 'v4',
  });

export default i18n;
