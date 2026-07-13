// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

const { mockApi, mockToast } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/contexts', () => createMockContexts({
  useToast: () => mockToast,
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (path: string) => `/test${path}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
}));

import { MarketplaceCasesPage } from './MarketplaceCasesPage';

const receivedReport = {
  id: 11,
  marketplace_listing_id: 5,
  reason: 'unsafe',
  description: 'Unsafe item',
  status: 'received',
  listing: { id: 5, title: 'Power tool' },
  reporter: { id: 3, name: 'Alice Reporter' },
  created_at: '2026-07-12T10:00:00Z',
};

const openDispute = {
  id: 21,
  order_id: 90,
  reason: 'item_not_received',
  description: 'Not delivered',
  status: 'open',
  order: { id: 90, order_number: 'MKT-90' },
  opened_by_user: { id: 4, name: 'Bob Buyer' },
  created_at: '2026-07-12T11:00:00Z',
};

function installListResponses(report = receivedReport, dispute = openDispute) {
  mockApi.get.mockImplementation((endpoint: string) => {
    if (endpoint.startsWith('/v2/admin/marketplace/reports')) {
      return Promise.resolve({ success: true, data: { items: [report], total: 1, page: 1, per_page: 20 } });
    }
    return Promise.resolve({ success: true, data: { items: [dispute], total: 1, page: 1, per_page: 20 } });
  });
}

describe('MarketplaceCasesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    installListResponses();
  });

  it('loads both report and dispute queues and acknowledges a report', async () => {
    const user = userEvent.setup();
    mockApi.post.mockResolvedValue({ success: true });
    render(<MarketplaceCasesPage />);

    expect(await screen.findByText('Power tool')).toBeInTheDocument();
    expect(mockApi.get).toHaveBeenCalledWith('/v2/admin/marketplace/disputes?page=1&per_page=20');
    await user.click(screen.getByRole('button', { name: /acknowledge/i }));

    await waitFor(() => expect(mockApi.post).toHaveBeenCalledWith('/v2/admin/marketplace/reports/11/acknowledge'));
    expect(mockToast.success).toHaveBeenCalled();
  });

  it('resolves an appealed report through the appeal-specific endpoint', async () => {
    const user = userEvent.setup();
    installListResponses({ ...receivedReport, id: 12, status: 'appealed', appeal_text: 'Please review again' });
    mockApi.put.mockResolvedValue({ success: true });
    render(<MarketplaceCasesPage />);

    await user.click(await screen.findByRole('button', { name: /resolve appeal/i }));
    await user.type(screen.getByLabelText(/decision explanation/i), 'The appeal evidence was reviewed.');
    const resolveButtons = screen.getAllByRole('button', { name: /^resolve$/i });
    await user.click(resolveButtons.at(-1)!);

    await waitFor(() => expect(mockApi.put).toHaveBeenCalledWith(
      '/v2/admin/marketplace/reports/12/resolve-appeal',
      { action_taken: 'none', resolution_reason: 'The appeal evidence was reviewed.' },
    ));
  });

  it('resolves an order dispute with the backend resolution value', async () => {
    const user = userEvent.setup();
    mockApi.put.mockResolvedValue({ success: true });
    render(<MarketplaceCasesPage />);

    await user.click(await screen.findByRole('tab', { name: /order disputes/i }));
    await user.click(await screen.findByRole('button', { name: /^resolve$/i }));
    await user.type(screen.getByLabelText(/resolution notes/i), 'The buyer did not receive the order.');
    const resolveButtons = screen.getAllByRole('button', { name: /^resolve$/i });
    await user.click(resolveButtons.at(-1)!);

    await waitFor(() => expect(mockApi.put).toHaveBeenCalledWith(
      '/v2/admin/marketplace/disputes/21/resolve',
      { resolution: 'buyer', resolution_notes: 'The buyer did not receive the order.' },
    ));
  });

  it('never renders legacy non-http evidence as an executable admin link', async () => {
    const user = userEvent.setup();
    installListResponses({
      ...receivedReport,
      evidence_urls: ['javascript:alert(1)', 'https://evidence.example/item'],
    });
    render(<MarketplaceCasesPage />);

    await user.click(await screen.findByRole('button', { name: /^resolve$/i }));

    const unsafe = screen.getByText('javascript:alert(1)');
    expect(unsafe.closest('a')).toBeNull();
    expect(screen.getByRole('link', { name: 'https://evidence.example/item' }))
      .toHaveAttribute('href', 'https://evidence.example/item');
  });

  it('formats zero-decimal dispute currencies without invented decimals', async () => {
    const user = userEvent.setup();
    installListResponses(receivedReport, {
      ...openDispute,
      order: { ...openDispute.order, currency: 'JPY', total_price: 1000 },
    });
    render(<MarketplaceCasesPage />);

    await user.click(await screen.findByRole('tab', { name: /order disputes/i }));
    await user.click(await screen.findByRole('button', { name: /^resolve$/i }));

    expect(screen.getByText(/1,000/)).not.toHaveTextContent('1,000.00');
  });
});
