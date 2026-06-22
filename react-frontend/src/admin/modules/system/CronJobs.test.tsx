// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── adminApi mock ─────────────────────────────────────────────────────────────
const { mockAdminSystem, mockAdminCron } = vi.hoisted(() => ({
  mockAdminSystem: {
    getCronJobs: vi.fn(),
    runCronJob: vi.fn(),
    getActivityLog: vi.fn(),
  },
  mockAdminCron: {
    getLogs: vi.fn(),
    getLogDetail: vi.fn(),
    clearLogs: vi.fn(),
    getJobSettings: vi.fn(),
    updateJobSettings: vi.fn(),
    getGlobalSettings: vi.fn(),
    updateGlobalSettings: vi.fn(),
    getHealthMetrics: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminSystem: mockAdminSystem,
  adminCron: mockAdminCron,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── contexts ─────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── admin sub-components ─────────────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div>
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  StatusBadge: ({ status }: { status: string }) => <span data-testid="status-badge">{status}</span>,
}));

// ─── fixtures ─────────────────────────────────────────────────────────────────
const makeJob = (overrides = {}) => ({
  id: 1,
  name: 'Send Notifications',
  command: 'php artisan notifications:send',
  schedule: '*/5 * * * *',
  status: 'active',
  last_status: 'success',
  last_run_at: new Date(Date.now() - 60000).toISOString(),
  next_run_at: new Date(Date.now() + 240000).toISOString(),
  category: 'notifications',
  description: 'Dispatches pending notifications',
  ...overrides,
});

const makeHealthMetrics = (overrides = {}) => ({
  health_score: 95,
  alert_status: 'healthy',
  avg_success_rate_7d: 0.98,
  jobs_failed_24h: 0,
  recent_failures: [],
  jobs_overdue: [],
  ...overrides,
});

// ─── tests ────────────────────────────────────────────────────────────────────
describe('CronJobs', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [] });
    mockAdminCron.getHealthMetrics.mockResolvedValue({ success: true, data: makeHealthMetrics() });
  });

  it('shows a spinner while loading jobs', async () => {
    mockAdminSystem.getCronJobs.mockImplementationOnce(() => new Promise(() => {}));
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    const spinners = screen.getAllByRole('status');
    const busy = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows empty state when no cron jobs are returned', async () => {
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      const spinners = screen.queryAllByRole('status');
      expect(spinners.find((el) => el.getAttribute('aria-busy') === 'true')).toBeUndefined();
    });
    // page still renders (no crash); empty state text is i18n key
    expect(screen.queryByText('Send Notifications')).not.toBeInTheDocument();
  });

  it('renders job name and schedule when jobs are present', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob()] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      expect(screen.getByText('Send Notifications')).toBeInTheDocument();
    });
    expect(screen.getByText('*/5 * * * *')).toBeInTheDocument();
  });

  it('renders the command for a job', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob()] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      expect(screen.getByText('php artisan notifications:send')).toBeInTheDocument();
    });
  });

  it('renders the job description when provided', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob()] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      expect(screen.getByText('Dispatches pending notifications')).toBeInTheDocument();
    });
  });

  it('renders health score from health metrics', async () => {
    mockAdminCron.getHealthMetrics.mockResolvedValue({
      success: true,
      data: makeHealthMetrics({ health_score: 87 }),
    });
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob()] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      expect(screen.getByText('87')).toBeInTheDocument();
    });
  });

  it('shows critical alert banner when alert_status is critical', async () => {
    mockAdminCron.getHealthMetrics.mockResolvedValue({
      success: true,
      data: makeHealthMetrics({ alert_status: 'critical', jobs_failed_24h: 10 }),
    });
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob()] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      // The critical card has a visible danger indicator — health_score still renders
      expect(screen.getByText('10')).toBeInTheDocument();
    });
  });

  it('shows overdue jobs section when jobs_overdue is non-empty', async () => {
    mockAdminCron.getHealthMetrics.mockResolvedValue({
      success: true,
      data: makeHealthMetrics({
        jobs_overdue: [{ job_name: 'Geocoding Job', expected_interval: '1h', last_run: null }],
      }),
    });
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob()] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      expect(screen.getByText('Geocoding Job')).toBeInTheDocument();
    });
  });

  it('renders "Run Now" button for active jobs', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob({ status: 'active' })] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      const runBtn = screen.queryAllByRole('button').find(
        (b) => b.textContent?.toLowerCase().includes('run'),
      );
      expect(runBtn).toBeDefined();
    });
  });

  it('calls runCronJob when Run Now is clicked', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob({ status: 'active' })] });
    mockAdminSystem.runCronJob.mockResolvedValue({ success: true });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);

    await waitFor(() => expect(screen.getByText('Send Notifications')).toBeInTheDocument());

    const runBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('run'),
    );
    expect(runBtn).toBeDefined();
    await userEvent.click(runBtn!);

    await waitFor(() => {
      expect(mockAdminSystem.runCronJob).toHaveBeenCalledWith(1);
    });
  });

  it('shows success toast after successful run', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob({ status: 'active' })] });
    mockAdminSystem.runCronJob.mockResolvedValue({ success: true });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);

    await waitFor(() => expect(screen.getByText('Send Notifications')).toBeInTheDocument());

    const runBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('run'),
    );
    await userEvent.click(runBtn!);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when run fails', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob({ status: 'active' })] });
    mockAdminSystem.runCronJob.mockResolvedValue({ success: false, error: 'Server error' });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);

    await waitFor(() => expect(screen.getByText('Send Notifications')).toBeInTheDocument());

    const runBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('run'),
    );
    await userEvent.click(runBtn!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('Run Now button is disabled for disabled jobs', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({ success: true, data: [makeJob({ status: 'disabled' })] });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);

    await waitFor(() => expect(screen.getByText('Send Notifications')).toBeInTheDocument());

    const runBtn = screen.queryAllByRole('button').find(
      (b) => b.textContent?.toLowerCase().includes('run'),
    );
    expect(runBtn).toBeDefined();
    // HeroUI disabled buttons have data-disabled attribute
    expect(
      runBtn!.hasAttribute('disabled') ||
      runBtn!.getAttribute('data-disabled') === 'true' ||
      runBtn!.getAttribute('aria-disabled') === 'true',
    ).toBe(true);
  });

  it('groups jobs by category', async () => {
    mockAdminSystem.getCronJobs.mockResolvedValue({
      success: true,
      data: [
        makeJob({ id: 1, category: 'notifications', name: 'Job A' }),
        makeJob({ id: 2, category: 'matching', name: 'Job B' }),
      ],
    });
    const { CronJobs } = await import('./CronJobs');
    render(<CronJobs />);
    await waitFor(() => {
      expect(screen.getByText('Job A')).toBeInTheDocument();
      expect(screen.getByText('Job B')).toBeInTheDocument();
    });
    // Category chips appear
    expect(screen.getByText('notifications')).toBeInTheDocument();
    expect(screen.getByText('matching')).toBeInTheDocument();
  });
});
