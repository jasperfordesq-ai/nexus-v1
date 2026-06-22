// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for OfferCard component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import type { MarketplaceOffer } from '@/types/marketplace';

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

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null | undefined) => url || '',
}));

vi.mock('@/contexts', () => ({
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { OfferCard } from './OfferCard';

const BASE_OFFER: MarketplaceOffer = {
  id: 1,
  amount: 25,
  currency: 'EUR',
  status: 'pending',
  created_at: '2024-01-15T10:00:00Z',
  listing: {
    id: 10,
    title: 'Vintage Lamp',
    price: 30,
    price_currency: 'EUR',
    status: 'active',
  },
  buyer: { id: 2, name: 'Alice', avatar_url: null },
  seller: { id: 3, name: 'Bob', avatar_url: null },
};

describe('OfferCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the listing title', () => {
    render(<OfferCard offer={BASE_OFFER} />);
    expect(screen.getByText('Vintage Lamp')).toBeInTheDocument();
  });

  it('displays the offer amount formatted as currency', () => {
    render(<OfferCard offer={BASE_OFFER} />);
    // Should contain "25" as part of a currency string (€25 or similar)
    expect(screen.getByText(/25/)).toBeInTheDocument();
  });

  it('renders the pending status chip', () => {
    render(<OfferCard offer={BASE_OFFER} />);
    // i18n key: offer.status.pending
    expect(screen.getByText(/pending/i)).toBeInTheDocument();
  });

  it('renders accepted status chip for an accepted offer', () => {
    render(<OfferCard offer={{ ...BASE_OFFER, status: 'accepted' }} />);
    expect(screen.getByText(/accepted/i)).toBeInTheDocument();
  });

  it('renders declined status chip', () => {
    render(<OfferCard offer={{ ...BASE_OFFER, status: 'declined' }} />);
    expect(screen.getByText(/declined/i)).toBeInTheDocument();
  });

  it('displays the offer message when present', () => {
    render(<OfferCard offer={{ ...BASE_OFFER, message: 'Happy to negotiate!' }} />);
    expect(screen.getByText('Happy to negotiate!')).toBeInTheDocument();
  });

  it('shows counterparty name from seller perspective (buyer is counterparty)', () => {
    render(<OfferCard offer={BASE_OFFER} perspective="seller" />);
    expect(screen.getByText('Alice')).toBeInTheDocument();
  });

  it('shows counterparty name from buyer perspective (seller is counterparty)', () => {
    render(<OfferCard offer={BASE_OFFER} perspective="buyer" />);
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('shows Accept, Decline, Counter buttons for seller on pending offer', () => {
    render(<OfferCard offer={BASE_OFFER} perspective="seller" />);
    expect(screen.getByRole('button', { name: /accept/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /decline/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /counter/i })).toBeInTheDocument();
  });

  it('shows Withdraw button for buyer on pending offer', () => {
    render(<OfferCard offer={BASE_OFFER} perspective="buyer" />);
    expect(screen.getByRole('button', { name: /withdraw/i })).toBeInTheDocument();
  });

  it('calls onAccept with offer id when seller clicks Accept', () => {
    const onAccept = vi.fn();
    render(<OfferCard offer={BASE_OFFER} perspective="seller" onAccept={onAccept} />);
    fireEvent.click(screen.getByRole('button', { name: /accept/i }));
    expect(onAccept).toHaveBeenCalledWith(1);
  });

  it('calls onDecline with offer id when seller clicks Decline', () => {
    const onDecline = vi.fn();
    render(<OfferCard offer={BASE_OFFER} perspective="seller" onDecline={onDecline} />);
    fireEvent.click(screen.getByRole('button', { name: /decline/i }));
    expect(onDecline).toHaveBeenCalledWith(1);
  });

  it('calls onCounter with offer id when seller clicks Counter', () => {
    const onCounter = vi.fn();
    render(<OfferCard offer={BASE_OFFER} perspective="seller" onCounter={onCounter} />);
    fireEvent.click(screen.getByRole('button', { name: /counter/i }));
    expect(onCounter).toHaveBeenCalledWith(1);
  });

  it('calls onWithdraw with offer id when buyer clicks Withdraw', () => {
    const onWithdraw = vi.fn();
    render(<OfferCard offer={BASE_OFFER} perspective="buyer" onWithdraw={onWithdraw} />);
    fireEvent.click(screen.getByRole('button', { name: /withdraw/i }));
    expect(onWithdraw).toHaveBeenCalledWith(1);
  });

  it('shows counter amount and Accept Counter button for buyer on countered offer', () => {
    const onAcceptCounter = vi.fn();
    const counteredOffer: MarketplaceOffer = {
      ...BASE_OFFER,
      status: 'countered',
      counter_amount: 22,
    };
    render(<OfferCard offer={counteredOffer} perspective="buyer" onAcceptCounter={onAcceptCounter} />);
    expect(screen.getByText(/22/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /accept/i }));
    expect(onAcceptCounter).toHaveBeenCalledWith(1);
  });

  it('shows counter message when present', () => {
    render(
      <OfferCard
        offer={{ ...BASE_OFFER, status: 'countered', counter_amount: 20, counter_message: 'Best I can do' }}
        perspective="buyer"
      />
    );
    expect(screen.getByText('Best I can do')).toBeInTheDocument();
  });

  it('does not render action buttons for non-pending, non-countered status', () => {
    render(<OfferCard offer={{ ...BASE_OFFER, status: 'expired' }} perspective="seller" />);
    expect(screen.queryByRole('button', { name: /accept|decline|counter|withdraw/i })).not.toBeInTheDocument();
  });

  it('renders listing thumbnail when image URL is present', () => {
    const offerWithImage: MarketplaceOffer = {
      ...BASE_OFFER,
      listing: {
        ...BASE_OFFER.listing!,
        image: { url: 'https://example.com/img.jpg', thumbnail_url: 'https://example.com/thumb.jpg' },
      },
    };
    render(<OfferCard offer={offerWithImage} />);
    const img = screen.getByRole('img', { name: /vintage lamp/i });
    expect(img).toBeInTheDocument();
  });
});
