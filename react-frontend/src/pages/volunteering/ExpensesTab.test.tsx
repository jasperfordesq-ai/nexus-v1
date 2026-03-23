// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ExpensesTab
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
    get: vi.fn().mockResolvedValue({ success: true, data: { items: [], has_more: false } }),
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

import { ExpensesTab } from './ExpensesTab';
import { api } from '@/lib/api';

const mockExpense = {
  id: 1,
  expense_type: 'travel' as const,
  amount: '45.50',
  currency: 'EUR',
  description: 'Bus fare to volunteer site',
  status: 'approved' as const,
  submitted_at: '2026-03-10T10:00:00Z',
  reviewed_at: '2026-03-12T10:00:00Z',
  review_notes: null,
  paid_at: null,
  payment_reference: null,
};

const mockPaidExpense = {
  id: 2,
  expense_type: 'meals' as const,
  amount: '12.00',
  currency: 'EUR',
  description: 'Lunch during event',
  status: 'paid' as const,
  submitted_at: '2026-03-08T10:00:00Z',
  reviewed_at: '2026-03-09T10:00:00Z',
  review_notes: 'Approved for reimbursement',
  paid_at: '2026-03-15T10:00:00Z',
  payment_reference: 'PAY-123',
};

describe('ExpensesTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the heading and Submit Expense button', () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { items: [], has_more: false } });
    render(<ExpensesTab />);
    expect(screen.getByText('My Expenses')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Submit Expense/i })).toBeInTheDocument();
  });

  it('shows empty state when no expenses exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: { items: [], has_more: false } });
    render(<ExpensesTab />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('No expenses yet')).toBeInTheDocument();
    });
  });

  it('shows loading skeleton while data is being fetched', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<ExpensesTab />);
    const cards = screen.getAllByTestId('glass-card');
    const pulsingCards = cards.filter((c) => c.className?.includes('animate-pulse'));
    expect(pulsingCards.length).toBeGreaterThan(0);
  });

  it('displays expense items when data is loaded', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [mockExpense, mockPaidExpense], has_more: false },
    });
    render(<ExpensesTab />);
    await waitFor(() => {
      expect(screen.getByText('Bus fare to volunteer site')).toBeInTheDocument();
    });
    expect(screen.getByText('Lunch during event')).toBeInTheDocument();
  });

  it('displays stats cards when expenses exist', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [mockExpense, mockPaidExpense], has_more: false },
    });
    render(<ExpensesTab />);
    await waitFor(() => {
      expect(screen.getByText('Total Claimed')).toBeInTheDocument();
      expect(screen.getByText('Approved')).toBeInTheDocument();
      expect(screen.getByText('Paid')).toBeInTheDocument();
    });
  });

  it('shows review notes when present', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [mockPaidExpense], has_more: false },
    });
    render(<ExpensesTab />);
    await waitFor(() => {
      expect(document.body.textContent).toContain('Approved for reimbursement');
    });
  });

  it('shows error state and Try Again button when API fails', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });
    render(<ExpensesTab />);
    await waitFor(() => {
      expect(screen.getByText('Unable to load expenses. Please try again.')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('Try Again'));
    expect(tryAgainBtn).toBeTruthy();
  });

  it('retries loading when Try Again is clicked', async () => {
    let callCount = 0;
    vi.mocked(api.get).mockImplementation(() => {
      callCount++;
      if (callCount === 1) return Promise.resolve({ success: false, data: null });
      return Promise.resolve({ success: true, data: { items: [], has_more: false } });
    });
    render(<ExpensesTab />);
    await waitFor(() => {
      expect(screen.getByText('Unable to load expenses. Please try again.')).toBeInTheDocument();
    });
    const buttons = screen.getAllByRole('button');
    const tryAgainBtn = buttons.find((btn) => btn.textContent?.includes('Try Again'));
    fireEvent.click(tryAgainBtn!);
    await waitFor(() => {
      expect(callCount).toBe(2);
    });
  });
});
