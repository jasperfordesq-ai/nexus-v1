// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const english = require('./en/event_tickets.json') as Record<string, unknown>;
const locales = {
  de: require('./de/event_tickets.json'),
  es: require('./es/event_tickets.json'),
  fr: require('./fr/event_tickets.json'),
  ga: require('./ga/event_tickets.json'),
  it: require('./it/event_tickets.json'),
  pt: require('./pt/event_tickets.json'),
} as const;

function flatten(value: Record<string, unknown>, prefix = ''): Map<string, string> {
  const result = new Map<string, string>();
  for (const [key, item] of Object.entries(value)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (item && typeof item === 'object' && !Array.isArray(item)) {
      for (const [nestedPath, nestedValue] of flatten(item as Record<string, unknown>, path)) {
        result.set(nestedPath, nestedValue);
      }
    } else if (typeof item === 'string') {
      result.set(path, item);
    }
  }
  return result;
}

function placeholders(value: string): string[] {
  return [...value.matchAll(/\{\{([^}]+)\}\}/g)].map((match) => match[1]).sort();
}

describe('mobile event-ticket locale content', () => {
  const englishFlat = flatten(english);
  const translatedCore = [
    'tickets.mobile.gatewayDisabledDescription',
    'tickets.mobile.cancelDescription',
    'tickets.mobile.catalogueTitle',
    'tickets.mobile.timeCreditDisabledDescription',
    'tickets.mobile.claimFreeTicket',
  ] as const;

  it.each(Object.entries(locales))('%s preserves every ticket key and placeholder', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    expect([...translated.keys()].sort()).toEqual([...englishFlat.keys()].sort());
    for (const [path, source] of englishFlat) {
      expect(placeholders(translated.get(path) ?? '')).toEqual(placeholders(source));
    }
  });

  it.each(Object.entries(locales))('%s contains genuine free-only ticket translations', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    for (const path of translatedCore) {
      expect(translated.get(path)).not.toBe(englishFlat.get(path));
    }
  });
});
