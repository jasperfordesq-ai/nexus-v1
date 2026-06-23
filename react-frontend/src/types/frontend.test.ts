// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { transformUser, transformListing } from './frontend';
import type { User as ApiUser, Listing as ApiListing } from './api';

// Minimal valid API objects; transform functions only read a few fields, the
// rest are spread through untouched, so casts keep the fixtures small.
const makeUser = (overrides: Partial<ApiUser> = {}): ApiUser =>
  ({ id: 1, email: 'a@b.com', ...overrides }) as ApiUser;

const makeListing = (overrides: Partial<ApiListing> = {}): ApiListing =>
  ({ id: 5, title: 'Test listing', ...overrides }) as ApiListing;

describe('transformUser', () => {
  it('uses the existing name when present', () => {
    const result = transformUser(makeUser({ name: 'Alice Smith' }));
    expect(result.name).toBe('Alice Smith');
  });

  it('composes name from first_name + last_name when name is absent', () => {
    const result = transformUser(makeUser({ first_name: 'Bob', last_name: 'Jones' }));
    expect(result.name).toBe('Bob Jones');
  });

  it('uses only first_name when last_name is missing', () => {
    const result = transformUser(makeUser({ first_name: 'Carol' }));
    expect(result.name).toBe('Carol');
  });

  it('uses only last_name when first_name is missing', () => {
    const result = transformUser(makeUser({ last_name: 'Doe' }));
    expect(result.name).toBe('Doe');
  });

  it('falls back to "Unknown" when no name parts exist', () => {
    const result = transformUser(makeUser());
    expect(result.name).toBe('Unknown');
  });

  it('falls back to "Unknown" when name is an empty string and no name parts', () => {
    const result = transformUser(makeUser({ name: '' }));
    expect(result.name).toBe('Unknown');
  });

  it('preserves all other user fields', () => {
    const user = makeUser({ id: 42, email: 'x@y.com', name: 'Z' });
    const result = transformUser(user);
    expect(result.id).toBe(42);
    expect(result.email).toBe('x@y.com');
  });

  it('returns a new object (does not mutate the input)', () => {
    const user = makeUser({ first_name: 'A', last_name: 'B' });
    const result = transformUser(user);
    expect(result).not.toBe(user);
    expect('name' in user).toBe(false);
  });
});

describe('transformListing', () => {
  it('aliases estimated_hours to hours_estimate', () => {
    const result = transformListing(makeListing({ estimated_hours: 3 } as Partial<ApiListing>));
    expect(result.hours_estimate).toBe(3);
  });

  it('sets hours_estimate to undefined when estimated_hours is absent', () => {
    const result = transformListing(makeListing());
    expect(result.hours_estimate).toBeUndefined();
  });

  it('preserves the original listing fields', () => {
    const listing = makeListing({ id: 9, title: 'Garden help' });
    const result = transformListing(listing);
    expect(result.id).toBe(9);
    expect(result.title).toBe('Garden help');
  });

  it('returns a new object (does not mutate the input)', () => {
    const listing = makeListing({ estimated_hours: 2 } as Partial<ApiListing>);
    const result = transformListing(listing);
    expect(result).not.toBe(listing);
  });
});
