// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export interface EventScheduleIdentity {
  start_at: string | null;
  end_at: string | null;
  timezone: string;
  all_day: boolean;
}

export interface FormattedEventSchedule {
  allDay: boolean;
  dateLabel: string | null;
  startDateLabel: string | null;
  endDateLabel: string | null;
  timeLabel: string | null;
  monthLabel: string | null;
  dayLabel: string | null;
  weekdayLabel: string | null;
}

function validDate(value: string | null): Date | null {
  if (!value) return null;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

function safeTimeZone(timeZone: string): string {
  try {
    new Intl.DateTimeFormat('en', { timeZone }).format(0);
    return timeZone;
  } catch {
    return 'UTC';
  }
}

export function formatEventSchedule(
  schedule: EventScheduleIdentity,
  locale: string,
): FormattedEventSchedule {
  const start = validDate(schedule.start_at);
  const end = validDate(schedule.end_at);
  const timeZone = safeTimeZone(schedule.timezone);
  if (!start) {
    return {
      allDay: schedule.all_day,
      dateLabel: null,
      startDateLabel: null,
      endDateLabel: null,
      timeLabel: null,
      monthLabel: null,
      dayLabel: null,
      weekdayLabel: null,
    };
  }

  const dateFormatter = new Intl.DateTimeFormat(locale, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    timeZone,
  });
  const startDateLabel = dateFormatter.format(start);
  const calendarParts = new Intl.DateTimeFormat(locale, {
    month: 'short',
    day: 'numeric',
    weekday: 'short',
    timeZone,
  }).formatToParts(start);
  const part = (type: 'month' | 'day' | 'weekday'): string | null => (
    calendarParts.find((item) => item.type === type)?.value ?? null
  );

  if (schedule.all_day) {
    // All-day end_at is exclusive. One millisecond before the boundary lands
    // on the final local calendar day even when a DST transition changes its
    // UTC offset, unlike subtracting a fixed 24 hours.
    const inclusiveEnd = end && end.getTime() > start.getTime()
      ? new Date(end.getTime() - 1)
      : null;
    const endDateLabel = inclusiveEnd ? dateFormatter.format(inclusiveEnd) : null;
    const visibleEnd = endDateLabel && endDateLabel !== startDateLabel ? endDateLabel : null;

    return {
      allDay: true,
      dateLabel: visibleEnd ? `${startDateLabel} – ${visibleEnd}` : startDateLabel,
      startDateLabel,
      endDateLabel: visibleEnd,
      timeLabel: null,
      monthLabel: part('month'),
      dayLabel: part('day'),
      weekdayLabel: part('weekday'),
    };
  }

  const timeLabel = new Intl.DateTimeFormat(locale, {
    hour: '2-digit',
    minute: '2-digit',
    timeZoneName: 'short',
    timeZone,
  }).format(start);

  return {
    allDay: false,
    dateLabel: startDateLabel,
    startDateLabel,
    endDateLabel: end ? dateFormatter.format(end) : null,
    timeLabel,
    monthLabel: part('month'),
    dayLabel: part('day'),
    weekdayLabel: part('weekday'),
  };
}
