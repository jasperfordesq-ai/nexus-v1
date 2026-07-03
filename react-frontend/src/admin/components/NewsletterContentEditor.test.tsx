// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

// Confirm dialog — controllable per test.
const mockConfirm = vi.fn();
// Stub Tabs to deterministic role="tab" buttons keyed by id, so tab selection
// is i18n-independent (the real Tabs render translated accessible names).
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    useConfirm: () => mockConfirm,
    Tabs: ({
      children,
      onSelectionChange,
    }: {
      children: React.ReactNode;
      onSelectionChange?: (key: string) => void;
    }) => (
      <div role="tablist">
        {(Array.isArray(children) ? children : [children]).map((child) => {
          const id = (child as { props: { id: string } }).props.id;
          return (
            <button key={id} role="tab" data-testid={`tab-${id}`} onClick={() => onSelectionChange?.(id)}>
              {id}
            </button>
          );
        })}
      </div>
    ),
    Tab: () => null,
  };
});

// Stub the heavy sub-editors as simple textareas that report their format.
vi.mock('./RichTextEditor', () => ({
  RichTextEditor: ({ value, onChange }: { value: string; onChange: (v: string) => void }) => (
    <textarea aria-label="richtext" value={value} onChange={(e) => onChange(e.target.value)} />
  ),
}));
vi.mock('./HtmlSourceEditor', () => ({
  HtmlSourceEditor: ({ value, onChange }: { value: string; onChange: (v: string) => void }) => (
    <textarea aria-label="html" value={value} onChange={(e) => onChange(e.target.value)} />
  ),
}));
vi.mock('./PlainTextEditor', () => ({
  PlainTextEditor: ({ value, onChange }: { value: string; onChange: (v: string) => void }) => (
    <textarea aria-label="plaintext" value={value} onChange={(e) => onChange(e.target.value)} />
  ),
}));

import { NewsletterContentEditor } from './NewsletterContentEditor';
import type { ContentFormat } from './contentFormat';

function setup(format: ContentFormat, value = '') {
  const onChange = vi.fn();
  render(
    <NewsletterContentEditor value={value} format={format} onChange={onChange} />,
  );
  return { onChange };
}

describe('NewsletterContentEditor', () => {
  beforeEach(() => {
    mockConfirm.mockReset();
  });

  it('renders the editor for the active format', async () => {
    setup('html', '<p>x</p>');
    expect(await screen.findByLabelText('html')).toBeInTheDocument();
  });

  it('emits an atomic { content, content_format } object on edit', async () => {
    const { onChange } = setup('plaintext', 'hi');
    const ta = await screen.findByLabelText('plaintext');
    fireEvent.change(ta, { target: { value: 'hello' } });
    expect(onChange).toHaveBeenCalledWith({ content: 'hello', content_format: 'plaintext' });
  });

  it('switches safely (richtext -> html) without a confirm dialog', async () => {
    const { onChange } = setup('richtext', '<p>keep</p>');
    fireEvent.click(await screen.findByTestId('tab-html'));
    await waitFor(() => expect(onChange).toHaveBeenCalled());
    expect(mockConfirm).not.toHaveBeenCalled();
    expect(onChange).toHaveBeenCalledWith({ content: '<p>keep</p>', content_format: 'html' });
  });

  it('confirms before a destructive switch (html -> richtext) and applies when accepted', async () => {
    mockConfirm.mockResolvedValue(true);
    const { onChange } = setup('html', '<table><tr><td>x</td></tr></table>');
    fireEvent.click(await screen.findByTestId('tab-richtext'));
    await waitFor(() => expect(mockConfirm).toHaveBeenCalled());
    await waitFor(() =>
      expect(onChange).toHaveBeenCalledWith({
        content: '<table><tr><td>x</td></tr></table>',
        content_format: 'richtext',
      }),
    );
  });

  it('does NOT switch when the destructive confirm is cancelled', async () => {
    mockConfirm.mockResolvedValue(false);
    const { onChange } = setup('html', '<table><tr><td>x</td></tr></table>');
    fireEvent.click(await screen.findByTestId('tab-richtext'));
    await waitFor(() => expect(mockConfirm).toHaveBeenCalled());
    expect(onChange).not.toHaveBeenCalled();
  });

  it('skips the confirm dialog when content is empty', async () => {
    const { onChange } = setup('html', '   ');
    fireEvent.click(await screen.findByTestId('tab-plaintext'));
    await waitFor(() => expect(onChange).toHaveBeenCalled());
    expect(mockConfirm).not.toHaveBeenCalled();
  });
});
