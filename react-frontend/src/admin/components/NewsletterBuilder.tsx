// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NewsletterBuilder — the enterprise drag-and-drop email builder (GrapesJS +
 * MJML). This is the "Design" mode of NewsletterContentEditor.
 *
 * - Blocks are MJML components (grapesjs-mjml), so the exported HTML is
 *   inbox-safe/table-based by construction.
 * - The editable project is serialized to `design_json` (so a design reopens
 *   losslessly); the compiled, ready-to-send HTML is what gets stored in
 *   `content` (content_format='builder', which the backend renders like html).
 * - Image uploads go through our own domain (POST /v2/upload) via the same
 *   endpoint the HTML editor uses.
 *
 * IMPORTANT (integration constraints, learned the hard way):
 *  - grapesjs-mjml@1.0.8 supports grapesjs ^0.21.x ONLY. On 0.22/0.23 the MJML
 *    canvas wiring fails silently (blocks show but won't drop). Keep grapesjs
 *    pinned to 0.21.x.
 *  - The canvas MUST be seeded with an <mjml><mj-body> document or there is no
 *    valid mj-body to drop blocks into (the layer tree shows a bare "Body").
 *  - GrapesJS's UI CSS is imported once in src/main.tsx (before Tailwind), not
 *    here, so Tailwind's preflight can't win the cascade over the editor chrome.
 *
 * GrapesJS is imperative; it's mounted once in a useEffect, guarded against
 * React StrictMode's mount→cleanup→remount by gating on the editor INSTANCE
 * ref (a boolean guard never re-arms — see src/test/mount-guard-convention).
 */

import { useEffect, useRef, useState } from 'react';
import grapesjs, { type Editor } from 'grapesjs';
import mjmlPlugin from 'grapesjs-mjml';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminNewsletters } from '../api/adminApi';

interface NewsletterBuilderProps {
  /** Current compiled HTML (unused for restore — design_json drives restore). */
  html: string;
  /** Serialized GrapesJS project (JSON string) or null for a blank canvas. */
  designJson?: string | null;
  /** True only for already-sent newsletters — freezes the canvas. NOT set while saving. */
  readOnly?: boolean;
  onChange: (payload: { html: string; designJson: string }) => void;
}

const AUTOSAVE_DEBOUNCE_MS = 1000;

/** Blank starting canvas — establishes the mjml > mj-body wrapper blocks drop into. */
const DEFAULT_MJML =
  '<mjml><mj-body><mj-section><mj-column><mj-text>Start designing your email…</mj-text></mj-column></mj-section></mj-body></mjml>';

/** Resolve the plugin whether Vite hands us `fn` or `{ default: fn }`. */
type MjmlPluginFn = (editor: Editor, opts: Record<string, unknown>) => void;
function resolveMjmlPlugin(): MjmlPluginFn | null {
  const mod = mjmlPlugin as unknown;
  if (typeof mod === 'function') return mod as MjmlPluginFn;
  if (mod && typeof (mod as { default?: unknown }).default === 'function') {
    return (mod as { default: MjmlPluginFn }).default;
  }
  return null;
}

