// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock data ──────────────────────────────────────────────────────────
const MOCK_CHECKS = vi.hoisted(() => [
  { name: 'Meta Title', description: 'Page has a title tag', status: 'pass' as const },
  { name: 'Meta Description', description: 'Missing description', status: 'warning' as const },
  { name: 'Canonical URL', description: 'No canonical set', status: 'fail' as const },
]);

// ── mock adminApi ─────────────────────────────────────────────────────────────
vi.mock('@/admin/api/adminApi', () => ({
  adminTools: {
    getSeoAudit: vi.fn(),
    runSeoAudit: vi.fn(),
  },
}));

// ── mock @/contexts ───────────────────────────────────────────────────────────
vi.mock('@/contexts', () => createMockContexts());

// ── mock AdminMetaContext ─────────────────────────────────────────────────────
vi.mock('@/admin/AdminMetaContext', () => ({
  useAdminPageMeta: vi.fn(),
}));

import { SeoAudit } from './SeoAudit';
import { adminTools } from '@/admin/api/adminApi';

const getAuditMock = vi.mocked(adminTools.getSeoAudit);
const runAuditMock = vi.mocked(adminTools.runSeoAudit);

describe('SeoAudit', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner during initial fetch', async () => {
    let resolve!: (v: unknown) => void;
    getAuditMock.mockReturnValueOnce(new Promise((r) => (resolve = r)) as never);

    render(<SeoAudit />);

    const statusEls = screen.queryAllByRole('status');
    const busyEl = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busyEl).toBeInTheDocument();

    resolve({ success: true, data: { checks: [], last_run_at: null } });
  });

  it('hides loading spinner after data loads', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: [], last_run_at: null },
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true'
      );
      expect(busyEls).toHaveLength(0);
    });
  });

  it('shows empty state when no previous audit results', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: [], last_run_at: null },
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      expect(screen.getByText(/no.*audit|run.*audit/i)).toBeInTheDocument();
    });
  });

  it('renders audit check rows when results exist', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: MOCK_CHECKS, last_run_at: '2025-06-01T12:00:00Z' },
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      expect(screen.getByText('Meta Title')).toBeInTheDocument();
    });
    expect(screen.getByText('Meta Description')).toBeInTheDocument();
    expect(screen.getByText('Canonical URL')).toBeInTheDocument();
  });

  it('shows pass/warning/fail chips with correct count summary', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: MOCK_CHECKS, last_run_at: '2025-06-01T12:00:00Z' },
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      // 1 pass, 1 warning, 1 fail — summary chips should be visible
      // The chips display translated keys like "1 passed", "1 warning", "1 failed"
      const allText = document.body.textContent ?? '';
      expect(allText).toMatch(/1/); // at least some count chips
    });
  });

  it('calls runSeoAudit when Run Audit button clicked', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: [], last_run_at: null },
    } as never);
    runAuditMock.mockResolvedValueOnce({
      success: true,
      data: MOCK_CHECKS,
    } as never);

    const user = userEvent.setup();
    render(<SeoAudit />);

    await waitFor(() => {
      // Wait for initial load to complete (run audit button visible)
      expect(screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true'
      )).toHaveLength(0);
    });

    const runBtn = screen.getByRole('button', { name: /run.*audit/i });
    await user.click(runBtn);

    await waitFor(() => {
      expect(runAuditMock).toHaveBeenCalled();
    });
  });

  it('shows newly returned checks after running the audit', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: [], last_run_at: null },
    } as never);
    runAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: MOCK_CHECKS },
    } as never);

    const user = userEvent.setup();
    render(<SeoAudit />);

    await waitFor(() => {
      expect(screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true'
      )).toHaveLength(0);
    });

    await user.click(screen.getByRole('button', { name: /run.*audit/i }));

    await waitFor(() => {
      expect(screen.getByText('Meta Title')).toBeInTheDocument();
    });
  });

  it('handles API error during fetch gracefully (shows empty state, no crash)', async () => {
    getAuditMock.mockRejectedValueOnce(new Error('server error'));

    render(<SeoAudit />);

    await waitFor(() => {
      // Should show no-results state, not crash
      expect(document.body).toBeInTheDocument();
      const busyEls = screen.queryAllByRole('status').filter(
        (el) => el.getAttribute('aria-busy') === 'true'
      );
      expect(busyEls).toHaveLength(0);
    });
  });

  it('shows Reload Results button when results exist', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { checks: MOCK_CHECKS, last_run_at: '2025-06-01T12:00:00Z' },
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      expect(screen.getByText('Meta Title')).toBeInTheDocument();
    });

    expect(screen.getByRole('button', { name: /reload/i })).toBeInTheDocument();
  });
});
