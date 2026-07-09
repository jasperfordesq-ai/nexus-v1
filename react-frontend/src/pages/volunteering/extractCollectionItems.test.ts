// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { extractCollectionItems } from './extractCollectionItems';

describe('extractCollectionItems', () => {
  it('returns a bare array unchanged', () => {
    expect(extractCollectionItems<number>([1, 2, 3])).toEqual([1, 2, 3]);
  });

  it('unwraps an { items } envelope', () => {
    expect(extractCollectionItems<number>({ items: [4, 5] })).toEqual([4, 5]);
  });

  it('unwraps a nested { data: { items } } envelope', () => {
    expect(extractCollectionItems<number>({ data: { items: [6] } })).toEqual([6]);
  });

  it('returns [] for null / undefined / non-collection shapes', () => {
    expect(extractCollectionItems(null)).toEqual([]);
    expect(extractCollectionItems(undefined)).toEqual([]);
    expect(extractCollectionItems({ foo: 'bar' })).toEqual([]);
    expect(extractCollectionItems(42)).toEqual([]);
  });
});
