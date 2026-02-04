/**
 * Tests for helper utilities
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  formatRelativeTime,
  formatDate,
  formatTime,
  formatHours,
  truncate,
  cn,
  getUserDisplayName,
  getUserInitials,
} from './helpers';

describe('formatRelativeTime', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(new Date('2026-02-04T12:00:00Z'));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('returns "just now" for times less than a minute ago', () => {
    const date = new Date('2026-02-04T11:59:30Z').toISOString();
    expect(formatRelativeTime(date)).toBe('just now');
  });

  it('returns minutes ago for times less than an hour ago', () => {
    const date = new Date('2026-02-04T11:30:00Z').toISOString();
    expect(formatRelativeTime(date)).toBe('30m ago');
  });

  it('returns hours ago for times less than a day ago', () => {
    const date = new Date('2026-02-04T06:00:00Z').toISOString();
    expect(formatRelativeTime(date)).toBe('6h ago');
  });

  it('returns days ago for times less than a week ago', () => {
    const date = new Date('2026-02-01T12:00:00Z').toISOString();
    expect(formatRelativeTime(date)).toBe('3d ago');
  });

  it('returns weeks ago for times less than a month ago', () => {
    const date = new Date('2026-01-21T12:00:00Z').toISOString();
    expect(formatRelativeTime(date)).toBe('2w ago');
  });
});

describe('formatDate', () => {
  it('formats date with default options', () => {
    const result = formatDate('2026-02-04T12:00:00Z');
    expect(result).toContain('2026');
    expect(result).toContain('February');
  });

  it('formats date with custom options', () => {
    const result = formatDate('2026-02-04T12:00:00Z', {
      month: 'short',
      day: 'numeric',
    });
    expect(result).toContain('Feb');
    expect(result).toContain('4');
  });
});

describe('formatTime', () => {
  it('formats time correctly', () => {
    const result = formatTime('2026-02-04T14:30:00Z');
    // Time will vary based on timezone, just check it's a valid time format
    expect(result).toMatch(/\d{1,2}:\d{2}/);
  });
});

describe('formatHours', () => {
  it('returns singular for 1 hour', () => {
    expect(formatHours(1)).toBe('1 hour');
  });

  it('returns plural for multiple hours', () => {
    expect(formatHours(2)).toBe('2 hours');
    expect(formatHours(5)).toBe('5 hours');
  });
});

describe('truncate', () => {
  it('returns original string if shorter than max length', () => {
    expect(truncate('hello', 10)).toBe('hello');
  });

  it('truncates string and adds ellipsis', () => {
    expect(truncate('hello world', 5)).toBe('hello...');
  });

  it('handles exact length', () => {
    expect(truncate('hello', 5)).toBe('hello');
  });
});

describe('cn', () => {
  it('joins class names', () => {
    expect(cn('foo', 'bar')).toBe('foo bar');
  });

  it('filters out falsy values', () => {
    expect(cn('foo', false, null, undefined, 'bar')).toBe('foo bar');
  });

  it('handles empty input', () => {
    expect(cn()).toBe('');
  });
});

describe('getUserDisplayName', () => {
  it('returns full name', () => {
    expect(getUserDisplayName({ first_name: 'John', last_name: 'Doe' })).toBe('John Doe');
  });

  it('trims whitespace', () => {
    expect(getUserDisplayName({ first_name: 'John', last_name: '' })).toBe('John');
  });
});

describe('getUserInitials', () => {
  it('returns initials', () => {
    expect(getUserInitials({ first_name: 'John', last_name: 'Doe' })).toBe('JD');
  });

  it('handles missing names', () => {
    expect(getUserInitials({ first_name: '', last_name: '' })).toBe('');
  });

  it('handles undefined names', () => {
    expect(getUserInitials({ first_name: undefined, last_name: undefined } as any)).toBe('');
  });
});
