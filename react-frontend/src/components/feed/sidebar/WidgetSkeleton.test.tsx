// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock contexts ────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub GlassCard + Skeleton so we get predictable DOM output ───────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="glass-card" className={className}>
        {children}
      </div>
    ),
    Skeleton: ({ className }: { className?: string }) => (
      <div data-testid="skeleton" className={className} />
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('WidgetSkeleton', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders a GlassCard wrapper', async () => {
    const { WidgetSkeleton } = await import('./WidgetSkeleton');
    render(<WidgetSkeleton />);
    expect(screen.getByTestId('glass-card')).toBeInTheDocument();
  });

  it('renders the default 3 row skeletons (header + 3 rows × 3 skeletons each = 10 total)', async () => {
    const { WidgetSkeleton } = await import('./WidgetSkeleton');
    render(<WidgetSkeleton />);
    // 1 header + 3 rows × 3 skeletons (avatar, text-line-1, text-line-2) = 10
    const skeletons = screen.getAllByTestId('skeleton');
    expect(skeletons.length).toBe(10);
  });

  it('renders the correct number of skeletons when lines=1', async () => {
    const { WidgetSkeleton } = await import('./WidgetSkeleton');
    render(<WidgetSkeleton lines={1} />);
    // 1 header + 1 row × 3 skeletons = 4
    const skeletons = screen.getAllByTestId('skeleton');
    expect(skeletons.length).toBe(4);
  });

  it('renders the correct number of skeletons when lines=5', async () => {
    const { WidgetSkeleton } = await import('./WidgetSkeleton');
    render(<WidgetSkeleton lines={5} />);
    // 1 header + 5 rows × 3 skeletons = 16
    const skeletons = screen.getAllByTestId('skeleton');
    expect(skeletons.length).toBe(16);
  });

  it('renders only 1 skeleton when lines=0', async () => {
    const { WidgetSkeleton } = await import('./WidgetSkeleton');
    render(<WidgetSkeleton lines={0} />);
    // only the header skeleton remains
    const skeletons = screen.getAllByTestId('skeleton');
    expect(skeletons.length).toBe(1);
  });

  it('the header skeleton has rounded and margin class', async () => {
    const { WidgetSkeleton } = await import('./WidgetSkeleton');
    render(<WidgetSkeleton />);
    const first = screen.getAllByTestId('skeleton')[0];
    expect(first.className).toMatch(/mb-4/);
  });

  it('each row has an avatar skeleton with rounded-full class', async () => {
    const { WidgetSkeleton } = await import('./WidgetSkeleton');
    render(<WidgetSkeleton lines={2} />);
    const skeletons = screen.getAllByTestId('skeleton');
    // skeletons[0]=header, [1]=row1-avatar, [2]=row1-text1, [3]=row1-text2, [4]=row2-avatar
    expect(skeletons[1].className).toMatch(/rounded-full/);
    expect(skeletons[4].className).toMatch(/rounded-full/);
  });
});
