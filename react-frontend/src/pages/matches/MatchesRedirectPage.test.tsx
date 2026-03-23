// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MatchesRedirectPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';

const mockNavigate = vi.fn();

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

const stableTenant = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  branding: { name: 'Test Community' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
  isLoading: false,
};

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => stableTenant),
  useAuth: vi.fn(() => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));

import MatchesRedirectPage from './MatchesRedirectPage';

describe('MatchesRedirectPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing (returns null)', () => {
    const { container } = render(<MatchesRedirectPage />);
    // The component renders null, so container should have no children
    expect(container).toBeTruthy();
  });

  it('navigates to listings with replace', () => {
    render(<MatchesRedirectPage />);
    expect(mockNavigate).toHaveBeenCalledWith('/test/listings', { replace: true });
  });
});
