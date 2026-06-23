// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render } from '@/test/test-utils';

describe('BookmarksEmptyIllustration', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an svg element', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
  });

  it('has the correct viewBox attribute', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('viewBox')).toBe('0 0 128 128');
  });

  it('is aria-hidden by default', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('applies the default className to the svg', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-32 h-32');
  });

  it('applies a custom className when provided', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration className="w-20 h-20 bookmarks" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-20 h-20 bookmarks');
  });

  it('renders circle elements (background and decorative sparkles)', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const circles = container.querySelectorAll('circle');
    // background circle + 3 decorative sparkle circles
    expect(circles.length).toBeGreaterThanOrEqual(4);
  });

  it('renders path elements (bookmark ribbon and heart)', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const paths = container.querySelectorAll('path');
    // two ribbon paths (fill + stroke) + one heart path
    expect(paths.length).toBeGreaterThanOrEqual(3);
  });

  it('has fill="none" on the root svg', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('fill')).toBe('none');
  });

  it('has the correct xmlns attribute', async () => {
    const { BookmarksEmptyIllustration } = await import('./BookmarksEmptyIllustration');
    const { container } = render(<BookmarksEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('xmlns')).toBe('http://www.w3.org/2000/svg');
  });
});
