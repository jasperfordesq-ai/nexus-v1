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

// `common` namespace — synced from the React app's locale source of truth (one
// source of truth) into src/_shared-messages by scripts/sync-shared.mjs on
// predev/prebuild/pretest. The shared public chrome (Navbar/Footer) renders
// t('common') keys (footer.*, nav.*, legal.*, release_status.*, aria.*).
import arCommonMessages from '../_shared-messages/ar/common.json';
import deCommonMessages from '../_shared-messages/de/common.json';
import enCommonMessages from '../_shared-messages/en/common.json';
import esCommonMessages from '../_shared-messages/es/common.json';
import frCommonMessages from '../_shared-messages/fr/common.json';
import gaCommonMessages from '../_shared-messages/ga/common.json';
import itCommonMessages from '../_shared-messages/it/common.json';
import jaCommonMessages from '../_shared-messages/ja/common.json';
import nlCommonMessages from '../_shared-messages/nl/common.json';
import plCommonMessages from '../_shared-messages/pl/common.json';
import ptCommonMessages from '../_shared-messages/pt/common.json';

interface MessageTree {
  [key: string]: MessageTree | string;
}
type Replacements = Record<string, number | string>;
export type Translator = (key: string, replacements?: Replacements) => string;

export const publicMessageLocales = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'] as const;
export type PublicMessageLocale = (typeof publicMessageLocales)[number];

/** Deep-merge two message trees; `over` wins on leaf collisions (only `footer` collides). */
function deepMerge(base: MessageTree, over: MessageTree): MessageTree {
  const out: MessageTree = { ...base };
  for (const [key, value] of Object.entries(over)) {
    const existing = out[key];
    if (
      typeof existing === 'object' && existing !== null
      && typeof value === 'object' && value !== null
    ) {
      out[key] = deepMerge(existing, value);
    } else {
      out[key] = value;
    }
  }
  return out;
}

const messagesByLocale: Record<string, MessageTree> = {
  ar: deepMerge(arCommonMessages as MessageTree, arPublicMessages),
  de: deepMerge(deCommonMessages as MessageTree, dePublicMessages),
  en: deepMerge(enCommonMessages as MessageTree, enPublicMessages),
  es: deepMerge(esCommonMessages as MessageTree, esPublicMessages),
  fr: deepMerge(frCommonMessages as MessageTree, frPublicMessages),
  ga: deepMerge(gaCommonMessages as MessageTree, gaPublicMessages),
  it: deepMerge(itCommonMessages as MessageTree, itPublicMessages),
  ja: deepMerge(jaCommonMessages as MessageTree, jaPublicMessages),
  nl: deepMerge(nlCommonMessages as MessageTree, nlPublicMessages),
  pl: deepMerge(plCommonMessages as MessageTree, plPublicMessages),
  pt: deepMerge(ptCommonMessages as MessageTree, ptPublicMessages),
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
