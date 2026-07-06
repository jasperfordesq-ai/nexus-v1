// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuilderInspector - the right column for GrapesJS design builders. It hosts
 * three GrapesJS managers (Style / Settings-traits / Layers) that the editor
 * appends into `stylesRef` / `traitsRef` / `layersRef` at init time.
 *
 * IMPORTANT: all three manager nodes stay PERMANENTLY MOUNTED — we switch tabs
 * with `hidden` (display:none), never by conditionally rendering. HeroUI Tabs'
 * own panel content unmounts inactive panels, which would detach a manager's
 * DOM after GrapesJS appended to it; so Tabs here is a pure visibility selector.
 * The whole panel also collapses to a slim rail to free canvas width — again by
 * hiding, never unmounting, the manager nodes.
 */

import { Tabs, Tab } from '@/components/ui';
import type { RefObject } from 'react';
import PanelRightClose from 'lucide-react/icons/panel-right-close';
import PanelRightOpen from 'lucide-react/icons/panel-right-open';

export type InspectorTab = 'style' | 'settings' | 'layers';

interface BuilderInspectorProps {
  stylesRef: RefObject<HTMLDivElement | null>;
  traitsRef: RefObject<HTMLDivElement | null>;
  layersRef: RefObject<HTMLDivElement | null>;
  activeTab: InspectorTab;
  onTabChange: (tab: InspectorTab) => void;
  /** True when a canvas element is selected (Style/Settings act on selection). */
  hasSelection: boolean;
  collapsed?: boolean;
  onToggleCollapse?: () => void;
  expandLabel: string;
  collapseLabel: string;
  labels: {
    ariaLabel: string;
    style: string;
    settings: string;
    layers: string;
    empty: string;
  };
}

export function BuilderInspector({
  stylesRef,
  traitsRef,
  layersRef,
  activeTab,
  onTabChange,
  hasSelection,
  collapsed,
  onToggleCollapse,
  expandLabel,
  collapseLabel,
  labels,
}: BuilderInspectorProps) {
  const showEmptyHint = !hasSelection && activeTab !== 'layers';

  return (
    <aside className={`flex shrink-0 flex-col border-l border-border bg-surface ${collapsed ? 'w-9' : 'w-72'}`}>
      <div className="flex items-center gap-1 border-b border-border px-2 py-1.5">
        {onToggleCollapse && (
          <button
            type="button"
            onClick={onToggleCollapse}
            aria-label={collapsed ? expandLabel : collapseLabel}
            className="rounded p-1 text-muted hover:bg-surface-secondary hover:text-foreground"
          >
            {collapsed ? <PanelRightOpen size={16} /> : <PanelRightClose size={16} />}
          </button>
        )}
        {!collapsed && (
          <Tabs
            selectedKey={activeTab}
            onSelectionChange={(key) => onTabChange(key as InspectorTab)}
            aria-label={labels.ariaLabel}
            size="sm"
          >
            <Tab key="style" id="style" title={labels.style} />
            <Tab key="settings" id="settings" title={labels.settings} />
            <Tab key="layers" id="layers" title={labels.layers} />
          </Tabs>
        )}
      </div>
      {collapsed && (
        <div className="flex flex-1 justify-center pt-3">
          <span className="pointer-events-none select-none text-[10px] font-semibold uppercase tracking-wide text-muted [writing-mode:vertical-rl] rotate-180">
            {labels.ariaLabel}
          </span>
        </div>
      )}
      <div className={`relative flex-1 overflow-y-auto ${collapsed ? 'hidden' : ''}`}>
        {showEmptyHint && (
          <p className="px-4 py-6 text-center text-xs text-muted">{labels.empty}</p>
        )}
        {/* All three stay mounted; toggle visibility only (see file header). */}
        <div ref={stylesRef} hidden={activeTab !== 'style'} />
        <div ref={traitsRef} hidden={activeTab !== 'settings'} />
        <div ref={layersRef} hidden={activeTab !== 'layers'} />
      </div>
    </aside>
  );
}

export default BuilderInspector;
