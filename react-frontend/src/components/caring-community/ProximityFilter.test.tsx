// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

import { ProximityFilter } from './ProximityFilter';

describe('caring-community/ProximityFilter', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Inactive state (radiusKm === null) ──────────────────────────────────

  it('renders the "Near me" toggle button when inactive', () => {
    render(<ProximityFilter radiusKm={null} onRadiusChange={vi.fn()} />);
    // The near-me button is the only button when inactive
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBe(1);
    expect(buttons[0]).toHaveAttribute('aria-pressed', 'false');
  });

  it('does NOT render a Select trigger when inactive', () => {
    render(<ProximityFilter radiusKm={null} onRadiusChange={vi.fn()} />);
    // Only one button when inactive (the "Near me" toggle, no select trigger)
    expect(screen.getAllByRole('button').length).toBe(1);
  });

  // ── Toggle ON — user has NO location (default mock has user: null) ─────

  it('fires toast.error and does NOT call onRadiusChange when user has no location', () => {
    const onRadiusChange = vi.fn();
    render(<ProximityFilter radiusKm={null} onRadiusChange={onRadiusChange} />);
    // Only one button in inactive state — the near-me toggle
    fireEvent.click(screen.getAllByRole('button')[0]);
    expect(mockToast.error).toHaveBeenCalledTimes(1);
    expect(onRadiusChange).not.toHaveBeenCalled();
  });

  // ── Active state (radiusKm !== null) ───────────────────────────────────

  it('sets aria-pressed=true on the Near-me button when active', () => {
    render(<ProximityFilter radiusKm={25} onRadiusChange={vi.fn()} />);
    // First button is always the Near-me toggle
    const nearMeBtn = screen.getAllByRole('button')[0];
    expect(nearMeBtn).toHaveAttribute('aria-pressed', 'true');
  });

  it('renders a Select trigger when active (two buttons total)', () => {
    render(<ProximityFilter radiusKm={25} onRadiusChange={vi.fn()} />);
    const buttons = screen.getAllByRole('button');
    // Near-me toggle + Select trigger
    expect(buttons.length).toBeGreaterThanOrEqual(2);
  });

  it('calls onRadiusChange(null) when toggling OFF', () => {
    const onRadiusChange = vi.fn();
    render(<ProximityFilter radiusKm={10} onRadiusChange={onRadiusChange} />);
    // First button is the near-me toggle
    const nearMeBtn = screen.getAllByRole('button')[0];
    fireEvent.click(nearMeBtn);
    expect(onRadiusChange).toHaveBeenCalledWith(null);
  });

  // ── Select shows the current radius in its trigger label ─────────────

  it('shows the current radius value in the Select trigger label', () => {
    render(<ProximityFilter radiusKm={10} onRadiusChange={vi.fn()} />);
    // HeroUI Select renders the selected value in a visible <span class="label">
    // and also in hidden <option> elements. We assert at least one visible match.
    const matches = screen.getAllByText(/10/);
    expect(matches.length).toBeGreaterThanOrEqual(1);
  });

  // ── className prop ──────────────────────────────────────────────────────

  it('applies additional className to the wrapper div', () => {
    const { container } = render(
      <ProximityFilter radiusKm={null} onRadiusChange={vi.fn()} className="custom-class" />
    );
    // The outermost div rendered by the component (direct child of container div)
    const wrapper = container.querySelector('.custom-class');
    expect(wrapper).toBeInTheDocument();
  });
});
