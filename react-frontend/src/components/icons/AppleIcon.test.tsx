// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';

describe('AppleIcon', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an svg element', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(<AppleIcon />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
  });

  it('has viewBox="0 0 24 24"', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(<AppleIcon />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('viewBox')).toBe('0 0 24 24');
  });

  it('has aria-hidden="true" by default', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(<AppleIcon />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('renders a path with fill="currentColor"', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(<AppleIcon />);
    const paths = container.querySelectorAll('path');
    const fills = Array.from(paths).map((p) => p.getAttribute('fill'));
    expect(fills).toContain('currentColor');
  });

  it('renders exactly one path element', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(<AppleIcon />);
    const paths = container.querySelectorAll('path');
    expect(paths.length).toBe(1);
  });

  it('forwards className prop using getAttribute("class")', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(<AppleIcon className="w-5 h-5 apple-icon" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-5 h-5 apple-icon');
  });

  it('forwards width and height props', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(<AppleIcon width={48} height={48} />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('width')).toBe('48');
    expect(svg?.getAttribute('height')).toBe('48');
  });

  it('allows overriding aria-hidden', async () => {
    const { AppleIcon } = await import('./AppleIcon');
    const { container } = render(
      // eslint-disable-next-line jsx-a11y/aria-hidden-on-focusable
      <AppleIcon aria-hidden="false" aria-label="Sign in with Apple" />
    );
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('false');
  });

  it('is also the default export', async () => {
    const mod = await import('./AppleIcon');
    expect(mod.default).toBe(mod.AppleIcon);
  });
});
