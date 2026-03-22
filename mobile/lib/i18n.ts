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
import enSearch from '../locales/en/search.json';
import enBlog from '../locales/en/blog.json';
import enGroups from '../locales/en/groups.json';
import enGamification from '../locales/en/gamification.json';
import enGoals from '../locales/en/goals.json';
import enChat from '../locales/en/chat.json';
import enVolunteering from '../locales/en/volunteering.json';
import enOrganisations from '../locales/en/organisations.json';
import enEndorsements from '../locales/en/endorsements.json';
import enFederation from '../locales/en/federation.json';
import enJobs from '../locales/en/jobs.json';

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
import gaSearch from '../locales/ga/search.json';
import gaBlog from '../locales/ga/blog.json';
import gaGroups from '../locales/ga/groups.json';
import gaGamification from '../locales/ga/gamification.json';
import gaGoals from '../locales/ga/goals.json';
import gaChat from '../locales/ga/chat.json';
import gaVolunteering from '../locales/ga/volunteering.json';
import gaOrganisations from '../locales/ga/organisations.json';
import gaEndorsements from '../locales/ga/endorsements.json';
import gaFederation from '../locales/ga/federation.json';
import gaJobs from '../locales/ga/jobs.json';

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
import deSearch from '../locales/de/search.json';
import deBlog from '../locales/de/blog.json';
import deGroups from '../locales/de/groups.json';
import deGamification from '../locales/de/gamification.json';
import deGoals from '../locales/de/goals.json';
import deChat from '../locales/de/chat.json';
import deVolunteering from '../locales/de/volunteering.json';
import deOrganisations from '../locales/de/organisations.json';
import deEndorsements from '../locales/de/endorsements.json';
import deFederation from '../locales/de/federation.json';
import deJobs from '../locales/de/jobs.json';

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
import frSearch from '../locales/fr/search.json';
import frBlog from '../locales/fr/blog.json';
import frGroups from '../locales/fr/groups.json';
import frGamification from '../locales/fr/gamification.json';
import frGoals from '../locales/fr/goals.json';
import frChat from '../locales/fr/chat.json';
import frVolunteering from '../locales/fr/volunteering.json';
import frOrganisations from '../locales/fr/organisations.json';
import frEndorsements from '../locales/fr/endorsements.json';
import frFederation from '../locales/fr/federation.json';
import frJobs from '../locales/fr/jobs.json';

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
import itSearch from '../locales/it/search.json';
import itBlog from '../locales/it/blog.json';
import itGroups from '../locales/it/groups.json';
import itGamification from '../locales/it/gamification.json';
import itGoals from '../locales/it/goals.json';
import itChat from '../locales/it/chat.json';
import itVolunteering from '../locales/it/volunteering.json';
import itOrganisations from '../locales/it/organisations.json';
import itEndorsements from '../locales/it/endorsements.json';
import itFederation from '../locales/it/federation.json';
import itJobs from '../locales/it/jobs.json';

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
import ptSearch from '../locales/pt/search.json';
import ptBlog from '../locales/pt/blog.json';
import ptGroups from '../locales/pt/groups.json';
import ptGamification from '../locales/pt/gamification.json';
import ptGoals from '../locales/pt/goals.json';
import ptChat from '../locales/pt/chat.json';
import ptVolunteering from '../locales/pt/volunteering.json';
import ptOrganisations from '../locales/pt/organisations.json';
import ptEndorsements from '../locales/pt/endorsements.json';
import ptFederation from '../locales/pt/federation.json';
import ptJobs from '../locales/pt/jobs.json';

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
import esSearch from '../locales/es/search.json';
import esBlog from '../locales/es/blog.json';
import esGroups from '../locales/es/groups.json';
import esGamification from '../locales/es/gamification.json';
import esGoals from '../locales/es/goals.json';
import esChat from '../locales/es/chat.json';
import esVolunteering from '../locales/es/volunteering.json';
import esOrganisations from '../locales/es/organisations.json';
import esEndorsements from '../locales/es/endorsements.json';
import esFederation from '../locales/es/federation.json';
import esJobs from '../locales/es/jobs.json';

// Detect device locale, fall back to 'en'
const deviceLocale = Localization.getLocales()[0]?.languageCode ?? 'en';
const supportedLanguages = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es'];
const detectedLanguage = supportedLanguages.includes(deviceLocale) ? deviceLocale : 'en';

