// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PageDesignBuilder - GrapesJS webpage builder for tenant custom pages.
 *
 * This deliberately uses the web/page GrapesJS ecosystem, not the newsletter
 * MJML preset. The exported `content` is normal responsive HTML plus CSS, while
 * `design_json` stores the full project state for lossless reopening.
 * GrapesJS chrome CSS stays in this lazy builder chunk, not the public app shell.
 */

import 'grapesjs/dist/css/grapes.min.css';
import '@/styles/newsletter-builder.css';
import { forwardRef, lazy, Suspense, useEffect, useImperativeHandle, useRef, useState } from 'react';
import grapesjs, { type Editor } from 'grapesjs';
import presetWebpage from 'grapesjs-preset-webpage';
import blocksBasic from 'grapesjs-blocks-basic';
import formsPlugin from 'grapesjs-plugin-forms';
import tabsPlugin from 'grapesjs-tabs';
import tooltipPlugin from 'grapesjs-tooltip';
import customCodePlugin from 'grapesjs-custom-code';
import { useTranslation } from 'react-i18next';
import { Button, Modal, ModalBody, ModalContent, ModalFooter, ModalHeader, useConfirm } from '@/components/ui';
import Copy from 'lucide-react/icons/copy';
import Info from 'lucide-react/icons/info';
import X from 'lucide-react/icons/x';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';
import { adminBuilderAssets } from '../api/adminApi';
import { BuilderToolbar, type BuilderDevice } from './BuilderToolbar';
import { BuilderBlockPalette } from './BuilderBlockPalette';
import { BuilderInspector, type InspectorTab } from './BuilderInspector';
import { BuilderPreviewModal } from './BuilderPreviewModal';
import { resolveUploadedUrl, isEphemeralSrc, type GjsComp } from './builderImage';
import { stripUnsafePageBuilderHtml } from '@/lib/pageBuilderHtml';

const AssetLibraryModal = lazy(() =>
  import('./AssetLibraryModal').then((module) => ({ default: module.AssetLibraryModal })),
);

interface PageDesignBuilderProps {
  html: string;
  designJson?: string | null;
  readOnly?: boolean;
  onChange: (payload: { html: string; designJson: string }) => void;
}

export interface PageDesignBuilderHandle {
  flush: () => { html: string; designJson: string } | null;
}

type PluginFn = (editor: Editor, opts?: Record<string, unknown>) => void;
type UndoLike = { hasUndo?: () => boolean; hasRedo?: () => boolean; undo?: () => void; redo?: () => void };

const AUTOSAVE_DEBOUNCE_MS = 800;

function pageStarterHtml(t: (key: string) => string): string {
  return `
<section class="nexus-page-section nexus-page-hero">
  <div class="nexus-page-container">
    <p class="nexus-page-kicker">${t('page_builder.starter.kicker')}</p>
    <h1>${t('page_builder.starter.hero_title')}</h1>
    <p class="nexus-page-lede">${t('page_builder.starter.hero_body')}</p>
    <a class="nexus-page-button" href="/">${t('page_builder.starter.cta')}</a>
  </div>
</section>
<section class="nexus-page-section">
  <div class="nexus-page-container nexus-page-grid">
    <article class="nexus-page-card">
      <h2>${t('page_builder.starter.card_one_title')}</h2>
      <p>${t('page_builder.starter.card_one_body')}</p>
    </article>
    <article class="nexus-page-card">
      <h2>${t('page_builder.starter.card_two_title')}</h2>
      <p>${t('page_builder.starter.card_two_body')}</p>
    </article>
  </div>
</section>
`;
}

