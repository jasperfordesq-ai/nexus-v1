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

import { ProximityFilter, type ProximityFilterParams } from './ProximityFilter';

const ACTIVE_VALUE: ProximityFilterParams = {
  near_lat: 53.3498,
  near_lng: -6.2603,
  radius_km: 25,
};

describe('proximity/ProximityFilter', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Inactive state (value === null) ────────────────────────────────────

  it('renders only the "Near me" button when value is null', () => {
    render(<ProximityFilter value={null} onFilter={vi.fn()} />);
    expect(screen.getAllByRole('button').length).toBe(1);
    expect(screen.getAllByRole('button')[0]).toHaveAttribute('aria-pressed', 'false');
  });

  it('does NOT render a Select trigger when value is null', () => {
    render(<ProximityFilter value={null} onFilter={vi.fn()} />);
    // No select trigger — combobox/listbox not present
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument();
  });

  // ── Toggle ON — user has NO location (default mock has user: null) ─────

  it('fires toast.error and does NOT call onFilter when user has no location', () => {
    const onFilter = vi.fn();
    render(<ProximityFilter value={null} onFilter={onFilter} />);
    fireEvent.click(screen.getAllByRole('button')[0]);
    expect(mockToast.error).toHaveBeenCalledTimes(1);
    expect(onFilter).not.toHaveBeenCalled();
  });

  // ── Active state (value !== null) ──────────────────────────────────────

  it('sets aria-pressed=true on the Near-me button when value is non-null', () => {
    render(<ProximityFilter value={ACTIVE_VALUE} onFilter={vi.fn()} />);
    expect(screen.getAllByRole('button')[0]).toHaveAttribute('aria-pressed', 'true');
  });

  it('renders a Select trigger alongside the Near-me button when active', () => {
    render(<ProximityFilter value={ACTIVE_VALUE} onFilter={vi.fn()} />);
    // Near-me toggle + Select trigger
    expect(screen.getAllByRole('button').length).toBeGreaterThanOrEqual(2);
  });

  it('calls onFilter(null) when toggling OFF', () => {
    const onFilter = vi.fn();
    render(<ProximityFilter value={ACTIVE_VALUE} onFilter={onFilter} />);
    // First button is always the Near-me toggle
    fireEvent.click(screen.getAllByRole('button')[0]);
    expect(onFilter).toHaveBeenCalledWith(null);
  });

  // ── Select shows the current radius ───────────────────────────────────

  it('shows the current radius_km value in the Select when active', () => {
    render(<ProximityFilter value={{ near_lat: 1, near_lng: 2, radius_km: 50 }} onFilter={vi.fn()} />);
    // HeroUI Select renders the selected value in a visible <span> and also
    // in hidden <option> elements — use getAllByText to handle multiple matches.
    const matches = screen.getAllByText(/50/);
    expect(matches.length).toBeGreaterThanOrEqual(1);
  });

  // ── className prop ──────────────────────────────────────────────────────

  it('applies additional className to the wrapper div', () => {
    const { container } = render(
      <ProximityFilter value={null} onFilter={vi.fn()} className="extra-class" />
    );
    const wrapper = container.querySelector('.extra-class');
    expect(wrapper).toBeInTheDocument();
  });
});
