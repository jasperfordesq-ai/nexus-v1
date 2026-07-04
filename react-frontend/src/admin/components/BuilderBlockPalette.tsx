// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuilderBlockPalette — the always-available left column of the newsletter
 * Design builder. It is a thin host: GrapesJS renders its MJML BlockManager (the
 * draggable text/button/image/columns/… blocks) into `blocksRef` via the
 * editor's `blockManager.appendTo`. We only supply the labelled, scrollable
 * container so the palette can never be stranded behind a hidden toggle again.
 *
 * The panel collapses to a slim rail (a chevron) so the canvas can take the full
 * width when the author wants room — the blocks DOM stays mounted either way.
 */

import type { RefObject } from 'react';
import PanelLeftClose from 'lucide-react/icons/panel-left-close';
import PanelLeftOpen from 'lucide-react/icons/panel-left-open';

interface BuilderBlockPaletteProps {
  /** GrapesJS appends its BlockManager here (blockManager.appendTo). */
  blocksRef: RefObject<HTMLDivElement | null>;
  title: string;
  collapsed?: boolean;
  onToggleCollapse?: () => void;
  expandLabel: string;
  collapseLabel: string;
}

export function BuilderBlockPalette({
  blocksRef,
  title,
  collapsed,
  onToggleCollapse,
  expandLabel,
  collapseLabel,
}: BuilderBlockPaletteProps) {
  return (
    <aside
      className={`nb-palette flex shrink-0 flex-col border-r border-border bg-surface ${collapsed ? 'w-9' : 'w-52'}`}
      aria-label={title}
    >
      <div className="flex items-center justify-between border-b border-border px-2 py-2">
        {!collapsed && (
          <span className="truncate text-xs font-semibold uppercase tracking-wide text-muted">{title}</span>
        )}
        {onToggleCollapse && (
          <button
            type="button"
            onClick={onToggleCollapse}
            aria-label={collapsed ? expandLabel : collapseLabel}
            className="rounded p-1 text-muted hover:bg-surface-secondary hover:text-foreground"
          >
            {collapsed ? <PanelLeftOpen size={16} /> : <PanelLeftClose size={16} />}
          </button>
        )}
      </div>
      {/* Keep the blocks host mounted even when collapsed (GrapesJS owns its DOM). */}
      <div ref={blocksRef} className={`flex-1 overflow-y-auto ${collapsed ? 'hidden' : ''}`} />
    </aside>
  );
}

export default BuilderBlockPalette;
