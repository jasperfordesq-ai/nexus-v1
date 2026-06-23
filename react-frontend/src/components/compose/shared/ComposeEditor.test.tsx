// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';

// ─── No api calls in this component — but mock logger ─────────────────────
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts (no api) ───────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Stub Lexical heavy dependencies ─────────────────────────────────────────
// The entire Lexical plugin stack is virtually unusable in jsdom because it
// depends on contenteditable and Selection APIs that jsdom doesn't implement.
// We stub every Lexical import to remove the hard dependency.

vi.mock('@lexical/react/LexicalComposer', () => ({
  LexicalComposer: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="lexical-composer">{children}</div>
  ),
}));

vi.mock('@lexical/react/LexicalRichTextPlugin', () => ({
  RichTextPlugin: ({
    contentEditable,
    placeholder,
  }: {
    contentEditable: React.ReactNode;
    placeholder: React.ReactNode;
  }) => (
    <div data-testid="rich-text-plugin">
      {contentEditable}
      {placeholder}
    </div>
  ),
}));

vi.mock('@lexical/react/LexicalContentEditable', () => ({
  ContentEditable: ({ className, 'aria-label': ariaLabel }: { className?: string; 'aria-label'?: string }) => (
    <div
      data-testid="content-editable"
      contentEditable
      role="textbox"
      aria-label={ariaLabel}
      aria-multiline="true"
      className={className}
    />
  ),
}));

vi.mock('@lexical/react/LexicalHistoryPlugin', () => ({
  HistoryPlugin: () => null,
}));

vi.mock('@lexical/react/LexicalListPlugin', () => ({
  ListPlugin: () => null,
}));

vi.mock('@lexical/react/LexicalLinkPlugin', () => ({
  LinkPlugin: () => null,
}));

vi.mock('@lexical/react/LexicalOnChangePlugin', () => ({
  OnChangePlugin: () => null,
}));

vi.mock('@lexical/react/LexicalComposerContext', () => ({
  useLexicalComposerContext: () => [
    {
      dispatchCommand: vi.fn(),
      registerUpdateListener: vi.fn(() => () => {}),
      setEditable: vi.fn(),
      update: vi.fn(),
      read: vi.fn(),
    },
    true,
  ],
}));

vi.mock('@lexical/html', () => ({
  $generateHtmlFromNodes: vi.fn(() => '<p>test</p>'),
  $generateNodesFromDOM: vi.fn(() => []),
}));

vi.mock('@lexical/list', () => ({
  ListNode: class ListNode {},
  ListItemNode: class ListItemNode {},
  INSERT_ORDERED_LIST_COMMAND: 'INSERT_ORDERED_LIST_COMMAND',
  INSERT_UNORDERED_LIST_COMMAND: 'INSERT_UNORDERED_LIST_COMMAND',
  REMOVE_LIST_COMMAND: 'REMOVE_LIST_COMMAND',
  $isListNode: vi.fn(() => false),
}));

vi.mock('@lexical/link', () => ({
  AutoLinkNode: class AutoLinkNode {},
  LinkNode: class LinkNode {},
  TOGGLE_LINK_COMMAND: 'TOGGLE_LINK_COMMAND',
  $isLinkNode: vi.fn(() => false),
}));

vi.mock('@lexical/utils', () => ({
  $getNearestNodeOfType: vi.fn(() => null),
}));

vi.mock('lexical', () => ({
  $getSelection: vi.fn(() => null),
  $isRangeSelection: vi.fn(() => false),
  FORMAT_TEXT_COMMAND: 'FORMAT_TEXT_COMMAND',
  $getRoot: vi.fn(() => ({
    getTextContent: vi.fn(() => ''),
    clear: vi.fn(),
    append: vi.fn(),
    selectEnd: vi.fn(),
  })),
}));

// ─────────────────────────────────────────────────────────────────────────────
describe('ComposeEditor', () => {
  const defaultProps = {
    value: '',
    onChange: vi.fn(),
    onPlainTextChange: vi.fn(),
    placeholder: 'What would you like to share?',
  };

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders without crashing', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);
    expect(screen.getByTestId('lexical-composer')).toBeInTheDocument();
  });

  it('renders the rich text content editable area', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);
    expect(screen.getByTestId('content-editable')).toBeInTheDocument();
  });

  it('renders placeholder text', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} placeholder="Type here..." />);
    expect(screen.getByText('Type here...')).toBeInTheDocument();
  });

  it('renders toolbar buttons (bold, italic, underline, bullet, numbered, link)', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);

    const buttons = screen.getAllByRole('button');
    // Should have at least 6 toolbar buttons
    expect(buttons.length).toBeGreaterThanOrEqual(6);
  });

  it('renders bold button with correct aria-label', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);

    const boldBtn = screen.getAllByRole('button').find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('bold') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('aria.bold')
    );
    expect(boldBtn).toBeDefined();
  });

  it('renders italic button', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);

    const italicBtn = screen.getAllByRole('button').find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('italic') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('aria.italic')
    );
    expect(italicBtn).toBeDefined();
  });

  it('renders link button', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);

    const linkBtn = screen.getAllByRole('button').find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('link') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('insert')
    );
    expect(linkBtn).toBeDefined();
  });

  it('shows max length indicator when maxLength prop is provided', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} maxLength={280} />);

    // MaxLengthIndicator shows t('max_characters', { count: 280 }) — key fallback shows count
    await waitFor(() => {
      const indicator = screen.queryByText(/280/);
      expect(indicator).not.toBeNull();
    });
  });

  it('does not show max length indicator when maxLength is not provided', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);

    // No number indicating max length should appear
    const indicator = screen.queryByText(/max_characters/);
    expect(indicator).toBeNull();
  });

  it('applies disabled opacity class when isDisabled is true', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    const { container } = render(<ComposeEditor {...defaultProps} isDisabled={true} />);

    // The wrapper div gets opacity-50 + pointer-events-none when disabled
    const wrapper = container.firstElementChild as HTMLElement;
    expect(wrapper.className).toMatch(/opacity-50/);
  });

  it('does not apply disabled opacity when isDisabled is false', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    const { container } = render(<ComposeEditor {...defaultProps} isDisabled={false} />);

    const wrapper = container.firstElementChild as HTMLElement;
    expect(wrapper.className).not.toMatch(/opacity-50/);
  });

  it('exposes insertText via ref handle', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    const ref = React.createRef<import('./ComposeEditor').ComposeEditorHandle>();
    render(<ComposeEditor {...defaultProps} ref={ref} />);

    // ref.current should exist with insertText method
    expect(ref.current).toBeDefined();
    expect(typeof ref.current?.insertText).toBe('function');
  });

  it('clicking link button shows URL input field', async () => {
    const { ComposeEditor } = await import('./ComposeEditor');
    render(<ComposeEditor {...defaultProps} />);

    const linkBtn = screen.getAllByRole('button').find(
      (b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('link') ||
        b.getAttribute('aria-label')?.toLowerCase().includes('insert')
    );
    expect(linkBtn).toBeDefined();

    await userEvent.click(linkBtn!);

    await waitFor(() => {
      // After click, showLinkInput=true → an input for URL appears
      const urlInput = screen.queryByRole('textbox', { name: /url/i }) ||
        screen.queryByPlaceholderText(/https/i);
      expect(urlInput).not.toBeNull();
    });
  });
});
