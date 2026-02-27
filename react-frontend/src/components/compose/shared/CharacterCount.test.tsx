// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CharacterCount component
 */

import { describe, it, expect } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { CharacterCount } from './CharacterCount';

describe('CharacterCount', () => {
  it('renders current/max count text', () => {
    render(<CharacterCount current={50} max={500} />);
    expect(screen.getByText('50/500')).toBeInTheDocument();
  });

  it('renders zero count', () => {
    render(<CharacterCount current={0} max={280} />);
    expect(screen.getByText('0/280')).toBeInTheDocument();
  });

  it('shows default color when well below limit', () => {
    const { container } = render(<CharacterCount current={10} max={500} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveClass('bg-[var(--color-primary)]');
    expect(bar).not.toHaveClass('bg-amber-500');
    expect(bar).not.toHaveClass('bg-red-500');
  });

  it('shows warning color (amber) when at 80% usage', () => {
    // 80% of 500 = 400
    const { container } = render(<CharacterCount current={400} max={500} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveClass('bg-amber-500');
  });

  it('shows warning color at 90% usage (between 80% and 95%)', () => {
    // 90% of 500 = 450
    const { container } = render(<CharacterCount current={450} max={500} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveClass('bg-amber-500');
  });

  it('shows danger color (red) when at 95%+ usage', () => {
    // 95% of 500 = 475
    const { container } = render(<CharacterCount current={475} max={500} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveClass('bg-red-500');
  });

  it('shows danger color when at exactly the limit', () => {
    const { container } = render(<CharacterCount current={500} max={500} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveClass('bg-red-500');
  });

  it('shows red text color when at or exceeding the limit', () => {
    render(<CharacterCount current={500} max={500} />);
    const countText = screen.getByText('500/500');
    expect(countText).toHaveClass('text-red-500');
  });

  it('shows muted text color when below the limit', () => {
    render(<CharacterCount current={100} max={500} />);
    const countText = screen.getByText('100/500');
    expect(countText).toHaveClass('text-[var(--text-muted)]');
  });

  it('sets progress bar width based on percentage', () => {
    const { container } = render(<CharacterCount current={250} max={500} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveStyle({ width: '50%' });
  });

  it('caps progress bar at 100% when exceeding limit', () => {
    const { container } = render(<CharacterCount current={600} max={500} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveStyle({ width: '100%' });
  });

  it('handles zero max gracefully (no division by zero)', () => {
    const { container } = render(<CharacterCount current={10} max={0} />);
    const bar = container.querySelector('[style*="width"]');
    expect(bar).toHaveStyle({ width: '0%' });
    expect(screen.getByText('10/0')).toBeInTheDocument();
  });
});
