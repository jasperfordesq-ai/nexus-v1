// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React, { forwardRef, useImperativeHandle } from 'react';
import { describe, expect, it, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@/test/test-utils';
import type { ContentFormat } from './contentFormat';

const mockConfirm = vi.fn();
const mockBuilderFlush = vi.fn();

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

vi.mock('./PageDesignBuilder', () => ({
  PageDesignBuilder: forwardRef(function MockPageDesignBuilder(
    props: { onChange: (payload: { html: string; designJson: string }) => void },
    ref: React.Ref<{ flush: () => { html: string; designJson: string } }>,
  ) {
    useImperativeHandle(ref, () => ({
      flush: () => {
        const payload = mockBuilderFlush();
        props.onChange(payload);
        return payload;
      },
    }));
    return <div data-testid="page-design-builder" />;
  }),
}));

import { PageContentEditor } from './PageContentEditor';

function setup(format: ContentFormat, value = '<section>stale</section>') {
  const onChange = vi.fn();
  render(
    <PageContentEditor
      value={value}
      format={format}
      designJson='{"pages":[{"frames":[]}]}'
      onChange={onChange}
    />,
  );
  return { onChange };
}

function setupDisabled(format: ContentFormat, value = '<section>stale</section>') {
  const onChange = vi.fn();
  render(
    <PageContentEditor
      value={value}
      format={format}
      designJson='{"pages":[{"frames":[]}]}'
      isDisabled
      onChange={onChange}
    />,
  );
  return { onChange };
}

describe('PageContentEditor', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockConfirm.mockResolvedValue(true);
    mockBuilderFlush.mockReturnValue({
      html: '<section>fresh builder html</section>',
      designJson: '{"pages":[{"frames":[{"component":{"tagName":"section"}}]}]}',
    });
  });

  it('flushes the live design builder before switching away from Design mode', async () => {
    const { onChange } = setup('builder');

    expect(await screen.findByTestId('page-design-builder')).toBeInTheDocument();
    fireEvent.click(screen.getByTestId('tab-html'));

    await waitFor(() => expect(mockBuilderFlush).toHaveBeenCalled());
    await waitFor(() =>
      expect(onChange).toHaveBeenLastCalledWith({
        content: '<section>fresh builder html</section>',
        content_format: 'html',
        design_json: null,
      }),
    );
    expect(onChange).not.toHaveBeenCalledWith(expect.objectContaining({ content: '<section>stale</section>' }));
  });

  it('keeps the flushed builder state when a destructive switch is cancelled', async () => {
    mockConfirm.mockResolvedValueOnce(false);
    const { onChange } = setup('builder');

    fireEvent.click(await screen.findByTestId('tab-richtext'));

    await waitFor(() => expect(mockBuilderFlush).toHaveBeenCalled());
    await waitFor(() => expect(mockConfirm).toHaveBeenCalled());
    expect(onChange).toHaveBeenCalledWith({
      content: '<section>fresh builder html</section>',
      content_format: 'builder',
      design_json: '{"pages":[{"frames":[{"component":{"tagName":"section"}}]}]}',
    });
    expect(onChange).not.toHaveBeenCalledWith(expect.objectContaining({ content_format: 'richtext' }));
  });

  it('does not switch modes while the editor is disabled during save', async () => {
    const { onChange } = setupDisabled('builder');

    fireEvent.click(await screen.findByTestId('tab-html'));

    expect(mockBuilderFlush).not.toHaveBeenCalled();
    expect(mockConfirm).not.toHaveBeenCalled();
    expect(onChange).not.toHaveBeenCalled();
  });
});
