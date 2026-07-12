// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const english = require('./en/event_recurrence_blueprints.json') as Record<string, unknown>;
const translations: Record<string, Record<string, unknown>> = {
  de: require('./de/event_recurrence_blueprints.json'),
  es: require('./es/event_recurrence_blueprints.json'),
  fr: require('./fr/event_recurrence_blueprints.json'),
  ga: require('./ga/event_recurrence_blueprints.json'),
  it: require('./it/event_recurrence_blueprints.json'),
  pt: require('./pt/event_recurrence_blueprints.json'),
};

function leaves(value: unknown, prefix = ''): Record<string, string> {
  if (typeof value === 'string') return { [prefix]: value };
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {};
  return Object.entries(value).reduce<Record<string, string>>((result, [key, child]) => ({
    ...result,
    ...leaves(child, prefix ? `${prefix}.${key}` : key),
  }), {});
}

function tokens(value: string): string[] {
  return [...value.matchAll(/{{\s*([A-Za-z0-9_]+)\s*}}/g)]
    .map((match) => match[1]!)
    .sort();
}

describe('mobile recurrence definition blueprint translations', () => {
  const englishLeaves = leaves(english);

  it.each(Object.entries(translations))('%s keeps every key and interpolation token', (locale, translation) => {
    const localized = leaves(translation);
    expect(Object.keys(localized).sort()).toEqual(Object.keys(englishLeaves).sort());
    for (const [key, value] of Object.entries(localized)) {
      expect(tokens(value)).toEqual(tokens(englishLeaves[key]!));
    }
    expect(localized.title).not.toBe(englishLeaves.title);
    expect(locale).toBeTruthy();
  });
});
