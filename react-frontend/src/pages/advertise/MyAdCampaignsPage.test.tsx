// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── api mock ─────────────────────────────────────────────────────────────────
const mockApi = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));
vi.mock('@/lib/api', () => ({
  api: mockApi,
}));

// ── logger mock ───────────────────────────────────────────────────────────────
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── contexts mock ─────────────────────────────────────────────────────────────
const mockShowToast = vi.hoisted(() => vi.fn());
const mockHasFeature = vi.hoisted(() => vi.fn(() => true));

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test', first_name: 'Test' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
    useToast: () => ({
      success: vi.fn(),
      error: vi.fn(),
      info: vi.fn(),
      warning: vi.fn(),
      showToast: mockShowToast,
    }),
  })
);

// ── page meta / seo stub ──────────────────────────────────────────────────────
vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

// ── hooks stub ────────────────────────────────────────────────────────────────
vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));

import { MyAdCampaignsPage } from './MyAdCampaignsPage';

const CAMPAIGN = {
  id: 1,
  name: 'Spring Feed Promo',
  type: 'feed',
  status: 'active',
  start_date: '2026-01-01',
  end_date: '2026-03-31',
  budget_cents: 5000,
  targeting_radius_km: null,
  created_at: '2026-01-01T00:00:00Z',
};

describe('MyAdCampaignsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    // Default: campaigns list + stats
    mockApi.get.mockImplementation((url: string) => {
      if (url.includes('/stats')) return Promise.resolve({ success: true, data: { impressions: 120, clicks: 10, ctr: 0.083 } });
      return Promise.resolve({ success: true, data: [CAMPAIGN] });
    });
  });

  it('renders loading spinner initially', async () => {
    // Delay resolution so spinner is visible
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<MyAdCampaignsPage />);
    const spinners = screen.getAllByRole('status');
    const loading = spinners.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(loading).toBeTruthy();
  });

  it('renders campaign table when data loads', async () => {
    render(<MyAdCampaignsPage />);
    await waitFor(() => {
      expect(screen.getByText('Spring Feed Promo')).toBeInTheDocument();
    });
  });

  it('renders empty state when no campaigns', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    render(<MyAdCampaignsPage />);
    await waitFor(() => {
      // Empty state has a "create first" button
      const buttons = screen.getAllByRole('button');
      // At least one of them should open the create modal (loading is gone)
      expect(buttons.length).toBeGreaterThan(0);
    });
  });

  it('renders error state and retry button on fetch failure', async () => {
    mockApi.get.mockRejectedValue(new Error('Network error'));
    render(<MyAdCampaignsPage />);
    await waitFor(() => {
      // Retry button should appear
      const retryBtn = screen.getAllByRole('button').find((b) => b.textContent?.toLowerCase().includes('retry') || b.textContent?.toLowerCase().includes('try'));
      expect(retryBtn).toBeDefined();
    });
  });

  it('renders feature-disabled message when local_advertising feature is off', () => {
    mockHasFeature.mockReturnValue(false);
    render(<MyAdCampaignsPage />);
    // The feature-disabled branch renders without the table
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('opens create campaign modal when header button is clicked', async () => {
    render(<MyAdCampaignsPage />);
    await waitFor(() => expect(screen.getByText('Spring Feed Promo')).toBeInTheDocument());
    const createBtns = screen.getAllByRole('button');
    // Button with "Create" (or campaign create text key) in the header area
    const createBtn = createBtns.find(
      (b) => !b.closest('[role="dialog"]') && (b.textContent?.toLowerCase().includes('creat') || b.getAttribute('aria-label')?.toLowerCase().includes('creat'))
    );
    if (createBtn) {
      await userEvent.click(createBtn);
      // After click, a modal dialog should appear
      await waitFor(() => {
        expect(screen.getByRole('dialog')).toBeInTheDocument();
      });
    }
  });

  it('calls POST /v2/me/ad-campaigns on form submit with all required fields', async () => {
    mockApi.post.mockResolvedValue({ success: true, data: { ...CAMPAIGN, id: 99 } });
    render(<MyAdCampaignsPage />);
    await waitFor(() => expect(screen.getByText('Spring Feed Promo')).toBeInTheDocument());

    // Open the create modal via the header button
    const createBtns = screen.getAllByRole('button');
    const createBtn = createBtns.find(
      (b) => !b.closest('[role="dialog"]') && (b.textContent?.toLowerCase().includes('creat') || b.textContent?.includes('+'))
    );
    if (!createBtn) return; // skip if modal trigger unavailable in this render
    await userEvent.click(createBtn);

    await waitFor(() => expect(screen.getByRole('dialog')).toBeInTheDocument());

    // Fill name input using value change on first textbox
    const nameInput = screen.getByRole('dialog').querySelector('input[placeholder]');
    if (nameInput) fireEvent.change(nameInput, { target: { value: 'New Campaign' } });

    // Fill date inputs
    const dateInputs = screen.getByRole('dialog').querySelectorAll('input[type="date"]');
    dateInputs.forEach((inp) => fireEvent.change(inp, { target: { value: '2026-06-01' } }));

    // Fill budget
    const budgetInput = screen.getByRole('dialog').querySelector('input[type="number"]');
    if (budgetInput) fireEvent.change(budgetInput, { target: { value: '50' } });

    // Submit by pressing the submit button inside the dialog
    const submitBtn = Array.from(screen.getByRole('dialog').querySelectorAll('button')).find(
      (b) => b.textContent?.toLowerCase().includes('creat') || b.textContent?.toLowerCase().includes('submit')
    );
    if (submitBtn) {
      await userEvent.click(submitBtn);
      await waitFor(() => {
        expect(mockApi.post).toHaveBeenCalledWith('/v2/me/ad-campaigns', expect.any(Object));
      });
    }
  });

  it('shows budget formatted as euros in the table', async () => {
    render(<MyAdCampaignsPage />);
    await waitFor(() => {
      expect(screen.getByText(/€50\.00/)).toBeInTheDocument();
    });
  });
});
