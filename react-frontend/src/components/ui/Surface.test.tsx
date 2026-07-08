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

// ─── Stub HeroUI Surface so we can inspect the mapped variant prop ────────────
vi.mock('@heroui/react/surface', () => {
  return {
    Surface: ({ variant, children, ...rest }: { variant?: string; children?: React.ReactNode; [key: string]: unknown }) => (
      <div data-testid="heroui-surface" data-variant={variant} {...rest}>
        {children}
      </div>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('Surface', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders children', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface>content</Surface>);
    expect(screen.getByText('content')).toBeInTheDocument();
  });

  it('passes through HeroUI Surface (default variant → "default")', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface>default</Surface>);
    const el = screen.getByTestId('heroui-surface');
    expect(el).toHaveAttribute('data-variant', 'default');
  });

  it('maps "elevated" → "secondary"', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface variant="elevated">el</Surface>);
    expect(screen.getByTestId('heroui-surface')).toHaveAttribute('data-variant', 'secondary');
  });

  it('maps "filled" → "secondary"', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface variant="filled">fl</Surface>);
    expect(screen.getByTestId('heroui-surface')).toHaveAttribute('data-variant', 'secondary');
  });

  it('maps "outlined" → "transparent"', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface variant="outlined">ol</Surface>);
    expect(screen.getByTestId('heroui-surface')).toHaveAttribute('data-variant', 'transparent');
  });

  it('maps "ghost" → "transparent"', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface variant="ghost">gh</Surface>);
    expect(screen.getByTestId('heroui-surface')).toHaveAttribute('data-variant', 'transparent');
  });

  it('passes through native HeroUI variants unchanged', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface variant="secondary">s</Surface>);
    expect(screen.getByTestId('heroui-surface')).toHaveAttribute('data-variant', 'secondary');
  });

  it('forwards extra props to HeroUI Surface', async () => {
    const { Surface } = await import('./Surface');
    render(<Surface aria-label="my surface">forwarded</Surface>);
    const el = screen.getByTestId('heroui-surface');
    expect(el).toHaveAttribute('aria-label', 'my surface');
  });
});
