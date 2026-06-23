// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';
import React from 'react';

// Stub HeroUI ScrollShadow so we can inspect the `role` it receives.
// The real component is a complex DOM element; we just need a div that
// renders its children and forwards the `role` attribute.
vi.mock('@heroui/react', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@heroui/react')>();
  return {
    ...orig,
    ScrollShadow: ({
      role,
      children,
      ...rest
    }: {
      role?: string;
      children?: React.ReactNode;
      [key: string]: unknown;
    }) => (
      <div data-testid="heroui-scroll-shadow" role={role} {...rest}>
        {children}
      </div>
    ),
  };
});

describe('ScrollShadow', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('sets role="navigation" when as="nav" is passed', async () => {
    const { ScrollShadow } = await import('./ScrollShadow');
    const { container } = render(
      <ScrollShadow as="nav">nav content</ScrollShadow>
    );
    const el = container.querySelector('[data-testid="heroui-scroll-shadow"]');
    expect(el).not.toBeNull();
    expect(el?.getAttribute('role')).toBe('navigation');
  });

  it('uses an explicit role prop even when as="nav" is set', async () => {
    const { ScrollShadow } = await import('./ScrollShadow');
    const { container } = render(
      <ScrollShadow as="nav" role="region">explicit</ScrollShadow>
    );
    const el = container.querySelector('[data-testid="heroui-scroll-shadow"]');
    expect(el?.getAttribute('role')).toBe('region');
  });

  it('does not set a role when as is not "nav" and no role prop is given', async () => {
    const { ScrollShadow } = await import('./ScrollShadow');
    const { container } = render(<ScrollShadow>plain</ScrollShadow>);
    const el = container.querySelector('[data-testid="heroui-scroll-shadow"]');
    // role attribute should be absent (null) — no semantic role inferred
    expect(el?.getAttribute('role')).toBeNull();
  });

  it('forwards an explicit role when there is no as prop', async () => {
    const { ScrollShadow } = await import('./ScrollShadow');
    const { container } = render(
      <ScrollShadow role="list">items</ScrollShadow>
    );
    const el = container.querySelector('[data-testid="heroui-scroll-shadow"]');
    expect(el?.getAttribute('role')).toBe('list');
  });

  it('renders children inside the wrapper', async () => {
    const { ScrollShadow } = await import('./ScrollShadow');
    const { container } = render(
      <ScrollShadow>child text</ScrollShadow>
    );
    expect(container.textContent).toContain('child text');
  });

  it('forwards passthrough props (e.g. className) to HeroUI ScrollShadow', async () => {
    const { ScrollShadow } = await import('./ScrollShadow');
    const { container } = render(
      <ScrollShadow className="my-class">stuff</ScrollShadow>
    );
    const el = container.querySelector('[data-testid="heroui-scroll-shadow"]');
    expect(el?.getAttribute('class')).toBe('my-class');
  });

  it('does not set role when as is a non-nav element type', async () => {
    const { ScrollShadow } = await import('./ScrollShadow');
    const { container } = render(<ScrollShadow as="div">div</ScrollShadow>);
    const el = container.querySelector('[data-testid="heroui-scroll-shadow"]');
    expect(el?.getAttribute('role')).toBeNull();
  });
});
