// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SellerPickupScanPage component
 *
 * Camera-based QR scanning is SKIPPED — html5-qrcode is NOT bundled (comment
 * in source says "not bundled") and there is no camera trigger in the rendered
 * UI (the page only exposes manual code entry). Live camera branch is absent
 * from the current implementation and therefore has nothing to test here.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => mockToast),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: (p: string) => '/test' + p,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// usePageTitle hook — stub to avoid document.title side effects
vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

import { api } from '@/lib/api';
import { SellerPickupScanPage } from './SellerPickupScanPage';

describe('SellerPickupScanPage — static UI', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders an h1 heading', () => {
    render(<SellerPickupScanPage />);
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('renders a text input for the QR code', () => {
    render(<SellerPickupScanPage />);
    const input = document.querySelector('input');
    expect(input).toBeInTheDocument();
  });

  it('renders a confirm/verify button', () => {
    render(<SellerPickupScanPage />);
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('confirm button is disabled when input is empty', () => {
    render(<SellerPickupScanPage />);
    const btn = screen.getAllByRole('button')[0] as HTMLButtonElement;
    const isDisabled = btn.disabled || btn.getAttribute('aria-disabled') === 'true' || btn.getAttribute('data-disabled') === 'true';
    expect(isDisabled).toBe(true);
  });

  it('does not render the "last scan" card on initial render', () => {
    render(<SellerPickupScanPage />);
    // The result card only appears after a successful scan
    // Translation key 'marketplace.pickup.last_scan'
    // We assert by checking for border-success class not being present yet
    const successCards = document.querySelectorAll('.border-success');
    expect(successCards).toHaveLength(0);
  });
});

describe('SellerPickupScanPage — manual code entry flow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('enables confirm button when a code is typed', async () => {
    render(<SellerPickupScanPage />);
    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: '01HTEST123' } });

    await waitFor(() => {
      const btn = screen.getAllByRole('button')[0] as HTMLButtonElement;
      const isDisabled = btn.disabled || btn.getAttribute('aria-disabled') === 'true' || btn.getAttribute('data-disabled') === 'true';
      expect(isDisabled).toBe(false);
    });
  });

  it('posts to the pickup-scan endpoint with the trimmed code', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: {
        id: 1,
        order_id: 555,
        listing_id: 10,
        status: 'picked_up',
        picked_up_at: '2026-06-21T10:00:00Z',
      },
    });

    render(<SellerPickupScanPage />);

    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: '  01HTEST456  ' } });

    const btn = screen.getAllByRole('button')[0];
    fireEvent.click(btn);

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/marketplace/seller/pickup-scan',
        { qr_code: '01HTEST456' },
      );
    });
  });

  it('shows success toast on successful scan', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: {
        id: 2,
        order_id: 556,
        listing_id: 11,
        status: 'picked_up',
        picked_up_at: '2026-06-21T10:00:00Z',
      },
    });

    render(<SellerPickupScanPage />);

    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'VALID_CODE' } });
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('renders the last-scan result card after a successful scan', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: {
        id: 3,
        order_id: 777,
        listing_id: 12,
        status: 'picked_up',
        picked_up_at: '2026-06-21T10:00:00Z',
      },
    });

    render(<SellerPickupScanPage />);

    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'VALID_CODE' } });
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      // The success card with border-l-4 border-success renders
      const successCards = document.querySelectorAll('.border-success');
      expect(successCards.length).toBeGreaterThan(0);
    });
  });

  it('clears the input after a successful scan', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: {
        id: 4,
        order_id: 888,
        listing_id: 13,
        status: 'picked_up',
        picked_up_at: null,
      },
    });

    render(<SellerPickupScanPage />);

    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'CLEAR_ME' } });
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      expect(input.value).toBe('');
    });
  });

  it('shows error toast when API returns success: false', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({
      success: false,
      error: 'Invalid QR code',
    });

    render(<SellerPickupScanPage />);

    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'BAD_CODE' } });
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when API call throws', async () => {
    vi.mocked(api.post).mockRejectedValueOnce(new Error('Network error'));

    render(<SellerPickupScanPage />);

    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: 'THROW_CODE' } });
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('does not post when input contains only whitespace', async () => {
    render(<SellerPickupScanPage />);

    const input = document.querySelector('input') as HTMLInputElement;
    fireEvent.change(input, { target: { value: '   ' } });
    fireEvent.click(screen.getAllByRole('button')[0]);

    await waitFor(() => {
      expect(api.post).not.toHaveBeenCalled();
    });
  });
});

// NOTE: Live camera scanning (html5-qrcode) is SKIPPED.
// The component comment confirms "Camera scanning is optional via html5-qrcode (not bundled)."
// There is no camera trigger button in the current rendered UI.
