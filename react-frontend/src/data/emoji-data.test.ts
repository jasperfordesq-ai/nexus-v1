// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for emoji-data module
 */

import { describe, it, expect } from 'vitest';
import { EMOJI_CATEGORIES, EMOJI_KEYWORDS } from './emoji-data';

describe('EMOJI_CATEGORIES', () => {
  it('has exactly 8 categories', () => {
    expect(EMOJI_CATEGORIES).toHaveLength(8);
  });

  it('each category has key, label, icon, and emojis array', () => {
    for (const category of EMOJI_CATEGORIES) {
      expect(category).toHaveProperty('key');
      expect(category).toHaveProperty('label');
      expect(category).toHaveProperty('icon');
      expect(category).toHaveProperty('emojis');

      expect(typeof category.key).toBe('string');
      expect(typeof category.label).toBe('string');
      expect(typeof category.icon).toBe('string');
      expect(Array.isArray(category.emojis)).toBe(true);
      expect(category.emojis.length).toBeGreaterThan(0);
    }
  });

  it('contains the expected category keys', () => {
    const keys = EMOJI_CATEGORIES.map((cat) => cat.key);
    expect(keys).toEqual([
      'smileys',
      'people',
      'animals',
      'food',
      'travel',
      'activities',
      'objects',
      'symbols',
    ]);
  });

  it('each category has a unique key', () => {
    const keys = EMOJI_CATEGORIES.map((cat) => cat.key);
    const uniqueKeys = new Set(keys);
    expect(uniqueKeys.size).toBe(keys.length);
  });

  it('category labels follow the i18n key convention', () => {
    for (const category of EMOJI_CATEGORIES) {
      expect(category.label).toMatch(/^compose\.emoji_/);
    }
  });

  it('category icon is a single emoji string', () => {
    for (const category of EMOJI_CATEGORIES) {
      // Icon should be a short string (1-2 chars for emoji, potentially longer with variation selectors)
      expect(category.icon.length).toBeGreaterThan(0);
      expect(category.icon.length).toBeLessThan(5);
    }
  });

  it('each emoji within categories is a non-empty string', () => {
    for (const category of EMOJI_CATEGORIES) {
      for (const emoji of category.emojis) {
        expect(typeof emoji).toBe('string');
        expect(emoji.length).toBeGreaterThan(0);
      }
    }
  });
});

describe('EMOJI_KEYWORDS', () => {
  it('is a record mapping emoji strings to keyword arrays', () => {
    expect(typeof EMOJI_KEYWORDS).toBe('object');
    expect(EMOJI_KEYWORDS).not.toBeNull();
  });

  it('maps emoji to keyword arrays with string values', () => {
    for (const [emoji, keywords] of Object.entries(EMOJI_KEYWORDS)) {
      expect(typeof emoji).toBe('string');
      expect(Array.isArray(keywords)).toBe(true);
      for (const kw of keywords) {
        expect(typeof kw).toBe('string');
        expect(kw.length).toBeGreaterThan(0);
      }
    }
  });

  it('keyword search "love" maps to hearts and love-related emoji', () => {
    const loveEmoji = Object.entries(EMOJI_KEYWORDS)
      .filter(([, keywords]) => keywords.includes('love'))
      .map(([emoji]) => emoji);

    // Should include red heart and heart-eyes at minimum
    expect(loveEmoji).toContain('❤️');
    expect(loveEmoji).toContain('😍');
    expect(loveEmoji).toContain('🥰');
    expect(loveEmoji.length).toBeGreaterThan(3);
  });

  it('keyword search "happy" maps to smiling emoji', () => {
    const happyEmoji = Object.entries(EMOJI_KEYWORDS)
      .filter(([, keywords]) => keywords.includes('happy'))
      .map(([emoji]) => emoji);

    expect(happyEmoji).toContain('😀');
    expect(happyEmoji).toContain('😊');
    expect(happyEmoji.length).toBeGreaterThanOrEqual(2);
  });

  it('keyword search "laugh" maps to laughing emoji', () => {
    const laughEmoji = Object.entries(EMOJI_KEYWORDS)
      .filter(([, keywords]) => keywords.includes('laugh'))
      .map(([emoji]) => emoji);

    expect(laughEmoji).toContain('😂');
    expect(laughEmoji).toContain('🤣');
  });

  it('keyword search "fire" maps to fire emoji', () => {
    const fireEmoji = Object.entries(EMOJI_KEYWORDS)
      .filter(([, keywords]) => keywords.includes('fire'))
      .map(([emoji]) => emoji);

    expect(fireEmoji).toContain('🔥');
  });

  it('keyword search "sleep" maps to sleeping emoji', () => {
    const sleepEmoji = Object.entries(EMOJI_KEYWORDS)
      .filter(([, keywords]) => keywords.includes('sleep'))
      .map(([emoji]) => emoji);

    expect(sleepEmoji).toContain('😴');
    expect(sleepEmoji).toContain('💤');
  });

  it('has keywords for emoji across multiple categories', () => {
    // Verify keywords exist for emoji in different categories
    expect(EMOJI_KEYWORDS['😀']).toBeDefined();   // smileys
    expect(EMOJI_KEYWORDS['👋']).toBeDefined();   // people
    expect(EMOJI_KEYWORDS['❤️']).toBeDefined();   // symbols
    expect(EMOJI_KEYWORDS['🍕']).toBeDefined();   // food
    expect(EMOJI_KEYWORDS['⚽']).toBeDefined();   // activities
    expect(EMOJI_KEYWORDS['💡']).toBeDefined();   // objects
    expect(EMOJI_KEYWORDS['🌍']).toBeDefined();   // travel
    expect(EMOJI_KEYWORDS['🐱']).toBeDefined();   // animals
  });

  it('each keyword entry has at least one keyword', () => {
    for (const [, keywords] of Object.entries(EMOJI_KEYWORDS)) {
      expect(keywords.length).toBeGreaterThan(0);
    }
  });
});
