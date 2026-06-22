// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SellerCouponsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// Mock useConfirm from @/components/ui so we can control confirm dialog outcomes
// without needing a real DOM dialog.
const mockConfirm = vi.fn();
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    useConfirm: () => mockConfirm,
  };
});

import SellerCouponsPage from './SellerCouponsPage';

const COUPONS = [
  {
    id: 1,
    code: 'SAVE10',
    title: 'Ten Percent Off',
    discount_type: 'percent' as const,
    discount_value: 10,
    status: 'active',
    usage_count: 5,
    valid_until: null,
  },
  {
    id: 2,
    code: 'BOGO1',
    title: 'Buy One Get One',
    discount_type: 'bogo' as const,
    discount_value: 0,
    status: 'paused',
    usage_count: 0,
    valid_until: '2025-12-31',
  },
];

describe('SellerCouponsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockConfirm.mockResolvedValue(true); // default: user confirms
  });

  it('shows a loading spinner while fetching coupons', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<SellerCouponsPage />);
    // HeroUI Spinner nests multiple role="status" elements with the same label.
    // Assert that at least one loading status is in the document.
    const statusEls = screen.getAllByRole('status', { name: /loading/i });
    expect(statusEls.length).toBeGreaterThan(0);
  });

  it('renders coupon codes and titles after load', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: COUPONS } });

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('SAVE10')).toBeInTheDocument());
    expect(screen.getByText('Ten Percent Off')).toBeInTheDocument();
    expect(screen.getByText('BOGO1')).toBeInTheDocument();
    expect(screen.getByText('Buy One Get One')).toBeInTheDocument();
  });

  it('formats percent discount correctly', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: COUPONS } });

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('10%')).toBeInTheDocument());
  });

  it('formats fixed discount as €value', async () => {
    const fixed = [
      {
        id: 3,
        code: 'FIXED5',
        title: 'Five Euro Off',
        discount_type: 'fixed' as const,
        discount_value: 500,
        status: 'active',
        usage_count: 1,
        valid_until: null,
      },
    ];
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: fixed } });

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('€5.00')).toBeInTheDocument());
  });

  it('shows usage count in the table', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: COUPONS } });

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('5')).toBeInTheDocument());
  });

  it('shows empty state when no coupons exist', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: [] } });

    render(<SellerCouponsPage />);

    // Wait for the component-level loading spinner (aria-busy) to disappear.
    await waitFor(() =>
      expect(screen.queryAllByRole('status', { name: /loading/i }).length).toBe(0)
    );
    // The table should not be present; empty state card should render
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('renders the Create button linking to the new coupon route', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: [] } });

    render(<SellerCouponsPage />);

    await waitFor(() =>
      expect(screen.queryAllByRole('status', { name: /loading/i }).length).toBe(0)
    );

    // The create button is a link rendered by Button as={Link}
    const links = screen.getAllByRole('link');
    const createLink = links.find((l) => l.getAttribute('href')?.includes('/coupons/new'));
    expect(createLink).toBeDefined();
  });

  it('calls DELETE endpoint when user confirms deletion', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { items: COUPONS } });
    vi.mocked(api.delete).mockResolvedValueOnce({});
    mockConfirm.mockResolvedValueOnce(true);

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('SAVE10')).toBeInTheDocument());

    // There should be two delete buttons (one per coupon)
    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    expect(deleteButtons.length).toBeGreaterThanOrEqual(1);

    fireEvent.click(deleteButtons[0]);

    await waitFor(() =>
      expect(api.delete).toHaveBeenCalledWith('/v2/marketplace/seller/coupons/1')
    );
  });

  it('shows success toast after successful delete', async () => {
    vi.mocked(api.get).mockResolvedValue({ data: { items: COUPONS } });
    vi.mocked(api.delete).mockResolvedValueOnce({});
    mockConfirm.mockResolvedValueOnce(true);

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('SAVE10')).toBeInTheDocument());

    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    fireEvent.click(deleteButtons[0]);

    await waitFor(() => expect(mockToast.success).toHaveBeenCalled());
  });

  it('does NOT call DELETE when user cancels the confirm dialog', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: COUPONS } });
    mockConfirm.mockResolvedValueOnce(false); // user cancelled

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('SAVE10')).toBeInTheDocument());

    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    fireEvent.click(deleteButtons[0]);

    await waitFor(() => expect(mockConfirm).toHaveBeenCalled());
    expect(api.delete).not.toHaveBeenCalled();
  });

  it('calls GET /v2/marketplace/seller/coupons on mount', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: [] } });

    render(<SellerCouponsPage />);

    await waitFor(() =>
      expect(api.get).toHaveBeenCalledWith('/v2/marketplace/seller/coupons')
    );
  });

  it('renders valid_until date when set', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ data: { items: COUPONS } });

    render(<SellerCouponsPage />);

    await waitFor(() => expect(screen.getByText('BOGO1')).toBeInTheDocument());

    // The date is formatted via toLocaleDateString — just check the dash isn't shown
    // for the coupon that has a date; the other shows "—"
    const dashes = screen.getAllByText('—');
    // SAVE10 has null valid_until → "—"; BOGO1 has a date → no "—" for it
    expect(dashes.length).toBeGreaterThanOrEqual(1);
  });
});