export const DEFAULT_PAGE_CSS = `
.nexus-page-section{padding:clamp(3rem,6vw,6rem) 1.25rem;background:var(--background,#ffffff);color:var(--foreground,#111827)}
.nexus-page-section:nth-child(even){background:var(--surface-elevated,rgba(255,255,255,.9))}
.nexus-page-container{max-width:1120px;margin:0 auto}
.nexus-page-hero{background:linear-gradient(135deg,var(--surface-elevated,rgba(255,255,255,.9)) 0%,color-mix(in srgb,var(--accent-color,var(--color-accent,#0891b2)) 12%,var(--background,#ffffff)) 55%,color-mix(in srgb,var(--color-warning,#d97706) 10%,var(--background,#ffffff)) 100%);min-height:520px;display:flex;align-items:center}
.nexus-page-kicker{margin:0 0 1rem;text-transform:uppercase;font-size:.78rem;letter-spacing:.08em;font-weight:700;color:var(--accent-color,var(--color-accent,#0891b2))}
.nexus-page-hero h1{max-width:780px;margin:0;font-size:clamp(2.5rem,7vw,5.4rem);line-height:1.02;letter-spacing:0;color:var(--foreground,#111827)}
.nexus-page-lede{max-width:680px;margin:1.25rem 0 0;font-size:clamp(1.05rem,2vw,1.35rem);line-height:1.7;color:var(--foreground-muted,var(--foreground,#4b5563))}
.nexus-page-button{display:inline-flex;margin-top:2rem;border-radius:.65rem;background:var(--accent-color,var(--color-accent,#0891b2));color:var(--accent-foreground,#fff);padding:.85rem 1.15rem;font-weight:700;text-decoration:none}
.nexus-page-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1.25rem}
.nexus-page-card{border:1px solid var(--border-default,rgba(17,24,39,.12));border-radius:.75rem;background:var(--surface-elevated,rgba(255,255,255,.9));padding:1.5rem;box-shadow:var(--shadow-md,0 10px 30px rgba(15,23,42,.06));color:var(--foreground,#111827)}
.nexus-page-card h2{margin:0 0 .75rem;font-size:1.35rem;color:var(--foreground,#111827)}
.nexus-page-card p{margin:0;color:var(--foreground-muted,var(--foreground,#4b5563));line-height:1.65}
@media(max-width:760px){.nexus-page-grid{grid-template-columns:1fr}.nexus-page-hero{min-height:440px}}
`;

function resolvePlugin(plugin: unknown): PluginFn | null {
  if (typeof plugin === 'function') return plugin as PluginFn;
  const def = (plugin as { default?: unknown } | null)?.default;
  return typeof def === 'function' ? (def as PluginFn) : null;
}

function exportHtml(ed: Editor): string {
  const body = stripUnsafePageBuilderHtml(ed.getHtml() || '');
  const css = stripUnsafePageBuilderHtml(ed.getCss() || '');
  return `<style>${css}</style>${body}`;
}

function exportProject(ed: Editor): { html: string; designJson: string } {
  return {
    html: exportHtml(ed),
    designJson: JSON.stringify(ed.getProjectData()),
  };
}

function addNexusBlocks(ed: Editor, t: (key: string) => string): void {
  const bm = ed.BlockManager;
  const category = t('page_builder.blocks.category_nexus');
  bm.add('nexus-hero', {
    label: t('page_builder.blocks.hero_label'),
    category,
    content: `<section class="nexus-page-section nexus-page-hero"><div class="nexus-page-container"><p class="nexus-page-kicker">${t('page_builder.blocks.hero_kicker')}</p><h1>${t('page_builder.blocks.hero_title')}</h1><p class="nexus-page-lede">${t('page_builder.blocks.hero_body')}</p><a class="nexus-page-button" href="/">${t('page_builder.blocks.hero_cta')}</a></div></section>`,
  });
  bm.add('nexus-feature-grid', {
    label: t('page_builder.blocks.feature_grid_label'),
    category,
    content: `<section class="nexus-page-section"><div class="nexus-page-container nexus-page-grid"><article class="nexus-page-card"><h2>${t('page_builder.blocks.feature_one_title')}</h2><p>${t('page_builder.blocks.feature_one_body')}</p></article><article class="nexus-page-card"><h2>${t('page_builder.blocks.feature_two_title')}</h2><p>${t('page_builder.blocks.feature_two_body')}</p></article></div></section>`,
  });
  bm.add('nexus-story-band', {
    label: t('page_builder.blocks.story_band_label'),
    category,
    content: `<section class="nexus-page-section"><div class="nexus-page-container"><p class="nexus-page-kicker">${t('page_builder.blocks.story_kicker')}</p><h2>${t('page_builder.blocks.story_title')}</h2><p class="nexus-page-lede">${t('page_builder.blocks.story_body')}</p></div></section>`,
  });
}

