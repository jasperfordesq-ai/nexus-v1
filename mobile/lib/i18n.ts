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
import enExchanges from '../locales/en/exchanges.json';
import enEvents from '../locales/en/events.json';
import enNotifications from '../locales/en/notifications.json';
import enSettings from '../locales/en/settings.json';
import enWallet from '../locales/en/wallet.json';
import enProfile from '../locales/en/profile.json';

// --- Irish (Gaeilge) ---
import gaCommon from '../locales/ga/common.json';
import gaAuth from '../locales/ga/auth.json';
import gaMessages from '../locales/ga/messages.json';
import gaHome from '../locales/ga/home.json';
import gaMembers from '../locales/ga/members.json';
import gaExchanges from '../locales/ga/exchanges.json';
import gaEvents from '../locales/ga/events.json';
import gaNotifications from '../locales/ga/notifications.json';
import gaSettings from '../locales/ga/settings.json';
import gaWallet from '../locales/ga/wallet.json';
import gaProfile from '../locales/ga/profile.json';

// --- German ---
import deCommon from '../locales/de/common.json';
import deAuth from '../locales/de/auth.json';
import deMessages from '../locales/de/messages.json';
import deHome from '../locales/de/home.json';
import deMembers from '../locales/de/members.json';
import deExchanges from '../locales/de/exchanges.json';
import deEvents from '../locales/de/events.json';
import deNotifications from '../locales/de/notifications.json';
import deSettings from '../locales/de/settings.json';
import deWallet from '../locales/de/wallet.json';
import deProfile from '../locales/de/profile.json';

// --- French ---
import frCommon from '../locales/fr/common.json';
import frAuth from '../locales/fr/auth.json';
import frMessages from '../locales/fr/messages.json';
import frHome from '../locales/fr/home.json';
import frMembers from '../locales/fr/members.json';
import frExchanges from '../locales/fr/exchanges.json';
import frEvents from '../locales/fr/events.json';
import frNotifications from '../locales/fr/notifications.json';
import frSettings from '../locales/fr/settings.json';
import frWallet from '../locales/fr/wallet.json';
import frProfile from '../locales/fr/profile.json';

// --- Italian ---
import itCommon from '../locales/it/common.json';
import itAuth from '../locales/it/auth.json';
import itMessages from '../locales/it/messages.json';
import itHome from '../locales/it/home.json';
import itMembers from '../locales/it/members.json';
import itExchanges from '../locales/it/exchanges.json';
import itEvents from '../locales/it/events.json';
import itNotifications from '../locales/it/notifications.json';
import itSettings from '../locales/it/settings.json';
import itWallet from '../locales/it/wallet.json';
import itProfile from '../locales/it/profile.json';

// --- Portuguese ---
import ptCommon from '../locales/pt/common.json';
import ptAuth from '../locales/pt/auth.json';
import ptMessages from '../locales/pt/messages.json';
import ptHome from '../locales/pt/home.json';
import ptMembers from '../locales/pt/members.json';
import ptExchanges from '../locales/pt/exchanges.json';
import ptEvents from '../locales/pt/events.json';
import ptNotifications from '../locales/pt/notifications.json';
import ptSettings from '../locales/pt/settings.json';
import ptWallet from '../locales/pt/wallet.json';
import ptProfile from '../locales/pt/profile.json';

// --- Spanish ---
import esCommon from '../locales/es/common.json';
import esAuth from '../locales/es/auth.json';
import esMessages from '../locales/es/messages.json';
import esHome from '../locales/es/home.json';
import esMembers from '../locales/es/members.json';
import esExchanges from '../locales/es/exchanges.json';
import esEvents from '../locales/es/events.json';
import esNotifications from '../locales/es/notifications.json';
import esSettings from '../locales/es/settings.json';
import esWallet from '../locales/es/wallet.json';
import esProfile from '../locales/es/profile.json';

// Detect device locale, fall back to 'en'
const deviceLocale = Localization.getLocales()[0]?.languageCode ?? 'en';
const supportedLanguages = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es'];
const detectedLanguage = supportedLanguages.includes(deviceLocale) ? deviceLocale : 'en';

i18n
  .use(initReactI18next)
  .init({
    resources: {
      en: { common: enCommon, auth: enAuth, messages: enMessages, home: enHome, members: enMembers, exchanges: enExchanges, events: enEvents, notifications: enNotifications, settings: enSettings, wallet: enWallet, profile: enProfile },
      ga: { common: gaCommon, auth: gaAuth, messages: gaMessages, home: gaHome, members: gaMembers, exchanges: gaExchanges, events: gaEvents, notifications: gaNotifications, settings: gaSettings, wallet: gaWallet, profile: gaProfile },
      de: { common: deCommon, auth: deAuth, messages: deMessages, home: deHome, members: deMembers, exchanges: deExchanges, events: deEvents, notifications: deNotifications, settings: deSettings, wallet: deWallet, profile: deProfile },
      fr: { common: frCommon, auth: frAuth, messages: frMessages, home: frHome, members: frMembers, exchanges: frExchanges, events: frEvents, notifications: frNotifications, settings: frSettings, wallet: frWallet, profile: frProfile },
      it: { common: itCommon, auth: itAuth, messages: itMessages, home: itHome, members: itMembers, exchanges: itExchanges, events: itEvents, notifications: itNotifications, settings: itSettings, wallet: itWallet, profile: itProfile },
      pt: { common: ptCommon, auth: ptAuth, messages: ptMessages, home: ptHome, members: ptMembers, exchanges: ptExchanges, events: ptEvents, notifications: ptNotifications, settings: ptSettings, wallet: ptWallet, profile: ptProfile },
      es: { common: esCommon, auth: esAuth, messages: esMessages, home: esHome, members: esMembers, exchanges: esExchanges, events: esEvents, notifications: esNotifications, settings: esSettings, wallet: esWallet, profile: esProfile },
    },
    lng: detectedLanguage,
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: ['common', 'auth', 'messages', 'home', 'members', 'exchanges', 'events', 'notifications', 'settings', 'wallet', 'profile'],
    interpolation: {
      escapeValue: false, // React Native handles XSS
    },
    compatibilityJSON: 'v4',
  });

export default i18n;
