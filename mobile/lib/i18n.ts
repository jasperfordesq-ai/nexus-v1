// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import * as Localization from 'expo-localization';

/**
 * i18n setup with LAZY language loading.
 *
 * Only the active language (+ English fallback) is parsed at cold start.
 * Other languages are loaded on-demand when the user switches.
 * Metro still includes all JSON in the bundle, but wrapping require()
 * inside functions defers JS evaluation — saving ~200-400ms on cold start
 * and ~420KB of resident memory for unused languages.
 */

type NamespaceMap = Record<string, object>;
type LanguageLoader = () => NamespaceMap;

const NAMESPACES = [
  'common', 'auth', 'messages', 'home', 'members', 'exchanges', 'events',
  'notifications', 'settings', 'wallet', 'profile', 'search', 'blog',
  'groups', 'gamification', 'goals', 'chat', 'volunteering', 'organisations',
  'endorsements', 'federation', 'jobs',
] as const;

// Each loader is a function — require() calls inside are only evaluated when
// the function is invoked. This means non-active languages stay as un-parsed
// bytecode until the user explicitly switches language.
const languageLoaders: Record<string, LanguageLoader> = {
  en: () => ({
    common: require('../locales/en/common.json'),
    auth: require('../locales/en/auth.json'),
    messages: require('../locales/en/messages.json'),
    home: require('../locales/en/home.json'),
    members: require('../locales/en/members.json'),
    exchanges: require('../locales/en/exchanges.json'),
    events: require('../locales/en/events.json'),
    notifications: require('../locales/en/notifications.json'),
    settings: require('../locales/en/settings.json'),
    wallet: require('../locales/en/wallet.json'),
    profile: require('../locales/en/profile.json'),
    search: require('../locales/en/search.json'),
    blog: require('../locales/en/blog.json'),
    groups: require('../locales/en/groups.json'),
    gamification: require('../locales/en/gamification.json'),
    goals: require('../locales/en/goals.json'),
    chat: require('../locales/en/chat.json'),
    volunteering: require('../locales/en/volunteering.json'),
    organisations: require('../locales/en/organisations.json'),
    endorsements: require('../locales/en/endorsements.json'),
    federation: require('../locales/en/federation.json'),
    jobs: require('../locales/en/jobs.json'),
  }),
  ga: () => ({
    common: require('../locales/ga/common.json'),
    auth: require('../locales/ga/auth.json'),
    messages: require('../locales/ga/messages.json'),
    home: require('../locales/ga/home.json'),
    members: require('../locales/ga/members.json'),
    exchanges: require('../locales/ga/exchanges.json'),
    events: require('../locales/ga/events.json'),
    notifications: require('../locales/ga/notifications.json'),
    settings: require('../locales/ga/settings.json'),
    wallet: require('../locales/ga/wallet.json'),
    profile: require('../locales/ga/profile.json'),
    search: require('../locales/ga/search.json'),
    blog: require('../locales/ga/blog.json'),
    groups: require('../locales/ga/groups.json'),
    gamification: require('../locales/ga/gamification.json'),
    goals: require('../locales/ga/goals.json'),
    chat: require('../locales/ga/chat.json'),
    volunteering: require('../locales/ga/volunteering.json'),
    organisations: require('../locales/ga/organisations.json'),
    endorsements: require('../locales/ga/endorsements.json'),
    federation: require('../locales/ga/federation.json'),
    jobs: require('../locales/ga/jobs.json'),
  }),
  de: () => ({
    common: require('../locales/de/common.json'),
    auth: require('../locales/de/auth.json'),
    messages: require('../locales/de/messages.json'),
    home: require('../locales/de/home.json'),
    members: require('../locales/de/members.json'),
    exchanges: require('../locales/de/exchanges.json'),
    events: require('../locales/de/events.json'),
    notifications: require('../locales/de/notifications.json'),
    settings: require('../locales/de/settings.json'),
    wallet: require('../locales/de/wallet.json'),
    profile: require('../locales/de/profile.json'),
    search: require('../locales/de/search.json'),
    blog: require('../locales/de/blog.json'),
    groups: require('../locales/de/groups.json'),
    gamification: require('../locales/de/gamification.json'),
    goals: require('../locales/de/goals.json'),
    chat: require('../locales/de/chat.json'),
    volunteering: require('../locales/de/volunteering.json'),
    organisations: require('../locales/de/organisations.json'),
    endorsements: require('../locales/de/endorsements.json'),
    federation: require('../locales/de/federation.json'),
    jobs: require('../locales/de/jobs.json'),
  }),
  fr: () => ({
    common: require('../locales/fr/common.json'),
    auth: require('../locales/fr/auth.json'),
    messages: require('../locales/fr/messages.json'),
    home: require('../locales/fr/home.json'),
    members: require('../locales/fr/members.json'),
    exchanges: require('../locales/fr/exchanges.json'),
    events: require('../locales/fr/events.json'),
    notifications: require('../locales/fr/notifications.json'),
    settings: require('../locales/fr/settings.json'),
    wallet: require('../locales/fr/wallet.json'),
    profile: require('../locales/fr/profile.json'),
    search: require('../locales/fr/search.json'),
    blog: require('../locales/fr/blog.json'),
    groups: require('../locales/fr/groups.json'),
    gamification: require('../locales/fr/gamification.json'),
    goals: require('../locales/fr/goals.json'),
    chat: require('../locales/fr/chat.json'),
    volunteering: require('../locales/fr/volunteering.json'),
    organisations: require('../locales/fr/organisations.json'),
    endorsements: require('../locales/fr/endorsements.json'),
    federation: require('../locales/fr/federation.json'),
    jobs: require('../locales/fr/jobs.json'),
  }),
  it: () => ({
    common: require('../locales/it/common.json'),
    auth: require('../locales/it/auth.json'),
    messages: require('../locales/it/messages.json'),
    home: require('../locales/it/home.json'),
    members: require('../locales/it/members.json'),
    exchanges: require('../locales/it/exchanges.json'),
    events: require('../locales/it/events.json'),
    notifications: require('../locales/it/notifications.json'),
    settings: require('../locales/it/settings.json'),
    wallet: require('../locales/it/wallet.json'),
    profile: require('../locales/it/profile.json'),
    search: require('../locales/it/search.json'),
    blog: require('../locales/it/blog.json'),
    groups: require('../locales/it/groups.json'),
    gamification: require('../locales/it/gamification.json'),
    goals: require('../locales/it/goals.json'),
    chat: require('../locales/it/chat.json'),
    volunteering: require('../locales/it/volunteering.json'),
    organisations: require('../locales/it/organisations.json'),
    endorsements: require('../locales/it/endorsements.json'),
    federation: require('../locales/it/federation.json'),
    jobs: require('../locales/it/jobs.json'),
  }),
  pt: () => ({
    common: require('../locales/pt/common.json'),
    auth: require('../locales/pt/auth.json'),
    messages: require('../locales/pt/messages.json'),
    home: require('../locales/pt/home.json'),
    members: require('../locales/pt/members.json'),
    exchanges: require('../locales/pt/exchanges.json'),
    events: require('../locales/pt/events.json'),
    notifications: require('../locales/pt/notifications.json'),
    settings: require('../locales/pt/settings.json'),
    wallet: require('../locales/pt/wallet.json'),
    profile: require('../locales/pt/profile.json'),
    search: require('../locales/pt/search.json'),
    blog: require('../locales/pt/blog.json'),
    groups: require('../locales/pt/groups.json'),
    gamification: require('../locales/pt/gamification.json'),
    goals: require('../locales/pt/goals.json'),
    chat: require('../locales/pt/chat.json'),
    volunteering: require('../locales/pt/volunteering.json'),
    organisations: require('../locales/pt/organisations.json'),
    endorsements: require('../locales/pt/endorsements.json'),
    federation: require('../locales/pt/federation.json'),
    jobs: require('../locales/pt/jobs.json'),
  }),
  es: () => ({
    common: require('../locales/es/common.json'),
    auth: require('../locales/es/auth.json'),
    messages: require('../locales/es/messages.json'),
    home: require('../locales/es/home.json'),
    members: require('../locales/es/members.json'),
    exchanges: require('../locales/es/exchanges.json'),
    events: require('../locales/es/events.json'),
    notifications: require('../locales/es/notifications.json'),
    settings: require('../locales/es/settings.json'),
    wallet: require('../locales/es/wallet.json'),
    profile: require('../locales/es/profile.json'),
    search: require('../locales/es/search.json'),
    blog: require('../locales/es/blog.json'),
    groups: require('../locales/es/groups.json'),
    gamification: require('../locales/es/gamification.json'),
    goals: require('../locales/es/goals.json'),
    chat: require('../locales/es/chat.json'),
    volunteering: require('../locales/es/volunteering.json'),
    organisations: require('../locales/es/organisations.json'),
    endorsements: require('../locales/es/endorsements.json'),
    federation: require('../locales/es/federation.json'),
    jobs: require('../locales/es/jobs.json'),
  }),
};

