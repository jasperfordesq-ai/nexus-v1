// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
  getGrapesJsCoreMessages,
  getMjmlMessages,
  type GrapesJsMessages,
} from './grapesJsLocale';

function leafEntries(value: GrapesJsMessages, prefix = ''): Array<[string, unknown]> {
  return Object.entries(value).flatMap(([key, child]) => {
    const path = prefix ? `${prefix}.${key}` : key;
    if (child && typeof child === 'object' && !Array.isArray(child)) {
      return leafEntries(child as GrapesJsMessages, path);
    }
    return [[path, child]];
  });
}

function localeTranslator(locale: string, namespace: 'admin_editor' | 'admin_newsletters') {
  const file = resolve(process.cwd(), 'public', 'locales', locale, `${namespace}.json`);
  const resource = JSON.parse(readFileSync(file, 'utf8')) as GrapesJsMessages;

  return (key: string): string => {
    const value = key.split('.').reduce<unknown>((node, segment) => {
      if (!node || typeof node !== 'object' || Array.isArray(node)) return undefined;
      return (node as GrapesJsMessages)[segment];
    }, resource);
    return typeof value === 'string' ? value : `MISSING:${key}`;
  };
}

function expectCompleteDictionary(actual: GrapesJsMessages, reference: GrapesJsMessages) {
  const actualEntries = new Map(leafEntries(actual));
  const missingEntries = leafEntries(reference).filter(([path]) => {
    const value = actualEntries.get(path);
    return value === undefined || String(value).startsWith('MISSING:');
  });
  expect(missingEntries).toEqual([]);
}

describe('project-owned GrapesJS locales', () => {
  it.each(['ar', 'de', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt'])(
    'covers every core message in %s',
    (locale) => {
      expectCompleteDictionary(
        getGrapesJsCoreMessages(locale, localeTranslator(locale, 'admin_editor')),
        getGrapesJsCoreMessages('en'),
      );
    },
  );

  it.each(['ar', 'de', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt'])(
    'covers every MJML message in %s',
    (locale) => {
      expectCompleteDictionary(
        getMjmlMessages(locale, localeTranslator(locale, 'admin_newsletters')),
        getMjmlMessages('en'),
      );
    },
  );
});
