// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { formatEventSchedule } from './eventDateTime';

describe('formatEventSchedule', () => {
  it('formats timed events in the event IANA timezone', () => {
    const result = formatEventSchedule({
      start_at: '2026-08-09T23:30:00Z',
      end_at: '2026-08-10T00:30:00Z',
      timezone: 'Pacific/Auckland',
      all_day: false,
    }, 'en');

    expect(result.dayLabel).toBe('10');
    expect(result.dateLabel).toContain('August 10, 2026');
    expect(result.timeLabel).toContain('11:30');
  });

  it('uses the exclusive all-day end boundary across a DST offset change', () => {
    const result = formatEventSchedule({
      start_at: '2026-10-31T04:00:00Z',
      end_at: '2026-11-02T05:00:00Z',
      timezone: 'America/New_York',
      all_day: true,
    }, 'en');

    expect(result.startDateLabel).toContain('October 31, 2026');
    expect(result.endDateLabel).toContain('November 1, 2026');
    expect(result.dateLabel).not.toContain('November 2, 2026');
    expect(result.timeLabel).toBeNull();
  });
});
