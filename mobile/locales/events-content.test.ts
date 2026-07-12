// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

const english = require('./en/events.json') as Record<string, unknown>;
const locales = {
  de: require('./de/events.json'),
  es: require('./es/events.json'),
  fr: require('./fr/events.json'),
  ga: require('./ga/events.json'),
  it: require('./it/events.json'),
  pt: require('./pt/events.json'),
} as const;

const previouslyCopiedPaths = [
  'detail.organizerAttendance',
  'detail.loadingAttendees',
  'detail.attendeesLoadError',
  'detail.noAttendees',
  'detail.moreAttendees',
  'detail.waitlistCount',
  'detail.onWaitlistPosition',
  'detail.checkInProgress',
  'detail.attendanceLoadError',
  'detail.noCheckInAttendeesTitle',
  'detail.noCheckInAttendees',
  'detail.attendeeGoing',
  'detail.attendeeInterested',
  'detail.attendeeCheckedIn',
  'detail.checkIn',
  'detail.checkingIn',
  'detail.checkInError',
  'detail.checkInAttendeeLabel',
  'detail.joinWaitlist',
  'detail.leaveWaitlist',
  'detail.joinWaitlistError',
  'detail.leaveWaitlistError',
  'detail.eventPolls',
  'detail.loadingPolls',
  'detail.pollsLoadError',
  'detail.pollVoteError',
  'detail.pollOptionFallback',
  'detail.communityMember',
  'reminders.title',
  'reminders.subtitle',
  'reminders.loadError',
  'reminders.saved',
  'reminders.error',
  'reminders.option.60',
  'reminders.option.1440',
  'reminders.option.10080',
] as const;

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

describe('mobile Events locale content', () => {
  const englishFlat = flatten(english);
  const attendancePaths = [...englishFlat.keys()].filter((path) => path === 'allDay' || path.startsWith('attendance.'));
  const guardedPaths = [...previouslyCopiedPaths, ...attendancePaths];
  const agendaPaths = [...englishFlat.keys()].filter((path) => path.startsWith('agenda.'));
  const translatedAgendaCopy = [
    'agenda.title',
    'agenda.loading',
    'agenda.loadError',
    'agenda.retry',
    'agenda.starts',
    'agenda.ends',
    'agenda.track',
    'agenda.room',
    'agenda.speakers',
    'agenda.speakerFallback',
    'agenda.sessionAccessibility',
    'agenda.type.session',
    'agenda.type.keynote',
    'agenda.visibility.public',
    'agenda.visibility.registered',
    'agenda.visibility.staff',
    'agenda.status.cancelled',
  ] as const;

  it.each(Object.entries(locales))('%s has genuine event operations translations', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    for (const path of guardedPaths) {
      const source = englishFlat.get(path);
      const value = translated.get(path);
      expect(source).toBeDefined();
      expect(value).toBeDefined();
      expect(value).not.toBe(source);
      expect(placeholders(value ?? '')).toEqual(placeholders(source ?? ''));
    }
  });

  it.each(Object.entries(locales))('%s preserves the complete agenda key and placeholder contract', (_locale, resource) => {
    const translated = flatten(resource as Record<string, unknown>);
    for (const path of agendaPaths) {
      const source = englishFlat.get(path);
      const value = translated.get(path);
      expect(source).toBeDefined();
      expect(value).toBeDefined();
      expect(placeholders(value ?? '')).toEqual(placeholders(source ?? ''));
    }
    for (const path of translatedAgendaCopy) {
      expect(translated.get(path)).not.toBe(englishFlat.get(path));
    }
  });
});
