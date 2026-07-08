// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock adminApi ────────────────────────────────────────────────────────────
const mockAdminEnterprise = vi.hoisted(() => ({
  getHealthCheck: vi.fn(),
  getHealthHistory: vi.fn(),
}));

vi.mock('../../api/adminApi', () => ({
  adminEnterprise: mockAdminEnterprise,
}));

// ─── Mock contexts ───────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Sample data ─────────────────────────────────────────────────────────────
// Real i18n values (en/admin_enterprise.json):
//   enterprise.status_healthy → "Healthy"
//   enterprise.status_degraded → "Degraded"
//   enterprise.status_unhealthy → "Unhealthy"
//   enterprise.operational → "Operational"
//   enterprise.failed → "Failed"
//   enterprise.free_value → "Free: {{value}}"  →  renders "Free: 10 GB"
//   enterprise.total_value → "Total: {{value}}" → renders "Total: 50 GB"
//   enterprise.no_history_available → "No history available"
//   enterprise.last_n_checks → "Last {{count}} checks"
//   enterprise.refresh → "Refresh"

const HEALTHY_RESULT = {
  status: 'healthy',
  checks: [
    { name: 'Database', status: 'ok' },
    { name: 'Redis', status: 'ok' },
    { name: 'Storage', status: 'ok', free: '10 GB', total: '50 GB' },
  ],
};

const DEGRADED_RESULT = {
  status: 'degraded',
  checks: [
    { name: 'Database', status: 'ok' },
    { name: 'Redis', status: 'fail' },
  ],
};

const HISTORY_ENTRIES = [
  { id: 1, status: 'healthy', latency_ms: 42, created_at: '2026-06-21T10:00:00Z' },
  { id: 2, status: 'degraded', latency_ms: 200, created_at: '2026-06-21T09:00:00Z' },
];

import { HealthCheck } from './HealthCheck';

describe('HealthCheck', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while health data loads', () => {
    mockAdminEnterprise.getHealthCheck.mockReturnValue(new Promise(() => {}));
    mockAdminEnterprise.getHealthHistory.mockReturnValue(new Promise(() => {}));

    render(<HealthCheck />);

    const spinners = screen.getAllByRole('status');
    const busySpinner = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busySpinner).toBeInTheDocument();
  });

  it('renders "Healthy" status chip after successful healthy load', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: HISTORY_ENTRIES });

    render(<HealthCheck />);

    // "Healthy" appears in the overall status chip AND in history row
    await waitFor(() => {
      expect(screen.getAllByText('Healthy').length).toBeGreaterThan(0);
    });

    // Loading spinner should be gone
    expect(
      screen.queryAllByRole('status').find((el) => el.getAttribute('aria-busy') === 'true')
    ).toBeUndefined();
  });

  it('renders "Degraded" status correctly', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: DEGRADED_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    render(<HealthCheck />);

    await waitFor(() => {
      expect(screen.getByText('Degraded')).toBeInTheDocument();
    });
  });

  it('renders individual check cards with check names', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    render(<HealthCheck />);

    await waitFor(() => {
      expect(screen.getByText('Database')).toBeInTheDocument();
      expect(screen.getByText('Redis')).toBeInTheDocument();
      expect(screen.getByText('Storage')).toBeInTheDocument();
    });
  });

  it('shows "Operational" for ok checks and "Failed" for fail checks', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: DEGRADED_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    render(<HealthCheck />);

    await waitFor(() => {
      // enterprise.operational → "Operational"
      expect(screen.getByText('Operational')).toBeInTheDocument();
      // enterprise.failed → "Failed"
      expect(screen.getByText('Failed')).toBeInTheDocument();
    });
  });

  it('shows free/total storage info when present on a check', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    render(<HealthCheck />);

    await waitFor(() => {
      // free_value → "Free: {{value}}" → "Free: 10 GB" — may be split across child elements
      expect(
        screen.getAllByText((content) => /Free.*10 GB/.test(content) || content === 'Free: 10 GB').length
      ).toBeGreaterThan(0);
      // total_value → "Total: {{value}}" → "Total: 50 GB"
      expect(
        screen.getAllByText((content) => /Total.*50 GB/.test(content) || content === 'Total: 50 GB').length
      ).toBeGreaterThan(0);
    });
  });

  it('renders "Unhealthy" fallback when getHealthCheck throws', async () => {
    mockAdminEnterprise.getHealthCheck.mockRejectedValue(new Error('Network error'));
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    render(<HealthCheck />);

    // On error, component sets { status:'unhealthy', checks:[{name:t('enterprise.api'),status:'fail'}] }
    // enterprise.status_unhealthy → "Unhealthy"
    await waitFor(() => {
      expect(screen.getByText('Unhealthy')).toBeInTheDocument();
    });
  });

  it('renders history table with Healthy and Degraded rows', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: HISTORY_ENTRIES });

    render(<HealthCheck />);

    await waitFor(() => {
      // HISTORY_ENTRIES has one 'healthy' + one 'degraded'
      // Overall chip says "Healthy" too, so getAllByText
      expect(screen.getAllByText('Healthy').length).toBeGreaterThan(0);
      expect(screen.getByText('Degraded')).toBeInTheDocument();
    });
  });

  it('shows "No history available" when history is empty', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    render(<HealthCheck />);

    // enterprise.no_history_available → "No history available"
    await waitFor(() => {
      expect(screen.getByText('No history available')).toBeInTheDocument();
    });
  });

  it('shows "System Status" section heading', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    render(<HealthCheck />);

    // enterprise.system_status → "System Status"
    await waitFor(() => {
      expect(screen.getByText('System Status')).toBeInTheDocument();
    });
  });

  it('calls loadData again when Refresh button is pressed', async () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockResolvedValue({ success: true, data: [] });

    const user = userEvent.setup();
    render(<HealthCheck />);

    await waitFor(() => expect(screen.getAllByText('Healthy').length).toBeGreaterThan(0));

    // enterprise.refresh → "Refresh"
    const refreshBtn = screen.getByRole('button', { name: /^Refresh$/i });
    await user.click(refreshBtn);

    await waitFor(() => {
      // Called twice: once on mount, once on refresh
      expect(mockAdminEnterprise.getHealthCheck).toHaveBeenCalledTimes(2);
    });
  });

  it('renders history loading spinner while history fetches', () => {
    mockAdminEnterprise.getHealthCheck.mockResolvedValue({ success: true, data: HEALTHY_RESULT });
    mockAdminEnterprise.getHealthHistory.mockReturnValue(new Promise(() => {}));

    render(<HealthCheck />);

    // At least one busy spinner present (history section has its own)
    const allStatuses = screen.queryAllByRole('status');
    const anyBusy = allStatuses.some((el) => el.getAttribute('aria-busy') === 'true');
    expect(anyBusy).toBe(true);
  });
});
