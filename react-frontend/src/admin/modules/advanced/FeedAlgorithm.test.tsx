// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── vi.hoisted: stable mock references ─────────────────────────────────────
const { mockGetFeedAlgorithm, mockUpdateFeedAlgorithm } = vi.hoisted(() => ({
  mockGetFeedAlgorithm: vi.fn(),
  mockUpdateFeedAlgorithm: vi.fn(),
}));

// ─── Mock adminSettings from adminApi ────────────────────────────────────────
vi.mock('../../api/adminApi', () => ({
  adminSettings: {
    getFeedAlgorithm: mockGetFeedAlgorithm,
    updateFeedAlgorithm: mockUpdateFeedAlgorithm,
  },
  adminMatching: { getApprovals: vi.fn() },
  adminTimebanking: { getOrgWallets: vi.fn() },
}));

// ─── Mock @/contexts ─────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast })
);

// ─── Mock @/hooks ────────────────────────────────────────────────────────────
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Mock PageHeader ─────────────────────────────────────────────────────────
vi.mock('../../components', async (importOriginal) => {
  const { PageHeader: RealPageHeader } = await importOriginal<typeof import('../../components')>();
  return {
    PageHeader: RealPageHeader,
    DataTable: vi.fn(),
    StatusBadge: vi.fn(),
    EmptyState: vi.fn(),
  };
});

import { FeedAlgorithm } from './FeedAlgorithm';

const defaultAlgorithmData = {
  recency_weight: 70,
  engagement_weight: 50,
  connection_weight: 40,
  diversity_factor: 30,
  chronological_mode: false,
  include_polls: true,
  include_events: true,
};

describe('FeedAlgorithm — loading state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching algorithm settings', () => {
    mockGetFeedAlgorithm.mockReturnValue(new Promise(() => {}));

    render(<FeedAlgorithm />);

    const spinner = screen
      .getAllByRole('status')
      .find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });
});

describe('FeedAlgorithm — loaded state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('removes spinner and renders controls after settings load', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({ data: defaultAlgorithmData });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });

    // Save button should now be visible
    expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
  });

  it('renders the three toggle switches for feed options', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({ data: defaultAlgorithmData });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    // Three Switch components render as role=switch in React Aria
    const switches = screen.getAllByRole('switch');
    expect(switches.length).toBeGreaterThanOrEqual(3);
  });

  it('merges API data into default form values', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({
      data: {
        recency_weight: 90,
        chronological_mode: true,
      },
    });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    // chronological_mode=true → its switch should be checked
    const switches = screen.getAllByRole('switch');
    // The chronological_mode switch is the first one rendered
    const chronoSwitch = switches.find((sw) =>
      sw.getAttribute('aria-label')?.toLowerCase().includes('chronological')
    );
    expect(chronoSwitch).toBeDefined();
    // HeroUI Switch renders as type=checkbox; checked property reflects isSelected
    expect((chronoSwitch as HTMLInputElement).checked).toBe(true);
  });

  it('handles null data from API gracefully (uses defaults)', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({ data: null });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });
  });
});

describe('FeedAlgorithm — error loading', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls toast.error when getFeedAlgorithm rejects', async () => {
    mockGetFeedAlgorithm.mockRejectedValue(new Error('network'));

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('still renders the form (using defaults) after a load error', async () => {
    mockGetFeedAlgorithm.mockRejectedValue(new Error('500'));

    render(<FeedAlgorithm />);

    await waitFor(() => {
      // spinner gone
      const spinner = screen
        .queryAllByRole('status')
        .find((el) => el.getAttribute('aria-busy') === 'true');
      expect(spinner).toBeUndefined();
    });

    expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
  });
});

describe('FeedAlgorithm — save action', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('calls updateFeedAlgorithm when save button is pressed', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({ data: defaultAlgorithmData });
    mockUpdateFeedAlgorithm.mockResolvedValue({ success: true });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    const saveBtn = screen.getByRole('button', { name: /save/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      expect(mockUpdateFeedAlgorithm).toHaveBeenCalledWith(
        expect.objectContaining({ recency_weight: 70 })
      );
    });
  });

  it('shows success toast after successful save', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({ data: defaultAlgorithmData });
    mockUpdateFeedAlgorithm.mockResolvedValue({ success: true });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when updateFeedAlgorithm returns success=false', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({ data: defaultAlgorithmData });
    mockUpdateFeedAlgorithm.mockResolvedValue({ success: false, error: 'Bad request' });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when updateFeedAlgorithm throws', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({ data: defaultAlgorithmData });
    mockUpdateFeedAlgorithm.mockRejectedValue(new Error('server error'));

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});

describe('FeedAlgorithm — switch toggle', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('toggling a switch updates form state (include_polls can be toggled off)', async () => {
    mockGetFeedAlgorithm.mockResolvedValue({
      data: { ...defaultAlgorithmData, include_polls: true },
    });
    mockUpdateFeedAlgorithm.mockResolvedValue({ success: true });

    render(<FeedAlgorithm />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /save/i })).toBeInTheDocument();
    });

    // Find the include_polls switch by aria-label
    const pollsSwitch = screen
      .getAllByRole('switch')
      .find((sw) => sw.getAttribute('aria-label')?.toLowerCase().includes('polls'));

    expect(pollsSwitch).toBeDefined();
    // HeroUI Switch renders as type=checkbox; .checked reflects isSelected
    expect((pollsSwitch as HTMLInputElement).checked).toBe(true);

    // Toggle it off
    await userEvent.click(pollsSwitch!);

    // After toggle it should be unchecked
    await waitFor(() => {
      expect((pollsSwitch as HTMLInputElement).checked).toBe(false);
    });
  });
});
