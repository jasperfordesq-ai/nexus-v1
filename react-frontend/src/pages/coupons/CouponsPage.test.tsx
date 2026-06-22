// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/contexts', () => createMockContexts());
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import CouponsPage from './CouponsPage';

const COUPONS = [
  {
    id: 1,
    seller_id: 10,
    code: 'SAVE10',
    title: 'Ten Percent Off',
    description: 'Save 10% on your order',
    discount_type: 'percent' as const,
    discount_value: 10,
    min_order_cents: null,
    valid_until: '2026-12-31T23:59:59Z',
    status: 'active',
  },
  {
    id: 2,
    seller_id: 10,
    code: 'FIVE-OFF',
    title: 'Five Euro Off',
    description: null,
    discount_type: 'fixed' as const,
    discount_value: 500,
    min_order_cents: 1000,
    valid_until: null,
    status: 'active',
  },
  {
    id: 3,
    seller_id: 11,
    code: 'BOGO2024',
    title: 'Buy One Get One',
    description: 'Double up!',
    discount_type: 'bogo' as const,
    discount_value: 0,
    min_order_cents: null,
    valid_until: null,
    status: 'active',
  },
];

describe('CouponsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows a loading spinner while fetching', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    const { container } = render(<CouponsPage />);
    // outer div has aria-busy="true"; HeroUI Spinner also emits role=status internally
    expect(container.querySelector('[aria-busy="true"]')).toBeInTheDocument();
  });

  it('shows empty state when no coupons are returned', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: [] } });
    const { container } = render(<CouponsPage />);
    await waitFor(() => expect(container.querySelector('[aria-busy="true"]')).not.toBeInTheDocument());
    // Empty state heading: "No active coupons available" (coupon.no_coupons translation)
    expect(screen.getByText(/no.*coupon|coupon.*available/i)).toBeInTheDocument();
  });

  it('renders coupon titles after successful fetch', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: COUPONS } });
    render(<CouponsPage />);
    await waitFor(() => expect(screen.getByText('Ten Percent Off')).toBeInTheDocument());
    expect(screen.getByText('Five Euro Off')).toBeInTheDocument();
    expect(screen.getByText('Buy One Get One')).toBeInTheDocument();
  });

  it('renders coupon codes', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: COUPONS } });
    render(<CouponsPage />);
    await waitFor(() => expect(screen.getByText('SAVE10')).toBeInTheDocument());
    expect(screen.getByText('FIVE-OFF')).toBeInTheDocument();
    expect(screen.getByText('BOGO2024')).toBeInTheDocument();
  });

  it('shows percent discount chip', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: [COUPONS[0]] } });
    render(<CouponsPage />);
    // Chip shows "10% off" (coupon.type_percent translation appended to value)
    // Use chip-specific data-slot selector to avoid matching the description text
    await waitFor(() => {
      const chipLabel = document.querySelector('[data-slot="chip-label"]');
      expect(chipLabel?.textContent).toMatch(/10%/);
    });
  });

  it('shows fixed discount chip formatted as euro amount', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: [COUPONS[1]] } });
    render(<CouponsPage />);
    await waitFor(() => expect(screen.getByText(/5\.00/)).toBeInTheDocument());
  });

  it('shows bogo label', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: [COUPONS[2]] } });
    render(<CouponsPage />);
    await waitFor(() => expect(screen.getByText(/bogo|type_bogo/i)).toBeInTheDocument());
  });

  it('renders valid_until date when present', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: [COUPONS[0]] } });
    render(<CouponsPage />);
    await waitFor(() => expect(screen.getByText(/valid_until|2026/i)).toBeInTheDocument());
  });

  it('renders coupon description when present', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: [COUPONS[0]] } });
    render(<CouponsPage />);
    await waitFor(() => expect(screen.getByText('Save 10% on your order')).toBeInTheDocument());
  });

  it('renders a details link for each coupon', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: COUPONS } });
    render(<CouponsPage />);
    await waitFor(() => expect(screen.getAllByRole('link')).toHaveLength(3));
  });

  it('handles fetch error gracefully without crashing', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));
    const { container } = render(<CouponsPage />);
    await waitFor(() => expect(container.querySelector('[aria-busy="true"]')).not.toBeInTheDocument());
    // Page renders without throwing
    expect(document.body).toBeTruthy();
  });

  it('calls the correct API endpoint', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { items: [] } });
    render(<CouponsPage />);
    await waitFor(() => {
      expect(vi.mocked(api.get)).toHaveBeenCalledWith('/v2/coupons');
    });
  });
});
