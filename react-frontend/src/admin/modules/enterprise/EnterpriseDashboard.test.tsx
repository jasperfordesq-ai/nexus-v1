// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ---------------------------------------------------------------------------
// Hoist shared mock data so the factory closure can reference it
// ---------------------------------------------------------------------------
const mockGetDashboard = vi.hoisted(() => vi.fn());

vi.mock('../../api/adminApi', () => ({
  adminEnterprise: {
    getDashboard: mockGetDashboard,
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// Import AFTER mocks
import { EnterpriseDashboard } from './EnterpriseDashboard';

const STATS_PAYLOAD = {
  user_count: 42,
  role_count: 5,
  pending_gdpr_requests: 3,
  health_status: 'healthy',
  db_connected: true,
  redis_connected: true,
  memory_percent: 55,
  disk_percent: 40,
  recent_gdpr_activity: [
    {
      id: 1,
      action: 'export',
      entity_type: 'user',
      user_name: 'Alice',
      created_at: '2026-06-21T10:00:00Z',
    },
  ],
};

describe('EnterpriseDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    // Never resolves during this test
    mockGetDashboard.mockReturnValue(new Promise(() => {}));
    render(<EnterpriseDashboard />);

    // The quick-links section renders a role=status aria-busy=true div while loading
    const spinners = screen.getAllByRole('status');
    const busySpinner = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busySpinner).toBeInTheDocument();
  });

  it('renders stat values after successful load', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: STATS_PAYLOAD });
    render(<EnterpriseDashboard />);

    await waitFor(() => {
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    // User count and role count show up in the StatCards
    expect(screen.getByText('42')).toBeInTheDocument();
    expect(screen.getByText('5')).toBeInTheDocument();
    expect(screen.getByText('Healthy')).toBeInTheDocument();
  });

  it('renders system health chips when stats are loaded', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: STATS_PAYLOAD });
    render(<EnterpriseDashboard />);

    await waitFor(() => {
      expect(screen.getByText(/Database/i)).toBeInTheDocument();
    });
    expect(screen.getByText(/Redis/i)).toBeInTheDocument();
    expect(screen.getByText(/Memory/i)).toBeInTheDocument();
    expect(screen.getByText(/Disk/i)).toBeInTheDocument();
  });

  it('renders recent GDPR activity row when provided', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: STATS_PAYLOAD });
    render(<EnterpriseDashboard />);

    await waitFor(() => {
      expect(screen.getByText('Exported')).toBeInTheDocument();
    });
    expect(screen.getByText(/Alice/)).toBeInTheDocument();
  });

  it('renders quick-link buttons after loading completes', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: STATS_PAYLOAD });
    render(<EnterpriseDashboard />);

    await waitFor(() => {
      // At least one navigation link button should appear
      const links = screen.getAllByRole('link');
      expect(links.length).toBeGreaterThan(0);
    });
  });

  it('re-fetches data when Refresh button is pressed', async () => {
    const user = userEvent.setup();
    mockGetDashboard.mockResolvedValue({ success: true, data: STATS_PAYLOAD });
    render(<EnterpriseDashboard />);

    // Wait for initial load
    await waitFor(() => expect(mockGetDashboard).toHaveBeenCalledTimes(1));

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await user.click(refreshBtn);

    await waitFor(() => expect(mockGetDashboard).toHaveBeenCalledTimes(2));
  });

  it('handles API failure gracefully without crashing', async () => {
    mockGetDashboard.mockRejectedValue(new Error('network error'));
    render(<EnterpriseDashboard />);

    // Should finish loading without throwing
    await waitFor(() => {
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // Page header should still be visible
    expect(screen.getByRole('button', { name: /refresh/i })).toBeInTheDocument();
  });

  it('does not render GDPR activity section when list is empty', async () => {
    mockGetDashboard.mockResolvedValue({
      success: true,
      data: { ...STATS_PAYLOAD, recent_gdpr_activity: [] },
    });
    render(<EnterpriseDashboard />);

    await waitFor(() => {
      expect(screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });

    expect(screen.queryByText('Exported')).not.toBeInTheDocument();
  });
});
