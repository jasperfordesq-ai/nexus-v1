// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DonationsTab
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { framerMotionMock } from '@/test/mocks';

vi.mock('@/lib/motion', () => framerMotionMock);

const donationTranslations: Record<string, string> = {
  'donations.heading': 'Donations',
  'donations.refresh': 'Refresh',
  'donations.donate': 'Donate',
  'donations.donate_with_card': 'Donate with card',
  'donations.load_error': 'Unable to load donations data.',
  'donations.try_again': 'Try Again',
  'donations.empty_title': 'No giving days or donations',
  'donations.empty_description': 'No giving activity is available right now.',
  'donations.make_donation': 'Make a donation',
  'donations.active_giving_days': 'Active Giving Days',
  'donations.stats.total_raised': 'Total Raised',
  'donations.stats.total_donors': 'Total Donors',
  'donations.stats.active_campaigns': 'Active Campaigns',
  'donations.day_status.active': 'Active',
  'donations.day_status.ended': 'Ended',
  'donations.progress_aria': '{{percent}}% funded',
  'donations.donors_count': '{{count}} donors',
  'donations.my_donations': 'My Donations',
  'donations.status.completed': 'Completed',
  'donations.payment_methods.card': 'Card',
};
const stableT = (key: string, fallbackOrOpts?: string | Record<string, unknown>, opts?: Record<string, unknown>) => {
  const fallback = typeof fallbackOrOpts === 'string' ? fallbackOrOpts : donationTranslations[key] ?? key;
  const vars = typeof fallbackOrOpts === 'object' ? fallbackOrOpts : opts;
  return fallback.replace(/\{\{(\w+)\}\}/g, (_, k) => String(vars?.[k] ?? ''));
};
vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: stableT,
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
  Trans: ({ children }: { children: React.ReactNode }) => children,
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
  })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
      {action}
    </div>
  ),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { DonationsTab } from './DonationsTab';
import { api } from '@/lib/api';

const mockGivingDay = {
  id: 1,
  title: 'Spring Giving Day',
  description: 'Support our community this spring!',
  goal_amount: 5000,
  raised_amount: 2500,
  donor_count: 25,
  starts_at: '2026-03-01T00:00:00Z',
  ends_at: '2026-03-31T23:59:59Z',
  status: 'active' as const,
};

const mockDonation = {
  id: 1,
  amount: 50,
  payment_method: 'card',
  message: 'Keep up the great work!',
  anonymous: false,
  status: 'completed' as const,
  giving_day_title: 'Spring Giving Day',
  created_at: '2026-03-15T10:00:00Z',
};

describe('DonationsTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the heading, Refresh, and Donate buttons', () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<DonationsTab />);
    expect(screen.getByText('Donations')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Refresh/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Donate' })).toBeInTheDocument();
  });

  it('shows empty state when no giving days or donations exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<DonationsTab />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('No giving days or donations')).toBeInTheDocument();
    });
  });

  it('shows loading skeleton while data is being fetched', () => {
    let resolveRequest!: (value: { success: boolean; data: never[] }) => void;
    vi.mocked(api.get).mockReturnValue(new Promise((resolve) => {
      resolveRequest = resolve;
    }));
    render(<DonationsTab />);
    // The loading skeleton renders inside a role="status" container.
    const loadingContainers = screen.getAllByRole('status');
    expect(loadingContainers.length).toBeGreaterThan(0);
    resolveRequest({ success: true, data: [] });
  });

  it('displays giving day cards with progress when data is loaded', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: [mockGivingDay] })
      .mockResolvedValueOnce({ success: true, data: [] });
    render(<DonationsTab />);
    await waitFor(() => {
      expect(screen.getByText('Spring Giving Day')).toBeInTheDocument();
    });
    expect(screen.getByText('Support our community this spring!')).toBeInTheDocument();
    expect(screen.getByText('Active Giving Days')).toBeInTheDocument();
  });

  it('displays stats cards when data is loaded', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: [mockGivingDay] })
      .mockResolvedValueOnce({ success: true, data: [] });
    render(<DonationsTab />);
    await waitFor(() => {
      expect(screen.getByText('Total Raised')).toBeInTheDocument();
      expect(screen.getByText('Total Donors')).toBeInTheDocument();
      expect(screen.getByText('Active Campaigns')).toBeInTheDocument();
    });
  });

  it('displays donation history when donations exist', async () => {
    vi.mocked(api.get)
      .mockResolvedValueOnce({ success: true, data: [] })
      .mockResolvedValueOnce({ success: true, data: [mockDonation] });
    render(<DonationsTab />);
    await waitFor(() => {
      expect(screen.getByText('My Donations')).toBeInTheDocument();
    });
    expect(screen.getByText('Keep up the great work!')).toBeInTheDocument();
  });

  it('shows error state and Try Again button when API fails', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));
    render(<DonationsTab />);
    await waitFor(() => {
      expect(screen.getByText('Unable to load donations data.')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('Try Again'));
    expect(tryAgainBtn).toBeTruthy();
  });

  it('retries loading when Try Again is clicked', async () => {
    let callCount = 0;
    vi.mocked(api.get).mockImplementation(() => {
      callCount++;
      if (callCount <= 2) return Promise.reject(new Error('fail'));
      return Promise.resolve({ success: true, data: [] });
    });
    render(<DonationsTab />);
    await waitFor(() => {
      expect(screen.getByText('Unable to load donations data.')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('Try Again'));
    fireEvent.click(tryAgainBtn!);
    await waitFor(() => {
      expect(callCount).toBeGreaterThanOrEqual(3);
    });
  });
});
