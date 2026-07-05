// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * builderBlocks — turns grapesjs-mjml's icon-only block cards into clearly
 * LABELLED, grouped cards without giving up GrapesJS's native drag-to-canvas.
 *
 * grapesjs-mjml registers its blocks with terse, icon-first cards routed through
 * the plugin's own i18n — so "text" vs "button" vs "image" are indistinguishable
 * (the #1 usability complaint). Instead of rebuilding the palette in React (which
 * would mean re-implementing drag onto the canvas iframe), we re-`set()` each
 * registered block with a localized title + one-line description + a lucide-style
 * icon + a Layout/Content category. GrapesJS re-renders its own cards from those
 * attributes, so native drag keeps working — the cards just become legible.
 *
 * Labels come from the admin i18n namespace (keys under `newsletter_builder.*`),
 * so they translate with the rest of the admin UI.
 */

/** Minimal structural views of the grapesjs BlockManager pieces we touch. */
type BlockLike = { set?: (o: Record<string, unknown>) => void };
type BlockManagerLike = {
  get?: (id: string) => BlockLike | undefined;
  remove?: (id: string) => void;
};
type EditorWithBlocks = { BlockManager?: BlockManagerLike };

/** Wrap raw path markup as a 24px lucide-style stroke icon. */
const svg = (paths: string): string =>
  `<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

const ICONS = {
  section: svg('<rect x="3" y="5" width="18" height="14" rx="2"/>'),
  twoCol: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M12 4v16"/>'),
  threeCol: svg('<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16"/><path d="M15 4v16"/>'),
  divider: svg('<path d="M4 12h16"/>'),
  spacer: svg('<path d="M12 4v16"/><path d="m8 8 4-4 4 4"/><path d="m8 16 4 4 4-4"/>'),
  text: svg('<path d="M4 7V5h16v2"/><path d="M9 19h6"/><path d="M12 5v14"/>'),
  button: svg('<rect x="3" y="8" width="18" height="8" rx="4"/><path d="M8 12h8"/>'),
  image: svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.8"/><path d="m21 15-4.5-4.5L6 21"/>'),
  social: svg('<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4"/><path d="m15.4 6.5-6.8 4"/>'),
  hero: svg('<rect x="3" y="4" width="18" height="11" rx="1.5"/><path d="M3 11l4-3 4 3 3-2 7 4"/><path d="M6 19h12"/>'),
  html: svg('<path d="m9 8-5 4 5 4"/><path d="m15 8 5 4-5 4"/>'),
} as const;

type BlockCategory = 'layout' | 'content';

interface BlockMeta {
  cat: BlockCategory;
  titleKey: string;
  descKey: string;
  icon: string;
}

/**
 * The curated, well-labelled block set. Keys are grapesjs-mjml block ids; any id
 * absent from the running plugin is skipped, and a few noisy sub-element blocks
 * are removed entirely so the palette reads as a clean toolbox.
 */
export const BLOCK_META: Record<string, BlockMeta> = {
  oneColumn: { cat: 'layout', titleKey: 'blocks.section_label', descKey: 'blocks.section_desc', icon: ICONS.section },
  twoColumn: { cat: 'layout', titleKey: 'blocks.two_col_label', descKey: 'blocks.two_col_desc', icon: ICONS.twoCol },
  threeColumn: { cat: 'layout', titleKey: 'blocks.three_col_label', descKey: 'blocks.three_col_desc', icon: ICONS.threeCol },
  divider: { cat: 'layout', titleKey: 'blocks.divider_label', descKey: 'blocks.divider_desc', icon: ICONS.divider },
  spacer: { cat: 'layout', titleKey: 'blocks.spacer_label', descKey: 'blocks.spacer_desc', icon: ICONS.spacer },
  text: { cat: 'content', titleKey: 'blocks.text_label', descKey: 'blocks.text_desc', icon: ICONS.text },
  button: { cat: 'content', titleKey: 'blocks.button_label', descKey: 'blocks.button_desc', icon: ICONS.button },
  image: { cat: 'content', titleKey: 'blocks.image_label', descKey: 'blocks.image_desc', icon: ICONS.image },
  socialGroup: { cat: 'content', titleKey: 'blocks.social_label', descKey: 'blocks.social_desc', icon: ICONS.social },
  hero: { cat: 'content', titleKey: 'blocks.hero_label', descKey: 'blocks.hero_desc', icon: ICONS.hero },
  raw: { cat: 'content', titleKey: 'blocks.html_label', descKey: 'blocks.html_desc', icon: ICONS.html },
};

/** grapesjs-mjml sub-element / advanced blocks we hide to keep the palette clean. */
const REMOVE_BLOCKS = ['socialElement', 'navLink', 'navBar', 'wrapper'];

function escapeHtml(s: string): string {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * Re-label + group the registered MJML blocks. Safe to call once after
 * grapesjs.init(); unknown ids are skipped and every set() is guarded.
 */
export function customizeBlocks(editor: EditorWithBlocks, t: (key: string) => string): void {
  const bm = editor.BlockManager;
  if (!bm) return;

  for (const id of REMOVE_BLOCKS) {
    try {
      bm.remove?.(id);
    } catch {
      /* block absent in this plugin build — ignore */
    }
  }

  for (const [id, meta] of Object.entries(BLOCK_META)) {
    const block = bm.get?.(id);
    if (!block?.set) continue;

    const title = t(`newsletter_builder.${meta.titleKey}`);
    const desc = t(`newsletter_builder.${meta.descKey}`);
    const categoryLabel = t(`newsletter_builder.blocks.category_${meta.cat}`);

    try {
      block.set({
        // Own wrapper so the title/description stack regardless of whether the
        // grapesjs build wraps the label in .gjs-block-label.
        label: `<span class="nb-b-label"><span class="nb-b-title">${escapeHtml(title)}</span><span class="nb-b-desc">${escapeHtml(desc)}</span></span>`,
        media: meta.icon,
        category: { id: meta.cat, label: categoryLabel, order: meta.cat === 'layout' ? 1 : 2, open: true },
        attributes: { class: `nb-block nb-block--${id}`, title: `${title} — ${desc}` },
      });
    } catch {
      /* defensive: never let a labelling tweak break editor init */
    }
  }
}
