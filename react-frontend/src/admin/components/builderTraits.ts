// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * builderTraits — surface MJML attributes that grapesjs-mjml does NOT expose as
 * editable traits, so authors can actually configure them from the Settings tab.
 *
 * Concretely: grapesjs-mjml registers `mj-hero` with traits ['id','title',
 * 'full-width',…] but NOT `background-url` — so a dropped hero had no UI to set
 * its background image (the owner's "couldn't change the hero image" complaint).
 * We add the missing background traits to `mj-hero`/`mj-section` on selection.
 *
 * A trait whose `name` matches an MJML attribute is written straight to that
 * attribute by grapesjs, which is exactly what the MJML compiler reads
 * (`background-url`, `background-color`, `height`). No plugin fork needed.
 */

import { tagOf, type GjsComp } from './builderImage';

interface TraitDef {
  type: string;
  name: string;
  labelKey: string;
  placeholder?: string;
}

/** Extra traits per component tag (only added when missing). */
const EXTRA_TRAITS: Record<string, TraitDef[]> = {
  'mj-hero': [
    { type: 'text', name: 'background-url', labelKey: 'newsletter_builder.trait_background_image', placeholder: 'https://…' },
    { type: 'text', name: 'background-color', labelKey: 'newsletter_builder.trait_background_color', placeholder: '#f3f4f6' },
    { type: 'text', name: 'height', labelKey: 'newsletter_builder.trait_hero_height', placeholder: '400px' },
  ],
  'mj-section': [
    { type: 'text', name: 'background-url', labelKey: 'newsletter_builder.trait_background_image', placeholder: 'https://…' },
    { type: 'text', name: 'background-color', labelKey: 'newsletter_builder.trait_background_color', placeholder: '#ffffff' },
  ],
};

type CompWithTraits = GjsComp & {
  getTrait?: (name: string) => unknown;
  addTrait?: (t: Record<string, unknown>, o?: { at?: number }) => unknown;
};

type EditorLike = {
  on?: (ev: string, cb: (...a: unknown[]) => void) => void;
  off?: (ev: string, cb: (...a: unknown[]) => void) => void;
};

/**
 * Register a selection listener that lazily adds the missing background traits to
 * mj-hero / mj-section. Idempotent per component (WeakSet-guarded). Returns a
 * disposer; safe to call once after grapesjs.init().
 */
export function attachBackgroundTraits(editor: EditorLike, t: (key: string) => string): () => void {
  const augmented = new WeakSet<object>();

  const handler = (...args: unknown[]) => {
    const comp = args[0] as CompWithTraits | undefined;
    if (!comp || augmented.has(comp)) return;
    const defs = EXTRA_TRAITS[tagOf(comp)];
    if (!defs) return;
    augmented.add(comp);
    try {
      for (const d of defs) {
        if (comp.getTrait?.(d.name)) continue; // already present — don't duplicate
        comp.addTrait?.({ type: d.type, name: d.name, label: t(d.labelKey), placeholder: d.placeholder });
      }
    } catch {
      /* trait wiring is best-effort — never break selection */
    }
  };

  editor.on?.('component:selected', handler);
  return () => editor.off?.('component:selected', handler);
}
