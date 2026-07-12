// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const english = require('./en/event_templates.json') as Record<string, unknown>;
const locales = {
  de: require('./de/event_templates.json'),
  es: require('./es/event_templates.json'),
  fr: require('./fr/event_templates.json'),
  ga: require('./ga/event_templates.json'),
  it: require('./it/event_templates.json'),
  pt: require('./pt/event_templates.json'),
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
  return [...value.matchAll(/\{\{([^}]+)\}\}/g)]
    .map((match) => match[1])
    .sort();
}

describe('mobile event-template locale content', () => {
  const englishFlat = flatten(english);
  const genuinelyTranslated = [
    'templates.mobile.title',
    'templates.mobile.safetyTitle',
    'templates.mobile.safetyDescription',
    'templates.mobile.scheduleTitle',
    'templates.mobile.reviewTitle',
    'templates.mobile.auditTitle',
    'templates.mobile.auditImmutableTitle',
    'templates.mobile.createDraft',
    'templates.checks.event_template_check_private_records_skipped',
  ] as const;

  it.each(Object.entries(locales))('%s preserves the complete key and placeholder contract', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    expect([...translated.keys()].sort()).toEqual([...englishFlat.keys()].sort());

    for (const [path, source] of englishFlat) {
      expect(placeholders(translated.get(path) ?? '')).toEqual(placeholders(source));
    }
  });

  it.each(Object.entries(locales))('%s contains genuine template workflow translations', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    for (const path of genuinelyTranslated) {
      expect(translated.get(path)).toBeDefined();
      expect(translated.get(path)).not.toBe(englishFlat.get(path));
    }
  });
});
