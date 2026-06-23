// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render } from '@/test/test-utils';

describe('GoogleIcon', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an svg element', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const svg = container.querySelector('svg');
    expect(svg).not.toBeNull();
  });

  it('has the correct viewBox attribute', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('viewBox')).toBe('0 0 24 24');
  });

  it('is aria-hidden by default', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('aria-hidden')).toBe('true');
  });

  it('renders exactly four colored path elements', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const paths = container.querySelectorAll('path');
    expect(paths.length).toBe(4);
  });

  it('renders the Google blue path (#4285F4)', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const paths = container.querySelectorAll('path');
    const fills = Array.from(paths).map(p => p.getAttribute('fill'));
    expect(fills).toContain('#4285F4');
  });

  it('renders the Google green path (#34A853)', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const paths = container.querySelectorAll('path');
    const fills = Array.from(paths).map(p => p.getAttribute('fill'));
    expect(fills).toContain('#34A853');
  });

  it('renders the Google yellow path (#FBBC05)', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const paths = container.querySelectorAll('path');
    const fills = Array.from(paths).map(p => p.getAttribute('fill'));
    expect(fills).toContain('#FBBC05');
  });

  it('renders the Google red path (#EA4335)', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon />);
    const paths = container.querySelectorAll('path');
    const fills = Array.from(paths).map(p => p.getAttribute('fill'));
    expect(fills).toContain('#EA4335');
  });

  it('forwards className prop to the svg element', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon className="w-5 h-5 google-icon" />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('class')).toBe('w-5 h-5 google-icon');
  });

  it('forwards width and height props to the svg element', async () => {
    const { GoogleIcon } = await import('./GoogleIcon');
    const { container } = render(<GoogleIcon width={32} height={32} />);
    const svg = container.querySelector('svg');
    expect(svg?.getAttribute('width')).toBe('32');
    expect(svg?.getAttribute('height')).toBe('32');
  });

  it('is also the default export', async () => {
    const mod = await import('./GoogleIcon');
    expect(mod.default).toBe(mod.GoogleIcon);
  });
});
