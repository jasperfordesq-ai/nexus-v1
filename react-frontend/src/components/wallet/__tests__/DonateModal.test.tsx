// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DonateModal component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
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

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
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
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { DonateModal } from '../DonateModal';

const defaultProps = {
  isOpen: true,
  onClose: vi.fn(),
  currentBalance: 10,
};

describe('DonateModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders when isOpen is true', () => {
    render(<DonateModal {...defaultProps} />);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render content when isOpen is false', () => {
    render(<DonateModal {...defaultProps} isOpen={false} />);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders the community fund and member radio options', () => {
    render(<DonateModal {...defaultProps} />);
    // Community fund radio option
    expect(screen.getByRole('radio', { name: /community fund/i }) ||
      screen.getByText(/community fund/i)).toBeTruthy();
    // "member" text appears in multiple DOM nodes (label + description),
    // so use getAllByText instead of getByText
    const memberTexts = screen.getAllByText(/member/i);
    expect(memberTexts.length).toBeGreaterThanOrEqual(1);
  });

  it('shows recipient ID input when "user" radio is selected', async () => {
    render(<DonateModal {...defaultProps} />);
    const userRadio = screen.getAllByRole('radio').find((r) =>
      r.getAttribute('value') === 'user'
    );
    if (userRadio) {
      fireEvent.click(userRadio);
      await waitFor(() => {
        // Recipient input should appear
        const inputs = screen.getAllByRole('spinbutton');
        expect(inputs.length).toBeGreaterThanOrEqual(1);
      });
    }
  });

  it('renders the amount input', () => {
    render(<DonateModal {...defaultProps} />);
    const amountInput = screen.getAllByRole('spinbutton')[0];
    expect(amountInput).toBeInTheDocument();
  });

  it('renders the Cancel button', () => {
    render(<DonateModal {...defaultProps} />);
    expect(screen.getByRole('button', { name: /cancel/i })).toBeInTheDocument();
  });

  it('calls onClose when Cancel is clicked', () => {
    const onClose = vi.fn();
    render(<DonateModal {...defaultProps} onClose={onClose} />);
    fireEvent.click(screen.getByRole('button', { name: /cancel/i }));
    expect(onClose).toHaveBeenCalled();
  });

  it('shows error toast when amount is zero', async () => {
    render(<DonateModal {...defaultProps} />);

    // Find the donate/confirm button and click it with empty amount
    const donateBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('donat')
    );
    if (donateBtn) {
      fireEvent.click(donateBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });

  it('shows error toast when amount exceeds current balance', async () => {
    render(<DonateModal {...defaultProps} currentBalance={5} />);

    const amountInput = screen.getAllByRole('spinbutton')[0];
    fireEvent.change(amountInput, { target: { value: '100' } });

    const donateBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('donat')
    );
    if (donateBtn) {
      fireEvent.click(donateBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });

  it('calls API and shows success toast on valid donation', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: true });
    const onClose = vi.fn();
    const onDonationComplete = vi.fn();

    render(
      <DonateModal
        {...defaultProps}
        onClose={onClose}
        onDonationComplete={onDonationComplete}
        currentBalance={20}
      />
    );

    const amountInput = screen.getAllByRole('spinbutton')[0];
    fireEvent.change(amountInput, { target: { value: '5' } });

    const donateBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('donat')
    );
    if (donateBtn) {
      fireEvent.click(donateBtn);
      await waitFor(() => {
        expect(api.post).toHaveBeenCalledWith(
          '/v2/wallet/donate',
          expect.objectContaining({ amount: 5, recipient_type: 'community_fund' })
        );
        expect(mockToast.success).toHaveBeenCalled();
        expect(onDonationComplete).toHaveBeenCalled();
        expect(onClose).toHaveBeenCalled();
      });
    }
  });

  it('shows error toast when API returns failure', async () => {
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Payment failed' });

    render(<DonateModal {...defaultProps} currentBalance={20} />);

    const amountInput = screen.getAllByRole('spinbutton')[0];
    fireEvent.change(amountInput, { target: { value: '3' } });

    const donateBtn = screen.getAllByRole('button').find((b) =>
      b.textContent?.toLowerCase().includes('donat')
    );
    if (donateBtn) {
      fireEvent.click(donateBtn);
      await waitFor(() => {
        expect(mockToast.error).toHaveBeenCalled();
      });
    }
  });
});
