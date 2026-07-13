// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { render } from '@/test/test-utils';

function getChip() {
  const chip = document.querySelector<HTMLElement>('[data-slot="chip"]');
  expect(chip).not.toBeNull();
  return chip as HTMLElement;
}

describe('ConditionBadge', () => {
  it('renders nothing when condition is null', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    const { container } = render(<ConditionBadge condition={null} />);
    // ToastProvider wrapper exists but no chip
    expect(container.querySelector('[data-slot="chip"]')).toBeNull();
  });

  it('renders nothing for an unknown condition string', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    const { container } = render(<ConditionBadge condition="mint" />);
    expect(container.querySelector('[data-slot="chip"]')).toBeNull();
  });

  it('renders "New" for condition "new" with success color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="new" />);
    const chip = getChip();
    expect(chip).toBeInTheDocument();
    expect(chip).toHaveTextContent('New');
    expect(chip).toHaveClass('chip--success');
  });

  it('renders "Like New" for condition "like_new" with accent color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="like_new" />);
    const chip = getChip();
    expect(chip).toHaveTextContent('Like New');
    expect(chip).toHaveClass('chip--accent');
  });

  it('renders "Good" for condition "good" with default color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="good" />);
    const chip = getChip();
    expect(chip).toHaveTextContent('Good');
    expect(chip).toHaveClass('chip--default');
  });

  it('renders "Fair" for condition "fair" with warning color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="fair" />);
    const chip = getChip();
    expect(chip).toHaveTextContent('Fair');
    expect(chip).toHaveClass('chip--warning');
  });

  it('renders "Poor" for condition "poor" with danger color', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="poor" />);
    const chip = getChip();
    expect(chip).toHaveTextContent('Poor');
    expect(chip).toHaveClass('chip--danger');
  });

  it('sets a matching aria-label for accessibility', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="new" />);
    const chip = getChip();
    // aria-label uses t() which falls back to the English label when no translation key exists
    expect(chip).toHaveAttribute('aria-label');
    const label = chip.getAttribute('aria-label') ?? '';
    expect(label.length).toBeGreaterThan(0);
  });

  it('renders exactly one chip per call (no duplicates)', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    render(<ConditionBadge condition="good" />);
    const chips = document.querySelectorAll('[data-slot="chip"]');
    expect(chips).toHaveLength(1);
  });

  it('covers all five defined conditions without throwing', async () => {
    const { ConditionBadge } = await import('./ConditionBadge');
    const conditions = ['new', 'like_new', 'good', 'fair', 'poor'] as const;
    for (const condition of conditions) {
      const { unmount } = render(<ConditionBadge condition={condition} />);
      expect(getChip()).toBeInTheDocument();
      unmount();
    }
  });
});
