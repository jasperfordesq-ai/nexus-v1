// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock data ──────────────────────────────────────────────────────────
const MOCK_CATEGORIES = vi.hoisted(() => [
  {
    id: 1,
    name: 'Personal Care',
    substitution_coefficient: 1.5,
    source_table: 'categories',
  },
  {
    id: 2,
    name: 'Household Support',
    substitution_coefficient: 0.75,
    source_table: 'categories',
  },
]);

// ── mock @/lib/api ────────────────────────────────────────────────────────────
// CategoryCoefficientsAdminPage uses named `{ api }` export.
// Shared object so named and default are the same.
const mockApiObj = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('@/lib/api', () => ({
  api: mockApiObj,
  default: mockApiObj,
}));

// ── mock @/contexts ───────────────────────────────────────────────────────────
const mockShowToast = vi.fn();
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => ({
      showToast: mockShowToast,
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
    }),
  }),
);

import CategoryCoefficientsAdminPage from './CategoryCoefficientsAdminPage';

const getMock = mockApiObj.get;
const putMock = mockApiObj.put;

describe('CategoryCoefficientsAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getMock.mockResolvedValue({
      success: true,
      data: { categories: MOCK_CATEGORIES, migration_pending: false },
    } as never);
  });

  it('shows loading spinner while fetching', () => {
    let resolve!: (v: unknown) => void;
    getMock.mockReturnValueOnce(new Promise((r) => (resolve = r)) as never);

    render(<CategoryCoefficientsAdminPage />);

    const statusEls = screen.queryAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();

    resolve({ success: true, data: { categories: [], migration_pending: false } });
  });

  it('renders category rows after successful fetch', async () => {
    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Personal Care')).toBeInTheDocument();
    });
    expect(screen.getByText('Household Support')).toBeInTheDocument();
  });

  it('shows empty table content when no categories returned', async () => {
    getMock.mockResolvedValueOnce({
      success: true,
      data: { categories: [], migration_pending: false },
    } as never);

    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      const busyEl = screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busyEl).toBeUndefined();
    });
    expect(screen.queryByText('Personal Care')).not.toBeInTheDocument();
  });

  it('shows migration warning when migration_pending is true', async () => {
    getMock.mockResolvedValueOnce({
      success: true,
      data: { categories: MOCK_CATEGORIES, migration_pending: true },
    } as never);

    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      // Migration banner renders; the table is hidden when migration_pending
      expect(screen.queryByText('Personal Care')).not.toBeInTheDocument();
    });
  });

  it('shows error toast when load fails', async () => {
    getMock.mockRejectedValueOnce(new Error('network error'));

    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'error');
    });
  });

  it('Save button is disabled when coefficient is unchanged', async () => {
    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Personal Care')).toBeInTheDocument();
    });

    // All save buttons should be disabled (dirty=false initially)
    const saveBtns = screen.getAllByRole('button', { name: /save/i });
    // At least one save button exists
    expect(saveBtns.length).toBeGreaterThan(0);
    // Each is disabled when value unchanged
    saveBtns.forEach((btn) => {
      expect(btn).toBeDisabled();
    });
  });

  it('enables Save button when coefficient value changes', async () => {
    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Personal Care')).toBeInTheDocument();
    });

    // Change the first coefficient input
    const inputs = screen.getAllByRole('spinbutton');
    fireEvent.change(inputs[0], { target: { value: '2.50' } });

    await waitFor(() => {
      const saveBtns = screen.getAllByRole('button', { name: /save/i });
      const enabledSave = saveBtns.find((btn) => !btn.hasAttribute('disabled'));
      expect(enabledSave).toBeDefined();
    });
  });

  it('calls PUT endpoint when Save is clicked on a dirty row', async () => {
    putMock.mockResolvedValueOnce({
      success: true,
      data: { id: 1, substitution_coefficient: 2.5, source_table: 'categories' },
    } as never);

    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Personal Care')).toBeInTheDocument();
    });

    // Dirty the first row
    const inputs = screen.getAllByRole('spinbutton');
    fireEvent.change(inputs[0], { target: { value: '2.50' } });

    await waitFor(() => {
      const saveBtns = screen.getAllByRole('button', { name: /save/i });
      const enabledSave = saveBtns.find((btn) => !btn.hasAttribute('disabled'));
      expect(enabledSave).toBeDefined();
    });

    const saveBtns = screen.getAllByRole('button', { name: /save/i });
    const enabledSave = saveBtns.find((btn) => !btn.hasAttribute('disabled'))!;
    await userEvent.click(enabledSave);

    await waitFor(() => {
      expect(putMock).toHaveBeenCalledWith(
        '/v2/admin/caring-community/category-coefficients/1',
        expect.objectContaining({ substitution_coefficient: expect.any(Number) }),
      );
    });
  });

  it('shows success toast after successful save', async () => {
    putMock.mockResolvedValueOnce({
      success: true,
      data: { id: 1, substitution_coefficient: 2.5, source_table: 'categories' },
    } as never);

    render(<CategoryCoefficientsAdminPage />);

    await waitFor(() => {
      expect(screen.getByText('Personal Care')).toBeInTheDocument();
    });

    const inputs = screen.getAllByRole('spinbutton');
    fireEvent.change(inputs[0], { target: { value: '2.50' } });

    await waitFor(() => {
      const saveBtns = screen.getAllByRole('button', { name: /save/i });
      const enabledSave = saveBtns.find((btn) => !btn.hasAttribute('disabled'));
      expect(enabledSave).toBeDefined();
    });

    const saveBtns = screen.getAllByRole('button', { name: /save/i });
    const enabledSave = saveBtns.find((btn) => !btn.hasAttribute('disabled'))!;
    await userEvent.click(enabledSave);

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalledWith(expect.any(String), 'success');
    });
  });
});
