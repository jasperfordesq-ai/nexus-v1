// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// ─── Stub HeroUI Skeleton to avoid jsdom rendering issues ───────────────────
vi.mock('@heroui/react', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@heroui/react')>();
  return {
    ...orig,
    Skeleton: ({ className, animationType, ...rest }: { className?: string; animationType?: string; [key: string]: unknown }) => (
      <div
        data-testid="heroui-skeleton"
        data-animation-type={animationType}
        className={className}
        {...rest}
      />
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('Skeleton', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders a HeroUI skeleton element when isLoaded is false (default)', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton className="w-32 h-4" />);
    expect(screen.getByTestId('heroui-skeleton')).toBeInTheDocument();
  });

  it('renders children instead of skeleton when isLoaded is true', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton isLoaded><span data-testid="child">Loaded content</span></Skeleton>);
    expect(screen.getByTestId('child')).toBeInTheDocument();
    expect(screen.queryByTestId('heroui-skeleton')).not.toBeInTheDocument();
  });

  it('does NOT render children when isLoaded is false', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton isLoaded={false}><span data-testid="child">Hidden</span></Skeleton>);
    expect(screen.queryByTestId('child')).not.toBeInTheDocument();
    expect(screen.getByTestId('heroui-skeleton')).toBeInTheDocument();
  });

  it('forwards className to the underlying HeroUI skeleton', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton className="w-16 h-6 rounded-full" />);
    const el = screen.getByTestId('heroui-skeleton');
    expect(el.className).toContain('w-16');
    expect(el.className).toContain('h-6');
    expect(el.className).toContain('rounded-full');
  });

  it('merges classNames.base and classNames.content with className', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton className="extra" classNames={{ base: 'base-class', content: 'content-class' }} />);
    const el = screen.getByTestId('heroui-skeleton');
    expect(el.className).toContain('base-class');
    expect(el.className).toContain('content-class');
    expect(el.className).toContain('extra');
  });

  it('passes animationType="none" when disableAnimation is true', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton disableAnimation />);
    const el = screen.getByTestId('heroui-skeleton');
    expect(el.getAttribute('data-animation-type')).toBe('none');
  });

  it('passes through animationType prop when disableAnimation is false', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton animationType="wave" />);
    const el = screen.getByTestId('heroui-skeleton');
    expect(el.getAttribute('data-animation-type')).toBe('wave');
  });

  it('renders without className when none provided', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton />);
    const el = screen.getByTestId('heroui-skeleton');
    expect(el).toBeInTheDocument();
  });

  it('renders children (via fragment) when isLoaded is true — no wrapper div', async () => {
    const { Skeleton } = await import('./Skeleton');
    const { container } = render(
      <Skeleton isLoaded>
        <p data-testid="content">Real content here</p>
      </Skeleton>,
    );
    expect(screen.getByTestId('content')).toBeInTheDocument();
    // The skeleton wrapper must not be present
    expect(container.querySelector('[data-testid="heroui-skeleton"]')).toBeNull();
  });

  it('handles empty classNames object without crashing', async () => {
    const { Skeleton } = await import('./Skeleton');
    render(<Skeleton classNames={{}} className="test-class" />);
    const el = screen.getByTestId('heroui-skeleton');
    expect(el.className).toContain('test-class');
  });
});
