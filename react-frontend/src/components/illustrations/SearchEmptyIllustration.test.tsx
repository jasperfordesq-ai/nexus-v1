// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render } from '@/test/test-utils';

describe('SearchEmptyIllustration', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an svg element', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
  });

  it('has the correct viewBox attribute', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('viewBox')).toBe('0 0 128 128');
  });

  it('is aria-hidden by default', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('applies the default className to the svg', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-32 h-32');
  });

  it('applies a custom className when provided', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration className="w-48 h-48 search-icon" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-48 h-48 search-icon');
  });

  it('renders circle elements (lens and decorative dots)', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const circles = container.querySelectorAll('circle');
    // lens circle (×2 overlapping), dot at 58, decorative dots at 100/36 and 24/76
    expect(circles.length).toBeGreaterThanOrEqual(4);
  });

  it('renders a line element for the magnifying glass handle', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const lines = container.querySelectorAll('line');
    expect(lines.length).toBeGreaterThanOrEqual(1);
  });

  it('has fill="none" on the root svg', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('fill')).toBe('none');
  });

  it('has the correct xmlns attribute', async () => {
    const { SearchEmptyIllustration } = await import('./SearchEmptyIllustration');
    const { container } = render(<SearchEmptyIllustration />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('xmlns')).toBe('http://www.w3.org/2000/svg');
  });
});
