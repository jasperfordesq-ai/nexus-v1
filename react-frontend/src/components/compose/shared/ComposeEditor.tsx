// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ComposeEditor — Compact Lexical rich text editor for social post composition.
 * Lighter version of the admin RichTextEditor with a minimal toolbar:
 * Bold, Italic, Underline, Bullet list, Numbered list, Link insert/remove.
 * Outputs sanitized HTML and optionally reports plain text for character counting.
 */

import { useCallback, useEffect, useState } from 'react';
import { LexicalComposer } from '@lexical/react/LexicalComposer';
import { RichTextPlugin } from '@lexical/react/LexicalRichTextPlugin';
import { ContentEditable } from '@lexical/react/LexicalContentEditable';
import { HistoryPlugin } from '@lexical/react/LexicalHistoryPlugin';
import { ListPlugin } from '@lexical/react/LexicalListPlugin';
import { LinkPlugin } from '@lexical/react/LexicalLinkPlugin';
import { OnChangePlugin } from '@lexical/react/LexicalOnChangePlugin';
import { useLexicalComposerContext } from '@lexical/react/LexicalComposerContext';
import { $generateHtmlFromNodes, $generateNodesFromDOM } from '@lexical/html';
import { ListItemNode, ListNode } from '@lexical/list';
import { AutoLinkNode, LinkNode } from '@lexical/link';
import {
  $getSelection,
  $isRangeSelection,
  FORMAT_TEXT_COMMAND,
  $getRoot,
  type EditorState,
  type LexicalEditor,
} from 'lexical';
import {
  INSERT_ORDERED_LIST_COMMAND,
  INSERT_UNORDERED_LIST_COMMAND,
  REMOVE_LIST_COMMAND,
} from '@lexical/list';
import { TOGGLE_LINK_COMMAND, $isLinkNode } from '@lexical/link';
import { $getNearestNodeOfType } from '@lexical/utils';
import { $isListNode, ListNode as ListNodeClass } from '@lexical/list';
import { Button } from '@heroui/react';
import {
  Bold,
  Italic,
  Underline,
  List,
  ListOrdered,
  Link2,
} from 'lucide-react';

/* ───────────────────────── Types ───────────────────────── */

export interface ComposeEditorProps {
  /** Current HTML content */
  value: string;
  /** Callback when content changes (receives HTML string) */
  onChange: (html: string) => void;
  /** Callback with plain text content for character counting */
  onPlainTextChange?: (plainText: string) => void;
  /** Placeholder text */
  placeholder?: string;
  /** Optional character limit (on plain text, not HTML) */
  maxLength?: number;
  /** Disable the editor */
  isDisabled?: boolean;
}

/* ───────────────────────── Theme ───────────────────────── */

const composeEditorTheme = {
  paragraph: 'mb-1 leading-relaxed text-[var(--text-primary)]',
  text: {
    bold: 'font-bold',
    italic: 'italic',
    underline: 'underline',
  },
  list: {
    ul: 'list-disc pl-5 mb-2',
    ol: 'list-decimal pl-5 mb-2',
    listitem: 'mb-0.5',
    nested: {
      listitem: 'list-none',
    },
  },
  link: 'text-[var(--color-primary)] underline cursor-pointer',
};

/* ───────────────────────── Toolbar ───────────────────────── */

