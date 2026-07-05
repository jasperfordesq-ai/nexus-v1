// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * builderImage — pure, GrapesJS-agnostic helpers for the newsletter builder's
 * image pipeline. Extracted from NewsletterBuilder so the tricky bits (URL
 * resolution + MJML component insertion) are unit-testable WITHOUT mounting the
 * full GrapesJS editor.
 *
 * Two invariants this module enforces (the source of the original bugs):
 *  1. Only an ABSOLUTE upload url is ever inserted — never the relative `path`,
 *     which renders in the in-browser preview but is dead in a delivered email.
 *  2. Insertion uses the GrapesJS component-definition OBJECT api, never a raw
 *     markup string, so appending an image can't corrupt sibling MJML nesting.
 */

/** Minimal structural view of a grapesjs Component (avoids its heavy generics). */
export type GjsComp = {
  get?: (k: string) => unknown;
  parent?: () => GjsComp | undefined;
  append?: (content: string | Record<string, unknown>) => unknown;
  components?: () => { models?: GjsComp[] } | GjsComp[];
  set?: (k: string, v: unknown) => void;
};

/** Minimal structural view of the grapesjs Editor bits these helpers touch. */
export type EditorLike = {
  getSelected?: () => unknown;
  getWrapper?: () => unknown;
};

/** The response shape returned by adminNewsletters.uploadImage(). */
export type UploadResult =
  | { success?: boolean; data?: { url?: string; path?: string } | null }
  | null
  | undefined;

/**
 * Return ONLY the absolute upload url. The API returns `{ url, path }` where
 * `url` is absolute (APP_URL + /storage) and `path` is relative. Inserting the
 * relative path was the "renders in preview, dead in email" bug — so we never
 * fall back to it. Returns null when there's no usable absolute url.
 */
export function resolveUploadedUrl(res: UploadResult): string | null {
  const url = res && res.success && res.data ? res.data.url : undefined;
  return typeof url === 'string' && url.trim() !== '' ? url : null;
}

/** True when a src is a client-only reference that can't survive into an email. */
export function isEphemeralSrc(src: unknown): boolean {
  return typeof src === 'string' && (/^blob:/i.test(src) || /^data:/i.test(src));
}

/** First component from whatever grapesjs `append` returned (array or single). */
function firstComp(added: unknown): GjsComp | undefined {
  if (Array.isArray(added)) return added[0] as GjsComp | undefined;
  return (added as GjsComp) || undefined;
}

/** Tag/type of a component, however grapesjs exposes it. */
function compTag(comp: GjsComp | undefined): string {
  if (!comp) return '';
  return (comp.get?.('tagName') as string) || (comp.get?.('type') as string) || '';
}

/** Walk up from the selection to the enclosing mj-column, if any. */
export function enclosingColumn(ed: EditorLike): GjsComp | null {
  let node = ed.getSelected?.() as GjsComp | undefined;
  while (node) {
    if (compTag(node) === 'mj-column') return node;
    node = node.parent?.();
  }
  return null;
}

/** Find the mj-body so we can append a fresh section when nothing is selected. */
export function findBody(comp: GjsComp | undefined): GjsComp | null {
  if (!comp) return null;
  if (compTag(comp) === 'mj-body') return comp;
  const kids = comp.components?.();
  const list = Array.isArray(kids) ? kids : kids?.models;
  if (list) {
    for (const k of list) {
      const found = findBody(k);
      if (found) return found;
    }
  }
  return null;
}

/**
 * Insert an mj-image at the current selection's column, else append a new
 * section holding it. Uses the component-definition OBJECT api (not a markup
 * string) so grapesjs builds via its factory — no HTML re-parse, no escaping,
 * no sibling corruption. Returns the inserted mj-image when it can be resolved
 * (column case) so the caller can select it; undefined otherwise.
 */
export function insertImageComponent(ed: EditorLike, src: string, alt = ''): GjsComp | undefined {
  const def = { type: 'mj-image', attributes: { src, alt } };

  const col = enclosingColumn(ed);
  if (col?.append) {
    return firstComp(col.append(def));
  }

  const wrapper = ed.getWrapper?.() as GjsComp | undefined;
  const body = findBody(wrapper);
  const section = { type: 'mj-section', components: [{ type: 'mj-column', components: [def] }] };
  (body ?? wrapper)?.append?.(section);
  return undefined;
}
