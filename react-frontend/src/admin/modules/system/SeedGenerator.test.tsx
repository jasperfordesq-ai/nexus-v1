// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mock adminApi ────────────────────────────────────────────────────────────
vi.mock('@/admin/api/adminApi', () => {
  const runSeedGenerator = vi.fn();
  return {
    adminTools: { runSeedGenerator },
  };
});

// ── Mock contexts ────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

import { adminTools } from '@/admin/api/adminApi';
import { SeedGenerator } from './SeedGenerator';

describe('SeedGenerator', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── Initial render ────────────────────────────────────────────────────────
  it('renders the generate button', () => {
    render(<SeedGenerator />);
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('renders 8 checkboxes for the seed options', () => {
    render(<SeedGenerator />);
    const checkboxes = screen.getAllByRole('checkbox');
    expect(checkboxes).toHaveLength(8);
  });

  it('all checkboxes start unchecked', () => {
    render(<SeedGenerator />);
    const checkboxes = screen.getAllByRole('checkbox');
    checkboxes.forEach((cb) => {
      expect(cb).not.toBeChecked();
    });
  });

  it('generate button is disabled when nothing is selected', () => {
    render(<SeedGenerator />);
    const btn = screen.getByRole('button');
    // HeroUI v3 Button with isDisabled uses data-disabled (React Aria pattern)
    expect(btn).toHaveAttribute('data-disabled');
  });

  // ── Checkbox interaction ──────────────────────────────────────────────────
  it('enables generate button after selecting at least one option', async () => {
    const user = userEvent.setup();
    render(<SeedGenerator />);

    const [firstCheckbox] = screen.getAllByRole('checkbox');
    await user.click(firstCheckbox);

    const btn = screen.getByRole('button');
    expect(btn).not.toHaveAttribute('data-disabled');
  });

  it('can deselect a previously selected option', async () => {
    const user = userEvent.setup();
    render(<SeedGenerator />);

    const [firstCheckbox] = screen.getAllByRole('checkbox');
    await user.click(firstCheckbox); // select
    await user.click(firstCheckbox); // deselect

    const btn = screen.getByRole('button');
    expect(btn).toHaveAttribute('data-disabled');
  });

  // ── Successful seed run ───────────────────────────────────────────────────
  it('calls adminTools.runSeedGenerator with selected types', async () => {
    const user = userEvent.setup();
    vi.mocked(adminTools.runSeedGenerator).mockResolvedValueOnce({
      success: true,
      data: { success: true, created: { users: 50 } },
    } as never);

    render(<SeedGenerator />);

    const [firstCheckbox] = screen.getAllByRole('checkbox');
    await user.click(firstCheckbox);

    const btn = screen.getByRole('button');
    await user.click(btn);

    await waitFor(() => {
      expect(adminTools.runSeedGenerator).toHaveBeenCalledWith(
        expect.objectContaining({
          types: expect.arrayContaining([expect.any(String)]),
          counts: expect.any(Object),
        })
      );
    });
  });

  it('shows toast.success after a successful seed run', async () => {
    const user = userEvent.setup();
    vi.mocked(adminTools.runSeedGenerator).mockResolvedValueOnce({
      success: true,
      data: { success: true, created: {} },
    } as never);

    render(<SeedGenerator />);

    const [firstCheckbox] = screen.getAllByRole('checkbox');
    await user.click(firstCheckbox);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  // ── Failed seed run (API returns success:false) ───────────────────────────
  it('shows toast.error when API returns success:false', async () => {
    const user = userEvent.setup();
    vi.mocked(adminTools.runSeedGenerator).mockResolvedValueOnce({
      success: false,
      error: 'Cannot seed in production',
    } as never);

    render(<SeedGenerator />);

    const [firstCheckbox] = screen.getAllByRole('checkbox');
    await user.click(firstCheckbox);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Error: API throws ─────────────────────────────────────────────────────
  it('shows toast.error when runSeedGenerator throws', async () => {
    const user = userEvent.setup();
    vi.mocked(adminTools.runSeedGenerator).mockRejectedValueOnce(new Error('Network'));

    render(<SeedGenerator />);

    const [firstCheckbox] = screen.getAllByRole('checkbox');
    await user.click(firstCheckbox);
    await user.click(screen.getByRole('button'));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Guard: no API call when nothing selected ──────────────────────────────
  it('does not call runSeedGenerator when no types are selected', async () => {
    // The button is disabled; userEvent.click on aria-disabled returns without firing
    // We also verify at the mock level.
    render(<SeedGenerator />);
    // Attempt click via fireEvent (bypasses HeroUI disabled guard)
    const btn = screen.getByRole('button');
    fireEvent.click(btn);

    await waitFor(() => {
      expect(adminTools.runSeedGenerator).not.toHaveBeenCalled();
    });
  });

  // ── Count input fields ────────────────────────────────────────────────────
  it('renders a numeric count input for each seed option', () => {
    render(<SeedGenerator />);
    // Each row has an <input type="number">
    const inputs = screen.getAllByRole('spinbutton');
    expect(inputs).toHaveLength(8);
  });
});
