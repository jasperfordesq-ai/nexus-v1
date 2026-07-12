// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const english = require('./en/events.json').lifecycleHistory as Record<string, unknown>;
const locales = {
  de: require('./de/events.json').lifecycleHistory,
  es: require('./es/events.json').lifecycleHistory,
  fr: require('./fr/events.json').lifecycleHistory,
  ga: require('./ga/events.json').lifecycleHistory,
  it: require('./it/events.json').lifecycleHistory,
  pt: require('./pt/events.json').lifecycleHistory,
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

describe('mobile event lifecycle-history locale content', () => {
  const englishFlat = flatten(english);
  const genuine = ['title', 'description', 'open', 'loadMore', 'immutable'] as const;

  it.each(Object.entries(locales))('%s preserves complete keys and placeholders', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    expect([...translated.keys()].sort()).toEqual([...englishFlat.keys()].sort());
    for (const [path, source] of englishFlat) {
      expect(placeholders(translated.get(path) ?? '')).toEqual(placeholders(source));
    }
  });

  it.each(Object.entries(locales))('%s contains genuine lifecycle translations', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    for (const path of genuine) {
      expect(translated.get(path)).toBeDefined();
      expect(translated.get(path)).not.toBe(englishFlat.get(path));
    }
  });
});
