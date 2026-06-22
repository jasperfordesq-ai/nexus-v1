// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock adminApi ────────────────────────────────────────────────────────────
const { mockAdminMatching } = vi.hoisted(() => ({
  mockAdminMatching: {
    getApproval: vi.fn(),
    approveMatch: vi.fn(),
    rejectMatch: vi.fn(),
  },
}));

vi.mock('../../api/adminApi', () => ({
  adminMatching: mockAdminMatching,
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminMatching: mockAdminMatching,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Toast / Router / Contexts ───────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useNavigate: () => mockNavigate,
    useParams: () => ({ id: '42' }),
  };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));

// ─── Stub admin sub-components ───────────────────────────────────────────────
vi.mock('../../components', () => ({
  PageHeader: ({ title, actions }: { title: string; actions?: React.ReactNode }) => (
    <div data-testid="page-header">
      <h1>{title}</h1>
      {actions}
    </div>
  ),
  StatusBadge: ({ status }: { status: string }) => <span data-testid="status-badge">{status}</span>,
}));

// ─── Fixture ─────────────────────────────────────────────────────────────────
const makeApproval = (overrides = {}) => ({
  success: true,
  data: {
    id: 42,
    status: 'pending',
    match_score: 82,
    match_type: 'one_way',
    match_reasons: ['skill_overlap', 'location'],
    distance_km: 3.5,
    category_name: 'Gardening',
    user_1_name: 'Alice Smith',
    user_1_email: 'alice@example.com',
    user_1_avatar: null,
    user_1_location: 'Dublin',
    user_1_bio: 'Loves plants',
    user_2_name: 'Bob Jones',
    user_2_email: 'bob@example.com',
    user_2_avatar: null,
    user_2_location: 'Cork',
    user_2_bio: 'Expert gardener',
    listing_title: 'Gardening Help Wanted',
    listing_type: 'request',
    listing_status: 'active',
    listing_description: 'Need help with my garden',
    reviewed_at: null,
    reviewer_name: null,
    notes: null,
    ...overrides,
  },
});

// ─────────────────────────────────────────────────────────────────────────────
describe('MatchDetail', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('shows loading spinner while fetching', async () => {
    mockAdminMatching.getApproval.mockImplementationOnce(() => new Promise(() => {}));
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    const statuses = screen.getAllByRole('status');
    const busy = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('shows error state when load fails', async () => {
    mockAdminMatching.getApproval.mockResolvedValue({ success: false, error: 'Not found' });
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => {
      // Error card shows a back button
      const btns = screen.getAllByRole('button');
      const backBtn = btns.find((b) => b.textContent?.toLowerCase().includes('back'));
      expect(backBtn).toBeInTheDocument();
    });
  });

  it('renders user names and match score', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval());
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => {
      expect(screen.getByText('Alice Smith')).toBeInTheDocument();
      expect(screen.getByText('Bob Jones')).toBeInTheDocument();
      expect(screen.getByText(/82%/)).toBeInTheDocument();
    });
  });

  it('renders listing title when present', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval());
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => {
      expect(screen.getByText('Gardening Help Wanted')).toBeInTheDocument();
    });
  });

  it('renders match reasons as chips', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval());
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => {
      expect(screen.getByText('skill_overlap')).toBeInTheDocument();
      expect(screen.getByText('location')).toBeInTheDocument();
    });
  });

  it('shows approve and reject buttons for pending match', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval({ status: 'pending' }));
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const approveBtn = btns.find((b) => b.textContent?.toLowerCase().includes('approve'));
      const rejectBtn = btns.find((b) => b.textContent?.toLowerCase().includes('reject'));
      expect(approveBtn).toBeInTheDocument();
      expect(rejectBtn).toBeInTheDocument();
    });
  });

  it('does NOT show approve/reject buttons for approved match', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval({ status: 'approved' }));
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => {
      expect(screen.queryByText(/approve match/i)).not.toBeInTheDocument();
    });
  });

  it('calls approveMatch and shows success toast on approve', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval({ status: 'pending' }));
    mockAdminMatching.approveMatch.mockResolvedValue({ success: true });
    // Second call after reload
    mockAdminMatching.getApproval.mockResolvedValueOnce(makeApproval({ status: 'pending' }))
                                  .mockResolvedValueOnce(makeApproval({ status: 'approved' }));

    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const approveBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('approve'));
    if (approveBtn) fireEvent.click(approveBtn);

    await waitFor(() => {
      expect(mockAdminMatching.approveMatch).toHaveBeenCalledWith(42);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('opens reject modal when reject button is clicked', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval({ status: 'pending' }));
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const rejectBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('reject'));
    if (rejectBtn) fireEvent.click(rejectBtn);

    await waitFor(() => {
      const modal = document.querySelector('[role="dialog"]');
      expect(modal).toBeTruthy();
    });
  });

  it('calls rejectMatch with reason and shows success toast', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval({ status: 'pending' }));
    mockAdminMatching.rejectMatch.mockResolvedValue({ success: true });
    mockAdminMatching.getApproval.mockResolvedValueOnce(makeApproval({ status: 'pending' }))
                                  .mockResolvedValueOnce(makeApproval({ status: 'rejected' }));

    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => screen.getByText('Alice Smith'));

    // Open reject modal
    const rejectBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('reject'));
    if (rejectBtn) fireEvent.click(rejectBtn);

    // Type a reason in the textarea
    await waitFor(() => document.querySelector('[role="dialog"]'));
    const textarea = document.querySelector('textarea');
    if (textarea) fireEvent.change(textarea, { target: { value: 'Not a good fit' } });

    // Click confirm reject inside modal
    const confirmBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('reject') && !b.getAttribute('data-disabled')
    );
    // The confirm button has the same label; click the last one (inside modal footer)
    const allRejectBtns = screen.getAllByRole('button').filter((b) => b.textContent?.toLowerCase().includes('reject'));
    const modalRejectBtn = allRejectBtns[allRejectBtns.length - 1];
    if (modalRejectBtn) fireEvent.click(modalRejectBtn);

    await waitFor(() => {
      expect(mockAdminMatching.rejectMatch).toHaveBeenCalledWith(42, 'Not a good fit');
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows review details section when reviewed_at is present', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval({
      status: 'approved',
      reviewed_at: '2025-03-01T10:00:00Z',
      reviewer_name: 'Admin User',
      notes: 'Looks good',
    }));
    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => {
      expect(screen.getByText('Admin User')).toBeInTheDocument();
      expect(screen.getByText('Looks good')).toBeInTheDocument();
    });
  });

  it('shows error toast when approve API fails', async () => {
    mockAdminMatching.getApproval.mockResolvedValue(makeApproval({ status: 'pending' }));
    mockAdminMatching.approveMatch.mockResolvedValue({ success: false, error: 'Server error' });

    const { MatchDetail } = await import('./MatchDetail');
    render(<MatchDetail />);

    await waitFor(() => screen.getByText('Alice Smith'));

    const approveBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('approve'));
    if (approveBtn) fireEvent.click(approveBtn);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