export function NewsletterBuilder({ designJson, readOnly, onChange }: NewsletterBuilderProps) {
  const { t } = useTranslation('admin');
  const toast = useToast();

  const containerRef = useRef<HTMLDivElement>(null);
  const editorRef = useRef<Editor | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;
  // Seed only at mount time — later prop changes shouldn't reset the canvas.
  const seedRef = useRef(designJson);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    if (!containerRef.current || editorRef.current) return undefined;

    const mjmlFn = resolveMjmlPlugin();
    if (!mjmlFn) {
      logError('NewsletterBuilder: grapesjs-mjml plugin did not resolve to a function', mjmlPlugin);
      setFailed(true);
      return undefined;
    }

    let editor: Editor;
    try {
      editor = grapesjs.init({
        container: containerRef.current,
        height: '640px',
        width: '100%',
        fromElement: false,
        storageManager: { type: 'none' },
        undoManager: { trackSelection: false },
        plugins: [
          (ed: Editor) =>
            mjmlFn(ed, {
              resetBlocks: true,
              resetStyleManager: true,
              resetDevices: true,
              hideSelector: true,
            }),
        ],
        assetManager: {
          // Custom upload: /v2/upload returns { url, path }, not GrapesJS's
          // { data: [...] } shape, so take full control here.
          uploadFile: async (ev: Event) => {
            const target = ev.target as HTMLInputElement | null;
            const dropped = (ev as DragEvent).dataTransfer?.files;
            const file = (dropped && dropped[0]) || target?.files?.[0];
            if (!file) return;
            try {
              const res = await adminNewsletters.uploadImage(file);
              const data = res.success && res.data ? (res.data as { url?: string; path?: string }) : null;
              const src = data?.url || data?.path;
              if (src) {
                editorRef.current?.AssetManager.add(src);
              } else {
                toast.error(t('newsletter_content_editor.image_upload_failed'));
              }
            } catch (err) {
              logError('NewsletterBuilder: asset upload failed', err);
              toast.error(t('newsletter_content_editor.image_upload_failed'));
            }
          },
        },
      });
    } catch (err) {
      logError('NewsletterBuilder: GrapesJS init failed', err);
      setFailed(true);
      return undefined;
    }

    editorRef.current = editor;

    // Restore a previously-saved design, otherwise seed a blank MJML document
    // so there's a valid mj-body wrapper to drop blocks into.
    let restored = false;
    if (seedRef.current) {
      try {
        const parsed = JSON.parse(seedRef.current) as Record<string, unknown>;
        (editor.loadProjectData as (d: unknown) => void)(parsed);
        restored = true;
      } catch (err) {
        logError('NewsletterBuilder: could not restore design_json', err);
      }
    }
    if (!restored) {
      editor.setComponents(DEFAULT_MJML);
    }

    // Native tooltips: make sure every top-bar/panel button is labelled.
    applyButtonTooltips(editor, t);

    const emit = () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => {
        const ed = editorRef.current;
        if (!ed) return;
        try {
          const projectData = ed.getProjectData();
          const result = ed.runCommand('mjml-code-to-html') as { html?: string } | undefined;
          const compiled = result?.html ?? '';
          onChangeRef.current({ html: compiled, designJson: JSON.stringify(projectData) });
        } catch (err) {
          logError('NewsletterBuilder: export failed', err);
        }
      }, AUTOSAVE_DEBOUNCE_MS);
    };

    // 'update' fires on any project change (components, styles, everything).
    editor.on('update', emit);

    return () => {
      editor.off('update', emit);
      if (debounceRef.current) clearTimeout(debounceRef.current);
      try {
        editor.destroy();
      } catch {
        /* already torn down */
      }
      editorRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- init once; seed is a mount-time snapshot
  }, []);

  if (failed) {
    return (
      <div className="rounded-lg border-2 border-dashed border-border p-8 text-center text-sm text-muted">
        {t('newsletter_content_editor.builder_error')}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-1.5">
      <label className="text-sm font-medium text-foreground">
        {t('newsletter_content_editor.mode_design')}
      </label>
      <div className="relative overflow-hidden rounded-lg border-2 border-border" style={{ height: 700 }}>
        <div ref={containerRef} className="h-full w-full" />
        {readOnly && (
          <div className="absolute inset-0 z-10 cursor-not-allowed bg-white/40" aria-hidden="true" />
        )}
      </div>
    </div>
  );
}

/**
 * GrapesJS renders native tooltips from each panel button's `title` attribute.
 * Some plugin reset options drop them, so backfill a friendly title for every
 * button (falling back to a humanized id).
 */
function applyButtonTooltips(editor: Editor, t: (k: string) => string): void {
  const labels: Record<string, string> = {
    'sw-visibility': t('newsletter_content_editor.tip_borders'),
    preview: t('newsletter_content_editor.tip_preview'),
    fullscreen: t('newsletter_content_editor.tip_fullscreen'),
    'export-template': t('newsletter_content_editor.tip_code'),
    'undo': t('newsletter_content_editor.tip_undo'),
    'redo': t('newsletter_content_editor.tip_redo'),
    'canvas-clear': t('newsletter_content_editor.tip_clear'),
    'open-sm': t('newsletter_content_editor.tip_styles'),
    'open-tm': t('newsletter_content_editor.tip_settings'),
    'open-layers': t('newsletter_content_editor.tip_layers'),
    'open-blocks': t('newsletter_content_editor.tip_blocks'),
  };
  type PanelBtn = { get: (k: string) => unknown; set: (k: string, v: unknown) => void; id?: string };
  type Panel = { get: (k: string) => unknown };
  // getPanels() returns a Backbone-style `Panels` collection (has forEach at
  // runtime but isn't array-typed), so cast via unknown to a structural shape.
  type ForEachOf<T> = { forEach: (cb: (item: T) => void) => void };
  try {
    (editor.Panels.getPanels() as unknown as ForEachOf<Panel>).forEach((panel) => {
      const buttons = panel.get('buttons') as ForEachOf<PanelBtn> | undefined;
      buttons?.forEach((btn) => {
        const id = String((btn.get('id') as string) ?? btn.id ?? '');
        const attrs = (btn.get('attributes') as Record<string, unknown>) || {};
        if (!attrs.title) {
          btn.set('attributes', { ...attrs, title: labels[id] ?? id.replace(/[-_]/g, ' ') });
        }
      });
    });
  } catch (err) {
    logError('NewsletterBuilder: could not apply button tooltips', err);
  }
}

export default NewsletterBuilder;
