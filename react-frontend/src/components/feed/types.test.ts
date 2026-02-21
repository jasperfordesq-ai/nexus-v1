// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for feed types and getAuthor helper
 */

import { describe, it, expect } from 'vitest';
import { getAuthor } from './types';
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
