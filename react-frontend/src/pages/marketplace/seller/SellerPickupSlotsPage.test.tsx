// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SellerPickupSlotsPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
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

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

// Stable references for mock hook return values
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockAuthValue = { user: { id: 1, name: 'Seller' }, isAuthenticated: true, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle' as const, error: null };
const mockTenantValue = { tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) };

vi.mock('@/contexts', () => ({
  useToast: () => mockToast,
  useAuth: () => mockAuthValue,
  useTenant: () => mockTenantValue,
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

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/components/seo/PageMeta', () => ({
  PageMeta: () => null,
}));

// Mock useConfirm so delete flows work without a real dialog
const mockConfirm = vi.fn(async () => true);
vi.mock('@/components/ui/ConfirmDialog', () => {
  return {
    useConfirm: () => mockConfirm,
  };
});

import { SellerPickupSlotsPage } from './SellerPickupSlotsPage';

const SLOT_1 = {
  id: 1,
  slot_start: '2024-06-01T10:00:00Z',
  slot_end: '2024-06-01T12:00:00Z',
  capacity: 5,
  booked_count: 2,
  is_recurring: false,
  is_active: true,
};

const SLOT_2 = {
  id: 2,
  slot_start: '2024-06-02T14:00:00Z',
  slot_end: '2024-06-02T16:00:00Z',
  capacity: 3,
  booked_count: 1,
  is_recurring: true,
  is_active: true,
};

describe('SellerPickupSlotsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockConfirm.mockResolvedValue(true);
  });

  it('shows a loading spinner initially while fetching slots', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<SellerPickupSlotsPage />);
    // HeroUI Spinner renders multiple role=status elements
    expect(screen.getAllByRole('status').length).toBeGreaterThan(0);
  });

  it('renders pickup slots returned by the API', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [SLOT_1, SLOT_2] });
    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      // capacity chips: 2/5 and 1/3
      expect(screen.getByText(/2\/5/)).toBeInTheDocument();
      expect(screen.getByText(/1\/3/)).toBeInTheDocument();
    });
  });

  it('shows an empty state when no slots exist', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      // No slots card is shown — no capacity chips
      expect(screen.queryByText(/\/\d/)).not.toBeInTheDocument();
    });
  });

  it('shows a "recurring" chip for recurring slots', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [SLOT_2] });
    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      expect(screen.getByText(/recurring/i)).toBeInTheDocument();
    });
  });

  it('shows an "inactive" chip for inactive slots', async () => {
    const inactiveSlot = { ...SLOT_1, is_active: false };
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [inactiveSlot] });
    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      expect(screen.getByText(/inactive/i)).toBeInTheDocument();
    });
  });

  it('renders a "New Pickup Slot" button to open the create modal', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      // i18n key marketplace.pickup.new_slot = "New Pickup Slot"
      expect(screen.getByRole('button', { name: /new pickup slot/i })).toBeInTheDocument();
    });
  });

  it('calls DELETE endpoint and removes slot after confirmed deletion', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [SLOT_1] });
    vi.mocked(api.delete).mockResolvedValue({ success: true });
    mockConfirm.mockResolvedValue(true);

    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      expect(screen.getByText(/2\/5/)).toBeInTheDocument();
    });

    // Click delete button
    const deleteBtn = screen.getByRole('button', { name: /delete/i });
    fireEvent.click(deleteBtn);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalledWith(expect.objectContaining({
        title: expect.any(String),
        status: 'danger',
        confirmLabel: expect.any(String),
      }));
      expect(api.delete).toHaveBeenCalledWith('/v2/marketplace/seller/pickup-slots/1');
      expect(screen.queryByText(/2\/5/)).not.toBeInTheDocument();
      expect(mockToast.success).toHaveBeenCalledTimes(1);
    });
  });

  it('does NOT call DELETE when user cancels the confirmation', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [SLOT_1] });
    mockConfirm.mockResolvedValue(false);

    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      expect(screen.getByText(/2\/5/)).toBeInTheDocument();
    });

    const deleteBtn = screen.getByRole('button', { name: /delete/i });
    fireEvent.click(deleteBtn);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalledWith(expect.objectContaining({
        status: 'danger',
      }));
    });
    expect(api.delete).not.toHaveBeenCalled();
    expect(screen.getByText(/2\/5/)).toBeInTheDocument();
  });

  it('calls api.get on /v2/marketplace/seller/pickup-slots', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });
    render(<SellerPickupSlotsPage />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/marketplace/seller/pickup-slots');
    });
  });
});
