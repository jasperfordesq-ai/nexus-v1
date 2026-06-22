// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { __testing, sanitizeInline, sanitizeRichText, stripHtmlToText } from './sanitize';

const { isSafeUrl } = __testing;

describe('sanitizeRichText', () => {
  it('returns an empty string for nullish input', () => {
    expect(sanitizeRichText(null)).toBe('');
    expect(sanitizeRichText(undefined)).toBe('');
    expect(sanitizeRichText('')).toBe('');
  });

  it('keeps allowed block-level rich content', () => {
    const out = sanitizeRichText('<h2>Title</h2><p>Body <strong>bold</strong></p><ul><li>x</li></ul>');
    expect(out).toContain('<h2>Title</h2>');
    expect(out).toContain('<strong>bold</strong>');
    expect(out).toContain('<li>x</li>');
  });

  it('strips <script> tags but keeps surrounding content', () => {
    const out = sanitizeRichText('<p>safe</p><script>alert(1)</script>');
    expect(out).toContain('safe');
    expect(out).not.toContain('<script');
    expect(out).not.toContain('alert(1)');
  });

  it('forces safe anchor attributes', () => {
    const out = sanitizeRichText('<a href="https://example.com">link</a>');
    expect(out).toContain('target="_blank"');
    expect(out).toContain('rel="noopener noreferrer nofollow"');
    expect(out).toContain('href="https://example.com"');
  });

  it('strips javascript: hrefs', () => {
    const out = sanitizeRichText('<a href="javascript:alert(1)">x</a>');
    expect(out.toLowerCase()).not.toContain('javascript:');
  });

  it('adds lazy loading and no-referrer to images', () => {
    const out = sanitizeRichText('<img src="https://example.com/a.png" alt="a">');
    expect(out).toContain('loading="lazy"');
    expect(out).toContain('referrerpolicy="no-referrer"');
  });

  it('strips data: image sources', () => {
    const out = sanitizeRichText('<img src="data:image/png;base64,AAAA" alt="a">');
    expect(out).not.toContain('data:image');
  });
});

describe('sanitizeInline', () => {
  it('returns empty string for nullish input', () => {
    expect(sanitizeInline(null)).toBe('');
    expect(sanitizeInline('')).toBe('');
  });

  it('keeps inline emphasis tags', () => {
    expect(sanitizeInline('Hello <strong>world</strong>')).toContain('<strong>world</strong>');
  });

  it('unwraps disallowed block tags but keeps their text', () => {
    const out = sanitizeInline('<div><p>only text</p></div>');
    expect(out).toContain('only text');
    expect(out).not.toContain('<div');
    expect(out).not.toContain('<p>');
  });
});

describe('stripHtmlToText', () => {
  it('returns empty string for nullish input', () => {
    expect(stripHtmlToText(null)).toBe('');
    expect(stripHtmlToText(undefined)).toBe('');
  });

  it('removes all tags and returns text content', () => {
    expect(stripHtmlToText('<b>Hi</b> <i>there</i>')).toBe('Hi there');
  });

  it('drops script content entirely', () => {
    const out = stripHtmlToText('<p>keep</p><script>drop()</script>');
    expect(out).toContain('keep');
    expect(out).not.toContain('drop()');
  });
});

describe('isSafeUrl', () => {
  it('accepts absolute http(s) and mailto URLs', () => {
    expect(isSafeUrl('http://example.com')).toBe(true);
    expect(isSafeUrl('https://example.com/path?q=1#a')).toBe(true);
    expect(isSafeUrl('mailto:hi@example.com')).toBe(true);
  });

  it('accepts relative paths and fragments', () => {
    expect(isSafeUrl('/dashboard')).toBe(true);
    expect(isSafeUrl('#section')).toBe(true);
    expect(isSafeUrl('?query=x')).toBe(true);
    expect(isSafeUrl('foo/bar')).toBe(true);
    expect(isSafeUrl('./rel')).toBe(true);
    expect(isSafeUrl('../rel')).toBe(true);
  });

  it('rejects dangerous schemes', () => {
    expect(isSafeUrl('javascript:alert(1)')).toBe(false);
    expect(isSafeUrl('data:text/html,<script>')).toBe(false);
    expect(isSafeUrl('vbscript:msgbox')).toBe(false);
    expect(isSafeUrl('file:///etc/passwd')).toBe(false);
    expect(isSafeUrl('ftp://example.com')).toBe(false);
  });

  it('rejects empty / whitespace-only values', () => {
    expect(isSafeUrl('')).toBe(false);
    expect(isSafeUrl('   ')).toBe(false);
  });

  it('rejects control-character obfuscated schemes', () => {
    expect(isSafeUrl('java\tscript:alert(1)')).toBe(false);
    expect(isSafeUrl('java\nscript:alert(1)')).toBe(false);
  });
});