export const SUPPORTED_LANGUAGES = Object.keys(languageLoaders);

// Detect device locale, fall back to 'en'
const deviceLocale = Localization.getLocales()[0]?.languageCode ?? 'en';
const detectedLanguage = SUPPORTED_LANGUAGES.includes(deviceLocale) ? deviceLocale : 'en';

// Only load the active language + English fallback (if different).
// This parses at most 44 JSON files (~150KB) instead of all 154 (~495KB).
const resources: Record<string, NamespaceMap> = {
  en: languageLoaders.en(),
};
if (detectedLanguage !== 'en') {
  resources[detectedLanguage] = languageLoaders[detectedLanguage]();
}

i18n
  .use(initReactI18next)
  .init({
    resources,
    lng: detectedLanguage,
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: [...NAMESPACES],
    interpolation: {
      escapeValue: false, // React Native handles XSS
    },
    compatibilityJSON: 'v4',
  });

/**
 * Load a language on demand (e.g. when the user switches in Settings).
 * No-op if the language is already loaded.
 */
export function loadLanguage(lang: string): void {
  if (i18n.hasResourceBundle(lang, 'common')) return;
  const loader = languageLoaders[lang];
  if (!loader) return;
  const bundles = loader();
  for (const [ns, data] of Object.entries(bundles)) {
    i18n.addResourceBundle(lang, ns, data);
  }
}

/**
 * Switch the active language. Loads resources on demand if needed.
 */
export async function changeLanguage(lang: string): Promise<void> {
  loadLanguage(lang);
  await i18n.changeLanguage(lang);
}

export default i18n;
