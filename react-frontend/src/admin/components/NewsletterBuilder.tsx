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
 * GrapesJS is imperative; it's mounted once in a useEffect, guarded against
 * React StrictMode's mount→cleanup→remount by gating on the editor INSTANCE
 * ref (a boolean guard never re-arms — see src/test/mount-guard-convention).
 */

import { useEffect, useRef, useState } from 'react';
import grapesjs, { type Editor } from 'grapesjs';
import mjmlPlugin from 'grapesjs-mjml';
import 'grapesjs/dist/css/grapes.min.css';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminNewsletters } from '../api/adminApi';

interface NewsletterBuilderProps {
  /** Current compiled HTML (unused for restore — design_json drives restore). */
  html: string;
  /** Serialized GrapesJS project (JSON string) or null for a blank canvas. */
  designJson?: string | null;
  isDisabled?: boolean;
  onChange: (payload: { html: string; designJson: string }) => void;
}

const AUTOSAVE_DEBOUNCE_MS = 1000;

export function NewsletterBuilder({ designJson, isDisabled, onChange }: NewsletterBuilderProps) {
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

    let editor: Editor;
    try {
      editor = grapesjs.init({
        container: containerRef.current,
        height: '640px',
        width: '100%',
        fromElement: false,
        storageManager: { type: 'none' },
        undoManager: { trackSelection: false },
        // Wrap the plugin call so options pass without the pluginsOpts key dance.
        plugins: [
          (ed: Editor) =>
            (mjmlPlugin as unknown as (e: Editor, o: Record<string, unknown>) => void)(ed, {
              resetBlocks: true,
              resetStyleManager: true,
              resetDevices: true,
              hideSelector: true,
            }),
        ],
        assetManager: {
          // Custom upload: our /v2/upload returns { url, path }, not GrapesJS's
          // { data: [...] } shape, so we take full control here.
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

    // Restore a previously-saved design.
    if (seedRef.current) {
      try {
        const parsed = JSON.parse(seedRef.current) as Record<string, unknown>;
        (editor.loadProjectData as (d: unknown) => void)(parsed);
      } catch (err) {
        logError('NewsletterBuilder: could not restore design_json', err);
      }
    }

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
      <div className="relative overflow-hidden rounded-lg border-2 border-border">
        <div ref={containerRef} className="min-h-[640px] w-full" />
        {isDisabled && <div className="absolute inset-0 z-10 cursor-not-allowed bg-white/40" aria-hidden="true" />}
      </div>
    </div>
  );
}

export default NewsletterBuilder;
