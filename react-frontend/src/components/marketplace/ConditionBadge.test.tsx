// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

vi.mock('@/contexts', () => {
  const { createMockContexts } = require('@/test/mock-contexts');
  return createMockContexts();
});

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Stub the Chip component so we can test ConditionBadge's label logic without
// worrying about HeroUI internals in jsdom.
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Chip: ({
      children,
      color,
      'aria-label': ariaLabel,
    }: {
      children?: React.ReactNode;
      color?: string;
      'aria-label'?: string;
    }) => (
      <span
        data-testid="chip"
        data-color={color}
        aria-label={ariaLabel}
      >
        {children}
      </span>
    ),
  };
});

describe('ConditionBadge', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders nothing when condition is null', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    const { container } = render(<ConditionBadge condition={null} />);
    // ToastProvider wrapper exists but no chip
    expect(container.querySelector('[data-testid="chip"]')).toBeNull();
  });

  it('renders nothing for an unknown condition string', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    const { container } = render(<ConditionBadge condition="mint" />);
    expect(container.querySelector('[data-testid="chip"]')).toBeNull();
  });

  it('renders "New" for condition "new" with success color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="new" />);
    const chip = screen.getByTestId('chip');
    expect(chip).toBeInTheDocument();
    expect(chip).toHaveTextContent('New');
    expect(chip).toHaveAttribute('data-color', 'success');
  });

  it('renders "Like New" for condition "like_new" with accent color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="like_new" />);
    const chip = screen.getByTestId('chip');
    expect(chip).toHaveTextContent('Like New');
    expect(chip).toHaveAttribute('data-color', 'accent');
  });

  it('renders "Good" for condition "good" with default color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="good" />);
    const chip = screen.getByTestId('chip');
    expect(chip).toHaveTextContent('Good');
    expect(chip).toHaveAttribute('data-color', 'default');
  });

  it('renders "Fair" for condition "fair" with warning color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="fair" />);
    const chip = screen.getByTestId('chip');
    expect(chip).toHaveTextContent('Fair');
    expect(chip).toHaveAttribute('data-color', 'warning');
  });

  it('renders "Poor" for condition "poor" with danger color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="poor" />);
    const chip = screen.getByTestId('chip');
    expect(chip).toHaveTextContent('Poor');
    expect(chip).toHaveAttribute('data-color', 'danger');
  });

  it('sets a matching aria-label for accessibility', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="new" />);
    const chip = screen.getByTestId('chip');
    // aria-label uses t() which falls back to the English label when no translation key exists
    expect(chip).toHaveAttribute('aria-label');
    const label = chip.getAttribute('aria-label') ?? '';
    expect(label.length).toBeGreaterThan(0);
  });

  it('renders exactly one chip per call (no duplicates)', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="good" />);
    const chips = screen.getAllByTestId('chip');
    expect(chips).toHaveLength(1);
  });

  it('covers all five defined conditions without throwing', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    const conditions = ['new', 'like_new', 'good', 'fair', 'poor'] as const;
    for (const condition of conditions) {
      const { unmount } = render(<ConditionBadge condition={condition} />);
      expect(screen.getByTestId('chip')).toBeInTheDocument();
      unmount();
    }
  });
});
