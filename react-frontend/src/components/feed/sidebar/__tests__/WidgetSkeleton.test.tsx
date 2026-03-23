// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for WidgetSkeleton
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useAuth: vi.fn(() => ({
    isAuthenticated: true,
    user: { id: 1, first_name: 'Alice', last_name: 'Smith', username: 'asmith', avatar: '/alice.png' },
  })),
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

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url: string | undefined) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url: string | undefined) => url || ''),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { WidgetSkeleton } from '../WidgetSkeleton';

describe('WidgetSkeleton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(<WidgetSkeleton />);
    expect(container.firstChild).toBeTruthy();
  });

  it('renders default 3 skeleton rows', () => {
    const { container } = render(<WidgetSkeleton />);
    const skeletonItems = container.querySelectorAll('[class*="flex items-center gap-3"]');
    expect(skeletonItems.length).toBe(3);
  });

  it('renders custom number of rows via lines prop', () => {
    const { container } = render(<WidgetSkeleton lines={5} />);
    const skeletonItems = container.querySelectorAll('[class*="flex items-center gap-3"]');
    expect(skeletonItems.length).toBe(5);
  });

  it('renders 1 row when lines=1', () => {
    const { container } = render(<WidgetSkeleton lines={1} />);
    const skeletonItems = container.querySelectorAll('[class*="flex items-center gap-3"]');
    expect(skeletonItems.length).toBe(1);
  });

  it('renders 0 rows when lines=0', () => {
    const { container } = render(<WidgetSkeleton lines={0} />);
    const skeletonItems = container.querySelectorAll('[class*="flex items-center gap-3"]');
    expect(skeletonItems.length).toBe(0);
  });

  it('renders a header skeleton placeholder', () => {
    const { container } = render(<WidgetSkeleton />);
    // The header skeleton has class "h-4 w-32 rounded mb-4"
    const headerSkeleton = container.querySelector('[class*="h-4"][class*="w-32"]');
    expect(headerSkeleton).toBeTruthy();
  });

  it('each row contains circular and rectangular skeleton elements', () => {
    const { container } = render(<WidgetSkeleton lines={1} />);
    // Circular avatar skeleton: "w-8 h-8 rounded-full"
    const circularSkeleton = container.querySelector('[class*="w-8"][class*="h-8"][class*="rounded-full"]');
    expect(circularSkeleton).toBeTruthy();
    // Rectangular text skeletons within the row
    const textSkeletons = container.querySelectorAll('[class*="flex-1"] [class*="rounded"]');
    expect(textSkeletons.length).toBeGreaterThanOrEqual(1);
  });
});
