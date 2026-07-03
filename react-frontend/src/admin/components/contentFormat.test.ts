// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import {
  escapePlainToHtml,
  stripToPlainText,
  isDestructiveSwitch,
  transformContent,
} from './contentFormat';

describe('contentFormat utils', () => {
  describe('escapePlainToHtml', () => {
    it('escapes HTML special characters', () => {
      expect(escapePlainToHtml('a < b & "c"')).toBe('a &lt; b &amp; &quot;c&quot;');
    });

    it('converts newlines to <br>', () => {
      expect(escapePlainToHtml('line1\nline2')).toContain('<br>');
    });
  });

  describe('stripToPlainText', () => {
    it('removes tags and keeps text', () => {
      const out = stripToPlainText('<h1>Title</h1><p>Body <strong>bold</strong></p>');
      expect(out).toContain('Title');
      expect(out).toContain('Body');
      expect(out).toContain('bold');
      expect(out).not.toContain('<h1>');
      expect(out).not.toContain('<strong>');
    });
  });

  describe('isDestructiveSwitch', () => {
    it('is false for same format', () => {
      expect(isDestructiveSwitch('html', 'html', 'x')).toBe(false);
    });

    it('is false when content is empty', () => {
      expect(isDestructiveSwitch('html', 'richtext', '   ')).toBe(false);
    });

    it('flags html -> richtext (Lexical mangles)', () => {
      expect(isDestructiveSwitch('html', 'richtext', '<table><tr><td>x</td></tr></table>')).toBe(true);
    });

    it('flags any -> plaintext', () => {
      expect(isDestructiveSwitch('richtext', 'plaintext', '<p>hi</p>')).toBe(true);
      expect(isDestructiveSwitch('html', 'plaintext', '<div>hi</div>')).toBe(true);
    });

    it('does NOT flag safe transforms', () => {
      expect(isDestructiveSwitch('plaintext', 'html', 'hi')).toBe(false);
      expect(isDestructiveSwitch('richtext', 'html', '<p>hi</p>')).toBe(false);
      expect(isDestructiveSwitch('plaintext', 'richtext', 'hi')).toBe(false);
    });
  });

  describe('transformContent', () => {
    it('escapes when leaving plaintext', () => {
      expect(transformContent('a < b', 'plaintext', 'html')).toBe('a &lt; b');
    });

    it('strips tags when entering plaintext', () => {
      expect(transformContent('<p>Hello</p>', 'html', 'plaintext')).toContain('Hello');
      expect(transformContent('<p>Hello</p>', 'html', 'plaintext')).not.toContain('<p>');
    });

    it('relabels between HTML-bearing formats without changing the string', () => {
      const html = '<table><tr><td>x</td></tr></table>';
      expect(transformContent(html, 'html', 'richtext')).toBe(html);
      expect(transformContent(html, 'richtext', 'html')).toBe(html);
    });

    it('returns content unchanged for same format', () => {
      expect(transformContent('x', 'html', 'html')).toBe('x');
    });
  });
});