function ComposeToolbar({ isDisabled }: { isDisabled?: boolean }) {
  const [editor] = useLexicalComposerContext();
  const [isBold, setIsBold] = useState(false);
  const [isItalic, setIsItalic] = useState(false);
  const [isUnderline, setIsUnderline] = useState(false);
  const [isLink, setIsLink] = useState(false);
  const [blockType, setBlockType] = useState<string>('paragraph');

  const updateToolbar = useCallback(() => {
    const selection = $getSelection();
    if ($isRangeSelection(selection)) {
      setIsBold(selection.hasFormat('bold'));
      setIsItalic(selection.hasFormat('italic'));
      setIsUnderline(selection.hasFormat('underline'));

      const anchorNode = selection.anchor.getNode();
      const element =
        anchorNode.getKey() === 'root'
          ? anchorNode
          : anchorNode.getTopLevelElementOrThrow();

      if ($isListNode(element)) {
        const parentList = $getNearestNodeOfType(anchorNode, ListNodeClass);
        setBlockType(parentList ? parentList.getListType() : 'paragraph');
      } else {
        setBlockType(element.getType());
      }

      // Check for link
      const parent = anchorNode.getParent();
      setIsLink($isLinkNode(parent) || $isLinkNode(anchorNode));
    }
  }, []);

  useEffect(() => {
    return editor.registerUpdateListener(({ editorState }) => {
      editorState.read(() => {
        updateToolbar();
      });
    });
  }, [editor, updateToolbar]);

  const formatList = (type: 'bullet' | 'number') => {
    if (blockType === type) {
      editor.dispatchCommand(REMOVE_LIST_COMMAND, undefined);
    } else {
      editor.dispatchCommand(
        type === 'bullet' ? INSERT_UNORDERED_LIST_COMMAND : INSERT_ORDERED_LIST_COMMAND,
        undefined,
      );
    }
  };

  const insertLink = () => {
    if (isLink) {
      editor.dispatchCommand(TOGGLE_LINK_COMMAND, null);
    } else {
      const url = prompt('Enter URL:');
      if (url) {
        editor.dispatchCommand(TOGGLE_LINK_COMMAND, url);
      }
    }
  };

  return (
    <div className="flex items-center gap-0.5 bg-[var(--surface-elevated)] border-b border-[var(--border-default)] px-2 py-1 rounded-t-lg">
      {/* Bold */}
      <Button
        isIconOnly
        size="sm"
        variant={isBold ? 'flat' : 'light'}
        color={isBold ? 'primary' : 'default'}
        isDisabled={isDisabled}
        onPress={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'bold')}
        aria-label="Bold"
        className="min-w-9 w-9 h-9"
      >
        <Bold size={16} />
      </Button>

      {/* Italic */}
      <Button
        isIconOnly
        size="sm"
        variant={isItalic ? 'flat' : 'light'}
        color={isItalic ? 'primary' : 'default'}
        isDisabled={isDisabled}
        onPress={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'italic')}
        aria-label="Italic"
        className="min-w-9 w-9 h-9"
      >
        <Italic size={16} />
      </Button>

      {/* Underline */}
      <Button
        isIconOnly
        size="sm"
        variant={isUnderline ? 'flat' : 'light'}
        color={isUnderline ? 'primary' : 'default'}
        isDisabled={isDisabled}
        onPress={() => editor.dispatchCommand(FORMAT_TEXT_COMMAND, 'underline')}
        aria-label="Underline"
        className="min-w-9 w-9 h-9"
      >
        <Underline size={16} />
      </Button>

      {/* Bullet list */}
      <Button
        isIconOnly
        size="sm"
        variant={blockType === 'bullet' ? 'flat' : 'light'}
        color={blockType === 'bullet' ? 'primary' : 'default'}
        isDisabled={isDisabled}
        onPress={() => formatList('bullet')}
        aria-label="Bullet List"
        className="min-w-9 w-9 h-9"
      >
        <List size={16} />
      </Button>

      {/* Numbered list */}
      <Button
        isIconOnly
        size="sm"
        variant={blockType === 'number' ? 'flat' : 'light'}
        color={blockType === 'number' ? 'primary' : 'default'}
        isDisabled={isDisabled}
        onPress={() => formatList('number')}
        aria-label="Numbered List"
        className="min-w-9 w-9 h-9"
      >
        <ListOrdered size={16} />
      </Button>

      {/* Link */}
      <Button
        isIconOnly
        size="sm"
        variant={isLink ? 'flat' : 'light'}
        color={isLink ? 'primary' : 'default'}
        isDisabled={isDisabled}
        onPress={insertLink}
        aria-label={isLink ? 'Remove Link' : 'Insert Link'}
        className="min-w-9 w-9 h-9"
      >
        <Link2 size={16} />
      </Button>
    </div>
  );
}

/* ───────────────────────── HTML Import Plugin ───────────────────────── */

/**
 * Plugin that loads initial HTML content into the editor.
 * Only runs once when the component mounts with initial content.
 */
