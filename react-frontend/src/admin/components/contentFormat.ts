// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Content-format primitives shared by the multi-mode newsletter editor.
 *
 * A newsletter's `content` is one string, but its meaning depends on the
 * authoring format. These pure helpers describe how content converts between
 * formats and which conversions lose information (and therefore need a confirm
 * dialog). No React — trivially unit-testable.
 */

export type ContentFormat = 'plaintext' | 'richtext' | 'html' | 'builder';

/** Formats selectable in the editor UI. */
export const EDITOR_MODES: ContentFormat[] = ['plaintext', 'richtext', 'html', 'builder'];

/** Escape raw text into HTML, preserving line breaks as <br>. */
export function escapePlainToHtml(text: string): string {
  const escaped = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
  return escaped.replace(/\r?\n/g, '<br>\n');
}

/** Strip HTML down to plain text (best-effort, DOM-based). */
export function stripToPlainText(html: string): string {
  if (typeof document === 'undefined') {
    // SSR / test fallback: naive tag strip.
    return html.replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim();
  }
  const doc = new DOMParser().parseFromString(html, 'text/html');
  // Turn <br> and block boundaries into newlines before reading text.
  doc.querySelectorAll('br').forEach((br) => br.replaceWith('\n'));
  doc.querySelectorAll('p, div, li, tr, h1, h2, h3, h4').forEach((el) => {
    el.append('\n');
  });
  return (doc.body.textContent || '').replace(/\n{3,}/g, '\n\n').trim();
}

/**
 * Is switching from -> to lossy enough to warrant a confirm dialog?
 *
 * - html -> richtext: Lexical drops tables/inline styles/unsupported tags.
 * - anything-with-markup -> plaintext: all formatting is discarded.
 * Everything else is a safe transform (escape or relabel).
 */
export function isDestructiveSwitch(from: ContentFormat, to: ContentFormat, content: string): boolean {
  if (from === to) return false;
  if (content.trim() === '') return false; // nothing to lose

  if (to === 'plaintext' && (from === 'html' || from === 'richtext' || from === 'builder')) {
    return true;
  }
  if (to === 'richtext' && (from === 'html' || from === 'builder')) {
    return true;
  }
  if (to === 'builder' && from !== 'builder') {
    return true;
  }
  return false;
}

/**
 * Convert content when the editor mode changes. Safe transforms run silently;
 * destructive ones should be gated by isDestructiveSwitch() + a confirm first.
 */
export function transformContent(content: string, from: ContentFormat, to: ContentFormat): string {
  if (from === to) return content;
  if (content.trim() === '') return content;

  // plaintext -> any HTML-bearing format: escape + <br>.
  if (from === 'plaintext' && to !== 'plaintext') {
    return escapePlainToHtml(content);
  }
  // any HTML-bearing format -> plaintext: strip tags.
  if (to === 'plaintext' && from !== 'plaintext') {
    return stripToPlainText(content);
  }
  // richtext <-> html <-> builder: all already HTML strings; just relabel.
  return content;
}
