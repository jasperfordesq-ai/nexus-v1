// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// No api calls in this component — no api mock needed.
// No contexts consumed — no context mock needed.

describe('ImagePlaceholder', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders without crashing with default props', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder />);
    // Root element is aria-hidden — verify it is in the DOM
    const root = container.querySelector('[aria-hidden="true"]');
    expect(root).toBeInTheDocument();
  });

  it('is aria-hidden by default (decorative element)', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder />);
    const root = container.querySelector('[aria-hidden="true"]');
    expect(root).toBeTruthy();
    expect(root?.getAttribute('aria-hidden')).toBe('true');
  });

  it('applies sm height class for size="sm"', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder size="sm" />);
    const root = container.querySelector('[aria-hidden="true"]');
    expect(root?.className).toContain('h-36');
  });

  it('applies md height class for size="md" (default)', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder size="md" />);
    const root = container.querySelector('[aria-hidden="true"]');
    expect(root?.className).toContain('h-48');
  });

  it('applies lg height class for size="lg"', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder size="lg" />);
    const root = container.querySelector('[aria-hidden="true"]');
    expect(root?.className).toContain('h-56');
  });

  it('passes extra className to the outer container', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder className="my-custom-class" />);
    const root = container.querySelector('[aria-hidden="true"]');
    expect(root?.className).toContain('my-custom-class');
  });

  it('renders a custom icon when icon prop is provided', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    // Use a Lucide icon that renders an SVG with a predictable structure
    const FakeIcon = ({ className }: { className?: string }) => (
      <svg data-testid="custom-icon" className={className} />
    );
    render(<ImagePlaceholder icon={FakeIcon as never} />);
    expect(screen.getByTestId('custom-icon')).toBeInTheDocument();
  });

  it('renders the default ShoppingBag icon when no icon prop is given', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder />);
    // Lucide renders an SVG; verify at least one SVG is present inside the glass container
    const svgs = container.querySelectorAll('svg');
    expect(svgs.length).toBeGreaterThan(0);
  });

  it('always has overflow-hidden on the root for clip safety', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder />);
    const root = container.querySelector('[aria-hidden="true"]');
    expect(root?.className).toContain('overflow-hidden');
  });

  it('renders decorative orbs (multiple children inside root)', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');
    const { container } = render(<ImagePlaceholder />);
    const root = container.querySelector('[aria-hidden="true"]');
    // Should have at least 4 child divs (gradient, pattern, orb ×2, glass center)
    expect(root?.children.length).toBeGreaterThanOrEqual(4);
  });

  it('sm icon has smaller class than lg icon', async () => {
    const { ImagePlaceholder } = await import('./ImagePlaceholder');

    const SmIcon = ({ className }: { className?: string }) => (
      <svg data-testid="sm-icon" className={className} />
    );
    const { rerender, container } = render(
      <ImagePlaceholder size="sm" icon={SmIcon as never} />
    );
    const smClass = screen.getByTestId('sm-icon').getAttribute('class') ?? '';

    const LgIcon = ({ className }: { className?: string }) => (
      <svg data-testid="lg-icon" className={className} />
    );
    rerender(<ImagePlaceholder size="lg" icon={LgIcon as never} />);
    const lgClass = screen.getByTestId('lg-icon').getAttribute('class') ?? '';

    // sm uses w-10 h-10, lg uses w-16 h-16
    expect(smClass).toContain('w-10');
    expect(lgClass).toContain('w-16');
    void container;
  });
});
