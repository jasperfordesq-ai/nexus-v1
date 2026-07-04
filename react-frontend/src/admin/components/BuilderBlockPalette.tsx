// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuilderBlockPalette — the always-visible left column of the newsletter Design
 * builder. It is a thin host: GrapesJS renders its MJML BlockManager (the
 * draggable hero/text/button/image/columns/… blocks) into `blocksRef` via the
 * editor's `blockManager.appendTo`. We only supply the labelled, scrollable
 * container so the palette can never be stranded behind a hidden toggle again.
 */

import type { RefObject } from 'react';

interface BuilderBlockPaletteProps {
  /** GrapesJS appends its BlockManager here (blockManager.appendTo). */
  blocksRef: RefObject<HTMLDivElement | null>;
  title: string;
}

export function BuilderBlockPalette({ blocksRef, title }: BuilderBlockPaletteProps) {
  return (
    <aside className="nb-palette flex w-56 shrink-0 flex-col border-r border-border bg-surface" aria-label={title}>
      <div className="border-b border-border px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted">
        {title}
      </div>
      <div ref={blocksRef} className="flex-1 overflow-y-auto" />
    </aside>
  );
}

export default BuilderBlockPalette;
