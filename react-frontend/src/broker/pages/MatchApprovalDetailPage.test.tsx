// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import { HelmetProvider } from 'react-helmet-async';
import { createMockContexts } from '@/test/mock-contexts';

const mockGetApproval = vi.hoisted(() => vi.fn());
const mockApproveMatch = vi.hoisted(() => vi.fn());
const mockRejectMatch = vi.hoisted(() => vi.fn());

vi.mock('@/admin/api/adminApi', () => ({
  adminMatching: {
    getApproval: mockGetApproval,
    approveMatch: mockApproveMatch,
    rejectMatch: mockRejectMatch,
  },
}));

const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));

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

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

import { MatchApprovalDetailPage } from './MatchApprovalDetailPage';

const DETAIL = {
  id: 11,
  user_1_id: 1,
  user_1_name: 'Alice Member',
  user_1_email: 'alice@example.com',
  user_1_avatar: null,
  user_1_bio: 'Loves gardening',
  user_1_location: 'Northside',
  user_2_id: 2,
  user_2_name: 'Bob Owner',
  user_2_email: 'bob@example.com',
  user_2_avatar: null,
  user_2_bio: null,
  user_2_location: null,
  listing_id: 5,
  listing_title: 'Garden help wanted',
  listing_type: 'request',
  listing_status: 'active',
  listing_description: 'Weekly weeding',
  category_name: 'Gardening',
  match_score: 91,
  match_type: 'one_way',
  match_reasons: ['Category match', 'Nearby'],
  distance_km: 2.4,
  status: 'pending' as const,
  notes: null,
  created_at: '2026-06-30T10:00:00Z',
  reviewed_at: null,
  reviewer_id: null,
  reviewer_name: null,
};

function renderPage(id = '11') {
  return render(
    <HelmetProvider>
      <MemoryRouter initialEntries={[`/test/broker/match-approvals/${id}`]}>
        <Routes>
          <Route path="/test/broker/match-approvals/:id" element={<MatchApprovalDetailPage />} />
        </Routes>
      </MemoryRouter>
    </HelmetProvider>
  );
}

describe('MatchApprovalDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockGetApproval.mockResolvedValue({ success: true, data: DETAIL });
  });

  it('renders the score gauge, quality label and match reasons', async () => {
    renderPage();
    await waitFor(() => {
      expect(screen.getByText('91%')).toBeInTheDocument();
      expect(screen.getByText('Excellent match')).toBeInTheDocument();
      expect(screen.getByText('Category match')).toBeInTheDocument();
      expect(screen.getByText('Nearby')).toBeInTheDocument();
    });
  });

  it('renders both party cards and the associated listing', async () => {
    renderPage();
    await waitFor(() => {
      expect(screen.getByText('Alice Member')).toBeInTheDocument();
      expect(screen.getByText('Bob Owner')).toBeInTheDocument();
      expect(screen.getByText('Garden help wanted')).toBeInTheDocument();
      expect(screen.getByText('Gardening')).toBeInTheDocument();
    });
  });

  it('approves a pending match from the decision bar', async () => {
    mockApproveMatch.mockResolvedValue({ success: true });
    const user = userEvent.setup();
    renderPage();
    await waitFor(() => expect(screen.getByText('91%')).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: 'Approve Match' }));

    await waitFor(() => {
      expect(mockApproveMatch).toHaveBeenCalledWith(11);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows review details instead of the decision bar once reviewed', async () => {
    mockGetApproval.mockResolvedValue({
      success: true,
      data: {
        ...DETAIL,
        status: 'rejected',
        reviewed_at: '2026-07-01T09:00:00Z',
        reviewer_name: 'Rita Broker',
        notes: 'Too far apart',
      },
    });
    renderPage();
    await waitFor(() => {
      expect(screen.getByText('Review Details')).toBeInTheDocument();
      expect(screen.getByText('Rita Broker')).toBeInTheDocument();
      expect(screen.getByText('Too far apart')).toBeInTheDocument();
    });
    expect(screen.queryByRole('button', { name: 'Approve Match' })).not.toBeInTheDocument();
  });

  it('renders an honest not-found state when the load fails', async () => {
    mockGetApproval.mockResolvedValue({ success: false, error: 'Not found' });
    renderPage('999');
    await waitFor(() => {
      expect(screen.getByText('Match not found')).toBeInTheDocument();
    });
  });
});
