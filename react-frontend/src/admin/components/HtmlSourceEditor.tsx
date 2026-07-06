// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HtmlSourceEditor - verbatim raw-HTML editor (CodeMirror 6).
 *
 * This mode lets admins paste or author designed HTML without round-tripping
 * through Lexical. CodeMirror holds the document as a plain string, so tables,
 * inline styles, custom sections, and pasted page fragments stay intact.
 * Lazy-loaded so the editor chunk only ships when HTML mode is opened.
 */

import { useRef } from 'react';
import CodeMirror, { type ReactCodeMirrorRef } from '@uiw/react-codemirror';
import { html as htmlLang } from '@codemirror/lang-html';
import { EditorView } from '@codemirror/view';
import { InsertImageButton } from './InsertImageButton';

interface HtmlSourceEditorProps {
  value: string;
  onChange: (html: string) => void;
  isDisabled?: boolean;
  labels: {
    label: string;
    hint: string;
    insertImage: string;
    uploadFailed: string;
  };
}

// Theme mapped to the project's CSS tokens so it respects light/dark mode
// without leaking global CSS.
const nexusTheme = EditorView.theme({
  '&': {
    fontSize: '13px',
    backgroundColor: 'var(--color-surface, #fff)',
    color: 'var(--color-foreground, #111)',
    borderRadius: '0 0 0.5rem 0.5rem',
  },
  '.cm-gutters': {
    backgroundColor: 'var(--color-surface-secondary, #f5f5f5)',
    color: 'var(--color-muted, #888)',
    border: 'none',
  },
  '.cm-content': { fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace" },
  '&.cm-focused': { outline: 'none' },
});

export function HtmlSourceEditor({ value, onChange, isDisabled, labels }: HtmlSourceEditorProps) {
  const cmRef = useRef<ReactCodeMirrorRef>(null);

  const insertAtCursor = (snippet: string) => {
    const view = cmRef.current?.view;
    if (!view) {
      // Fallback: append if the editor view isn't ready.
      onChange(value + snippet);
      return;
    }
    view.dispatch(view.state.replaceSelection(snippet));
    view.focus();
  };

  return (
    <div className="flex flex-col gap-1.5">
      <label className="text-sm font-medium text-foreground">
        {labels.label}
      </label>
      <div className="rounded-lg border-2 border-border focus-within:border-accent transition-colors overflow-hidden">
        <div className="flex flex-wrap items-center gap-1 border-b border-border bg-surface px-2 py-1.5">
          <InsertImageButton
            onInsert={insertAtCursor}
            isDisabled={isDisabled}
            labels={{
              insertImage: labels.insertImage,
              uploadFailed: labels.uploadFailed,
            }}
          />
          <span className="ml-auto text-xs text-muted">
            {labels.hint}
          </span>
        </div>
        <CodeMirror
          ref={cmRef}
          value={value}
          onChange={onChange}
          editable={!isDisabled}
          readOnly={isDisabled}
          height="400px"
          extensions={[htmlLang(), EditorView.lineWrapping, nexusTheme]}
          basicSetup={{
            lineNumbers: true,
            foldGutter: true,
            highlightActiveLine: !isDisabled,
            autocompletion: true,
          }}
          aria-label={labels.label}
        />
      </div>
    </div>
  );
}

export default HtmlSourceEditor;
