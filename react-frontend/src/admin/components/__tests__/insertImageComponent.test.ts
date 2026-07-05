// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi } from 'vitest';
import {
  resolveUploadedUrl,
  isEphemeralSrc,
  insertImageComponent,
  imageActionFor,
  type GjsComp,
  type EditorLike,
} from '../builderImage';

const compOfTag = (tag: string): GjsComp => ({ get: (k) => (k === 'tagName' ? tag : undefined) });

describe('resolveUploadedUrl', () => {
  it('returns the absolute url when present', () => {
    expect(
      resolveUploadedUrl({ success: true, data: { url: 'https://api.test/storage/a.png', path: 'tenant_1/a.png' } }),
    ).toBe('https://api.test/storage/a.png');
  });

  it('rejects a response that only has a relative path (never inserts /storage-relative)', () => {
    expect(resolveUploadedUrl({ success: true, data: { path: 'tenant_1/a.png' } })).toBeNull();
  });

  it('rejects an unsuccessful or empty response', () => {
    expect(resolveUploadedUrl({ success: false, data: { url: 'https://x' } })).toBeNull();
    expect(resolveUploadedUrl({ success: true, data: { url: '   ' } })).toBeNull();
    expect(resolveUploadedUrl(null)).toBeNull();
    expect(resolveUploadedUrl(undefined)).toBeNull();
  });
});

describe('isEphemeralSrc', () => {
  it('flags blob: and data: urls as ephemeral', () => {
    expect(isEphemeralSrc('blob:https://app/xyz')).toBe(true);
    expect(isEphemeralSrc('data:image/png;base64,AAAA')).toBe(true);
  });
  it('treats absolute https urls as durable', () => {
    expect(isEphemeralSrc('https://api.test/storage/a.png')).toBe(false);
    expect(isEphemeralSrc(undefined)).toBe(false);
  });
});

describe('imageActionFor', () => {
  it('sets the background on a selected mj-hero (the hero image)', () => {
    expect(imageActionFor(compOfTag('mj-hero'))).toBe('hero-background');
  });
  it('replaces the src of a selected image', () => {
    expect(imageActionFor(compOfTag('mj-image'))).toBe('set-src');
    expect(imageActionFor(compOfTag('image'))).toBe('set-src');
  });
  it('inserts a fresh image for anything else (or nothing selected)', () => {
    expect(imageActionFor(compOfTag('mj-column'))).toBe('insert');
    expect(imageActionFor(undefined)).toBe('insert');
  });
});

describe('insertImageComponent', () => {
  it('appends an mj-image OBJECT (not a markup string) to the selected column', () => {
    const append = vi.fn(() => [{ id: 'img' } as GjsComp]);
    const column: GjsComp = {
      get: (k) => (k === 'tagName' ? 'mj-column' : undefined),
      append,
    };
    const ed: EditorLike = { getSelected: () => column };

    insertImageComponent(ed, 'https://api.test/storage/a.png', 'Hero');

    expect(append).toHaveBeenCalledTimes(1);
    const arg = append.mock.calls[0][0];
    expect(typeof arg).toBe('object');
    expect(arg).toMatchObject({ type: 'mj-image', attributes: { src: 'https://api.test/storage/a.png', alt: 'Hero' } });
  });

  it('appends a new mj-section OBJECT to mj-body when nothing is selected', () => {
    const bodyAppend = vi.fn();
    const body: GjsComp = {
      get: (k) => (k === 'tagName' ? 'mj-body' : undefined),
      append: bodyAppend,
    };
    const wrapper: GjsComp = {
      get: () => undefined,
      components: () => [body],
    };
    const ed: EditorLike = { getSelected: () => undefined, getWrapper: () => wrapper };

    insertImageComponent(ed, 'https://api.test/storage/b.png');

    expect(bodyAppend).toHaveBeenCalledTimes(1);
    const arg = bodyAppend.mock.calls[0][0] as Record<string, unknown>;
    expect(typeof arg).toBe('object');
    expect(arg.type).toBe('mj-section');
    // …and the section wraps a column wrapping the mj-image.
    const column = (arg.components as Array<Record<string, unknown>>)[0];
    const image = (column.components as Array<Record<string, unknown>>)[0];
    expect(image).toMatchObject({ type: 'mj-image', attributes: { src: 'https://api.test/storage/b.png' } });
  });
});
