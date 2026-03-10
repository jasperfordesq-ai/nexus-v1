// Jest setup

// Initialise i18next synchronously with English translations so that
// useTranslation() / t() returns real strings in all tests.
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import enCommon from './locales/en/common.json';
import enAuth from './locales/en/auth.json';
import enMessages from './locales/en/messages.json';
import enHome from './locales/en/home.json';
import enMembers from './locales/en/members.json';

i18n.use(initReactI18next).init({
  // Suppress the i18next "made possible by Locize" promo log in tests
  debug: false,
  resources: {
    en: { common: enCommon, auth: enAuth, messages: enMessages, home: enHome, members: enMembers },
  },
  lng: 'en',
  fallbackLng: 'en',
  defaultNS: 'common',
  ns: ['common', 'auth', 'messages', 'home', 'members'],
  interpolation: { escapeValue: false },
  compatibilityJSON: 'v4',
  initAsync: false,
});

// Mock expo-router
jest.mock('expo-router', () => ({
    useRouter: () => ({
        push: jest.fn(),
        replace: jest.fn(),
        back: jest.fn(),
    }),
    useSegments: () => ['(tabs)'],
    Link: 'Link',
    router: {
        push: jest.fn(),
        replace: jest.fn(),
        back: jest.fn(),
    }
}));

// Mock secure store
jest.mock('expo-secure-store', () => ({
    getItemAsync: jest.fn(),
    setItemAsync: jest.fn(),
    deleteItemAsync: jest.fn(),
}));