function HtmlImportPlugin({ html }: { html: string }) {
  const [editor] = useLexicalComposerContext();
  const [hasLoaded, setHasLoaded] = useState(false);

  useEffect(() => {
    if (hasLoaded || !html) return;

    editor.update(() => {
      const parser = new DOMParser();
      const dom = parser.parseFromString(html, 'text/html');
      const nodes = $generateNodesFromDOM(editor, dom);
      const root = $getRoot();
      root.clear();
      nodes.forEach((node) => root.append(node));
    });

    setHasLoaded(true);
  }, [editor, html, hasLoaded]);

  return null;
}

/* ───────────────────────── Disabled Plugin ───────────────────────── */

function DisabledPlugin({ isDisabled }: { isDisabled?: boolean }) {
  const [editor] = useLexicalComposerContext();

  useEffect(() => {
    editor.setEditable(!isDisabled);
  }, [editor, isDisabled]);

  return null;
}

/* ───────────────────────── Error Boundary ───────────────────────── */

function ComposeEditorErrorBoundary({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}

/* ───────────────────────── Main Component ───────────────────────── */

export function ComposeEditor({
  value,
  onChange,
  onPlainTextChange,
  placeholder = 'What would you like to share?',
  maxLength,
  isDisabled = false,
}: ComposeEditorProps) {
  const initialConfig = {
    namespace: 'NexusComposeEditor',
    theme: composeEditorTheme,
    nodes: [ListNode, ListItemNode, LinkNode, AutoLinkNode],
    onError: (error: Error) => {
      console.error('ComposeEditor error:', error);
    },
    editable: !isDisabled,
  };

  const handleChange = useCallback(
    (_editorState: EditorState, editor: LexicalEditor) => {
      editor.read(() => {
        const html = $generateHtmlFromNodes(editor);
        const root = $getRoot();
        const plainText = root.getTextContent();

        // Lexical outputs <p><br></p> for empty content, normalize to empty string
        if (html === '<p><br></p>' || html === '<p></p>') {
          onChange('');
          onPlainTextChange?.('');
        } else {
          onChange(html);
          onPlainTextChange?.(plainText);
        }
      });
    },
    [onChange, onPlainTextChange],
  );

  return (
    <div
      className={`
        border border-[var(--border-default)] rounded-lg
        focus-within:border-[var(--color-primary)] transition-colors
        ${isDisabled ? 'opacity-50 pointer-events-none' : ''}
      `}
    >
      <LexicalComposer initialConfig={initialConfig}>
        <ComposeToolbar isDisabled={isDisabled} />
        <div className="relative">
          <RichTextPlugin
            contentEditable={
              <ContentEditable
                className="min-h-[120px] max-h-[300px] overflow-y-auto px-3 py-2 outline-none text-sm text-[var(--text-primary)]"
                aria-label="Post content editor"
              />
            }
            placeholder={
              <div className="pointer-events-none absolute top-2 left-3 text-sm text-[var(--text-subtle)]">
                {placeholder}
              </div>
            }
            ErrorBoundary={ComposeEditorErrorBoundary}
          />
        </div>
        <HistoryPlugin />
        <ListPlugin />
        <LinkPlugin />
        <OnChangePlugin onChange={handleChange} />
        <HtmlImportPlugin html={value} />
        <DisabledPlugin isDisabled={isDisabled} />
      </LexicalComposer>

      {/* Character count indicator */}
      {maxLength !== undefined && (
        <MaxLengthIndicator maxLength={maxLength} />
      )}
    </div>
  );
}

/* ───────────────────────── Max Length Indicator ───────────────────────── */

/**
 * Visual indicator for character limit. Reads plain text length from the editor.
 * Rendered outside the LexicalComposer, so it receives maxLength as a prop
 * and the parent is responsible for tracking plain text length externally.
 *
 * Note: This is a placeholder rendered in the border area.
 * The parent component should use onPlainTextChange to track length
 * and conditionally show warnings.
 */
function MaxLengthIndicator({ maxLength }: { maxLength: number }) {
  return (
    <div className="px-3 py-1 text-right">
      <span className="text-xs text-[var(--text-subtle)]">
        max {maxLength} characters
      </span>
    </div>
  );
}

export default ComposeEditor;
