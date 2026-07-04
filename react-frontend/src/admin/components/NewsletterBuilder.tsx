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
 *   losslessly); the compiled, ready-to-send HTML is stored in `content`
 *   (content_format='builder', which the backend renders like html).
 * - Image uploads go through our own domain (POST /v2/upload).
 *
 * WHY THIS OWNS ITS OWN CHROME (learned the hard way): GrapesJS's DEFAULT panel
 * layout is driven by toolbar buttons whose glyphs come from Font Awesome. This
 * app has no Font Awesome (it uses lucide-react), so those buttons render as
 * blank squares and the block palette gets stranded behind an invisible toggle.
 * So we suppress GrapesJS's default panels (`panels: { defaults: [] }`) and pin
 * each manager into our OWN React layout: a permanent block palette
 * (BuilderBlockPalette), a labelled lucide toolbar (BuilderToolbar), and a
 * tabbed Style/Settings/Layers inspector (BuilderInspector).
 *
 * INTEGRATION CONSTRAINTS:
 *  - grapesjs-mjml@1.0.8 supports grapesjs ^0.21.x ONLY. Keep grapesjs pinned.
 *  - The canvas MUST be seeded with an <mjml><mj-body> document or there is no
 *    valid mj-body to drop blocks into.
 *  - GrapesJS UI CSS is imported once in src/main.tsx (with our theme overrides
 *    right after), not here, so the cascade order is deterministic.
 *
 * GrapesJS is imperative; it's mounted once in a useEffect, guarded against
 * React StrictMode's mount→cleanup→remount by gating on the editor INSTANCE ref.
 */

import { useEffect, useRef, useState } from 'react';
import grapesjs, { type Editor } from 'grapesjs';
import mjmlPlugin from 'grapesjs-mjml';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { Button, Modal, ModalBody, ModalContent, ModalFooter, ModalHeader, useConfirm } from '@/components/ui';
import Copy from 'lucide-react/icons/copy';
import { logError } from '@/lib/logger';
import { adminNewsletters } from '../api/adminApi';
import { BuilderToolbar, type BuilderDevice } from './BuilderToolbar';
import { BuilderBlockPalette } from './BuilderBlockPalette';
import { BuilderInspector, type InspectorTab } from './BuilderInspector';

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

/** Minimal structural view of grapesjs' UndoManager (avoids depending on its types here). */
type UndoLike = { hasUndo?: () => boolean; hasRedo?: () => boolean; undo?: () => void; redo?: () => void };

