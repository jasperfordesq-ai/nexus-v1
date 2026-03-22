// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for WalletPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({
      success: true,
      data: { balance: 10, pending_in: 2, total_spent: 5, total_earned: 15 },
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),

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
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title }: { title: string }) => <div data-testid="empty-state">{title}</div>,
}));

vi.mock('@/components/wallet', () => ({
  TransferModal: () => null,
  DonateModal: () => null,
  CommunityFundCard: () => null,
  RatingModal: () => null,
  CategorySelect: () => null,
}));

vi.mock('framer-motion', () => {  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);  const filterMotion = (props: Record<string, unknown>) => {    const filtered: Record<string, unknown> = {};    for (const [k, v] of Object.entries(props)) {      if (!motionProps.has(k)) filtered[k] = v;    }    return filtered;  };  return {    motion: {      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,    },    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,  };});

import { WalletPage } from './WalletPage';

describe('WalletPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<WalletPage />);
    expect(screen.getByText('Wallet')).toBeInTheDocument();
  });

  it('shows the page description', () => {
    render(<WalletPage />);
    expect(screen.getByText('Track your time credits and transactions')).toBeInTheDocument();
  });

  it('shows Send Credits button', () => {
    render(<WalletPage />);
    expect(screen.getByText('Send Credits')).toBeInTheDocument();
  });

  it('shows Transaction History section', () => {
    render(<WalletPage />);
    expect(screen.getByText('Transaction History')).toBeInTheDocument();
  });

  it('shows filter tabs', () => {
    render(<WalletPage />);
    // These labels appear in both stat cards and filter tabs
    expect(screen.getAllByText('Earned').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Spent').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Pending').length).toBeGreaterThanOrEqual(1);
  });

  it('shows Export button', () => {
    render(<WalletPage />);
    expect(screen.getByText('Export')).toBeInTheDocument();
  });
});