i18n
  .use(initReactI18next)
  .init({
    resources: {
      en: { common: enCommon, auth: enAuth, messages: enMessages, home: enHome, members: enMembers, exchanges: enExchanges, events: enEvents, notifications: enNotifications, settings: enSettings, wallet: enWallet, profile: enProfile, search: enSearch, blog: enBlog, groups: enGroups, gamification: enGamification, goals: enGoals, chat: enChat, volunteering: enVolunteering, organisations: enOrganisations, endorsements: enEndorsements, federation: enFederation, jobs: enJobs },
      ga: { common: gaCommon, auth: gaAuth, messages: gaMessages, home: gaHome, members: gaMembers, exchanges: gaExchanges, events: gaEvents, notifications: gaNotifications, settings: gaSettings, wallet: gaWallet, profile: gaProfile, search: gaSearch, blog: gaBlog, groups: gaGroups, gamification: gaGamification, goals: gaGoals, chat: gaChat, volunteering: gaVolunteering, organisations: gaOrganisations, endorsements: gaEndorsements, federation: gaFederation, jobs: gaJobs },
      de: { common: deCommon, auth: deAuth, messages: deMessages, home: deHome, members: deMembers, exchanges: deExchanges, events: deEvents, notifications: deNotifications, settings: deSettings, wallet: deWallet, profile: deProfile, search: deSearch, blog: deBlog, groups: deGroups, gamification: deGamification, goals: deGoals, chat: deChat, volunteering: deVolunteering, organisations: deOrganisations, endorsements: deEndorsements, federation: deFederation, jobs: deJobs },
      fr: { common: frCommon, auth: frAuth, messages: frMessages, home: frHome, members: frMembers, exchanges: frExchanges, events: frEvents, notifications: frNotifications, settings: frSettings, wallet: frWallet, profile: frProfile, search: frSearch, blog: frBlog, groups: frGroups, gamification: frGamification, goals: frGoals, chat: frChat, volunteering: frVolunteering, organisations: frOrganisations, endorsements: frEndorsements, federation: frFederation, jobs: frJobs },
      it: { common: itCommon, auth: itAuth, messages: itMessages, home: itHome, members: itMembers, exchanges: itExchanges, events: itEvents, notifications: itNotifications, settings: itSettings, wallet: itWallet, profile: itProfile, search: itSearch, blog: itBlog, groups: itGroups, gamification: itGamification, goals: itGoals, chat: itChat, volunteering: itVolunteering, organisations: itOrganisations, endorsements: itEndorsements, federation: itFederation, jobs: itJobs },
      pt: { common: ptCommon, auth: ptAuth, messages: ptMessages, home: ptHome, members: ptMembers, exchanges: ptExchanges, events: ptEvents, notifications: ptNotifications, settings: ptSettings, wallet: ptWallet, profile: ptProfile, search: ptSearch, blog: ptBlog, groups: ptGroups, gamification: ptGamification, goals: ptGoals, chat: ptChat, volunteering: ptVolunteering, organisations: ptOrganisations, endorsements: ptEndorsements, federation: ptFederation, jobs: ptJobs },
      es: { common: esCommon, auth: esAuth, messages: esMessages, home: esHome, members: esMembers, exchanges: esExchanges, events: esEvents, notifications: esNotifications, settings: esSettings, wallet: esWallet, profile: esProfile, search: esSearch, blog: esBlog, groups: esGroups, gamification: esGamification, goals: esGoals, chat: esChat, volunteering: esVolunteering, organisations: esOrganisations, endorsements: esEndorsements, federation: esFederation, jobs: esJobs },
    },
    lng: detectedLanguage,
    fallbackLng: 'en',
    defaultNS: 'common',
    ns: ['common', 'auth', 'messages', 'home', 'members', 'exchanges', 'events', 'notifications', 'settings', 'wallet', 'profile', 'search', 'blog', 'groups', 'gamification', 'goals', 'chat', 'volunteering', 'organisations', 'endorsements', 'federation', 'jobs'],
    interpolation: {
      escapeValue: false, // React Native handles XSS
    },
    compatibilityJSON: 'v4',
  });

export default i18n;
