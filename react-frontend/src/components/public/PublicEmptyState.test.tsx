// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub Card and Chip from @/components/ui so HeroUI internals don't bite ─
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();

  const MockCard = ({
    children,
    className,
  }: {
    children: React.ReactNode;
    className?: string;
  }) => (
    <div data-testid="card" className={className}>
      {children}
    </div>
  );

  const MockCardContent = ({
    children,
    className,
  }: {
    children: React.ReactNode;
    className?: string;
  }) => (
    <div data-testid="card-content" className={className}>
      {children}
    </div>
  );

  MockCard.Content = MockCardContent;

  const MockChip = ({
    children,
    className,
    size,
    variant,
  }: {
    children: React.ReactNode;
    className?: string;
    size?: string;
    variant?: string;
  }) => (
    <span
      data-testid="chip"
      data-size={size}
      data-variant={variant}
      className={className}
    >
      {children}
    </span>
  );

  return {
    ...orig,
    Card: MockCard,
    Chip: MockChip,
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('PublicEmptyState', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the title', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState
        icon={<span data-testid="icon">🔍</span>}
        title="Nothing here yet"
        description="Check back later."
      />,
    );
    expect(screen.getByText('Nothing here yet')).toBeInTheDocument();
  });

  it('renders the description', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState
        icon={<span data-testid="icon">🔍</span>}
        title="Empty"
        description="No results found for your search."
      />,
    );
    expect(screen.getByText('No results found for your search.')).toBeInTheDocument();
  });

  it('renders the icon element', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState
        icon={<span data-testid="empty-icon">⭐</span>}
        title="Stars"
        description="No stars."
      />,
    );
    expect(screen.getByTestId('empty-icon')).toBeInTheDocument();
  });

  it('renders action slot when action prop is provided', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState
        icon={<span />}
        title="Empty"
        description="Desc"
        action={<button type="button">Browse items</button>}
      />,
    );
    expect(screen.getByRole('button', { name: 'Browse items' })).toBeInTheDocument();
  });

  it('does not render action slot when action prop is omitted', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState icon={<span />} title="Empty" description="Desc" />,
    );
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('renders tips as Chip elements', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState
        icon={<span />}
        title="Empty"
        description="Desc"
        tips={['Tip one', 'Tip two', 'Tip three']}
      />,
    );
    const chips = screen.getAllByTestId('chip');
    expect(chips).toHaveLength(3);
    expect(chips[0]).toHaveTextContent('Tip one');
    expect(chips[1]).toHaveTextContent('Tip two');
    expect(chips[2]).toHaveTextContent('Tip three');
  });

  it('does not render tips section when tips is empty', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState
        icon={<span />}
        title="Empty"
        description="Desc"
        tips={[]}
      />,
    );
    expect(screen.queryByTestId('chip')).not.toBeInTheDocument();
  });

  it('does not render tips section when tips is not provided', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState icon={<span />} title="Empty" description="Desc" />,
    );
    expect(screen.queryByTestId('chip')).not.toBeInTheDocument();
  });

  it('applies the correct accent gradient class for "emerald" accent', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    const { container } = render(
      <PublicEmptyState
        icon={<span />}
        title="Empty"
        description="Desc"
        accent="emerald"
      />,
    );
    // The icon wrapper div carries the accent gradient class
    const iconWrapper = container.querySelector('.from-emerald-500\\/20');
    expect(iconWrapper).toBeInTheDocument();
  });

  it('applies the indigo accent class by default', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    const { container } = render(
      <PublicEmptyState icon={<span />} title="Empty" description="Desc" />,
    );
    const iconWrapper = container.querySelector('.from-accent\\/20');
    expect(iconWrapper).toBeInTheDocument();
  });

  it('applies the amber accent class when accent="amber"', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    const { container } = render(
      <PublicEmptyState
        icon={<span />}
        title="Empty"
        description="Desc"
        accent="amber"
      />,
    );
    const iconWrapper = container.querySelector('.from-amber-500\\/20');
    expect(iconWrapper).toBeInTheDocument();
  });

  it('title is rendered as an h2 element', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState
        icon={<span />}
        title="My Heading"
        description="Desc"
      />,
    );
    const heading = screen.getByRole('heading', { level: 2, name: 'My Heading' });
    expect(heading).toBeInTheDocument();
  });

  it('renders Card wrapper', async () => {
    const { PublicEmptyState } = await import('./PublicEmptyState');
    render(
      <PublicEmptyState icon={<span />} title="Empty" description="Desc" />,
    );
    expect(screen.getByTestId('card')).toBeInTheDocument();
    expect(screen.getByTestId('card-content')).toBeInTheDocument();
  });
});
