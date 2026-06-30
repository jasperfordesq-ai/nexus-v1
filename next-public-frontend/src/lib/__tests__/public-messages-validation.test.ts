// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';

import * as i18n from '../i18n';
import routeOwnershipManifest from '../../../route-ownership.json';
import { createTranslator } from '../i18n';

const SUPPORTED_PUBLIC_LOCALES = ['en', 'ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'] as const;

describe('public message validation', () => {
  it('resolves every Next-owned public route label key', () => {
    const t = createTranslator('en');

    for (const route of routeOwnershipManifest.nextPublicRoutes) {
      expect(t(route.labelKey), route.routeKey).not.toBe(route.labelKey);
    }
  });

  it('keeps public message keys in parity across every supported locale', () => {
    expect((i18n as { publicMessageLocales?: readonly string[] }).publicMessageLocales).toEqual(
      SUPPORTED_PUBLIC_LOCALES,
    );

    const canonicalKeys = flattenKeys(readPublicMessages('en'));

    for (const locale of SUPPORTED_PUBLIC_LOCALES) {
      expect(flattenKeys(readPublicMessages(locale)), locale).toEqual(canonicalKeys);
    }
  });

  it('resolves every Next-owned public route label key in every supported locale', () => {
    for (const locale of SUPPORTED_PUBLIC_LOCALES) {
      const t = createTranslator(locale);

      for (const route of routeOwnershipManifest.nextPublicRoutes) {
        expect(t(route.labelKey), `${locale}:${route.routeKey}`).not.toBe(route.labelKey);
      }
    }
  });

  it('resolves listings chrome through the Irish locale path', () => {
    const t = createTranslator('ga');

    expect(t('pages.listings.title')).toBe('Liostuithe');
    expect(t('listings.providerLabel')).toBe('Soláthraí');
    expect(t('listings.valueHours', { count: 2 })).toBe('2 uair');
  });
});

function readPublicMessages(locale: string): Record<string, unknown> {
  const filePath = join(process.cwd(), 'messages', locale, 'public.json');

  return JSON.parse(readFileSync(filePath, 'utf8')) as Record<string, unknown>;
}

function flattenKeys(messages: Record<string, unknown>, prefix = ''): string[] {
  return Object.entries(messages)
    .flatMap(([key, value]) => {
      const path = prefix ? `${prefix}.${key}` : key;

      if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        return flattenKeys(value as Record<string, unknown>, path);
      }

      return [path];
    })
    .sort();
}
