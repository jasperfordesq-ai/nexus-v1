// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LegalNoticeNode — Custom Lexical ElementNode for <div class="legal-notice"> callout boxes.
 *
 * Handles the full import/export roundtrip so the amber notice boxes in legal documents
 * survive editing via LegalDocEditor. Without this node, Lexical's generic <div> handler
 * (priority 0) would discard the legal-notice class on import.
 *
 * - importDOM: matches <div class="legal-notice">, priority 1 (beats generic div handler)
 * - exportDOM: outputs <div class="legal-notice">
 * - createDOM: renders an amber callout in the editor canvas so authors see it styled
 */

import {
  ElementNode,
  createCommand,
  type DOMConversionMap,
  type DOMExportOutput,
  type DOMConversionOutput,
  type EditorConfig,
  type LexicalNode,
  type NodeKey,
  type SerializedElementNode,
  type LexicalCommand,
} from 'lexical';

export type SerializedLegalNoticeNode = SerializedElementNode & {
  type: 'legal-notice';
  version: 1;
};

/** Dispatched by the toolbar "Notice Box" button. Handled by LegalNoticePlugin. */
export const INSERT_LEGAL_NOTICE_COMMAND: LexicalCommand<undefined> =
  createCommand('INSERT_LEGAL_NOTICE_COMMAND');

export class LegalNoticeNode extends ElementNode {
  constructor(key?: NodeKey) {
    super(key);
  }

  static getType(): string {
    return 'legal-notice';
  }

  static clone(node: LegalNoticeNode): LegalNoticeNode {
    return new LegalNoticeNode(node.__key);
  }

  /** Amber callout box visible in the Lexical editor canvas. */
  createDOM(_config: EditorConfig): HTMLElement {
    const div = document.createElement('div');
    div.className =
      'border-l-4 border-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded-r-lg px-4 py-3 my-3';
    return div;
  }

  updateDOM(): boolean {
    return false;
  }

  /**
   * Matches <div class="legal-notice"> during HTML → Lexical import.
   * Priority 1 ensures this runs before Lexical's built-in generic div handler (priority 0).
   */
  static importDOM(): DOMConversionMap | null {
    return {
      div: (node: HTMLElement) => {
        if (node.classList.contains('legal-notice')) {
          return {
            conversion: convertLegalNoticeElement,
            priority: 1,
          };
        }
        return null;
      },
    };
  }

  /** Outputs <div class="legal-notice"> during Lexical → HTML export. */
  exportDOM(): DOMExportOutput {
    const element = document.createElement('div');
    element.className = 'legal-notice';
    return { element };
  }

  static importJSON(_serializedNode: SerializedLegalNoticeNode): LegalNoticeNode {
    return $createLegalNoticeNode();
  }

  exportJSON(): SerializedLegalNoticeNode {
    return {
      ...super.exportJSON(),
      type: 'legal-notice',
      version: 1,
    };
  }

  isShadowRoot(): boolean {
    return false;
  }
}

function convertLegalNoticeElement(_element: HTMLElement): DOMConversionOutput {
  return { node: $createLegalNoticeNode() };
}

export function $createLegalNoticeNode(): LegalNoticeNode {
  return new LegalNoticeNode();
}

export function $isLegalNoticeNode(
  node: LexicalNode | null | undefined,
): node is LegalNoticeNode {
  return node instanceof LegalNoticeNode;
}
