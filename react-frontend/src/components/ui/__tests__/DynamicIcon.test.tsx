// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DynamicIcon component
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

import { DynamicIcon, ICON_MAP, ICON_NAMES } from '../DynamicIcon';

describe('DynamicIcon', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders null when name is null', () => {
    const { container } = render(<DynamicIcon name={null} />);
    expect(container.querySelector('svg')).toBeNull();
  });

  it('renders null when name is undefined', () => {
    const { container } = render(<DynamicIcon name={undefined} />);
    expect(container.querySelector('svg')).toBeNull();
  });

  it('renders null when name is empty string', () => {
    const { container } = render(<DynamicIcon name="" />);
    expect(container.querySelector('svg')).toBeNull();
  });

  it('renders null when name is unknown icon', () => {
    const { container } = render(<DynamicIcon name="NonExistentIcon" />);
    expect(container.querySelector('svg')).toBeNull();
  });

  it('renders an SVG icon for a known icon name', () => {
    const { container } = render(<DynamicIcon name="Home" />);
    const svg = container.querySelector('svg');
    expect(svg).toBeInTheDocument();
  });

  it('renders the Home icon', () => {
    const { container } = render(<DynamicIcon name="Home" />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('renders the Users icon', () => {
    const { container } = render(<DynamicIcon name="Users" />);
    expect(container.querySelector('svg')).toBeInTheDocument();
  });

  it('applies custom className to the icon', () => {
    const { container } = render(<DynamicIcon name="Home" className="text-red-500 w-6 h-6" />);
    const svg = container.querySelector('svg');
    expect(svg).toHaveAttribute('class', expect.stringContaining('text-red-500'));
  });

  it('applies custom size to the icon', () => {
    const { container } = render(<DynamicIcon name="Home" size={24} />);
    const svg = container.querySelector('svg');
    expect(svg).toHaveAttribute('width', '24');
  });

  it('renders with aria-hidden="true" for decorative use', () => {
    const { container } = render(<DynamicIcon name="Heart" />);
    const svg = container.querySelector('svg');
    expect(svg).toHaveAttribute('aria-hidden', 'true');
  });
});

describe('ICON_MAP and ICON_NAMES', () => {
  it('ICON_MAP is a non-empty object', () => {
    expect(Object.keys(ICON_MAP).length).toBeGreaterThan(0);
  });

  it('ICON_NAMES matches keys of ICON_MAP', () => {
    expect(ICON_NAMES).toEqual(Object.keys(ICON_MAP));
  });

  it('ICON_MAP contains Home', () => {
    expect(ICON_MAP).toHaveProperty('Home');
  });

  it('ICON_MAP contains Wallet', () => {
    expect(ICON_MAP).toHaveProperty('Wallet');
  });

  it('ICON_MAP contains Trophy', () => {
    expect(ICON_MAP).toHaveProperty('Trophy');
  });
});
