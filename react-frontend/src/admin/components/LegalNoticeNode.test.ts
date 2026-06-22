// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Unit tests for LegalNoticeNode.
 *
 * Lexical node constructors require an active EditorState (they call
 * getActiveEditorState() internally). Tests that need an instance are run
 * inside createEditor() + editor.update() so Lexical's invariant is satisfied.
 *
 * Tests that only exercise static methods, exported constants, or DOM helpers
 * run without an editor context and are the majority here.
 */

import { describe, it, expect } from 'vitest';
import { createEditor } from 'lexical';

import {
  LegalNoticeNode,
  $createLegalNoticeNode,
  $isLegalNoticeNode,
  INSERT_LEGAL_NOTICE_COMMAND,
  type SerializedLegalNoticeNode,
} from './LegalNoticeNode';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Run a callback inside a Lexical editor update so node constructors work. */
function withEditor(fn: () => void): void {
  const editor = createEditor({
    nodes: [LegalNoticeNode],
    onError: (err) => { throw err; },
  });
  // editor.update() requires a DOM container to be set in some versions;
  // we use a detached div so jsdom satisfies any internal check.
  const div = document.createElement('div');
  editor.setRootElement(div);
  editor.update(fn, { discrete: true });
}

// ---------------------------------------------------------------------------
// Static / pure exports
// ---------------------------------------------------------------------------

describe('LegalNoticeNode.getType()', () => {
  it('returns "legal-notice"', () => {
    expect(LegalNoticeNode.getType()).toBe('legal-notice');
  });
});

describe('INSERT_LEGAL_NOTICE_COMMAND', () => {
  it('is defined and has the expected type tag', () => {
    // LexicalCommand objects have a `type` property set by createCommand().
    expect(INSERT_LEGAL_NOTICE_COMMAND).toBeDefined();
    expect((INSERT_LEGAL_NOTICE_COMMAND as { type: string }).type).toBe(
      'INSERT_LEGAL_NOTICE_COMMAND',
    );
  });
});

// ---------------------------------------------------------------------------
// importDOM — pure mapping function, no editor needed
// ---------------------------------------------------------------------------

describe('LegalNoticeNode.importDOM()', () => {
  it('returns a conversion map with a "div" entry', () => {
    const map = LegalNoticeNode.importDOM();
    expect(map).not.toBeNull();
    expect(typeof map!.div).toBe('function');
  });

  it('returns a conversion descriptor for a <div class="legal-notice"> element', () => {
    const map = LegalNoticeNode.importDOM()!;
    const div = document.createElement('div');
    div.className = 'legal-notice';
    const descriptor = map.div(div);
    expect(descriptor).not.toBeNull();
    expect(typeof descriptor!.conversion).toBe('function');
    expect(descriptor!.priority).toBe(1);
  });

  it('returns null for a plain <div> without the class', () => {
    const map = LegalNoticeNode.importDOM()!;
    const div = document.createElement('div');
    div.className = 'some-other-class';
    expect(map.div(div)).toBeNull();
  });

  it('returns null for a <div> with no class attribute', () => {
    const map = LegalNoticeNode.importDOM()!;
    const div = document.createElement('div');
    expect(map.div(div)).toBeNull();
  });
});

// ---------------------------------------------------------------------------
// createDOM / exportDOM — DOM helpers, no editor needed
// ---------------------------------------------------------------------------

describe('LegalNoticeNode#createDOM()', () => {
  it('returns an HTMLElement (a div)', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      // createDOM requires an EditorConfig-like argument; we pass a minimal stub.
      const el = node.createDOM({} as Parameters<typeof node.createDOM>[0]);
      expect(el.tagName).toBe('DIV');
    });
  });

  it('gives the editor canvas div its amber callout classes', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      const el = node.createDOM({} as Parameters<typeof node.createDOM>[0]);
      // The class must contain the amber border colour that signals a legal-notice box.
      expect(el.className).toContain('border-amber-400');
      expect(el.className).toContain('bg-amber-50');
    });
  });
});

