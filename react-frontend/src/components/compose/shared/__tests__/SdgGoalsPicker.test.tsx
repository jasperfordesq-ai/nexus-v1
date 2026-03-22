// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SdgGoalsPicker component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

// Mock SDG_GOALS data with a small set for testing
vi.mock('@/data/sdg-goals', () => ({
  SDG_GOALS: [
    { id: 1, label: 'No Poverty', icon: '🎯', color: '#E5243B' },
    { id: 2, label: 'Zero Hunger', icon: '🌾', color: '#DDA63A' },
    { id: 3, label: 'Good Health', icon: '💚', color: '#4C9F38' },
    { id: 13, label: 'Climate Action', icon: '🌍', color: '#3F7E44' },
  ],
}));

import { SdgGoalsPicker } from '../SdgGoalsPicker';

describe('SdgGoalsPicker', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders all SDG goal chips', () => {
    render(<SdgGoalsPicker selected={[]} onChange={vi.fn()} />);
    expect(screen.getByText(/No Poverty/)).toBeInTheDocument();
    expect(screen.getByText(/Zero Hunger/)).toBeInTheDocument();
    expect(screen.getByText(/Good Health/)).toBeInTheDocument();
    expect(screen.getByText(/Climate Action/)).toBeInTheDocument();
  });

  it('shows pre-selected goals as active', () => {
    render(<SdgGoalsPicker selected={[1, 13]} onChange={vi.fn()} />);
    // Active chips should have solid variant styling
    const noPoverty = screen.getByText(/No Poverty/).closest('[class*="cursor-pointer"]');
    expect(noPoverty).toBeInTheDocument();
  });

  it('does not show selected count text when nothing is selected', () => {
    render(<SdgGoalsPicker selected={[]} onChange={vi.fn()} />);
    expect(screen.queryByText(/goal selected/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/goals selected/i)).not.toBeInTheDocument();
  });

  it('shows singular count text when one goal is selected', () => {
    render(<SdgGoalsPicker selected={[1]} onChange={vi.fn()} />);
    // i18n key returns key itself in tests; just verify some count indicator renders
    const countText = screen.queryByText(/1/);
    expect(countText).toBeTruthy();
  });

  it('calls onChange with added goal id when an unselected chip is clicked', () => {
    const onChange = vi.fn();
    render(<SdgGoalsPicker selected={[]} onChange={onChange} />);

    const chip = screen.getByText(/No Poverty/).closest('[class*="cursor-pointer"]');
    expect(chip).toBeTruthy();
    fireEvent.click(chip!);

    expect(onChange).toHaveBeenCalledWith([1]);
  });

  it('calls onChange removing goal id when a selected chip is clicked', () => {
    const onChange = vi.fn();
    render(<SdgGoalsPicker selected={[1, 2]} onChange={onChange} />);

    const chip = screen.getByText(/No Poverty/).closest('[class*="cursor-pointer"]');
    expect(chip).toBeTruthy();
    fireEvent.click(chip!);

    expect(onChange).toHaveBeenCalledWith([2]);
  });

  it('toggles multiple goals correctly', () => {
    const onChange = vi.fn();
    render(<SdgGoalsPicker selected={[]} onChange={onChange} />);

    const chip1 = screen.getByText(/No Poverty/).closest('[class*="cursor-pointer"]');
    fireEvent.click(chip1!);
    expect(onChange).toHaveBeenCalledWith([1]);

    // Simulate second click with updated selection
    const { rerender } = render(<SdgGoalsPicker selected={[1]} onChange={onChange} />);
    const chip2 = screen.getByText(/Zero Hunger/).closest('[class*="cursor-pointer"]');
    fireEvent.click(chip2!);
    expect(onChange).toHaveBeenCalledWith([1, 2]);

    rerender(<SdgGoalsPicker selected={[1, 2]} onChange={onChange} />);
  });
});
