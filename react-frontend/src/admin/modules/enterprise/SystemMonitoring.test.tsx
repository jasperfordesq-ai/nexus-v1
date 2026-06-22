// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock refs ──────────────────────────────────────────────────────────
const { mockGetMonitoring } = vi.hoisted(() => ({
  mockGetMonitoring: vi.fn(),
}));

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/api', () => {
  const m = { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() };
  return { default: m, api: m };
});

vi.mock('../../api/adminApi', () => ({
  adminEnterprise: {
    getMonitoring: mockGetMonitoring,
  },
}));

import { SystemMonitoring } from './SystemMonitoring';

// ── Test data ─────────────────────────────────────────────────────────────────
const HEALTH_DATA = {
  php_version: '8.2.10',
  db_size: '128 MB',
  redis_memory: '16 MB',
  uptime: '15 days',
  server_time: '2026-06-22 10:00:00',
  os: 'Linux Ubuntu 22.04',
  db_connected: true,
  redis_connected: true,
  memory_usage: '24 MB',
  memory_limit: '256 MB',
  sys_memory: {
    total: '8 GB',
    used: '4 GB',
    available: '4 GB',
    used_pct: 50,
  },
};

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('SystemMonitoring', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    mockGetMonitoring.mockReturnValue(new Promise(() => {}));
    render(<SystemMonitoring />);
    const spinner = Array.from(document.body.querySelectorAll('[role="status"]')).find(
      (el) => el.getAttribute('aria-busy') === 'true'
    );
    expect(spinner).toBeTruthy();
  });

  it('renders metric cards after successful load', async () => {
    mockGetMonitoring.mockResolvedValue({ success: true, data: HEALTH_DATA });
    render(<SystemMonitoring />);
    await waitFor(() => {
      expect(screen.getByText('8.2.10')).toBeInTheDocument();
      expect(screen.getByText('128 MB')).toBeInTheDocument();
    });
  });

  it('shows database connected chip', async () => {
    mockGetMonitoring.mockResolvedValue({ success: true, data: HEALTH_DATA });
    render(<SystemMonitoring />);
    await waitFor(() => {
      expect(screen.getByText(/database.*connected/i)).toBeInTheDocument();
    });
  });

  it('shows redis connected chip', async () => {
    mockGetMonitoring.mockResolvedValue({ success: true, data: HEALTH_DATA });
    render(<SystemMonitoring />);
    await waitFor(() => {
      expect(screen.getByText(/redis.*connected/i)).toBeInTheDocument();
    });
  });

  it('shows disconnected status when db is down', async () => {
    mockGetMonitoring.mockResolvedValue({
      success: true,
      data: { ...HEALTH_DATA, db_connected: false },
    });
    render(<SystemMonitoring />);
    await waitFor(() => {
      expect(screen.getByText(/database.*disconnected/i)).toBeInTheDocument();
    });
  });

  it('renders PHP process memory progress bar', async () => {
    mockGetMonitoring.mockResolvedValue({ success: true, data: HEALTH_DATA });
    render(<SystemMonitoring />);
    await waitFor(() => {
      // Progress bar has aria-label for PHP memory
      expect(
        document.body.querySelector('[aria-label*="memory" i]') ||
        document.body.querySelector('[aria-label*="Memory" i]')
      ).toBeTruthy();
    });
  });

  it('renders VM memory card when sys_memory is present', async () => {
    mockGetMonitoring.mockResolvedValue({ success: true, data: HEALTH_DATA });
    render(<SystemMonitoring />);
    await waitFor(() => {
      // Shows used/total memory text
      expect(screen.getByText(/4 GB.*\/.*8 GB/i) ||
             screen.getByText('4 GB')).toBeInTheDocument();
    });
  });

  it('hides PHP memory card when memory data is absent', async () => {
    mockGetMonitoring.mockResolvedValue({
      success: true,
      data: { ...HEALTH_DATA, memory_usage: null, memory_limit: null },
    });
    render(<SystemMonitoring />);
    await waitFor(() => {
      // The "24 MB / 256 MB" text should not appear
      expect(screen.queryByText(/24 MB/)).not.toBeInTheDocument();
    });
  });

  it('calls getMonitoring again when Refresh is clicked', async () => {
    mockGetMonitoring.mockResolvedValue({ success: true, data: HEALTH_DATA });
    render(<SystemMonitoring />);
    await waitFor(() => screen.getByText('8.2.10'));

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await userEvent.click(refreshBtn);

    await waitFor(() => {
      expect(mockGetMonitoring).toHaveBeenCalledTimes(2);
    });
  });

  it('does not crash when API returns empty data', async () => {
    mockGetMonitoring.mockResolvedValue({ success: false, data: null });
    render(<SystemMonitoring />);
    // Should render without crashing; no metrics cards
    await waitFor(() => {
      expect(screen.queryByText('8.2.10')).not.toBeInTheDocument();
    });
  });

  it('renders quick link cards for log files, requirements, module configuration', async () => {
    mockGetMonitoring.mockResolvedValue({ success: true, data: HEALTH_DATA });
    render(<SystemMonitoring />);
    await waitFor(() => {
      // Multiple elements with these labels exist (header buttons + quick link cards)
      expect(screen.getAllByText(/log files/i).length).toBeGreaterThan(0);
      expect(screen.getAllByText(/system requirements/i).length).toBeGreaterThan(0);
      expect(screen.getAllByText(/module configuration/i).length).toBeGreaterThan(0);
    });
  });
});
