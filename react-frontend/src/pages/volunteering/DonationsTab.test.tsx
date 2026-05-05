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

vi.mock('framer-motion', () => framerMotionMock);

const stableT = (_key: string, fallback: string, _opts?: object) => fallback ?? _key;
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

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

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
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<DonationsTab />);
    const cards = screen.getAllByTestId('glass-card');
    const pulsingCards = cards.filter((c) => c.className?.includes('animate-pulse'));
    expect(pulsingCards.length).toBeGreaterThan(0);
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
