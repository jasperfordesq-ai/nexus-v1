// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for FeaturedBadge component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

import { FeaturedBadge } from '../FeaturedBadge';

describe('FeaturedBadge', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(<FeaturedBadge />);
    expect(container.firstChild).toBeTruthy();
  });

  it('renders "Featured" text', () => {
    render(<FeaturedBadge />);
    expect(screen.getByText(/Featured/i)).toBeInTheDocument();
  });

  it('has aria-label "Featured listing"', () => {
    render(<FeaturedBadge />);
    expect(screen.getByLabelText('Featured listing')).toBeInTheDocument();
  });

  it('renders a star icon inside the badge', () => {
    const { container } = render(<FeaturedBadge />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('applies sm size classes by default', () => {
    const { container } = render(<FeaturedBadge />);
    const badge = container.querySelector('span[aria-label="Featured listing"]') as HTMLElement;
    expect(badge.className).toContain('text-[10px]');
  });

  it('applies md size classes when size="md"', () => {
    const { container } = render(<FeaturedBadge size="md" />);
    const badge = container.querySelector('span[aria-label="Featured listing"]') as HTMLElement;
    expect(badge.className).toContain('text-xs');
  });

  it('applies custom className to the badge', () => {
    const { container } = render(<FeaturedBadge className="absolute top-2 right-2" />);
    const badge = container.querySelector('span[aria-label="Featured listing"]') as HTMLElement;
    expect(badge.className).toContain('absolute');
    expect(badge.className).toContain('top-2');
  });

  it('has amber color classes', () => {
    const { container } = render(<FeaturedBadge />);
    const badge = container.querySelector('span[aria-label="Featured listing"]') as HTMLElement;
    expect(badge.className).toContain('amber');
  });
});
