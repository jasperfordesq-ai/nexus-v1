// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

// ─── Hoisted API mocks ────────────────────────────────────────────────────────
const { api } = vi.hoisted(() => ({
  api: {
    get: vi.fn(),
    approve: vi.fn(),
    suspend: vi.fn(),
    reactivate: vi.fn(),
    reset2fa: vi.fn(),
    sendPasswordReset: vi.fn(),
    sendVerificationEmail: vi.fn(),
    update: vi.fn(),
    getConsents: vi.fn(),
    adjustBalance: vi.fn(),
    getUserRecords: vi.fn(),
    getUserCertificates: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminUsers: {
    get: api.get,
    approve: api.approve,
    suspend: api.suspend,
    reactivate: api.reactivate,
    reset2fa: api.reset2fa,
    sendPasswordReset: api.sendPasswordReset,
    sendVerificationEmail: api.sendVerificationEmail,
    update: api.update,
    getConsents: api.getConsents,
  },
  adminTimebanking: { adjustBalance: api.adjustBalance },
  adminVetting: { getUserRecords: api.getUserRecords },
  adminInsurance: { getUserCertificates: api.getUserCertificates },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
vi.mock('@/contexts', () => ({ useToast: () => mockToast }));

vi.mock('@/lib/helpers', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/lib/helpers')>();
  return { ...actual, resolveAvatarUrl: (u: string | null) => u ?? null };
});
vi.mock('@/lib/serverTime', () => ({
  formatServerDate: (s: string) => s ?? '',
  formatServerDateTime: (s: string) => s ?? '',
}));

// Passthrough i18n: return a readable label from the key's last segment so we
// can assert on button presence without depending on loaded translations.
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: { defaultValue?: string }) => opts?.defaultValue ?? key,
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

import MemberDetailModal from './MemberDetailModal';

const activeMember = {
  id: 5,
  name: 'Dana Member',
  email: 'dana@example.com',
  role: 'member',
  status: 'active',
  email_verified_at: '2025-01-01T00:00:00Z',
  balance: 12,
  created_at: '2024-01-01T00:00:00Z',
  last_active_at: '2025-05-01T00:00:00Z',
  onboarding_completed: true,
  first_name: 'Dana',
  last_name: 'Member',
};

describe('MemberDetailModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockResolvedValue({ success: true, data: activeMember });
    api.getUserRecords.mockResolvedValue({ success: true, data: [] });
    api.getUserCertificates.mockResolvedValue({ success: true, data: [] });
    api.getConsents.mockResolvedValue({ success: true, data: [] });
    api.adjustBalance.mockResolvedValue({ success: true });
    api.sendVerificationEmail.mockResolvedValue({ success: true });
  });

  it('does not fetch when userId is null (modal closed)', () => {
    render(<MemberDetailModal userId={null} onClose={vi.fn()} onChanged={vi.fn()} />);
    expect(api.get).not.toHaveBeenCalled();
  });

  it('loads the member and shows the operational action set', async () => {
    render(<MemberDetailModal userId={5} onClose={vi.fn()} onChanged={vi.fn()} />);

    await waitFor(() => expect(api.get).toHaveBeenCalledWith(5));
    await waitFor(() => expect(screen.getByText('Dana Member')).toBeInTheDocument());

    // Sensitive/operational actions the broker should have.
    expect(screen.getByText('member_detail.action_resend_verification')).toBeInTheDocument();
    expect(screen.getByText('member_detail.action_send_password_reset')).toBeInTheDocument();
    expect(screen.getByText('member_detail.action_reset_2fa')).toBeInTheDocument();
    expect(screen.getByText('member_detail.action_adjust_balance')).toBeInTheDocument();
    // Active member → Suspend offered, not Approve/Reactivate.
    expect(screen.getByText('members.suspend')).toBeInTheDocument();
  });

  it('never exposes privileged actions (ban / delete / role change)', async () => {
    render(<MemberDetailModal userId={5} onClose={vi.fn()} onChanged={vi.fn()} />);
    await waitFor(() => expect(screen.getByText('Dana Member')).toBeInTheDocument());

    expect(screen.queryByText(/\bban\b/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/\bdelete\b/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/impersonate/i)).not.toBeInTheDocument();
  });

  it('adjusts the balance through the sub-modal', async () => {
    render(<MemberDetailModal userId={5} onClose={vi.fn()} onChanged={vi.fn()} />);
    await waitFor(() => expect(screen.getByText('Dana Member')).toBeInTheDocument());

    fireEvent.click(screen.getByText('member_detail.action_adjust_balance'));
    await waitFor(() => expect(screen.getByText('member_detail.balance_modal_title')).toBeInTheDocument());

    fireEvent.change(screen.getByPlaceholderText('0'), { target: { value: '5' } });
    fireEvent.change(screen.getByPlaceholderText('member_detail.balance_reason_placeholder'), {
      target: { value: 'Manual correction' },
    });
    fireEvent.click(screen.getByText('member_detail.balance_submit'));

    await waitFor(() => expect(api.adjustBalance).toHaveBeenCalledWith(5, 5, 'Manual correction'));
  });
});
