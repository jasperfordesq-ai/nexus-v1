// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable hoisted data ────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
}));

const SUPER_ADMIN_USER = vi.hoisted(() => ({
  id: 1,
  name: 'Super Admin',
  role: 'super_admin',
  is_super_admin: true,
}));

// ── Mocks ──────────────────────────────────────────────────────────────────────

vi.mock('../../api/adminApi', () => ({
  adminSystem: {
    getCronJobs: vi.fn(),
  },
  adminCron: {
    getJobSettings: vi.fn(),
    updateJobSettings: vi.fn(),
    getGlobalSettings: vi.fn(),
    updateGlobalSettings: vi.fn(),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useAuth: () => ({
      user: SUPER_ADMIN_USER,
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
  })
);

vi.mock('../../components', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../components')>();
  return {
    ...actual,
    PageHeader: ({ title }: { title: string }) => <h1>{title}</h1>,
  };
});

import { adminSystem, adminCron } from '../../api/adminApi';
import { CronJobSettingsPage } from './CronJobSettings';

const MOCK_JOBS = [
  { id: 1, slug: 'daily-digest', translation_key: 'daily_digest', schedule: '0 9 * * *', status: 'active' },
  { id: 2, slug: 'weekly-digest', translation_key: 'weekly_digest', schedule: '0 8 * * 1', status: 'disabled' },
];

const MOCK_JOB_SETTINGS = {
  job_id: 'daily-digest',
  is_enabled: true,
  custom_schedule: '0 9 * * *',
  notify_on_failure: false,
  notify_emails: '',
  max_retries: 3,
  timeout_seconds: 300,
};

const MOCK_GLOBAL_SETTINGS = {
  default_notify_email: 'ops@example.com',
  log_retention_days: 30,
  max_concurrent_jobs: 5,
};

describe('CronJobSettingsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(adminSystem.getCronJobs).mockResolvedValue({ success: true, data: MOCK_JOBS });
    vi.mocked(adminCron.getGlobalSettings).mockResolvedValue({ success: true, data: MOCK_GLOBAL_SETTINGS });
    vi.mocked(adminCron.getJobSettings).mockResolvedValue({ success: true, data: MOCK_JOB_SETTINGS });
  });

  it('shows loading spinners while fetching jobs and global settings', async () => {
    vi.mocked(adminSystem.getCronJobs).mockReturnValue(new Promise(() => {}));
    vi.mocked(adminCron.getGlobalSettings).mockReturnValue(new Promise(() => {}));
    render(<CronJobSettingsPage />);
    // Component shows role="status" aria-busy="true" divs while loadingJobs || loadingGlobalSettings
    await waitFor(() => {
      const statuses = screen.getAllByRole('status');
      const busy = statuses.filter((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy.length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders the page title heading after load', async () => {
    render(<CronJobSettingsPage />);
    await waitFor(() => {
      expect(adminSystem.getCronJobs).toHaveBeenCalled();
      expect(adminCron.getGlobalSettings).toHaveBeenCalled();
    });
    // The mocked PageHeader renders <h1>{title}</h1>
    const heading = screen.getByRole('heading');
    expect(heading).toBeInTheDocument();
  });

  it('shows the default notification email value after global settings load', async () => {
    render(<CronJobSettingsPage />);
    await waitFor(() => {
      // HeroUI Input renders a native <input>; wait for globalSettings to load
      // and look for the input with the email value
      const emailInputs = Array.from(document.querySelectorAll('input[type="email"]'));
      const found = emailInputs.find((inp) => (inp as HTMLInputElement).value === 'ops@example.com');
      expect(found).toBeTruthy();
    });
  });

  it('saves global settings and shows success toast', async () => {
    const user = userEvent.setup();
    vi.mocked(adminCron.updateGlobalSettings).mockResolvedValue({ success: true });

    render(<CronJobSettingsPage />);
    await waitFor(() => {
      const emailInputs = Array.from(document.querySelectorAll('input[type="email"]'));
      const found = emailInputs.find((inp) => (inp as HTMLInputElement).value === 'ops@example.com');
      expect(found).toBeTruthy();
    });

    // Find save global settings button (the second Save button on the page)
    const saveButtons = screen.getAllByRole('button');
    // Global settings card save button is typically last on the page
    const globalSaveBtn = saveButtons[saveButtons.length - 1];

    await user.click(globalSaveBtn);

    await waitFor(() => {
      expect(adminCron.updateGlobalSettings).toHaveBeenCalled();
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows error toast when saving global settings fails', async () => {
    const user = userEvent.setup();
    vi.mocked(adminCron.updateGlobalSettings).mockResolvedValue({ success: false, error: 'Server error' });

    render(<CronJobSettingsPage />);
    await waitFor(() => {
      const emailInputs = Array.from(document.querySelectorAll('input[type="email"]'));
      expect(emailInputs.find((inp) => (inp as HTMLInputElement).value === 'ops@example.com')).toBeTruthy();
    });

    const saveButtons = screen.getAllByRole('button');
    const globalSaveBtn = saveButtons[saveButtons.length - 1];
    await user.click(globalSaveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when loading global settings throws', async () => {
    vi.mocked(adminCron.getGlobalSettings).mockRejectedValue(new Error('Failed'));
    render(<CronJobSettingsPage />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows empty-job placeholder until a job is selected', async () => {
    render(<CronJobSettingsPage />);
    await waitFor(() => {
      // Jobs loaded — component shows the Select; no job is selected initially
      // so it shows the AlertCircle + hint paragraph
      expect(adminSystem.getCronJobs).toHaveBeenCalled();
    });
    // Confirm adminCron.getJobSettings was NOT called (no job selected yet)
    expect(adminCron.getJobSettings).not.toHaveBeenCalled();
  });

  it('calls both getCronJobs and getGlobalSettings on mount', async () => {
    render(<CronJobSettingsPage />);
    await waitFor(() => {
      expect(adminSystem.getCronJobs).toHaveBeenCalledTimes(1);
      expect(adminCron.getGlobalSettings).toHaveBeenCalledTimes(1);
    });
  });
});
