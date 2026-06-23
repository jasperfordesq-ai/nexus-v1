// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';

describe('FacebookIcon', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an svg element', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(<FacebookIcon />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
  });

  it('has viewBox="0 0 24 24"', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(<FacebookIcon />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('viewBox')).toBe('0 0 24 24');
  });

  it('has aria-hidden="true" by default', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(<FacebookIcon />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('renders a path with the Facebook blue fill (#1877F2)', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(<FacebookIcon />);
    const paths = container.querySelectorAll('path');
    const fills = Array.from(paths).map((p) => p.getAttribute('fill'));
    expect(fills).toContain('#1877F2');
  });

  it('renders exactly one path element', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(<FacebookIcon />);
    const paths = container.querySelectorAll('path');
    expect(paths.length).toBe(1);
  });

  it('forwards className prop using getAttribute("class")', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(<FacebookIcon className="w-6 h-6 fb-icon" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-6 h-6 fb-icon');
  });

  it('forwards width and height props', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(<FacebookIcon width={32} height={32} />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('width')).toBe('32');
    expect(svg?.getAttribute('height')).toBe('32');
  });

  it('allows overriding aria-hidden', async () => {
    const { FacebookIcon } = await import('./FacebookIcon');
    const { container } = render(
      // eslint-disable-next-line jsx-a11y/aria-hidden-on-focusable
      <FacebookIcon aria-hidden="false" aria-label="Facebook" />
    );
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('false');
  });

  it('is also the default export', async () => {
    const mod = await import('./FacebookIcon');
    expect(mod.default).toBe(mod.FacebookIcon);
  });
});
