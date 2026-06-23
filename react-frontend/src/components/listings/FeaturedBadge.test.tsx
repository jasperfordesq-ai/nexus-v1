// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

beforeEach(() => {
  vi.resetAllMocks();
});

describe('FeaturedBadge', () => {
  it('renders the translated "Featured" label', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    render(<FeaturedBadge />);
    expect(screen.getByText('Featured')).toBeInTheDocument();
  });

  it('has aria-label "Featured listing"', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    render(<FeaturedBadge />);
    expect(screen.getByLabelText('Featured listing')).toBeInTheDocument();
  });

  it('renders a <span> element (not a Chip)', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    const { container } = render(<FeaturedBadge />);
    const span = container.querySelector('span[aria-label="Featured listing"]');
    expect(span).toBeInTheDocument();
  });

  it('includes the star SVG icon', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    const { container } = render(<FeaturedBadge />);
    const svg = container.querySelector('svg');
    expect(svg).toBeInTheDocument();
  });

  it('applies amber background classes for sm size', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    const { container } = render(<FeaturedBadge size="sm" />);
    const span = container.querySelector('span[aria-label="Featured listing"]');
    expect(span?.className).toContain('bg-amber-500/20');
    expect(span?.className).toContain('text-[10px]');
  });

  it('applies md-size classes when size="md"', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    const { container } = render(<FeaturedBadge size="md" />);
    const span = container.querySelector('span[aria-label="Featured listing"]');
    expect(span?.className).toContain('text-xs');
    expect(span?.className).toContain('px-2');
  });

  it('applies a custom className alongside base classes', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    const { container } = render(<FeaturedBadge className="mt-2" />);
    const span = container.querySelector('span[aria-label="Featured listing"]');
    expect(span?.className).toContain('mt-2');
  });

  it('defaults to size "sm" when no size prop is given', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    const { container } = render(<FeaturedBadge />);
    const span = container.querySelector('span[aria-label="Featured listing"]');
    // sm uses text-[10px], md uses text-xs — confirm sm classes
    expect(span?.className).toContain('text-[10px]');
  });

  it('renders both icon and text inside the span', async () => {
    const { FeaturedBadge } = await import('./FeaturedBadge');
    const { container } = render(<FeaturedBadge />);
    const span = container.querySelector('span[aria-label="Featured listing"]');
    expect(span).not.toBeNull();
    expect(span?.textContent).toContain('Featured');
    expect(span?.querySelector('svg')).toBeInTheDocument();
  });
});
