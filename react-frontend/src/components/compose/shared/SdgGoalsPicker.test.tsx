// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── No API calls in this component — no api mock needed ─────────────────────

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ─── Stub @/components/ui — Chip must be clickable ───────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    // Pass-through Chip with all relevant attributes so onClick/aria-pressed work
    Chip: ({
      children,
      onClick,
      onKeyDown,
      role,
      tabIndex,
      'aria-pressed': ariaPressedRaw,
      variant,
      ...rest
    }: React.HTMLAttributes<HTMLSpanElement> & {
      variant?: string;
      size?: string;
      color?: string;
    }) => (
      <span
        role={role ?? 'button'}
        tabIndex={tabIndex ?? 0}
        aria-pressed={ariaPressedRaw}
        data-variant={variant}
        onClick={onClick}
        onKeyDown={onKeyDown}
        {...rest}
      >
        {children}
      </span>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('SdgGoalsPicker', () => {
  const mockOnChange = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders all 17 SDG goal chips', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    const chips = screen.getAllByRole('button');
    expect(chips.length).toBe(17);
  });

  it('renders the SDG label heading', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    // Real i18n resolves compose.sdg_label → "UN Sustainable Development Goals (optional)"
    expect(screen.getByText(/sustainable development goals/i)).toBeInTheDocument();
  });

  it('shows goal labels like "No Poverty" and "Zero Hunger"', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    expect(screen.getByText(/No Poverty/)).toBeInTheDocument();
    expect(screen.getByText(/Zero Hunger/)).toBeInTheDocument();
  });

  it('marks no chips as pressed when selected is empty', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    const pressedChips = screen
      .getAllByRole('button')
      .filter((el) => el.getAttribute('aria-pressed') === 'true');
    expect(pressedChips).toHaveLength(0);
  });

  it('marks pre-selected goals as aria-pressed=true', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[1, 3]} onChange={mockOnChange} />);

    const pressedChips = screen
      .getAllByRole('button')
      .filter((el) => el.getAttribute('aria-pressed') === 'true');
    expect(pressedChips).toHaveLength(2);
  });

  it('calls onChange with added id when an unselected goal is clicked', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    // Goal id=1 is "No Poverty"
    const chip = screen
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('No Poverty'));
    expect(chip).toBeDefined();
    fireEvent.click(chip!);

    expect(mockOnChange).toHaveBeenCalledOnce();
    expect(mockOnChange).toHaveBeenCalledWith([1]);
  });

  it('calls onChange removing id when an already-selected goal is clicked', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[1, 3]} onChange={mockOnChange} />);

    const chip = screen
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('No Poverty'));
    expect(chip).toBeDefined();
    fireEvent.click(chip!);

    expect(mockOnChange).toHaveBeenCalledWith([3]);
  });

  it('calls onChange adding a new id to existing selection', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[1]} onChange={mockOnChange} />);

    const chip = screen
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('Zero Hunger'));
    expect(chip).toBeDefined();
    fireEvent.click(chip!);

    expect(mockOnChange).toHaveBeenCalledWith([1, 2]);
  });

  it('triggers toggle on Enter key', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    const chip = screen
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('Good Health'));
    expect(chip).toBeDefined();
    fireEvent.keyDown(chip!, { key: 'Enter' });

    expect(mockOnChange).toHaveBeenCalledWith([3]);
  });

  it('triggers toggle on Space key', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    const chip = screen
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('Climate Action'));
    expect(chip).toBeDefined();
    fireEvent.keyDown(chip!, { key: ' ' });

    expect(mockOnChange).toHaveBeenCalledWith([13]);
  });

  it('does NOT trigger toggle on Tab key', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    const chip = screen.getAllByRole('button')[0];
    fireEvent.keyDown(chip, { key: 'Tab' });

    expect(mockOnChange).not.toHaveBeenCalled();
  });

  it('shows selected count text when at least one goal is selected', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[1, 5, 17]} onChange={mockOnChange} />);

    // i18n key compose.sdg_selected — falls back to key or has count in it
    // Just verify a <p> exists (the count paragraph)
    const paras = document.querySelectorAll('p');
    expect(paras.length).toBeGreaterThan(0);
  });

  it('does NOT show count paragraph when nothing is selected', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    render(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);

    const paras = document.querySelectorAll('p');
    expect(paras.length).toBe(0);
  });

  it('correctly handles deselecting all goals one by one', async () => {
    const { SdgGoalsPicker } = await import('./SdgGoalsPicker');
    const { rerender } = render(
      <SdgGoalsPicker selected={[1]} onChange={mockOnChange} />,
    );

    const chip = screen
      .getAllByRole('button')
      .find((el) => el.textContent?.includes('No Poverty'));
    fireEvent.click(chip!);

    expect(mockOnChange).toHaveBeenCalledWith([]);

    rerender(<SdgGoalsPicker selected={[]} onChange={mockOnChange} />);
    const pressedAfter = screen
      .getAllByRole('button')
      .filter((el) => el.getAttribute('aria-pressed') === 'true');
    expect(pressedAfter).toHaveLength(0);
  });
});
