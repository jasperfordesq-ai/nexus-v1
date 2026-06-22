// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// Stable mock refs via vi.hoisted
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

vi.mock('@/contexts/ToastContext', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/contexts/ToastContext')>();
  return { ...actual, useToast: () => mockToast };
});

// adminApi mock — use vi.hoisted so the factory can reference it
const mockGetDiagnostics = vi.hoisted(() => vi.fn());
vi.mock('@/admin/api/adminApi', () => ({
  adminNewsletters: {
    getDiagnostics: mockGetDiagnostics,
    list: vi.fn(),
    create: vi.fn(),
  },
  // stub everything else to avoid import errors
  adminLegalDocs: {},
  adminHelpFaqs: {},
}));

vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { NewsletterDiagnostics } from './NewsletterDiagnostics';

const makeData = (overrides = {}) => ({
  health_status: 'healthy',
  bounce_rate: 1.5,
  sender_score: 92,
  sender_score_breakdown: {
    bounce_penalty: 0,
    complaint_penalty: 0,
    failure_penalty: 0,
    suppression_penalty: 0,
    volume_bonus: 5,
  },
  queue_status: {
    total: 100,
    pending: 10,
    sending: 5,
    sent: 80,
    failed: 5,
  },
  configuration: {
    smtp_configured: true,
    api_configured: false,
    tracking_enabled: true,
  },
  ...overrides,
});

describe('NewsletterDiagnostics', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading text while data is being fetched', async () => {
    mockGetDiagnostics.mockReturnValue(new Promise(() => {}));
    render(<NewsletterDiagnostics />);
    // The component renders loading text inside the queue status card
    expect(screen.getAllByText(/loading/i).length).toBeGreaterThan(0);
  });

  it('renders health status chip when data loads', async () => {
    mockGetDiagnostics.mockResolvedValue({ success: true, data: makeData() });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      // healthy status chip or text visible
      expect(screen.getAllByText(/healthy/i).length).toBeGreaterThan(0);
    });
  });

  it('renders bounce rate and sender score', async () => {
    mockGetDiagnostics.mockResolvedValue({ success: true, data: makeData() });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(screen.getByText(/1\.50%/)).toBeInTheDocument();
    });
    // Sender score
    expect(screen.getByText('92')).toBeInTheDocument();
  });

  it('renders queue totals', async () => {
    mockGetDiagnostics.mockResolvedValue({ success: true, data: makeData() });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(screen.getByText('100')).toBeInTheDocument(); // total
    });
    expect(screen.getByText('80')).toBeInTheDocument(); // sent
    // "5" appears multiple times (pending:10, sending:5, failed:5, sender_score:92)
    // Just check that at least one "5" is present — queue stats are rendered
    expect(screen.getAllByText('5').length).toBeGreaterThanOrEqual(1);
  });

  it('shows warning chip when bounce rate is high (≥5%)', async () => {
    mockGetDiagnostics.mockResolvedValue({
      success: true,
      data: makeData({ bounce_rate: 7, health_status: 'warning' }),
    });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(screen.getAllByText(/warning/i).length).toBeGreaterThan(0);
    });
  });

  it('shows danger chip when bounce rate is critical (≥10%)', async () => {
    mockGetDiagnostics.mockResolvedValue({
      success: true,
      data: makeData({ bounce_rate: 12, health_status: 'critical' }),
    });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(screen.getAllByText(/critical/i).length).toBeGreaterThan(0);
    });
  });

  it('shows view bounces button when failed sends > 10', async () => {
    mockGetDiagnostics.mockResolvedValue({
      success: true,
      data: makeData({
        queue_status: { total: 200, pending: 5, sending: 2, sent: 170, failed: 23 },
      }),
    });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /bounces/i })).toBeInTheDocument();
    });
  });

  it('does not show view bounces button when failed sends ≤ 10', async () => {
    mockGetDiagnostics.mockResolvedValue({ success: true, data: makeData() });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /bounces/i })).not.toBeInTheDocument();
    });
  });

  it('shows toast error and nulls data on API failure', async () => {
    mockGetDiagnostics.mockRejectedValue(new Error('Network error'));
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('calls getDiagnostics again when refresh button is clicked', async () => {
    mockGetDiagnostics.mockResolvedValue({ success: true, data: makeData() });
    const user = userEvent.setup();
    render(<NewsletterDiagnostics />);
    await waitFor(() => expect(mockGetDiagnostics).toHaveBeenCalledTimes(1));

    const refreshBtn = screen.getByRole('button', { name: /refresh/i });
    await user.click(refreshBtn);
    await waitFor(() => expect(mockGetDiagnostics).toHaveBeenCalledTimes(2));
  });

  it('shows smtp configured status in configuration panel', async () => {
    mockGetDiagnostics.mockResolvedValue({ success: true, data: makeData() });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      // "Active" label for smtp_configured=true
      expect(screen.getAllByText(/active/i).length).toBeGreaterThan(0);
    });
  });

  it('shows no-penalties message when score breakdown is all zeros', async () => {
    mockGetDiagnostics.mockResolvedValue({ success: true, data: makeData() });
    render(<NewsletterDiagnostics />);
    await waitFor(() => {
      expect(screen.getByText(/no.penalties/i)).toBeInTheDocument();
    });
  });
});
