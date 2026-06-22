// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable hoisted refs ───────────────────────────────────────────────────────
const { mockToast, mockGetDashboard, mockApiDownload } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockGetDashboard: vi.fn(),
  mockApiDownload: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({ useToast: () => mockToast }),
);

vi.mock('@/lib/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: mockApiDownload,
  },
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: mockApiDownload,
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminCrm: {
    getDashboard: mockGetDashboard,
  },
}));

vi.mock('../../AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

import { CrmDashboard } from './CrmDashboard';

// ── Test data ─────────────────────────────────────────────────────────────────
const DASHBOARD_DATA = {
  total_members: 1500,
  active_members: 800,
  new_this_month: 45,
  pending_approvals: 12,
  open_tasks: 7,
  overdue_tasks: 3,
  total_notes: 234,
  never_logged_in: 150,
  retention_rate: 53,
};

const EMPTY_DASHBOARD_DATA = {
  total_members: 0,
  active_members: 0,
  new_this_month: 0,
  pending_approvals: 0,
  open_tasks: 0,
  overdue_tasks: 0,
  total_notes: 0,
  never_logged_in: 0,
  retention_rate: 0,
};

// ── Tests ─────────────────────────────────────────────────────────────────────
describe('CrmDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApiDownload.mockResolvedValue(undefined);
  });

  it('shows a loading spinner while fetching dashboard data', () => {
    mockGetDashboard.mockReturnValue(new Promise(() => {}));
    render(<CrmDashboard />);
    const spinner = Array.from(document.body.querySelectorAll('[role="status"]')).find(
      (el) => el.getAttribute('aria-busy') === 'true'
    );
    expect(spinner).toBeTruthy();
  });

  it('renders stat cards after successful load', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      expect(screen.getByText('1,500')).toBeInTheDocument();
    });
  });

  it('shows active members count', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      // 800 appears in stat card AND activity summary chip
      const els = screen.getAllByText('800');
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('shows new this month count', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      const els = screen.getAllByText('45');
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('shows retention rate percentage in activity summary', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      // 53% appears in both stat card paragraph and a chip span
      const els = screen.getAllByText('53%');
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('shows pending approvals count', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      expect(screen.getByText('12')).toBeInTheDocument();
    });
  });

  it('shows overdue tasks count', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      expect(screen.getByText('3')).toBeInTheDocument();
    });
  });

  it('renders quick action links', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      // Quick action labels appear in both card text and aria-label/button text — use getAllByText
      expect(screen.getAllByText(/member notes/i).length).toBeGreaterThan(0);
      expect(screen.getAllByText(/crm tasks/i).length).toBeGreaterThan(0);
      expect(screen.getAllByText(/member tags/i).length).toBeGreaterThan(0);
    });
  });

  it('calls getDashboard again when Refresh is pressed', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => screen.getByText('1,500'));

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await userEvent.click(refreshBtn);

    await waitFor(() => {
      expect(mockGetDashboard).toHaveBeenCalledTimes(2);
    });
  });

  it('calls api.download with dashboard type on Export Stats click', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => screen.getByText('1,500'));

    const exportStatsBtn = screen.getByRole('button', { name: /export stats/i });
    await userEvent.click(exportStatsBtn);

    await waitFor(() => {
      expect(mockApiDownload).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/crm/export/dashboard'),
        expect.any(Object)
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('calls api.download with notes type on Export Notes click', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => screen.getByText('1,500'));

    const exportNotesBtn = screen.getByRole('button', { name: /export notes/i });
    await userEvent.click(exportNotesBtn);

    await waitFor(() => {
      expect(mockApiDownload).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/crm/export/notes'),
        expect.any(Object)
      );
    });
  });

  it('calls api.download with tasks type on Export Tasks click', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => screen.getByText('1,500'));

    const exportTasksBtn = screen.getByRole('button', { name: /export tasks/i });
    await userEvent.click(exportTasksBtn);

    await waitFor(() => {
      expect(mockApiDownload).toHaveBeenCalledWith(
        expect.stringContaining('/v2/admin/crm/export/tasks'),
        expect.any(Object)
      );
    });
  });

  it('shows error toast when export fails', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: DASHBOARD_DATA });
    mockApiDownload.mockRejectedValue(new Error('Download failed'));

    render(<CrmDashboard />);
    await waitFor(() => screen.getByText('1,500'));

    const exportStatsBtn = screen.getByRole('button', { name: /export stats/i });
    await userEvent.click(exportStatsBtn);

    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });

  it('shows error toast when dashboard load fails', async () => {
    mockGetDashboard.mockRejectedValue(new Error('Network error'));
    render(<CrmDashboard />);
    await waitFor(() => expect(mockToast.error).toHaveBeenCalled());
  });

  it('renders zero values correctly for empty dashboard data', async () => {
    mockGetDashboard.mockResolvedValue({ success: true, data: EMPTY_DASHBOARD_DATA });
    render(<CrmDashboard />);
    await waitFor(() => {
      // 0% appears in stat card paragraph and chip span
      const els = screen.getAllByText('0%');
      expect(els.length).toBeGreaterThan(0);
    });
  });
});
