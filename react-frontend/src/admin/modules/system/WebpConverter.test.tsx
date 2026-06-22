// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable hoisted mock data ──────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));
const mockGetWebpStats = vi.hoisted(() => vi.fn());
const mockRunWebpConversion = vi.hoisted(() => vi.fn());

// ── Module mocks ──────────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
}));

vi.mock('../../api/adminApi', () => ({
  adminTools: {
    getWebpStats: mockGetWebpStats,
    runWebpConversion: mockRunWebpConversion,
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

import { WebpConverter } from './WebpConverter';

// ── Test helpers ──────────────────────────────────────────────────────────────
const SAMPLE_STATS = {
  total_images: 1200,
  webp_images: 800,
  pending_conversion: 400,
};

const DONE_STATS = {
  total_images: 1200,
  webp_images: 1200,
  pending_conversion: 0,
};

describe('WebpConverter', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetWebpStats.mockResolvedValue({ data: SAMPLE_STATS });
    mockRunWebpConversion.mockResolvedValue({ success: true, data: { converted: 400 } });
  });

  // ── Loading state ─────────────────────────────────────────────────────────
  it('shows a loading spinner while fetching stats', () => {
    mockGetWebpStats.mockReturnValue(new Promise(() => {}));
    render(<WebpConverter />);

    const spinners = screen.getAllByRole('status');
    const busyEl = spinners.find(el => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();
  });

  // ── Populated state ───────────────────────────────────────────────────────
  it('renders total_images stat after load', async () => {
    render(<WebpConverter />);

    await waitFor(() => {
      expect(screen.getByText('1,200')).toBeInTheDocument();
    });
  });

  it('renders webp_images (already converted) stat', async () => {
    render(<WebpConverter />);

    await waitFor(() => {
      // 800 formatted
      expect(screen.getByText('800')).toBeInTheDocument();
    });
  });

  it('renders pending_conversion stat', async () => {
    render(<WebpConverter />);

    await waitFor(() => {
      expect(screen.getByText('400')).toBeInTheDocument();
    });
  });

  // ── Loading clears ────────────────────────────────────────────────────────
  it('removes loading indicator after stats are loaded', async () => {
    render(<WebpConverter />);

    await waitFor(() => {
      const busyEl = screen.queryAllByRole('status').find(
        el => el.getAttribute('aria-busy') === 'true',
      );
      expect(busyEl).toBeUndefined();
    });
  });

  // ── Error state (stats) ───────────────────────────────────────────────────
  it('calls toast.error when getWebpStats throws', async () => {
    mockGetWebpStats.mockRejectedValue(new Error('Network Error'));
    render(<WebpConverter />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Start conversion button disabled when pending = 0 ───────────────────
  it('disables Start Conversion button when pending_conversion is 0', async () => {
    mockGetWebpStats.mockResolvedValue({ data: DONE_STATS });
    render(<WebpConverter />);

    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /start conversion/i });
      // HeroUI disabled button has aria-disabled or disabled attribute
      expect(btn).toBeDisabled();
    });
  });

  // ── Start conversion button enabled when pending > 0 ────────────────────
  it('enables Start Conversion button when pending_conversion > 0', async () => {
    render(<WebpConverter />);

    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /start conversion/i });
      expect(btn).not.toBeDisabled();
    });
  });

  // ── Successful conversion ─────────────────────────────────────────────────
  it('calls runWebpConversion and shows success toast on convert', async () => {
    const user = userEvent.setup();
    // After conversion, refetch returns updated stats
    mockGetWebpStats
      .mockResolvedValueOnce({ data: SAMPLE_STATS })
      .mockResolvedValueOnce({ data: DONE_STATS });

    render(<WebpConverter />);

    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /start conversion/i });
      expect(btn).not.toBeDisabled();
    });

    const btn = screen.getByRole('button', { name: /start conversion/i });
    await user.click(btn);

    await waitFor(() => {
      expect(mockRunWebpConversion).toHaveBeenCalledTimes(1);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  // ── Conversion failure (success=false) ────────────────────────────────────
  it('shows error toast when conversion returns success=false', async () => {
    const user = userEvent.setup();
    mockRunWebpConversion.mockResolvedValue({ success: false, error: 'Disk full' });
    render(<WebpConverter />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /start conversion/i })).not.toBeDisabled();
    });

    await user.click(screen.getByRole('button', { name: /start conversion/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Conversion throws ────────────────────────────────────────────────────
  it('shows error toast when runWebpConversion throws', async () => {
    const user = userEvent.setup();
    mockRunWebpConversion.mockRejectedValue(new Error('Unexpected'));
    render(<WebpConverter />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /start conversion/i })).not.toBeDisabled();
    });

    await user.click(screen.getByRole('button', { name: /start conversion/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  // ── Null data fallback ───────────────────────────────────────────────────
  it('renders -- placeholders when getWebpStats returns no data', async () => {
    mockGetWebpStats.mockResolvedValue({ data: null });
    render(<WebpConverter />);

    await waitFor(() => {
      // All three stat tiles should show '--'
      const dashes = screen.getAllByText('--');
      expect(dashes.length).toBeGreaterThanOrEqual(3);
    });
  });
});
