// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import arPublicMessages from '../../messages/ar/public.json';
import dePublicMessages from '../../messages/de/public.json';
import enPublicMessages from '../../messages/en/public.json';
import esPublicMessages from '../../messages/es/public.json';
import frPublicMessages from '../../messages/fr/public.json';
import gaPublicMessages from '../../messages/ga/public.json';
import itPublicMessages from '../../messages/it/public.json';
import jaPublicMessages from '../../messages/ja/public.json';
import nlPublicMessages from '../../messages/nl/public.json';
import plPublicMessages from '../../messages/pl/public.json';
import ptPublicMessages from '../../messages/pt/public.json';

interface MessageTree {
  [key: string]: MessageTree | string;
}
type Replacements = Record<string, number | string>;
export type Translator = (key: string, replacements?: Replacements) => string;

export const publicMessageLocales = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'] as const;
export type PublicMessageLocale = (typeof publicMessageLocales)[number];

const messagesByLocale: Record<string, MessageTree> = {
  ar: arPublicMessages,
  de: dePublicMessages,
  en: enPublicMessages,
  es: esPublicMessages,
  fr: frPublicMessages,
  ga: gaPublicMessages,
  it: itPublicMessages,
  ja: jaPublicMessages,
  nl: nlPublicMessages,
  pl: plPublicMessages,
  pt: ptPublicMessages,
};

export function createTranslator(locale: string | undefined): Translator {
  const messages = messagesByLocale[normalizeLocale(locale)] ?? messagesByLocale.en;

  return (key: string, replacements: Replacements = {}) => {
    const message = lookupMessage(messages, key) ?? lookupMessage(messagesByLocale.en, key) ?? key;

    return Object.entries(replacements).reduce(
      (current, [replacementKey, replacementValue]) =>
        current.replaceAll(`{{${replacementKey}}}`, String(replacementValue)),
      message,
    );
  };
}

function lookupMessage(messages: MessageTree, key: string): string | undefined {
  const value = key.split('.').reduce<string | MessageTree | undefined>((current, part) => {
    if (typeof current !== 'object' || current === null) {
      return undefined;
    }

    return current[part];
  }, messages);

  return typeof value === 'string' ? value : undefined;
}

function normalizeLocale(locale: string | undefined): string {
  return (locale ?? 'en').split('-')[0]?.toLowerCase() || 'en';
}