export function NewsletterBuilder({ designJson, readOnly, onChange }: NewsletterBuilderProps) {
  const { t } = useTranslation('admin');
  const toast = useToast();
  const confirm = useConfirm();

  // One ref per GrapesJS appendTo target — all must be mounted before init runs.
  const canvasRef = useRef<HTMLDivElement>(null);
  const blocksRef = useRef<HTMLDivElement>(null);
  const stylesRef = useRef<HTMLDivElement>(null);
  const traitsRef = useRef<HTMLDivElement>(null);
  const layersRef = useRef<HTMLDivElement>(null);

  const editorRef = useRef<Editor | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;
  // Seed only at mount time — later prop changes shouldn't reset the canvas.
  const seedRef = useRef(designJson);

  const [editor, setEditor] = useState<Editor | null>(null);
  const [failed, setFailed] = useState(false);
  const [device, setDevice] = useState<BuilderDevice>('Desktop');
  const [showBorders, setShowBorders] = useState(false);
  const [canUndo, setCanUndo] = useState(false);
  const [canRedo, setCanRedo] = useState(false);
  const [hasSelection, setHasSelection] = useState(false);
  const [activeTab, setActiveTab] = useState<InspectorTab>('style');
  const [codeOpen, setCodeOpen] = useState(false);
  const [codeHtml, setCodeHtml] = useState('');

  // Reads the editor's undo/redo availability into React state. Reads refs +
  // stable setters only, so it's safe to call from the init effect too.
  const syncUndoState = () => {
    const um = editorRef.current?.UndoManager as unknown as UndoLike | undefined;
    setCanUndo(Boolean(um?.hasUndo?.()));
    setCanRedo(Boolean(um?.hasRedo?.()));
  };

  useEffect(() => {
    if (editorRef.current) return undefined;
    if (!canvasRef.current || !blocksRef.current || !stylesRef.current || !traitsRef.current || !layersRef.current) {
      return undefined;
    }

    const mjmlFn = resolveMjmlPlugin();
    if (!mjmlFn) {
      logError('NewsletterBuilder: grapesjs-mjml plugin did not resolve to a function', mjmlPlugin);
      setFailed(true);
      return undefined;
    }

    let ed: Editor;
    try {
      ed = grapesjs.init({
        container: canvasRef.current,
        height: '100%',
        width: '100%',
        fromElement: false,
        storageManager: { type: 'none' },
        undoManager: { trackSelection: false },
        // Suppress GrapesJS's default (icon-less) panels; we own the chrome.
        panels: { defaults: [] },
        // Pin each manager into our own React layout.
        blockManager: { appendTo: blocksRef.current },
        styleManager: { appendTo: stylesRef.current },
        traitManager: { appendTo: traitsRef.current },
        layerManager: { appendTo: layersRef.current },
        plugins: [
          (plug: Editor) =>
            mjmlFn(plug, {
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

    editorRef.current = ed;

    // Restore a previously-saved design, otherwise seed a blank MJML document.
    let restored = false;
    if (seedRef.current) {
      try {
        const parsed = JSON.parse(seedRef.current) as Record<string, unknown>;
        (ed.loadProjectData as (d: unknown) => void)(parsed);
        restored = true;
      } catch (err) {
        logError('NewsletterBuilder: could not restore design_json', err);
      }
    }
    if (!restored) {
      ed.setComponents(DEFAULT_MJML);
    }

    const scheduleEmit = () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => {
        const current = editorRef.current;
        if (!current) return;
        try {
          const projectData = current.getProjectData();
          const result = current.runCommand('mjml-code-to-html') as { html?: string } | undefined;
          onChangeRef.current({ html: result?.html ?? '', designJson: JSON.stringify(projectData) });
        } catch (err) {
          logError('NewsletterBuilder: export failed', err);
        }
      }, AUTOSAVE_DEBOUNCE_MS);
    };

    const handleUpdate = () => {
      syncUndoState();
      scheduleEmit();
    };
    const syncSelection = () => setHasSelection(Boolean(editorRef.current?.getSelected()));
    const syncDevice = () => setDevice((editorRef.current?.getDevice() as BuilderDevice) ?? 'Desktop');

    ed.on('update', handleUpdate);
    ed.on('component:selected component:deselected', syncSelection);
    ed.on('change:device', syncDevice);

    setEditor(ed);
    syncUndoState();

    return () => {
      ed.off('update', handleUpdate);
      ed.off('component:selected component:deselected', syncSelection);
      ed.off('change:device', syncDevice);
      if (debounceRef.current) clearTimeout(debounceRef.current);
      try {
        ed.destroy();
      } catch {
        /* already torn down */
      }
      editorRef.current = null;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- init once; seed is a mount-time snapshot
  }, []);

  const handleUndo = () => {
    (editorRef.current?.UndoManager as unknown as UndoLike | undefined)?.undo?.();
    syncUndoState();
  };
  const handleRedo = () => {
    (editorRef.current?.UndoManager as unknown as UndoLike | undefined)?.redo?.();
    syncUndoState();
  };
  const handleSetDevice = (next: BuilderDevice) => {
    editorRef.current?.setDevice(next);
    setDevice(next);
  };
  const handleToggleBorders = () => {
    const ed = editorRef.current;
    if (!ed) return;
    if (showBorders) ed.stopCommand('sw-visibility');
    else ed.runCommand('sw-visibility');
    setShowBorders((v) => !v);
  };
  const handleViewCode = () => {
    const ed = editorRef.current;
    if (!ed) return;
    try {
      const result = ed.runCommand('mjml-code-to-html') as { html?: string } | undefined;
      setCodeHtml(result?.html ?? '');
      setCodeOpen(true);
    } catch (err) {
      logError('NewsletterBuilder: view code failed', err);
    }
  };
  const handleClear = async () => {
    const ok = await confirm({
      title: t('newsletter_content_editor.builder_reset'),
      body: t('newsletter_content_editor.builder_reset_confirm'),
      status: 'warning',
      confirmLabel: t('newsletter_content_editor.builder_reset'),
    });
    if (!ok) return;
    editorRef.current?.setComponents(DEFAULT_MJML);
  };
  const copyCode = async () => {
    try {
      await navigator.clipboard.writeText(codeHtml);
      toast.success(t('newsletter_content_editor.code_copied'));
    } catch {
      /* clipboard blocked — no-op */
    }
  };

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

      <div
        className="nb-root relative flex flex-col overflow-hidden rounded-lg border-2 border-border bg-surface"
        style={{ height: 720 }}
      >
        <BuilderToolbar
          ready={Boolean(editor)}
          readOnly={readOnly}
          device={device}
          showBorders={showBorders}
          canUndo={canUndo}
          canRedo={canRedo}
          onUndo={handleUndo}
          onRedo={handleRedo}
          onSetDevice={handleSetDevice}
          onToggleBorders={handleToggleBorders}
          onViewCode={handleViewCode}
          onClear={handleClear}
          t={t}
        />

        <div className="flex min-h-0 flex-1">
          <BuilderBlockPalette blocksRef={blocksRef} title={t('newsletter_content_editor.palette_title')} />
          <main className="min-w-0 flex-1 overflow-hidden">
            <div ref={canvasRef} className="h-full w-full" />
          </main>
          <BuilderInspector
            stylesRef={stylesRef}
            traitsRef={traitsRef}
            layersRef={layersRef}
            activeTab={activeTab}
            onTabChange={setActiveTab}
            hasSelection={hasSelection}
            labels={{
              ariaLabel: t('newsletter_content_editor.inspector_label'),
              style: t('newsletter_content_editor.inspector_style'),
              settings: t('newsletter_content_editor.inspector_settings'),
              layers: t('newsletter_content_editor.inspector_layers'),
              empty: t('newsletter_content_editor.empty_inspector'),
            }}
          />
        </div>

        {readOnly && (
          <div className="absolute inset-0 z-10 cursor-not-allowed bg-white/40" aria-hidden="true" />
        )}
      </div>

      <Modal isOpen={codeOpen} onOpenChange={setCodeOpen} size="3xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>{t('newsletter_content_editor.code_modal_title')}</ModalHeader>
          <ModalBody>
            <pre className="max-h-[60vh] overflow-auto rounded-lg border border-border bg-surface p-3 text-xs text-foreground">
              <code>{codeHtml}</code>
            </pre>
          </ModalBody>
          <ModalFooter>
            <Button variant="primary" size="sm" startContent={<Copy size={14} />} onPress={copyCode}>
              {t('newsletter_content_editor.code_copy')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default NewsletterBuilder;
