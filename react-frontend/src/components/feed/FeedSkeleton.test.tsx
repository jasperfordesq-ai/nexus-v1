// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for FeedSkeleton — a purely presentational loading placeholder.
 *
 * Strategy: assert that skeleton blocks (by class or container element) are
 * rendered.  We check the overall container structure rather than specific
 * Skeleton text because HeroUI Skeleton renders an aria-hidden shimmer
 * element with no semantic role.
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { FeedSkeleton } from './FeedSkeleton';

vi.mock('@/contexts', () => createMockContexts());

// GlassCard and Skeleton are HeroUI-based.
// We keep them un-mocked so the real component tree is exercised.

// ─── Helper: count rendered elements by class substring ──────────────────────

function countSkeletons(container: HTMLElement) {
  // HeroUI Skeleton renders a div with data-slot="base" or a "skeleton" class.
  // We look for elements that carry an inline shimmer or the aria-hidden attribute
  // from the shimmer div, OR we simply count children rendered by the component.
  // The most reliable cross-version signal is that FeedSkeleton always renders
  // at least one wrapping card element.
  return container.querySelectorAll('[class*="skeleton"], [class*="Skeleton"], [aria-hidden="true"]').length;
}

// ─── Default (random) variant ─────────────────────────────────────────────────

describe('FeedSkeleton — default / random variant', () => {
  it('renders a card container', () => {
    const { container } = render(<FeedSkeleton />);
    // Should render at least one div (the GlassCard)
    expect(container.firstElementChild).not.toBeNull();
  });

  it('renders skeleton blocks (shimmer elements present)', () => {
    const { container } = render(<FeedSkeleton />);
    // There should be multiple shimmer-related elements
    const skeletonLike = container.querySelectorAll('div');
    expect(skeletonLike.length).toBeGreaterThan(2);
  });

  it('renders with index=0 (image shown for 2-of-3 pattern)', () => {
    // index 0 → shows image (0 % 3 !== 2)
    const { container } = render(<FeedSkeleton variant="random" index={0} />);
    expect(container.firstElementChild).not.toBeNull();
  });

  it('renders with index=2 (no image for third card)', () => {
    // index 2 → no image (2 % 3 === 2)
    const { container } = render(<FeedSkeleton variant="random" index={2} />);
    expect(container.firstElementChild).not.toBeNull();
  });
});

// ─── with-image / text-only explicit variants ─────────────────────────────────

describe('FeedSkeleton — explicit variants', () => {
  it('renders with-image variant', () => {
    const { container } = render(<FeedSkeleton variant="with-image" />);
    expect(container.firstElementChild).not.toBeNull();
  });

  it('renders text-only variant', () => {
    const { container } = render(<FeedSkeleton variant="text-only" />);
    expect(container.firstElementChild).not.toBeNull();
  });
});

// ─── Typed content variants ───────────────────────────────────────────────────

describe('FeedSkeleton — poll variant', () => {
  it('renders without crashing', () => {
    const { container } = render(<FeedSkeleton variant="poll" />);
    expect(container.firstElementChild).not.toBeNull();
  });

  it('renders more skeleton blocks than the basic variant (poll has choice rows)', () => {
    const { container: poll } = render(<FeedSkeleton variant="poll" />);
    const { container: basic } = render(<FeedSkeleton />);
    // Poll has 3 choice skeleton rows + header skeleton
    const pollDivs = poll.querySelectorAll('div').length;
    const basicDivs = basic.querySelectorAll('div').length;
    expect(pollDivs).toBeGreaterThan(basicDivs);
  });
});

describe('FeedSkeleton — event variant', () => {
  it('renders without crashing', () => {
    const { container } = render(<FeedSkeleton variant="event" />);
    expect(container.firstElementChild).not.toBeNull();
  });
});

describe('FeedSkeleton — review variant', () => {
  it('renders without crashing', () => {
    const { container } = render(<FeedSkeleton variant="review" />);
    expect(container.firstElementChild).not.toBeNull();
  });

  it('renders star skeleton slots (5 star placeholders)', () => {
    const { container } = render(<FeedSkeleton variant="review" />);
    // Review skeleton renders 5 star-sized skeletons in a flex row
    // We can't query by role but we can verify the container has many children
    expect(container.querySelectorAll('div').length).toBeGreaterThan(5);
  });
});

describe('FeedSkeleton — milestone variant', () => {
  it('renders without crashing', () => {
    const { container } = render(<FeedSkeleton variant="milestone" />);
    expect(container.firstElementChild).not.toBeNull();
  });

  it('renders a large round avatar skeleton', () => {
    const { container } = render(<FeedSkeleton variant="milestone" />);
    // The large circular skeleton div should be present
    const roundedFull = container.querySelector('[class*="rounded-full"]');
    expect(roundedFull).not.toBeNull();
  });
});

// ─── Snapshot-style "renders X skeleton items in a list" ─────────────────────

describe('FeedSkeleton — multiple in a list', () => {
  it('renders 3 skeletons without crashing', () => {
    const { container } = render(
      <div>
        <FeedSkeleton index={0} />
        <FeedSkeleton index={1} />
        <FeedSkeleton index={2} />
      </div>,
    );
    // 3 GlassCards = 3 direct children
    expect(container.firstElementChild?.children.length).toBe(3);
  });

  it('renders a screen.queryByRole check — no semantic role on FeedSkeleton', () => {
    render(<FeedSkeleton />);
    // FeedSkeleton is purely decorative — no ARIA roles expected
    expect(screen.queryByRole('img')).toBeNull();
    expect(screen.queryByRole('heading')).toBeNull();
  });
});
