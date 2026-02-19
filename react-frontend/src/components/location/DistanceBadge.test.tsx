// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { DistanceBadge } from './DistanceBadge';

describe('DistanceBadge', () => {
  it('renders distance in meters when less than 1km', () => {
    render(<DistanceBadge distanceKm={0.5} />);
    expect(screen.getByText('500m')).toBeInTheDocument();
  });

  it('rounds meters to the nearest whole number', () => {
    render(<DistanceBadge distanceKm={0.123} />);
    expect(screen.getByText('123m')).toBeInTheDocument();
  });

  it('renders distance with one decimal for values between 1km and 10km', () => {
    render(<DistanceBadge distanceKm={3.456} />);
    expect(screen.getByText('3.5km')).toBeInTheDocument();
  });

  it('renders exactly 1.0km for distanceKm=1', () => {
    render(<DistanceBadge distanceKm={1} />);
    expect(screen.getByText('1.0km')).toBeInTheDocument();
  });

  it('renders rounded km for values 10km and above', () => {
    render(<DistanceBadge distanceKm={15.7} />);
    expect(screen.getByText('16km')).toBeInTheDocument();
  });

  it('renders exactly 10km (boundary) as rounded km', () => {
    render(<DistanceBadge distanceKm={10} />);
    expect(screen.getByText('10km')).toBeInTheDocument();
  });

  it('renders large distances correctly', () => {
    render(<DistanceBadge distanceKm={123.4} />);
    expect(screen.getByText('123km')).toBeInTheDocument();
  });

  it('renders 0m for zero distance', () => {
    render(<DistanceBadge distanceKm={0} />);
    expect(screen.getByText('0m')).toBeInTheDocument();
  });

  it('applies custom className', () => {
    const { container } = render(
      <DistanceBadge distanceKm={5} className="ml-2 font-bold" />
    );
    const badge = container.querySelector('span');
    expect(badge).toHaveClass('ml-2');
    expect(badge).toHaveClass('font-bold');
  });

  it('applies default base classes', () => {
    const { container } = render(<DistanceBadge distanceKm={5} />);
    const badge = container.querySelector('span');
    expect(badge).toHaveClass('inline-flex');
    expect(badge).toHaveClass('items-center');
    expect(badge).toHaveClass('text-xs');
  });
});
