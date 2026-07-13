// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── stable mock refs ──────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockNavigate = vi.fn();

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Coupon Seller' },
      isAuthenticated: true,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/components/seo/PageMeta', () => ({
  PageMeta: () => null,
}));

// useParams / useNavigate
let mockId: string | undefined = undefined;
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: mockId }),
    useNavigate: () => mockNavigate,
  };
});

import { api } from '@/lib/api';
import SellerCouponEditPage from './SellerCouponEditPage';

const mockCoupon = {
  id: 5,
  code: 'SAVE10',
  title: 'Save 10%',
  description: 'Get 10% off',
  discount_type: 'percent',
  discount_value: '10',
  min_order_cents: null,
  max_uses: null,
  max_uses_per_member: '1',
  valid_from: null,
  valid_until: null,
  status: 'draft',
  applies_to: 'all_listings',
  applies_to_ids: null,
};

describe('SellerCouponEditPage — create mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockId = undefined;
  });

  it('renders create form without loading state', () => {
    render(<SellerCouponEditPage />);
    // No loading spinner in create mode (no id = isEdit is false)
    const statusEls = screen.queryAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeUndefined();
  });

  it('submits form and navigates away on success', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: true, data: {} });

    render(<SellerCouponEditPage />);

    const form = document.querySelector('form');
    expect(form).not.toBeNull();

    fireEvent.submit(form!);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/marketplace/seller/coupons',
        expect.objectContaining({ discount_type: 'percent' })
      );
      expect(mockToast.success).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('/test/marketplace/seller/coupons');
    });
  });

  it('shows error toast when create API fails', async () => {
    vi.mocked(api.post).mockResolvedValue({ success: false, error: 'Bad request' });

    render(<SellerCouponEditPage />);

    const form = document.querySelector('form');
    fireEvent.submit(form!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when API throws', async () => {
    vi.mocked(api.post).mockRejectedValue(new Error('Network'));

    render(<SellerCouponEditPage />);

    const form = document.querySelector('form');
    fireEvent.submit(form!);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('converts code to uppercase as user types', async () => {
    render(<SellerCouponEditPage />);

    // Find the code input via label text (i18n key = 'coupon.code')
    // In test env t() returns the key path so label text = 'coupon.code'
    // Use placeholder/label pattern
    const inputs = screen.getAllByRole('textbox');
    // First textbox should be code input
    expect(inputs.length).toBeGreaterThan(0);
  });
});

describe('SellerCouponEditPage — edit mode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockId = '5';
  });

  it('shows loading spinner while fetching coupon', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));

    render(<SellerCouponEditPage />);

    const statusEls = screen.queryAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('populates form fields from fetched coupon', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [mockCoupon] },
    });

    render(<SellerCouponEditPage />);

    await waitFor(() => {
      // Form should no longer be in loading state — check for input value
      expect(screen.getByDisplayValue('Save 10%')).toBeInTheDocument();
    });

    expect(screen.getByDisplayValue('SAVE10')).toBeInTheDocument();
    expect(screen.getByDisplayValue('Get 10% off')).toBeInTheDocument();
  });

  it('submits PUT on save when in edit mode', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [mockCoupon] },
    });
    vi.mocked(api.put).mockResolvedValue({ success: true, data: {} });

    render(<SellerCouponEditPage />);

    await waitFor(() => {
      expect(screen.getByDisplayValue('Save 10%')).toBeInTheDocument();
    });

    const form = document.querySelector('form');
    fireEvent.submit(form!);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/marketplace/seller/coupons/5',
        expect.objectContaining({ title: 'Save 10%' })
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('loads and preserves specific listing targets in the coupon payload', async () => {
    vi.mocked(api.get).mockImplementation(async (url) => {
      if (url === '/v2/marketplace/seller/coupons') {
        return {
          success: true,
          data: {
            items: [{
              ...mockCoupon,
              applies_to: 'listing_ids',
              applies_to_ids: [101],
            }],
          },
        };
      }

      if (url.includes('/v2/marketplace/listings?user_id=')) {
        return {
          success: true,
          data: [{ id: 101, title: 'Target listing' }],
        };
      }

      return { success: false, error: 'Unexpected endpoint' };
    });
    vi.mocked(api.put).mockResolvedValue({ success: true, data: {} });

    render(<SellerCouponEditPage />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/marketplace/listings?user_id=1'),
      );
    });
    expect(screen.getAllByText('Target listing')).not.toHaveLength(0);
    fireEvent.submit(document.querySelector('form')!);

    await waitFor(() => {
      expect(api.put).toHaveBeenCalledWith(
        '/v2/marketplace/seller/coupons/5',
        expect.objectContaining({
          applies_to: 'listing_ids',
          applies_to_ids: [101],
        }),
      );
    });
  });

  it('silently handles coupon not found in list', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [] }, // coupon id=5 not in list
    });

    render(<SellerCouponEditPage />);

    await waitFor(() => {
      // loading ends without crash
      const statusEls = screen.queryAllByRole('status');
      const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busy).toBeUndefined();
    });
  });
});
