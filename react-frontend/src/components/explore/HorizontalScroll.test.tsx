// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { HorizontalScroll } from './HorizontalScroll';

// ---------------------------------------------------------------------------
// Context mock — HorizontalScroll → Button → @/contexts (for translation
// callbacks used inside Button internals)
// ---------------------------------------------------------------------------
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

// ---------------------------------------------------------------------------
// Notes on what can and cannot be tested here:
//
// The left/right scroll buttons are conditionally rendered only when
// canScrollLeft / canScrollRight is true.  Those flags are driven by
// scrollLeft / clientWidth / scrollWidth on the container div — all of which
// are zero in jsdom (no layout engine).  ResizeObserver is already mocked to
// a no-op in setup.ts, so checkScroll() never observes actual overflow and the
// buttons never appear.  Testing the scroll-button branches would require
// overriding scrollLeft/scrollWidth per element — a brittle approach.
// We assert that the component renders children correctly and does not crash.
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

  it('does not render left scroll button in jsdom (no overflow)', () => {
    render(
      <HorizontalScroll>
        <div>A</div>
      </HorizontalScroll>
    );
    // aria.scroll_left is the i18n key used in the button's aria-label
    // In jsdom scrollLeft = 0 so canScrollLeft = false → button is absent
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('does not render right scroll button in jsdom (no overflow)', () => {
    render(
      <HorizontalScroll>
        <div>A</div>
      </HorizontalScroll>
    );
    expect(screen.queryByRole('button')).toBeNull();
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
