// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { eventIsoToLocalInput, eventLocalInputToIso } from './eventLocalDateTime';

describe('event-local date/time conversion', () => {
  it('renders an instant in the Event IANA timezone', () => {
    expect(eventIsoToLocalInput('2030-06-01T09:30:00Z', 'Europe/Dublin'))
      .toBe('2030-06-01T10:30');
  });

  it('round-trips valid wall-clock input to a UTC instant', () => {
    expect(eventLocalInputToIso('2030-06-01T10:30', 'Europe/Dublin'))
      .toBe('2030-06-01T09:30:00.000Z');
  });

  it('rejects a DST gap instead of silently moving the requested time', () => {
    expect(eventLocalInputToIso('2027-03-14T02:30', 'America/New_York')).toBeNull();
  });

  it('rejects a duplicated fall-back wall clock instead of choosing an arbitrary instant', () => {
    expect(eventLocalInputToIso('2027-11-07T01:30', 'America/New_York')).toBeNull();
  });

  it('rejects malformed browser input and invalid IANA zones', () => {
    expect(eventLocalInputToIso('2030-06-01 10:30', 'Europe/Dublin')).toBeNull();
    expect(eventIsoToLocalInput('not-a-date', 'UTC')).toBe('');
    expect(eventLocalInputToIso('2030-06-01T10:30', 'Not/A_Zone')).toBeNull();
    expect(eventIsoToLocalInput('2030-06-01T09:30:00Z', 'Not/A_Zone')).toBe('');
  });
});
