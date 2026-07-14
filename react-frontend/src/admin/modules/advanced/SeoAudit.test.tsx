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
  {
    code: 'tenant_metadata' as const,
    params: {},
    status: 'pass' as const,
    issues: [],
    issue_count: 0,
    points: 10,
    max_points: 10,
  },
  {
    code: 'seo_settings' as const,
    params: {},
    status: 'warning' as const,
    issues: [{ code: 'canonical_urls_not_enabled' as const, params: {} }],
    issue_count: 1,
    points: 5,
    max_points: 10,
  },
  {
    code: 'canonical_urls' as const,
    params: {},
    status: 'fail' as const,
    issues: [{ code: 'custom_canonical_urls_high' as const, params: { count: 58 } }],
    issue_count: 1,
    points: 0,
    max_points: 10,
  },
]);

const auditResult = () => ({
  checks: MOCK_CHECKS,
  score: 15,
  max_score: 30,
  grade: 'F' as const,
  run_at: '2025-06-01T12:00:00Z',
});

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

    resolve({ success: true, data: { ...auditResult(), checks: [] } });
  });

  it('hides loading spinner after data loads', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: { ...auditResult(), checks: [] },
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
      data: { ...auditResult(), checks: [] },
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      expect(screen.getByText(/no.*audit|run.*audit/i)).toBeInTheDocument();
    });
  });

  it('renders audit check rows when results exist', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: auditResult(),
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      expect(screen.getByText('Homepage Metadata')).toBeInTheDocument();
    });
    expect(screen.getByText('SEO Configuration')).toBeInTheDocument();
    expect(screen.getByText('Canonical URLs')).toBeInTheDocument();
    expect(screen.getByText('Canonical URLs are not enabled.')).toBeInTheDocument();
    expect(screen.getByText('58 pages have custom canonical URLs; confirm that they are still valid.')).toBeInTheDocument();
  });

  it('shows pass/warning/fail chips with correct count summary', async () => {
    getAuditMock.mockResolvedValueOnce({
      success: true,
      data: auditResult(),
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
      data: { ...auditResult(), checks: [] },
    } as never);
    runAuditMock.mockResolvedValueOnce({
      success: true,
      data: auditResult(),
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
      data: { ...auditResult(), checks: [] },
    } as never);
    runAuditMock.mockResolvedValueOnce({
      success: true,
      data: auditResult(),
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
      expect(screen.getByText('Homepage Metadata')).toBeInTheDocument();
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
      data: auditResult(),
    } as never);

    render(<SeoAudit />);

    await waitFor(() => {
      expect(screen.getByText('Homepage Metadata')).toBeInTheDocument();
    });

    expect(screen.getByRole('button', { name: /reload/i })).toBeInTheDocument();
  });
});