describe('LegalNoticeNode#exportDOM()', () => {
  it('returns an element tagged as a div', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      const { element } = node.exportDOM();
      expect(element).toBeDefined();
      expect(element!.tagName).toBe('DIV');
    });
  });

  it('gives the exported div the "legal-notice" class (HTML round-trip fidelity)', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      const { element } = node.exportDOM();
      expect(element!.className).toBe('legal-notice');
    });
  });
});

// ---------------------------------------------------------------------------
// updateDOM — pure method, returns boolean
// ---------------------------------------------------------------------------

describe('LegalNoticeNode#updateDOM()', () => {
  it('always returns false (DOM never needs reconciliation)', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      // updateDOM(prevNode, dom, config) — pass stubs; return value is all that matters.
      const result = node.updateDOM(
        node,
        document.createElement('div'),
        {} as Parameters<typeof node.updateDOM>[2],
      );
      expect(result).toBe(false);
    });
  });
});

// ---------------------------------------------------------------------------
// isShadowRoot
// ---------------------------------------------------------------------------

describe('LegalNoticeNode#isShadowRoot()', () => {
  it('returns false', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      expect(node.isShadowRoot()).toBe(false);
    });
  });
});

// ---------------------------------------------------------------------------
// exportJSON — verifies shape of the serialised form
// ---------------------------------------------------------------------------

describe('LegalNoticeNode#exportJSON()', () => {
  it('sets type to "legal-notice" and version to 1', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      const json = node.exportJSON();
      expect(json.type).toBe('legal-notice');
      expect(json.version).toBe(1);
    });
  });
});

// ---------------------------------------------------------------------------
// importJSON round-trip via exportJSON
// ---------------------------------------------------------------------------

describe('LegalNoticeNode.importJSON()', () => {
  it('produces a LegalNoticeNode that exports back to the same type/version', () => {
    withEditor(() => {
      const original = $createLegalNoticeNode();
      const serialized = original.exportJSON() as SerializedLegalNoticeNode;

      const restored = LegalNoticeNode.importJSON(serialized);

      expect(restored.exportJSON().type).toBe('legal-notice');
      expect(restored.exportJSON().version).toBe(1);
    });
  });
});

// ---------------------------------------------------------------------------
// $createLegalNoticeNode
// ---------------------------------------------------------------------------

describe('$createLegalNoticeNode()', () => {
  it('returns an instance of LegalNoticeNode', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      expect(node).toBeInstanceOf(LegalNoticeNode);
    });
  });
});

// ---------------------------------------------------------------------------
// $isLegalNoticeNode
// ---------------------------------------------------------------------------

describe('$isLegalNoticeNode()', () => {
  it('returns true for a LegalNoticeNode instance', () => {
    withEditor(() => {
      const node = $createLegalNoticeNode();
      expect($isLegalNoticeNode(node)).toBe(true);
    });
  });

  it('returns false for null', () => {
    expect($isLegalNoticeNode(null)).toBe(false);
  });

  it('returns false for undefined', () => {
    expect($isLegalNoticeNode(undefined)).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// LegalNoticeNode.clone
// ---------------------------------------------------------------------------

describe('LegalNoticeNode.clone()', () => {
  it('returns a new LegalNoticeNode', () => {
    withEditor(() => {
      const original = $createLegalNoticeNode();
      const cloned = LegalNoticeNode.clone(original);
      expect(cloned).toBeInstanceOf(LegalNoticeNode);
    });
  });

  it('cloned node preserves the same Lexical key as the original', () => {
    withEditor(() => {
      const original = $createLegalNoticeNode();
      const cloned = LegalNoticeNode.clone(original);
      // clone() passes node.__key explicitly, so both should share the key.
      expect(cloned.getKey()).toBe(original.getKey());
    });
  });
});
