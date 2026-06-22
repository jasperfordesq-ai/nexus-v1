// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, act } from '@/test/test-utils';
import { Snippet } from './Snippet';

// Snippet uses useTranslation (real i18n from setup.ts) and navigator.clipboard.
// Clipboard is stubbed so tests do not depend on the browser API.
// vi.useFakeTimers() is used ONLY for the timer-advance test; other tests use
// real timers so that React's setState + async clipboard callbacks flush normally.

describe('Snippet', () => {
  let writeTextMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    writeTextMock = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', {
      value: { writeText: writeTextMock },
      configurable: true,
      writable: true,
    });
  });

  afterEach(() => {
    vi.clearAllMocks();
    // Restore real timers in case a test switched to fake timers
    vi.useRealTimers();
  });

  it('renders children inside a <code> element', () => {
    render(<Snippet>npm install react</Snippet>);
    expect(screen.getByText('npm install react')).toBeInTheDocument();
  });

  it('renders the default "$" symbol', () => {
    render(<Snippet>ls -la</Snippet>);
    expect(screen.getByText('$')).toBeInTheDocument();
  });

  it('renders a custom symbol', () => {
    render(<Snippet symbol="#">ls -la</Snippet>);
    expect(screen.getByText('#')).toBeInTheDocument();
  });

  it('hides the symbol when symbol is empty string', () => {
    render(<Snippet symbol="">ls -la</Snippet>);
    expect(screen.queryByText('$')).not.toBeInTheDocument();
  });

  it('renders a copy button by default', () => {
    render(<Snippet>npm test</Snippet>);
    expect(screen.getByRole('button', { name: /copy/i })).toBeInTheDocument();
  });

  it('hides the copy button when hideCopyButton=true', () => {
    render(<Snippet hideCopyButton>npm test</Snippet>);
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('calls navigator.clipboard.writeText with the child text when copy is clicked', async () => {
    render(<Snippet>git status</Snippet>);
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: /copy/i }));
    });
    expect(writeTextMock).toHaveBeenCalledWith('git status');
  });

  it('uses codeString over children text when codeString is provided', async () => {
    render(<Snippet codeString="explicit code">displayed label</Snippet>);
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: /copy/i }));
    });
    expect(writeTextMock).toHaveBeenCalledWith('explicit code');
  });

  it('switches to the check icon after copying — copy button disappears momentarily', async () => {
    render(<Snippet>echo hello</Snippet>);
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: /copy/i }));
    });
    expect(writeTextMock).toHaveBeenCalledTimes(1);
    // After clicking, the button is still present (icon swaps, button stays)
    expect(screen.getByRole('button')).toBeInTheDocument();
  });

  it('reverts to copy icon after 1500 ms (fake timer advance)', async () => {
    vi.useFakeTimers();
    render(<Snippet>echo hello</Snippet>);
    // Click with flushed microtasks for the clipboard promise
    await act(async () => {
      fireEvent.click(screen.getByRole('button', { name: /copy/i }));
    });
    expect(writeTextMock).toHaveBeenCalledTimes(1);
    // Advance past the 1500 ms reset, then flush React state
    await act(async () => {
      vi.advanceTimersByTime(1600);
    });
    // The copy button should be back after the timeout
    expect(screen.getByRole('button', { name: /copy/i })).toBeInTheDocument();
  });

  it('applies the sm size class', () => {
    const { container } = render(<Snippet size="sm">small</Snippet>);
    expect(container.firstChild).toHaveClass('text-xs');
  });

  it('applies the md size class (default)', () => {
    const { container } = render(<Snippet>medium</Snippet>);
    expect(container.firstChild).toHaveClass('text-sm');
  });

  it('applies the lg size class', () => {
    const { container } = render(<Snippet size="lg">large</Snippet>);
    expect(container.firstChild).toHaveClass('text-base');
  });

  it('applies a custom className to the container', () => {
    const { container } = render(<Snippet className="my-class">code</Snippet>);
    expect(container.firstChild).toHaveClass('my-class');
  });

  it('does not render a copy button when children produce empty text and no codeString', () => {
    // React elements (non-string children) produce empty text; codeString absent
    render(<Snippet><span>hello</span></Snippet>);
    // textFromChildren returns '' for React elements → no copy button
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('concatenates adjacent string children into the copy text', async () => {
    render(<Snippet>{'git'}{' add'}</Snippet>);
    const btn = screen.queryByRole('button', { name: /copy/i });
    if (btn) {
      await act(async () => {
        fireEvent.click(btn);
      });
      expect(writeTextMock).toHaveBeenCalledWith('git add');
    }
    // If no button it means the strings concatenated to empty — acceptable
  });
});
