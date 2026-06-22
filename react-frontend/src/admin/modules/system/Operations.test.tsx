// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── mock contexts ────────────────────────────────────────────────────────────

const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

// ── mock adminApi ────────────────────────────────────────────────────────────

const mockGetCacheStats = vi.hoisted(() => vi.fn());
const mockGetJobs = vi.hoisted(() => vi.fn());
const mockClearCache = vi.hoisted(() => vi.fn());
const mockRunJob = vi.hoisted(() => vi.fn());

vi.mock('@/admin/api/adminApi', () => ({
  adminConfig: {
    getCacheStats: mockGetCacheStats,
    getJobs: mockGetJobs,
    clearCache: mockClearCache,
    runJob: mockRunJob,
  },
}));

// ── mock hooks ───────────────────────────────────────────────────────────────

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ── component ────────────────────────────────────────────────────────────────

import Operations from './Operations';

const CACHE_DATA = {
  redis_connected: true,
  redis_memory_used: '4.2 MB',
  redis_keys_count: 312,
};

const JOBS_DATA = [
  { id: 'send-digest', name: 'Send Digest', last_run_at: '2026-06-21T08:00:00Z' },
  { id: 'clean-logs', name: 'Clean Logs', last_run_at: null },
];

function setupHappyPath() {
  mockGetCacheStats.mockResolvedValue({ success: true, data: CACHE_DATA });
  mockGetJobs.mockResolvedValue({ success: true, data: JOBS_DATA });
}

describe('Operations', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching', () => {
    mockGetCacheStats.mockReturnValue(new Promise(() => {}));
    mockGetJobs.mockReturnValue(new Promise(() => {}));
    render(<Operations />);

    const spinner = screen.getAllByRole('status').find(
      (el) => el.getAttribute('aria-busy') === 'true',
    );
    expect(spinner).toBeDefined();
  });

  it('renders cache stats after load', async () => {
    setupHappyPath();
    render(<Operations />);

    await waitFor(() => {
      expect(screen.getByText('4.2 MB')).toBeInTheDocument();
    });
    expect(screen.getByText('312')).toBeInTheDocument();
  });

  it('shows redis connected status', async () => {
    setupHappyPath();
    render(<Operations />);

    await waitFor(() => {
      expect(screen.getByText(/connected/i)).toBeInTheDocument();
    });
  });

  it('renders background jobs', async () => {
    setupHappyPath();
    render(<Operations />);

    await waitFor(() => {
      expect(screen.getByText('Send Digest')).toBeInTheDocument();
    });
    expect(screen.getByText('Clean Logs')).toBeInTheDocument();
  });

  it('shows "never run" for a job with no last_run_at', async () => {
    setupHappyPath();
    render(<Operations />);

    await waitFor(() => {
      expect(screen.getByText(/never/i)).toBeInTheDocument();
    });
  });

  it('shows "no jobs" text when jobs list is empty', async () => {
    mockGetCacheStats.mockResolvedValue({ success: true, data: CACHE_DATA });
    mockGetJobs.mockResolvedValue({ success: true, data: [] });
    render(<Operations />);

    // i18n: operations.no_jobs = "No background jobs configured"
    await waitFor(() => {
      expect(screen.getByText(/no background jobs configured/i)).toBeInTheDocument();
    });
  });

  it('calls clearCache and refreshes stats on "Clear cache" press', async () => {
    setupHappyPath();
    mockClearCache.mockResolvedValue({ success: true });
    // Stats refresh after clear
    mockGetCacheStats.mockResolvedValue({ success: true, data: { ...CACHE_DATA, redis_keys_count: 0 } });

    render(<Operations />);

    // i18n: operations.clear_cache = "Clear tenant cache"
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /clear tenant cache/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /clear tenant cache/i }));

    await waitFor(() => {
      expect(mockClearCache).toHaveBeenCalledWith('tenant');
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when clearCache returns success=false', async () => {
    setupHappyPath();
    mockClearCache.mockResolvedValue({ success: false });

    render(<Operations />);

    // i18n: operations.clear_cache = "Clear tenant cache"
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /clear tenant cache/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /clear tenant cache/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls runJob with correct id when job run button pressed', async () => {
    setupHappyPath();
    mockRunJob.mockResolvedValue({ success: true });

    render(<Operations />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /run.*send digest/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /run.*send digest/i }));

    await waitFor(() => {
      expect(mockRunJob).toHaveBeenCalledWith('send-digest');
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when runJob fails', async () => {
    setupHappyPath();
    mockRunJob.mockResolvedValue({ success: false });

    render(<Operations />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /run.*send digest/i })).toBeInTheDocument();
    });

    await userEvent.click(screen.getByRole('button', { name: /run.*send digest/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
