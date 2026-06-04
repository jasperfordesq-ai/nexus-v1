// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OrgHoursReviewTab — the org-owner hours approval surface.
 *
 * Focus: the approve toast must reflect the backend's actual payment_result
 * (paid vs insufficient_balance) rather than blindly claiming "paid" whenever
 * auto-pay is on. This guards the regression where an org with an empty wallet
 * was told a volunteer had been paid when they had not.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

const { toastMock } = vi.hoisted(() => ({
  toastMock: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() },
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [], meta: { cursor: null, has_more: false } }),
    put: vi.fn().mockResolvedValue({ success: true, data: { id: 1, status: 'approved', payment_result: null } }),
  },
}));

vi.mock('@/contexts', () => ({
  useToast: () => toastMock,
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: true, login: vi.fn(), logout: vi.fn() }),
  useTenant: () => ({ tenant: { id: 2 }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, hasFeature: () => true, hasModule: () => true }),
}));

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({ resolveAvatarUrl: (u: string | null) => u ?? '' }));

import OrgHoursReviewTab from './OrgHoursReviewTab';
import { api } from '@/lib/api';

const makeEntry = (id = 1) => ({
  id,
  hours: 3,
  date: '2026-02-15',
  description: 'Helped at the food bank.',
  status: 'pending' as const,
  created_at: '2026-02-15T14:00:00Z',
  user: { id: 10, name: 'Jane Doe', avatar_url: null },
  opportunity: { id: 20, title: 'Food Bank Volunteer' },
});

const baseProps = { orgId: 5, balance: 100, autoPay: true, onBalanceChange: vi.fn() };

describe('OrgHoursReviewTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a pending entry with approve and decline buttons', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [makeEntry(1)], meta: { cursor: null, has_more: false } });
    render(<OrgHoursReviewTab {...baseProps} />);
    await waitFor(() => expect(screen.getByText('Jane Doe')).toBeInTheDocument());
    expect(screen.getByRole('button', { name: /Approve hours for Jane Doe/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Decline hours for Jane Doe/i })).toBeInTheDocument();
  });

  it('calls PUT with action=approve when Approve is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [makeEntry(1)], meta: { cursor: null, has_more: false } });
    vi.mocked(api.put).mockResolvedValue({ success: true, data: { id: 1, status: 'approved', payment_result: 'paid' } });
    render(<OrgHoursReviewTab {...baseProps} />);
    await waitFor(() => screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));
    fireEvent.click(screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));
    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith('/v2/volunteering/hours/1/verify', { action: 'approve' });
    });
  });

  it('shows a success toast (not a warning) when payment_result = paid', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [makeEntry(1)], meta: { cursor: null, has_more: false } });
    vi.mocked(api.put).mockResolvedValue({ success: true, data: { id: 1, status: 'approved', payment_result: 'paid' } });
    render(<OrgHoursReviewTab {...baseProps} />);
    await waitFor(() => screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));
    fireEvent.click(screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));
    await waitFor(() => expect(toastMock.success).toHaveBeenCalled());
    expect(toastMock.warning).not.toHaveBeenCalled();
  });

  it('shows a WARNING (not a "paid" success) when payment_result = insufficient_balance', async () => {
    // The core regression: auto-pay is ON but the wallet was empty, so the
    // backend approved the hours WITHOUT paying. The UI must not claim "paid".
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [makeEntry(1)], meta: { cursor: null, has_more: false } });
    vi.mocked(api.put).mockResolvedValue({ success: true, data: { id: 1, status: 'approved', payment_result: 'insufficient_balance' } });
    render(<OrgHoursReviewTab {...baseProps} />);
    await waitFor(() => screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));
    fireEvent.click(screen.getByRole('button', { name: /Approve hours for Jane Doe/i }));
    await waitFor(() => expect(toastMock.warning).toHaveBeenCalled());
    expect(toastMock.success).not.toHaveBeenCalled();
  });

  it('calls PUT with action=decline when Decline is clicked', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [makeEntry(1)], meta: { cursor: null, has_more: false } });
    vi.mocked(api.put).mockResolvedValue({ success: true, data: { id: 1, status: 'declined', payment_result: null } });
    render(<OrgHoursReviewTab {...baseProps} />);
    await waitFor(() => screen.getByRole('button', { name: /Decline hours for Jane Doe/i }));
    fireEvent.click(screen.getByRole('button', { name: /Decline hours for Jane Doe/i }));
    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith('/v2/volunteering/hours/1/verify', { action: 'decline' });
    });
  });
});