function insertImage(ed: Editor, url: string): void {
  const selected = ed.getSelected() as unknown as GjsComp | undefined;
  const tag = ((selected?.get?.('tagName') as string) || (selected?.get?.('type') as string) || '').toLowerCase();
  if ((tag === 'img' || tag === 'image') && selected?.set) {
    selected.set('src', url);
    return;
  }
  const component = ed.addComponents({ type: 'image', attributes: { src: url, alt: '' } });
  const first = Array.isArray(component) ? component[0] : component;
  if (first) ed.select(first);
}

export const PageDesignBuilder = forwardRef<PageDesignBuilderHandle, PageDesignBuilderProps>(function PageDesignBuilder(
  { html, designJson, readOnly, onChange },
  ref,
) {
  const { t } = useTranslation('admin_editor');
  const toast = useToast();
  const confirm = useConfirm();

  const canvasRef = useRef<HTMLDivElement>(null);
  const blocksRef = useRef<HTMLDivElement>(null);
  const stylesRef = useRef<HTMLDivElement>(null);
  const traitsRef = useRef<HTMLDivElement>(null);
  const layersRef = useRef<HTMLDivElement>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const editorRef = useRef<Editor | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const onChangeRef = useRef(onChange);
  const seedRef = useRef(designJson);
  const htmlSeedRef = useRef(html);
  const lastUploadRef = useRef<string | null>(null);
  onChangeRef.current = onChange;

  const [editor, setEditor] = useState<Editor | null>(null);
  const [failed, setFailed] = useState(false);
  const [legacyNotice, setLegacyNotice] = useState(false);
  const [device, setDevice] = useState<BuilderDevice>('Desktop');
  const [showBorders, setShowBorders] = useState(false);
  const [canUndo, setCanUndo] = useState(false);
  const [canRedo, setCanRedo] = useState(false);
  const [hasSelection, setHasSelection] = useState(false);
  const [activeTab, setActiveTab] = useState<InspectorTab>('style');
  const [codeOpen, setCodeOpen] = useState(false);
  const [codeHtml, setCodeHtml] = useState('');
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewHtml, setPreviewHtml] = useState('');
  const [libraryOpen, setLibraryOpen] = useState(false);
  const [paletteCollapsed, setPaletteCollapsed] = useState(false);
  const [inspectorCollapsed, setInspectorCollapsed] = useState(false);
  const [insertingImage, setInsertingImage] = useState(false);

  const syncUndoState = () => {
    const um = editorRef.current?.UndoManager as unknown as UndoLike | undefined;
    setCanUndo(Boolean(um?.hasUndo?.()));
    setCanRedo(Boolean(um?.hasRedo?.()));
  };

  const flush = () => {
    const current = editorRef.current;
    if (!current) return null;
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
      debounceRef.current = null;
    }
    const payload = exportProject(current);
    onChangeRef.current(payload);
    return payload;
  };

  useImperativeHandle(ref, () => ({ flush }), []);

  useEffect(() => {
    if (editorRef.current) return undefined;
    if (!canvasRef.current || !blocksRef.current || !stylesRef.current || !traitsRef.current || !layersRef.current) {
      return undefined;
    }

    const plugins = [presetWebpage, blocksBasic, formsPlugin, tabsPlugin, tooltipPlugin, customCodePlugin]
      .map(resolvePlugin)
      .filter((plugin): plugin is PluginFn => Boolean(plugin));

    try {
      const ed = grapesjs.init({
        container: canvasRef.current,
        height: '100%',
        width: '100%',
        fromElement: false,
        storageManager: { type: 'none' },
        undoManager: { trackSelection: false },
        panels: { defaults: [] },
        blockManager: { appendTo: blocksRef.current },
        styleManager: { appendTo: stylesRef.current },
        traitManager: { appendTo: traitsRef.current },
        layerManager: { appendTo: layersRef.current },
        deviceManager: {
          devices: [
            { name: 'Desktop', width: '' },
            { name: 'Tablet', width: '768px', widthMedia: '992px' },
            { name: 'Mobile portrait', width: '375px', widthMedia: '480px' },
          ],
        },
        canvas: {
          styles: [],
          scripts: [],
        },
        plugins: plugins.map((plugin) => (plugEd: Editor) => plugin(plugEd, {
          blocksBasicOpts: { flexGrid: true },
          navbarOpts: false,
          countdownOpts: false,
          formsOpts: {},
        })),
        assetManager: {
          uploadFile: async (ev: Event) => {
            const target = ev.target as HTMLInputElement | null;
            const dropped = (ev as DragEvent).dataTransfer?.files;
            const file = (dropped && dropped[0]) || target?.files?.[0];
            if (!file) return;
            try {
              const url = resolveUploadedUrl(await adminBuilderAssets.uploadImage(file));
              if (!url) {
                toast.error(t('page_builder.image_upload_failed'));
                return;
              }
              applyServerImage(url);
            } catch (err) {
              logError('PageDesignBuilder: asset upload failed', err);
              toast.error(t('page_builder.image_upload_failed'));
            }
          },
        },
      });

      editorRef.current = ed;
      addNexusBlocks(ed, t);
      ed.Css.setRule('.nexus-page-section', {});
      ed.addStyle(DEFAULT_PAGE_CSS);

      let restored = false;
      if (seedRef.current) {
        try {
          (ed.loadProjectData as (d: unknown) => void)(JSON.parse(seedRef.current));
          restored = true;
        } catch (err) {
          logError('PageDesignBuilder: could not restore design_json', err);
          setLegacyNotice(true);
        }
      }

      if (!restored) {
        const seed = htmlSeedRef.current.trim();
        if (seed) {
          const doc = new DOMParser().parseFromString(seed, 'text/html');
          const styleText = Array.from(doc.querySelectorAll('style')).map((style) => style.textContent || '').join('\n');
          doc.querySelectorAll('style, script').forEach((node) => node.remove());
          ed.setComponents(doc.body.innerHTML || pageStarterHtml(t));
          ed.addStyle(styleText || DEFAULT_PAGE_CSS);
        } else {
          ed.setComponents(pageStarterHtml(t));
        }
      }

      const scheduleEmit = () => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
          const current = editorRef.current;
          if (!current) return;
          try {
            onChangeRef.current(exportProject(current));
          } catch (err) {
            logError('PageDesignBuilder: export failed', err);
          }
        }, AUTOSAVE_DEBOUNCE_MS);
      };

      const handleUpdate = () => {
        syncUndoState();
        scheduleEmit();
      };
      const syncSelection = () => setHasSelection(Boolean(editorRef.current?.getSelected()));
      const syncDevice = () => setDevice((editorRef.current?.getDevice() as BuilderDevice) ?? 'Desktop');
      const handleComponentAdd = (comp: unknown) => {
        const c = comp as GjsComp | undefined;
        const src = c?.get?.('src') ?? (c?.get?.('attributes') as { src?: string } | undefined)?.src;
        if (isEphemeralSrc(src) && lastUploadRef.current && c?.set) {
          c.set('src', lastUploadRef.current);
        }
      };

      ed.on('update', handleUpdate);
      ed.on('component:selected component:deselected', syncSelection);
      ed.on('change:device', syncDevice);
      ed.on('component:add', handleComponentAdd);
      setEditor(ed);
      syncUndoState();
      scheduleEmit();

      return () => {
        ed.off('update', handleUpdate);
        ed.off('component:selected component:deselected', syncSelection);
        ed.off('change:device', syncDevice);
        ed.off('component:add', handleComponentAdd);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        try {
          ed.destroy();
        } catch {
          /* already destroyed */
        }
        editorRef.current = null;
      };
    } catch (err) {
      logError('PageDesignBuilder: GrapesJS init failed', err);
      setFailed(true);
      return undefined;
    }
  }, [t, toast]);

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
    setShowBorders((value) => !value);
  };
  const handleViewCode = () => {
    const ed = editorRef.current;
    if (!ed) return;
    const exported = exportHtml(ed);
    setCodeHtml(exported);
    setCodeOpen(true);
  };
  const handlePreview = () => {
    const ed = editorRef.current;
    if (!ed) return;
    setPreviewHtml(exportHtml(ed));
    setPreviewOpen(true);
  };
  const handleClear = async () => {
    const ok = await confirm({
      title: t('page_builder.reset'),
      body: t('page_builder.reset_confirm'),
      status: 'warning',
      confirmLabel: t('page_builder.reset'),
    });
    if (!ok) return;
    editorRef.current?.setComponents(pageStarterHtml(t));
    editorRef.current?.setStyle(DEFAULT_PAGE_CSS);
    setLegacyNotice(false);
  };
  const copyCode = async () => {
    try {
      await navigator.clipboard.writeText(codeHtml);
      toast.success(t('page_builder.code_copied'));
    } catch {
      /* clipboard blocked */
    }
  };
  const applyServerImage = (url: string) => {
    const ed = editorRef.current;
    if (!ed) return;
    lastUploadRef.current = url;
    try {
      ed.AssetManager?.add?.(url);
    } catch {
      /* best effort */
    }
    insertImage(ed, url);
  };
  const handleImageFile = async (ev: React.ChangeEvent<HTMLInputElement>) => {
    const file = ev.target.files?.[0];
    ev.target.value = '';
    if (!file) return;
    setInsertingImage(true);
    try {
      const url = resolveUploadedUrl(await adminBuilderAssets.uploadImage(file));
      if (url) applyServerImage(url);
      else toast.error(t('page_builder.image_upload_failed'));
    } catch (err) {
      logError('PageDesignBuilder: image upload failed', err);
      toast.error(t('page_builder.image_upload_failed'));
    } finally {
      setInsertingImage(false);
    }
  };

  if (failed) {
    return (
      <div className="rounded-lg border-2 border-dashed border-border p-8 text-center text-sm text-muted">
        {t('page_builder.builder_error')}
      </div>
    );
  }

  return (
    <div className="nb-root relative flex h-[760px] flex-col overflow-hidden rounded-lg border-2 border-border bg-surface">
      <BuilderToolbar
        ready={Boolean(editor)}
        readOnly={readOnly}
        device={device}
        showBorders={showBorders}
        canUndo={canUndo}
        canRedo={canRedo}
        insertingImage={insertingImage}
        onUndo={handleUndo}
        onRedo={handleRedo}
        onSetDevice={handleSetDevice}
        onToggleBorders={handleToggleBorders}
        onInsertImage={() => fileRef.current?.click()}
        onOpenLibrary={() => setLibraryOpen(true)}
        onOpenTemplates={() => undefined}
        onPreview={handlePreview}
        onViewCode={handleViewCode}
        onClear={handleClear}
        t={t}
        labels={{
          toolbarLabel: t('page_builder.toolbar_label'),
          deviceDesktop: t('page_builder.tip_device_desktop'),
          deviceTablet: t('page_builder.tip_device_tablet'),
          deviceMobile: t('page_builder.tip_device_mobile'),
          undo: t('page_builder.tip_undo'),
          redo: t('page_builder.tip_redo'),
          borders: t('page_builder.tip_borders'),
          code: t('page_builder.tip_code'),
          insertImage: t('page_builder.insert_image'),
          library: t('page_builder.library_title'),
          preview: t('page_builder.preview'),
          templates: t('page_builder.templates'),
          clear: t('page_builder.tip_clear'),
        }}
      />

      {legacyNotice && (
        <div className="flex items-start gap-2 border-b border-warning/40 bg-warning/10 px-3 py-2 text-xs text-foreground">
          <Info size={14} className="mt-0.5 shrink-0 text-warning" aria-hidden="true" />
          <span className="flex-1">{t('page_builder.legacy_notice')}</span>
          <button
            type="button"
            onClick={() => setLegacyNotice(false)}
            aria-label={t('page_builder.dismiss')}
            className="shrink-0 text-muted hover:text-foreground"
          >
            <X size={14} />
          </button>
        </div>
      )}

      <div className="relative flex min-h-0 flex-1">
        <BuilderBlockPalette
          blocksRef={blocksRef}
          title={t('page_builder.palette_title')}
          collapsed={paletteCollapsed}
          onToggleCollapse={() => setPaletteCollapsed((value) => !value)}
          expandLabel={t('page_builder.show_blocks')}
          collapseLabel={t('page_builder.hide_blocks')}
        />
        <main className="nb-canvas relative min-w-0 flex-1 overflow-hidden">
          <div ref={canvasRef} className="h-full w-full" />
        </main>
        <BuilderInspector
          stylesRef={stylesRef}
          traitsRef={traitsRef}
          layersRef={layersRef}
          activeTab={activeTab}
          onTabChange={setActiveTab}
          hasSelection={hasSelection}
          collapsed={inspectorCollapsed}
          onToggleCollapse={() => setInspectorCollapsed((value) => !value)}
          expandLabel={t('page_builder.show_inspector')}
          collapseLabel={t('page_builder.hide_inspector')}
          labels={{
            ariaLabel: t('page_builder.inspector_label'),
            style: t('page_builder.inspector_style'),
            settings: t('page_builder.inspector_settings'),
            layers: t('page_builder.inspector_layers'),
            empty: t('page_builder.empty_inspector'),
          }}
        />
        {readOnly && (
          <div
            role="status"
            aria-live="polite"
            aria-label={t('saving')}
            className="absolute inset-0 z-20 flex items-start justify-center bg-surface/35 px-4 pt-4 backdrop-blur-[1px]"
          >
            <span className="rounded-full border border-border bg-surface px-3 py-1 text-xs font-medium text-muted shadow-sm">
              {t('saving')}
            </span>
          </div>
        )}
      </div>

      <input
        ref={fileRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleImageFile}
        aria-hidden="true"
        tabIndex={-1}
      />

      {libraryOpen && (
        <Suspense fallback={null}>
          <AssetLibraryModal
            isOpen={libraryOpen}
            onClose={() => setLibraryOpen(false)}
            onSelect={applyServerImage}
            labels={{
              title: t('page_builder.library_title'),
              upload: t('page_builder.library_upload'),
              empty: t('page_builder.library_empty'),
              loadFailed: t('page_builder.library_failed'),
              uploadFailed: t('page_builder.image_upload_failed'),
            }}
          />
        </Suspense>
      )}
      <BuilderPreviewModal
        isOpen={previewOpen}
        onClose={() => setPreviewOpen(false)}
        html={previewHtml}
        t={t}
        labels={{
          title: t('page_builder.preview_title'),
          deviceLabel: t('page_builder.preview_device_label'),
          desktop: t('page_builder.preview_desktop'),
          mobile: t('page_builder.preview_mobile'),
        }}
      />
      <Modal isOpen={codeOpen} onOpenChange={setCodeOpen} size="3xl" scrollBehavior="inside">
        <ModalContent>
          <ModalHeader>{t('page_builder.code_modal_title')}</ModalHeader>
          <ModalBody>
            <pre className="max-h-[60vh] overflow-auto rounded-lg border border-border bg-surface p-3 text-xs text-foreground">
              <code>{codeHtml}</code>
            </pre>
          </ModalBody>
          <ModalFooter>
            <Button variant="primary" size="sm" startContent={<Copy size={14} />} onPress={copyCode}>
              {t('page_builder.code_copy')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
});

export default PageDesignBuilder;
