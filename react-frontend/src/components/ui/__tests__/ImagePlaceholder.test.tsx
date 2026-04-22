// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ImagePlaceholder component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';
import Heart from 'lucide-react/icons/heart';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

import { ImagePlaceholder } from '../ImagePlaceholder';

describe('ImagePlaceholder', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing with default props', () => {
    const { container } = render(<ImagePlaceholder />);
    expect(container.firstChild).toBeTruthy();
  });

  it('renders with aria-hidden="true" (decorative element)', () => {
    const { container } = render(<ImagePlaceholder />);
    const div = container.querySelector('[aria-hidden="true"]') as HTMLElement;
    expect(div).toBeInTheDocument();
    expect(div).toHaveAttribute('aria-hidden', 'true');
  });

  it('applies sm height class for size="sm"', () => {
    const { container } = render(<ImagePlaceholder size="sm" />);
    const div = container.querySelector('[aria-hidden="true"]') as HTMLElement;
    expect(div.className).toContain('h-36');
  });

  it('applies md height class for size="md" (default)', () => {
    const { container } = render(<ImagePlaceholder size="md" />);
    const div = container.querySelector('[aria-hidden="true"]') as HTMLElement;
    expect(div.className).toContain('h-48');
  });

  it('applies lg height class for size="lg"', () => {
    const { container } = render(<ImagePlaceholder size="lg" />);
    const div = container.querySelector('[aria-hidden="true"]') as HTMLElement;
    expect(div.className).toContain('h-56');
  });

  it('applies custom className to outer container', () => {
    const { container } = render(<ImagePlaceholder className="rounded-xl" />);
    const div = container.querySelector('[aria-hidden="true"]') as HTMLElement;
    expect(div.className).toContain('rounded-xl');
  });

  it('renders an icon SVG inside the placeholder', () => {
    const { container } = render(<ImagePlaceholder />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('renders a custom icon when provided', () => {
    const { container } = render(<ImagePlaceholder icon={Heart} />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('has correct w-full class', () => {
    const { container } = render(<ImagePlaceholder />);
    const div = container.querySelector('[aria-hidden="true"]') as HTMLElement;
    expect(div.className).toContain('w-full');
  });

  it('contains gradient background overlay', () => {
    const { container } = render(<ImagePlaceholder />);
    const gradients = container.querySelectorAll('[class*="gradient"]');
    expect(gradients.length).toBeGreaterThan(0);
  });
});
