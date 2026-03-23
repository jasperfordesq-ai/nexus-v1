// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ReviewModal component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => fallback ?? key,
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn() };

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ user: { id: 1 }, isAuthenticated: true })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantSlug: 'test',
    branding: { name: 'Test' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => mockToast),
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

const mockApiPost = vi.fn();
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: {} }),
    post: (...args: unknown[]) => mockApiPost(...args),
  },
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null | undefined) => url || '/default-avatar.png',
}));

import { ReviewModal } from '../ReviewModal';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter>{children}</MemoryRouter>
    </HeroUIProvider>
  );
}

const defaultProps = {
  isOpen: true,
  onClose: vi.fn(),
  receiverId: 5,
  receiverName: 'Jane Doe',
  receiverAvatar: null,
};

describe('ReviewModal', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockApiPost.mockResolvedValue({ success: true });
  });

  it('renders when isOpen is true', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    expect(screen.getByRole('dialog')).toBeInTheDocument();
  });

  it('does not render when isOpen is false', () => {
    render(<W><ReviewModal {...defaultProps} isOpen={false} /></W>);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders "Write a Review" heading', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    expect(screen.getByText('Write a Review')).toBeInTheDocument();
  });

  it('displays receiver name', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    expect(screen.getByText('Jane Doe')).toBeInTheDocument();
  });

  it('shows experience text with receiver name', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    expect(screen.getByText(/Share your experience with Jane Doe/)).toBeInTheDocument();
  });

  it('renders 5 star rating buttons', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    const starButtons = screen.getAllByRole('button', { name: /Rate \d out of 5 stars/ });
    expect(starButtons).toHaveLength(5);
  });

  it('renders Cancel and Submit buttons', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    expect(screen.getByText('Cancel')).toBeInTheDocument();
    expect(screen.getByText('Submit Review')).toBeInTheDocument();
  });

  it('Submit button is disabled until rating is selected', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    const submitBtn = screen.getByText('Submit Review').closest('button');
    expect(submitBtn).toBeDisabled();
  });

  it('shows "Transaction Review" text when transactionId is provided', () => {
    render(<W><ReviewModal {...defaultProps} transactionId={10} /></W>);
    expect(screen.getByText('Transaction Review')).toBeInTheDocument();
  });

  it('shows "General Review" text when no transactionId', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    expect(screen.getByText('General Review')).toBeInTheDocument();
  });

  it('shows character counter for comment', () => {
    render(<W><ReviewModal {...defaultProps} /></W>);
    expect(screen.getByText('0/2000 characters')).toBeInTheDocument();
  });

  it('submits review on button click after selecting rating', async () => {
    const onClose = vi.fn();
    const onSuccess = vi.fn();
    render(
      <W>
        <ReviewModal {...defaultProps} onClose={onClose} onSuccess={onSuccess} />
      </W>,
    );

    // Click 4th star
    fireEvent.click(screen.getByRole('button', { name: 'Rate 4 out of 5 stars' }));
    // Click submit
    fireEvent.click(screen.getByText('Submit Review'));

    await waitFor(() => {
      expect(mockApiPost).toHaveBeenCalledWith('/v2/reviews', expect.objectContaining({
        receiver_id: 5,
        rating: 4,
      }));
    });
  });

  it('calls onClose when Cancel is clicked', () => {
    const onClose = vi.fn();
    render(<W><ReviewModal {...defaultProps} onClose={onClose} /></W>);
    fireEvent.click(screen.getByText('Cancel'));
    expect(onClose).toHaveBeenCalled();
  });
});
