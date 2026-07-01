// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock adminApi ────────────────────────────────────────────────────────────
const { mockAdminBroker } = vi.hoisted(() => ({
  mockAdminBroker: {
    showExchange: vi.fn(),
    approveExchange: vi.fn(),
    rejectExchange: vi.fn(),
  },
}));

vi.mock('@/admin/api/adminApi', () => ({
  adminBroker: mockAdminBroker,
}));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/serverTime', () => ({
  formatServerDateTime: (s: string) => s,
}));

// ─── Routing ─────────────────────────────────────────────────────────────────
vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useParams: () => ({ id: '42' }),
    useNavigate: () => vi.fn(),
    Link: ({ to, children }: { to: string; children: React.ReactNode }) => <a href={to}>{children}</a>,
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeExchange = (overrides = {}) => ({
  id: 42,
  requester_id: 1,
  requester_name: 'Alice Requester',
  requester_email: 'alice@example.com',
  provider_id: 2,
  provider_name: 'Bob Provider',
  provider_email: 'bob@example.com',
  listing_id: 7,
  listing_title: 'Garden Help',
  status: 'pending_broker',
  broker_notes: null,
  broker_conditions: null,
  final_hours: 2,
  created_at: '2026-01-15T10:00:00Z',
  ...overrides,
});

const makeHistory = (overrides = {}) => ({
  id: 1,
  exchange_id: 42,
  actor_name: 'Admin User',
  action: 'created',
  notes: null,
  created_at: '2026-01-15T10:00:00Z',
  ...overrides,
});

const makeDetail = (exchangeOverrides = {}, historyItems = [makeHistory()], riskTag = null) => ({
  exchange: makeExchange(exchangeOverrides),
  history: historyItems,
  risk_tag: riskTag,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('ExchangeDetailPage (ExchangeDetail)', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockAdminBroker.showExchange.mockResolvedValue({
      success: true,
      data: makeDetail(),
    });
  });

  it('shows detail skeleton initially', async () => {
    mockAdminBroker.showExchange.mockImplementationOnce(() => new Promise(() => {}));
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    const skeleton = document.querySelector('[aria-busy="true"]');
    expect(skeleton).toBeTruthy();
  });

  it('calls showExchange with the numeric id from useParams', async () => {
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => expect(mockAdminBroker.showExchange).toHaveBeenCalledWith(42));
  });

  it('renders requester name after load', async () => {
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('Alice Requester')).toBeInTheDocument();
    });
  });

  it('renders provider name after load', async () => {
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('Bob Provider')).toBeInTheDocument();
    });
  });

  it('renders listing title in the page shell description', async () => {
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('Garden Help')).toBeInTheDocument();
    });
  });

  it('highlights the current stage in the lifecycle pipeline', async () => {
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      const current = document.querySelector('[aria-current="step"]');
      expect(current).toBeTruthy();
      expect(current?.textContent).toContain('Pending Broker Approval');
    });
  });

  it('marks a cancelled exchange as a terminal pipeline stage and hides actions', async () => {
    mockAdminBroker.showExchange.mockResolvedValue({
      success: true,
      data: makeDetail({ status: 'cancelled' }),
    });
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      const current = document.querySelector('[aria-current="step"]');
      expect(current?.textContent).toContain('Cancelled');
    });
    expect(screen.queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Reject' })).not.toBeInTheDocument();
  });

  it('approves a pending_broker exchange through the action modal', async () => {
    mockAdminBroker.approveExchange.mockResolvedValue({ success: true });
    const user = userEvent.setup();
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => expect(screen.getByText('Alice Requester')).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: 'Approve' }));
    await waitFor(() => expect(screen.getByText('Approve Exchange')).toBeInTheDocument());

    const approveButtons = screen.getAllByRole('button', { name: 'Approve' });
    await user.click(approveButtons[approveButtons.length - 1]);

    await waitFor(() => {
      expect(mockAdminBroker.approveExchange).toHaveBeenCalledWith(42, undefined);
    });
  });

  it('renders a not-found state when API returns failure', async () => {
    mockAdminBroker.showExchange.mockResolvedValue({ success: false, data: null });
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('Exchange not found.')).toBeInTheDocument();
      const backLink = screen.getAllByRole('link').find((el) =>
        el.getAttribute('href')?.includes('exchanges')
      );
      expect(backLink).toBeDefined();
    });
  });

  it('renders an honest error state with a retry button when the API throws', async () => {
    mockAdminBroker.showExchange.mockRejectedValue(new Error('network'));
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('Failed to load this exchange')).toBeInTheDocument();
      expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument();
      const backLink = screen.getAllByRole('link').find((el) =>
        el.getAttribute('href')?.includes('exchanges')
      );
      expect(backLink).toBeDefined();
    });
  });

  it('refetches the exchange when retry is pressed after a failure', async () => {
    mockAdminBroker.showExchange
      .mockRejectedValueOnce(new Error('network'))
      .mockResolvedValueOnce({ success: true, data: makeDetail() });
    const user = userEvent.setup();
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument());

    await user.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => {
      expect(mockAdminBroker.showExchange).toHaveBeenCalledTimes(2);
      expect(screen.getByText('Alice Requester')).toBeInTheDocument();
    });
  });

  it('shows risk tag section when risk_tag is present', async () => {
    const riskTag = {
      id: 5,
      listing_id: 7,
      risk_level: 'high' as const,
      risk_category: 'physical',
      risk_notes: 'Requires PPE',
      requires_approval: true,
      insurance_required: false,
      dbs_required: false,
      created_at: '2026-01-10T00:00:00Z',
    };
    mockAdminBroker.showExchange.mockResolvedValue({
      success: true,
      data: makeDetail({}, [makeHistory()], riskTag),
    });
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('Requires PPE')).toBeInTheDocument();
      expect(screen.getByText('Broker approval required')).toBeInTheDocument();
    });
  });

  it('shows broker notes when present', async () => {
    mockAdminBroker.showExchange.mockResolvedValue({
      success: true,
      data: makeDetail({ broker_notes: 'Please verify credentials' }),
    });
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('Please verify credentials')).toBeInTheDocument();
    });
  });

  it('shows history timeline entry with actor name', async () => {
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      // The component renders "by {name}" via the translation key exchanges.detail_history_by
      expect(screen.getByText(/Admin User/)).toBeInTheDocument();
    });
  });

  it('shows the "no history" empty state when history array is empty', async () => {
    mockAdminBroker.showExchange.mockResolvedValue({
      success: true,
      data: makeDetail({}, []),
    });
    const { default: ExchangeDetail } = await import('./ExchangeDetailPage');
    render(<ExchangeDetail />);
    await waitFor(() => {
      expect(screen.getByText('No history available.')).toBeInTheDocument();
    });
  });
});
