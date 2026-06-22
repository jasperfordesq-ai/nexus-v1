// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── Mocks ──────────────────────────────────────────────────────────────────────
vi.mock('../../api/adminApi', () => ({
  adminGamification: {
    listCampaigns: vi.fn(),
    deleteCampaign: vi.fn(),
    updateCampaign: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };
vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// useNavigate spy
const mockNavigate = vi.fn();
vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return { ...actual, useNavigate: () => mockNavigate };
});

import { adminGamification } from '../../api/adminApi';
import { CampaignList } from './CampaignList';
import type { Campaign } from '../../api/types';

const MOCK_CAMPAIGNS: Campaign[] = [
  {
    id: 1,
    name: 'Spring Campaign',
    description: 'Spring desc',
    status: 'active',
    badge_name: 'Spring Badge',
    target_audience: 'all_members',
    start_date: null,
    end_date: null,
    total_awards: 42,
    created_at: '2026-01-01T00:00:00Z',
  },
  {
    id: 2,
    name: 'Draft Campaign',
    description: 'Draft desc',
    status: 'draft',
    badge_name: 'Draft Badge',
    target_audience: 'new_members',
    start_date: null,
    end_date: null,
    total_awards: 0,
    created_at: '2026-02-01T00:00:00Z',
  },
];

describe('CampaignList', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a loading spinner while fetching', () => {
    // Never resolves during this test
    vi.mocked(adminGamification.listCampaigns).mockReturnValue(new Promise(() => {}));
    render(<CampaignList />);
    // DataTable shows a spinner while isLoading — role=status aria-busy=true
    const spinners = screen.getAllByRole('status');
    const busySpinner = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busySpinner).toBeInTheDocument();
  });

  it('renders campaign names after loading', async () => {
    vi.mocked(adminGamification.listCampaigns).mockResolvedValue({
      success: true,
      data: MOCK_CAMPAIGNS,
    });
    render(<CampaignList />);
    await waitFor(() => {
      expect(screen.getByText('Spring Campaign')).toBeInTheDocument();
    });
    expect(screen.getByText('Draft Campaign')).toBeInTheDocument();
  });

  it('renders EmptyState when campaigns array is empty', async () => {
    vi.mocked(adminGamification.listCampaigns).mockResolvedValue({
      success: true,
      data: [],
    });
    render(<CampaignList />);
    await waitFor(() => {
      // EmptyState renders the create campaign action button
      expect(screen.getByText(/create campaign/i)).toBeInTheDocument();
    });
  });

  it('shows error toast when API call fails', async () => {
    vi.mocked(adminGamification.listCampaigns).mockResolvedValue({
      success: false,
      data: null,
    });
    render(<CampaignList />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('opens delete confirmation modal when delete action is triggered', async () => {
    vi.mocked(adminGamification.listCampaigns).mockResolvedValue({
      success: true,
      data: MOCK_CAMPAIGNS,
    });
    render(<CampaignList />);
    await waitFor(() => {
      expect(screen.getByText('Spring Campaign')).toBeInTheDocument();
    });

    // Open the actions dropdown for the first campaign
    const actionButtons = screen.getAllByRole('button', {
      name: /campaign actions/i,
    });
    await userEvent.click(actionButtons[0]);

    // Click the Delete item in the dropdown (exact match to avoid column header)
    const deleteItems = await screen.findAllByText(/^delete$/i);
    await userEvent.click(deleteItems[0]);

    // ConfirmModal should be present — it renders as a modal dialog
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('calls deleteCampaign API and reloads on confirmation', async () => {
    vi.mocked(adminGamification.listCampaigns).mockResolvedValue({
      success: true,
      data: MOCK_CAMPAIGNS,
    });
    vi.mocked(adminGamification.deleteCampaign).mockResolvedValue({ success: true });

    render(<CampaignList />);
    await waitFor(() => {
      expect(screen.getByText('Spring Campaign')).toBeInTheDocument();
    });

    // Open actions dropdown
    const actionButtons = screen.getAllByRole('button', { name: /campaign actions/i });
    await userEvent.click(actionButtons[0]);

    const deleteItems = await screen.findAllByText(/^delete$/i);
    await userEvent.click(deleteItems[0]);

    // Wait for ConfirmModal to appear — find a dialog role or modal content
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    // Press the confirm button inside the dialog
    const dialog = screen.getByRole('dialog');
    const confirmBtn = Array.from(dialog.querySelectorAll('button')).find(
      (b) => /^delete$/i.test(b.textContent?.trim() ?? '')
    );
    expect(confirmBtn).toBeInTheDocument();
    await userEvent.click(confirmBtn!);

    await waitFor(() => {
      expect(adminGamification.deleteCampaign).toHaveBeenCalledWith(1);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });
});
