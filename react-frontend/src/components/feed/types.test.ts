// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for feed types and getAuthor helper
 */

import { describe, it, expect } from 'vitest';
import { getAuthor, getItemDetailPath, getItemDetailLabel } from './types';
import type { FeedItem } from './types';

describe('getAuthor', () => {
  it('extracts author from flat fields', () => {
    const item: FeedItem = {
      id: 1,
      content: 'test',
      author_id: 10,
      author_name: 'Jane',
      author_avatar: '/jane.png',
      created_at: '2026-01-01',
      type: 'post',
      likes_count: 0,
      comments_count: 0,
      is_liked: false,
    };
    const author = getAuthor(item);
    expect(author.id).toBe(10);
    expect(author.name).toBe('Jane');
    expect(author.avatar).toBe('/jane.png');
  });

  it('extracts author from nested author object', () => {
    const item: FeedItem = {
      id: 1,
      content: 'test',
      author: { id: 20, name: 'Bob', avatar_url: '/bob.png' },
      created_at: '2026-01-01',
      type: 'post',
      likes_count: 0,
      comments_count: 0,
      is_liked: false,
    };
    const author = getAuthor(item);
    expect(author.id).toBe(20);
    expect(author.name).toBe('Bob');
    expect(author.avatar).toBe('/bob.png');
  });

  it('prefers flat fields over nested object', () => {
    const item: FeedItem = {
      id: 1,
      content: 'test',
      author_id: 10,
      author_name: 'Flat',
      author_avatar: '/flat.png',
      author: { id: 20, name: 'Nested', avatar_url: '/nested.png' },
      created_at: '2026-01-01',
      type: 'post',
      likes_count: 0,
      comments_count: 0,
      is_liked: false,
    };
    const author = getAuthor(item);
    expect(author.id).toBe(10);
    expect(author.name).toBe('Flat');
    expect(author.avatar).toBe('/flat.png');
  });

  it('returns defaults when no author data', () => {
    const item: FeedItem = {
      id: 1,
      content: 'test',
      created_at: '2026-01-01',
      type: 'post',
      likes_count: 0,
      comments_count: 0,
      is_liked: false,
    };
    const author = getAuthor(item);
    expect(author.id).toBe(0);
    expect(author.name).toBe('Unknown');
    expect(author.avatar).toBeNull();
  });

  it('handles null avatar gracefully', () => {
    const item: FeedItem = {
      id: 1,
      content: 'test',
      author_id: 10,
      author_name: 'Jane',
      created_at: '2026-01-01',
      type: 'post',
      likes_count: 0,
      comments_count: 0,
      is_liked: false,
    };
    const author = getAuthor(item);
    expect(author.avatar).toBeNull();
  });
});

describe('getItemDetailPath', () => {
  const base: FeedItem = {
    id: 42,
    content: 'test',
    created_at: '2026-01-01',
    type: 'post',
    likes_count: 0,
    comments_count: 0,
    is_liked: false,
  };

  it('returns listings detail path', () => {
    expect(getItemDetailPath({ ...base, type: 'listing' })).toBe('/listings/42');
  });

  it('returns events detail path', () => {
    expect(getItemDetailPath({ ...base, type: 'event' })).toBe('/events/42');
  });

  it('returns goals list path', () => {
    expect(getItemDetailPath({ ...base, type: 'goal' })).toBe('/goals');
  });

  it('returns receiver profile for review', () => {
    expect(getItemDetailPath({ ...base, type: 'review', receiver: { id: 7, name: 'Bob' } })).toBe('/profile/7');
  });

  it('returns null for review without receiver', () => {
    expect(getItemDetailPath({ ...base, type: 'review' })).toBeNull();
  });

  it('returns null for post', () => {
    expect(getItemDetailPath({ ...base, type: 'post' })).toBeNull();
  });

  it('returns null for poll', () => {
    expect(getItemDetailPath({ ...base, type: 'poll' })).toBeNull();
  });
});

describe('getItemDetailLabel', () => {
  const base: FeedItem = {
    id: 1,
    content: 'test',
    created_at: '2026-01-01',
    type: 'post',
    likes_count: 0,
    comments_count: 0,
    is_liked: false,
  };

  it('returns correct labels for typed items', () => {
    expect(getItemDetailLabel({ ...base, type: 'listing' })).toBe('View Listing');
    expect(getItemDetailLabel({ ...base, type: 'event' })).toBe('View Event');
    expect(getItemDetailLabel({ ...base, type: 'goal' })).toBe('View Goals');
    expect(getItemDetailLabel({ ...base, type: 'review' })).toBe('View Profile');
  });

  it('returns null for post and poll', () => {
    expect(getItemDetailLabel({ ...base, type: 'post' })).toBeNull();
    expect(getItemDetailLabel({ ...base, type: 'poll' })).toBeNull();
  });
});
