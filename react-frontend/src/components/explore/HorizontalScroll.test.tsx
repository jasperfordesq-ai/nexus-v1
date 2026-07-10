// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import userEvent from '@testing-library/user-event';
import { fireEvent, render, screen, waitFor } from '@/test/test-utils';
import { HorizontalScroll } from './HorizontalScroll';

// ---------------------------------------------------------------------------
// Context mock — HorizontalScroll → Button → @/contexts (for translation
// callbacks used inside Button internals)
// ---------------------------------------------------------------------------
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

// ---------------------------------------------------------------------------
// jsdom has no layout engine. The overflow branch test configures dimensions
// only on the rendered track, then dispatches the same scroll event used in
// the browser. This avoids global layout mocks while exercising both controls.
// ---------------------------------------------------------------------------

describe('HorizontalScroll', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders its children', () => {
    render(
      <HorizontalScroll>
        <div>Card 1</div>
        <div>Card 2</div>
        <div>Card 3</div>
      </HorizontalScroll>
    );
    expect(screen.getByText('Card 1')).toBeInTheDocument();
    expect(screen.getByText('Card 2')).toBeInTheDocument();
    expect(screen.getByText('Card 3')).toBeInTheDocument();
  });

  it('renders a single child without crashing', () => {
    render(
      <HorizontalScroll>
        <span data-testid="only-child">Only</span>
      </HorizontalScroll>
    );
    expect(screen.getByTestId('only-child')).toBeInTheDocument();
  });

  it('accepts a custom className without crashing', () => {
    const { container } = render(
      <HorizontalScroll className="my-scroll">
        <div>Item</div>
      </HorizontalScroll>
    );
    // The outermost div should carry the custom class
    const outer = container.firstChild as HTMLElement;
    expect(outer).toHaveClass('my-scroll');
  });

  it('does not render scroll controls without overflow', () => {
    render(
      <HorizontalScroll>
        <div>A</div>
      </HorizontalScroll>
    );
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('renders 44px touch-aware controls and scrolls in both directions', async () => {
    const user = userEvent.setup();
    render(
      <HorizontalScroll>
        <div>A</div>
        <div>B</div>
      </HorizontalScroll>
    );

    const track = screen.getByTestId('horizontal-scroll-track');
    const scrollBy = vi.fn();
    Object.defineProperties(track, {
      clientWidth: { configurable: true, value: 100 },
      scrollLeft: { configurable: true, value: 20, writable: true },
      scrollWidth: { configurable: true, value: 300 },
      scrollBy: { configurable: true, value: scrollBy },
    });
    fireEvent.scroll(track);

    const left = await screen.findByRole('button', { name: /scroll left/i });
    const right = screen.getByRole('button', { name: /scroll right/i });

    for (const button of [left, right]) {
      expect(button).toHaveClass('size-11', 'min-h-11', 'min-w-11');
      expect(button).toHaveClass(
        'pointer-coarse:opacity-100',
        'any-pointer-coarse:opacity-100',
        'pointer-fine:opacity-0',
        'pointer-fine:group-hover:opacity-100',
        'group-focus-within:opacity-100',
        'focus-visible:opacity-100',
      );
      expect(button).toHaveAttribute('aria-controls', track.id);
    }

    await user.click(left);
    expect(scrollBy).toHaveBeenLastCalledWith({ left: -75, behavior: 'smooth' });
    await user.click(right);
    expect(scrollBy).toHaveBeenLastCalledWith({ left: 75, behavior: 'smooth' });
    await waitFor(() => expect(scrollBy).toHaveBeenCalledTimes(2));
  });

  it('keeps the touch-swipe track available without arrow controls', () => {
    render(
      <HorizontalScroll>
        <div>A</div>
      </HorizontalScroll>
    );
    expect(screen.getByTestId('horizontal-scroll-track')).toHaveClass('overflow-x-auto');
  });

  it('renders many children without crashing', () => {
    render(
      <HorizontalScroll>
        {Array.from({ length: 20 }, (_, i) => (
          <div key={i}>Item {i + 1}</div>
        ))}
      </HorizontalScroll>
    );
    expect(screen.getByText('Item 1')).toBeInTheDocument();
    expect(screen.getByText('Item 20')).toBeInTheDocument();
  });
});
