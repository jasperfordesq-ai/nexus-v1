// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Verify that every translated React resource preserves the interpolation
 * variables declared by the English source. Locale-specific CLDR plural forms
 * are compared with their English plural family when no exact English variant
 * exists. JSON parsing is part of the check, so malformed resources fail here.
 */

import { readdir, readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const frontendRoot = resolve(import.meta.dirname, '..');
const localesRoot = resolve(frontendRoot, 'public/locales');
const pluralCategories = ['zero', 'one', 'two', 'few', 'many', 'other'];

function flattenStrings(object, prefix = '', result = new Map()) {
  for (const [key, value] of Object.entries(object)) {
    const dottedKey = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      flattenStrings(value, dottedKey, result);
    } else if (typeof value === 'string') {
      result.set(dottedKey, value);
    }
  }
  return result;
}

const i18nextInterpolationPattern = /\{\{\s*([\w.-]+)(?:,[^}]*)?\s*\}\}/gu;

// Laravel placeholders start at a token boundary and use an ASCII identifier.
// Requiring the colon not to follow a letter, number, underscore, or another
// colon excludes URL schemes (https://, mailto:name), compact prose labels
// (Status:active), and scope-resolution syntax. Requiring an identifier as the
// first character excludes clock times such as 09:30.
const laravelInterpolationPattern = /(?<![\p{L}\p{N}_:]):([A-Za-z_][A-Za-z0-9_]*)(?![A-Za-z0-9_])/gu;

export function interpolationTokens(value) {
  const i18nextTokens = [...value.matchAll(i18nextInterpolationPattern)]
    .map((match) => `{{${match[1]}}}`);
  const laravelTokens = [...value.matchAll(laravelInterpolationPattern)]
    .map((match) => `:${match[1]}`);

  return [...i18nextTokens, ...laravelTokens].sort();
}

export function interpolationSignature(value) {
  return interpolationTokens(value).join('|');
}

function describePluralKey(key) {
  const ordinalMatch = key.match(/^(.*)_ordinal_(zero|one|two|few|many|other)$/u);
  if (ordinalMatch) return { base: ordinalMatch[1], ordinal: true };
  const cardinalMatch = key.match(/^(.*)_(zero|one|two|few|many|other)$/u);
  return cardinalMatch ? { base: cardinalMatch[1], ordinal: false } : null;
}

function familySignatures(strings, descriptor) {
  const signatures = new Set();
  const baseValue = strings.get(descriptor.base);
  if (baseValue !== undefined) signatures.add(interpolationSignature(baseValue));
  const prefix = descriptor.ordinal ? `${descriptor.base}_ordinal_` : `${descriptor.base}_`;
  for (const category of pluralCategories) {
    const value = strings.get(`${prefix}${category}`);
    if (value !== undefined) signatures.add(interpolationSignature(value));
  }
  return signatures;
}

async function checkInterpolationParity() {
  const locales = (await readdir(localesRoot, { withFileTypes: true }))
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .sort();
  const namespaces = (await readdir(resolve(localesRoot, 'en')))
    .filter((file) => file.endsWith('.json'))
    .sort();

  const resources = new Map();
  let filesParsed = 0;
  for (const locale of locales) {
    const localeResources = new Map();
    for (const namespaceFile of namespaces) {
      const path = resolve(localesRoot, locale, namespaceFile);
      const raw = await readFile(path, 'utf8');
      const parsed = JSON.parse(raw);
      localeResources.set(namespaceFile.slice(0, -'.json'.length), flattenStrings(parsed));
      filesParsed += 1;
    }
    resources.set(locale, localeResources);
  }

  const mismatches = [];
  let exactValuesChecked = 0;
  let localePluralValuesChecked = 0;
  const englishResources = resources.get('en');

  for (const locale of locales.filter((value) => value !== 'en')) {
    for (const [namespace, englishStrings] of englishResources) {
      const targetStrings = resources.get(locale).get(namespace);
      for (const [key, targetValue] of targetStrings) {
        const targetSignature = interpolationSignature(targetValue);
        const englishValue = englishStrings.get(key);
        if (englishValue !== undefined) {
          exactValuesChecked += 1;
          const englishSignature = interpolationSignature(englishValue);
          if (targetSignature !== englishSignature) {
            mismatches.push({ locale, namespace, key, expected: englishSignature, actual: targetSignature });
          }
          continue;
        }

        const descriptor = describePluralKey(key);
        if (!descriptor) continue;
        const expectedSignatures = familySignatures(englishStrings, descriptor);
        if (expectedSignatures.size === 0) continue;
        localePluralValuesChecked += 1;
        if (!expectedSignatures.has(targetSignature)) {
          mismatches.push({
            locale,
            namespace,
            key,
            expected: [...expectedSignatures].sort().join(' OR '),
            actual: targetSignature,
          });
        }
      }
    }
  }

  console.log(`Parsed ${filesParsed} locale JSON files.`);
  console.log(`Checked ${exactValuesChecked} exact translated values and ${localePluralValuesChecked} locale-specific plural values.`);
  if (mismatches.length > 0) {
    console.error(`Found ${mismatches.length} interpolation mismatch(es):`);
    for (const mismatch of mismatches) {
      console.error(
        `  ${mismatch.locale}/${mismatch.namespace}:${mismatch.key} expected [${mismatch.expected || '(none)'}] but found [${mismatch.actual || '(none)'}]`,
      );
    }
    process.exitCode = 1;
    return;
  }
  console.log('Interpolation parity passed.');
}

const invokedPath = process.argv[1] ? resolve(process.argv[1]) : null;
if (invokedPath === fileURLToPath(import.meta.url)) {
  await checkInterpolationParity();
}
