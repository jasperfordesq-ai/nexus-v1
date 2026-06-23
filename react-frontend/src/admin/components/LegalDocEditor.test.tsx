// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

// ─── Stub Lexical (same pattern as RichTextEditor.test.tsx) ──────────────────

const { mockDispatchCommand, mockUpdate, mockRegisterUpdateListener, mockRegisterCommand } =
  vi.hoisted(() => ({
    mockDispatchCommand: vi.fn(),
    mockUpdate: vi.fn((cb: () => void) => cb()),
    mockRegisterUpdateListener: vi.fn(() => () => {}),
    mockRegisterCommand: vi.fn(() => () => {}),
  }));

const mockEditor = {
  dispatchCommand: mockDispatchCommand,
  update: mockUpdate,
  registerUpdateListener: mockRegisterUpdateListener,
  registerCommand: mockRegisterCommand,
  setEditable: vi.fn(),
  read: vi.fn((cb: () => void) => cb()),
};

vi.mock('@lexical/react/LexicalComposer', () => ({
  LexicalComposer: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@lexical/react/LexicalRichTextPlugin', () => ({
  RichTextPlugin: ({
    contentEditable,
    placeholder,
  }: {
    contentEditable: React.ReactNode;
    placeholder: React.ReactNode;
    ErrorBoundary: unknown;
  }) => (
    <div data-testid="rich-text-plugin">
      {contentEditable}
      {placeholder}
    </div>
  ),
}));

vi.mock('@lexical/react/LexicalContentEditable', () => ({
  ContentEditable: ({
    className,
    'aria-label': ariaLabel,
  }: {
    className?: string;
    'aria-label'?: string;
  }) => (
    <div
      data-testid="content-editable"
      contentEditable="true"
      role="textbox"
      aria-label={ariaLabel}
      aria-multiline="true"
      className={className}
    />
  ),
}));

vi.mock('@lexical/react/LexicalHistoryPlugin', () => ({ HistoryPlugin: () => null }));
vi.mock('@lexical/react/LexicalListPlugin', () => ({ ListPlugin: () => null }));
vi.mock('@lexical/react/LexicalLinkPlugin', () => ({ LinkPlugin: () => null }));
vi.mock('@lexical/react/LexicalOnChangePlugin', () => ({
  OnChangePlugin: ({ onChange }: { onChange: (...args: unknown[]) => void }) => (
    <div
      data-testid="on-change-plugin"
      data-has-onchange={typeof onChange === 'function' ? 'true' : 'false'}
    />
  ),
}));
vi.mock('@lexical/react/LexicalComposerContext', () => ({
  useLexicalComposerContext: () => [mockEditor],
}));
vi.mock('@lexical/react/LexicalErrorBoundary', () => ({
  LexicalErrorBoundary: () => null,
}));
vi.mock('@lexical/html', () => ({
  $generateHtmlFromNodes: vi.fn(() => '<p>Hello</p>'),
  $generateNodesFromDOM: vi.fn(() => []),
}));
vi.mock('@lexical/list', () => ({
  ListItemNode: class {},
  ListNode: class {},
  $isListNode: vi.fn(() => false),
  INSERT_ORDERED_LIST_COMMAND: 'INSERT_ORDERED_LIST',
  INSERT_UNORDERED_LIST_COMMAND: 'INSERT_UNORDERED_LIST',
  REMOVE_LIST_COMMAND: 'REMOVE_LIST',
}));
vi.mock('@lexical/link', () => ({
  AutoLinkNode: class {},
  LinkNode: class {},
  TOGGLE_LINK_COMMAND: 'TOGGLE_LINK',
  $isLinkNode: vi.fn(() => false),
}));
vi.mock('@lexical/rich-text', () => ({
  HeadingNode: class {},
  QuoteNode: class {},
  $createHeadingNode: vi.fn(() => ({ append: vi.fn() })),
  $createQuoteNode: vi.fn(() => ({})),
  $isHeadingNode: vi.fn(() => false),
}));
vi.mock('@lexical/selection', () => ({
  $setBlocksType: vi.fn(),
}));
vi.mock('@lexical/utils', () => ({
  $getNearestNodeOfType: vi.fn(() => null),
  $findMatchingParent: vi.fn(() => null),
  $insertNodeToNearestRoot: vi.fn(),
}));
vi.mock('lexical', () => ({
  $getSelection: vi.fn(() => null),
  $isRangeSelection: vi.fn(() => false),
  $createParagraphNode: vi.fn(() => ({ append: vi.fn() })),
  $createTextNode: vi.fn(() => ({})),
  $getRoot: vi.fn(() => ({ clear: vi.fn(), append: vi.fn() })),
  FORMAT_TEXT_COMMAND: 'FORMAT_TEXT',
  UNDO_COMMAND: 'UNDO',
  REDO_COMMAND: 'REDO',
  COMMAND_PRIORITY_NORMAL: 3,
  SELECTION_CHANGE_COMMAND: 'SELECTION_CHANGE',
  BLUR_COMMAND: 'BLUR',
}));

