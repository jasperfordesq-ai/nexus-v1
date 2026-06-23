// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render } from '@/test/test-utils';

describe('FeedEmptyIllustration', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an svg element', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
  });

  it('has the correct viewBox attribute', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('viewBox')).toBe('0 0 128 128');
  });

  it('is aria-hidden by default', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('applies the default className to the svg', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-32 h-32');
  });

  it('applies a custom className when provided', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration className="w-64 h-64 custom-class" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-64 h-64 custom-class');
  });

  it('contains expected decorative child elements (circles and rects)', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const circles = container.querySelectorAll('circle');
    const rects = container.querySelectorAll('rect');
    expect(circles.length).toBeGreaterThan(0);
    expect(rects.length).toBeGreaterThan(0);
  });

  it('has the correct xmlns attribute', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('xmlns')).toBe('http://www.w3.org/2000/svg');
  });

  it('has fill="none" on the root svg', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('fill')).toBe('none');
  });

  it('contains path elements for the speech bubble tail and sparkle', async () => {
    const { FeedEmptyIllustration } = await import('./FeedEmptyIllustration');
    const { container } = render(<FeedEmptyIllustration />);
    const paths = container.querySelectorAll('path');
    expect(paths.length).toBeGreaterThan(0);
  });
});
