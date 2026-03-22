// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MaintenancePage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ user: null, isAuthenticated: false })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ showToast: vi.fn() })),

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
vi.mock('@/hooks/usePageTitle', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/tenant-routing', () => ({
  tenantPath: vi.fn((p: string) => `/test${p}`),
}));

import { MaintenancePage } from './MaintenancePage';

describe('MaintenancePage', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('renders without crashing', () => {
    const { container } = render(<MaintenancePage />);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