// ─── Stub LegalNoticeNode (custom Lexical node in same package) ───────────────
vi.mock('./LegalNoticeNode', () => ({
  LegalNoticeNode: class {},
  INSERT_LEGAL_NOTICE_COMMAND: 'INSERT_LEGAL_NOTICE',
  $createLegalNoticeNode: vi.fn(() => ({ append: vi.fn(), insertAfter: vi.fn() })),
  $isLegalNoticeNode: vi.fn(() => false),
}));

// ─── Stub CustomLegalDocument (heavy preview renderer) ───────────────────────
vi.mock('@/components/legal/CustomLegalDocument', () => ({
  CustomLegalDocument: ({ document: doc }: { document: { content: string } }) => (
    <div data-testid="legal-preview">{doc.content}</div>
  ),
}));

vi.mock('@/hooks/useLegalDocument', () => ({}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─────────────────────────────────────────────────────────────────────────────
describe('LegalDocEditor', () => {
  const mockOnChange = vi.fn();

  const defaultProps = {
    value: '',
    onChange: mockOnChange,
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing and shows the rich-text plugin', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    expect(screen.getByTestId('rich-text-plugin')).toBeInTheDocument();
  });

  it('renders a contenteditable region', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const editable = screen.getByTestId('content-editable');
    expect(editable.getAttribute('contenteditable')).toBe('true');
  });

  it('shows toolbar with Undo and Redo buttons', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const buttons = screen.getAllByRole('button');
    const undo = buttons.find((b) => b.getAttribute('aria-label')?.toLowerCase().includes('undo'));
    const redo = buttons.find((b) => b.getAttribute('aria-label')?.toLowerCase().includes('redo'));
    expect(undo).toBeDefined();
    expect(redo).toBeDefined();
  });

  it('shows inline formatting buttons (bold, italic, underline)', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const buttons = screen.getAllByRole('button');
    expect(buttons.find((b) => b.getAttribute('aria-label')?.toLowerCase().includes('bold'))).toBeDefined();
    expect(buttons.find((b) => b.getAttribute('aria-label')?.toLowerCase().includes('italic'))).toBeDefined();
    expect(buttons.find((b) => b.getAttribute('aria-label')?.toLowerCase().includes('underline'))).toBeDefined();
  });

  it('shows a Notice Box toolbar button (Megaphone)', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const buttons = screen.getAllByRole('button');
    const noticeBtn = buttons.find((b) => b.getAttribute('aria-label')?.toLowerCase().includes('notice'));
    expect(noticeBtn).toBeDefined();
  });

  it('shows a preview toggle button (Eye)', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('view') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('split') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('preview'),
    );
    expect(previewBtn).toBeDefined();
  });

  it('wires up OnChangePlugin with an onChange callback', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const onChangePlug = screen.getByTestId('on-change-plugin');
    expect(onChangePlug.getAttribute('data-has-onchange')).toBe('true');
  });

  it('dispatches UNDO_COMMAND when Undo button is clicked', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const undoBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('undo'),
    );
    if (undoBtn) fireEvent.click(undoBtn);
    expect(mockDispatchCommand).toHaveBeenCalledWith('UNDO', undefined);
  });

  it('dispatches FORMAT_TEXT_COMMAND with "bold" when Bold button is clicked', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const boldBtn = screen.getAllByRole('button').find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('bold'),
    );
    if (boldBtn) fireEvent.click(boldBtn);
    expect(mockDispatchCommand).toHaveBeenCalledWith('FORMAT_TEXT', 'bold');
  });

  it('calls editor.setEditable(false) when disabled=true', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} disabled={true} />);
    expect(mockEditor.setEditable).toHaveBeenCalledWith(false);
  });

  it('shows a validation error message when errorMessage is provided', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} errorMessage="Content is required" />);
    expect(screen.getByText('Content is required')).toBeInTheDocument();
  });

  it('does not show the live preview panel by default', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    expect(screen.queryByTestId('legal-preview')).not.toBeInTheDocument();
  });

  it('shows the live preview panel after clicking the preview toggle', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor value="<p>Draft content</p>" onChange={mockOnChange} />);

    const buttons = screen.getAllByRole('button');
    const previewBtn = buttons.find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('split') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('view') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('preview'),
    );
    expect(previewBtn).toBeDefined();
    if (previewBtn) fireEvent.click(previewBtn);

    await waitFor(() => {
      expect(screen.getByTestId('legal-preview')).toBeInTheDocument();
    });
  });

  it('renders heading 2 and heading 3 toolbar buttons', async () => {
    const { LegalDocEditor } = await import('./LegalDocEditor');
    render(<LegalDocEditor {...defaultProps} />);
    const buttons = screen.getAllByRole('button');
    const h2 = buttons.find((b) => b.getAttribute('aria-label')?.includes('2'));
    const h3 = buttons.find((b) => b.getAttribute('aria-label')?.includes('3'));
    expect(h2).toBeDefined();
    expect(h3).toBeDefined();
  });
});
