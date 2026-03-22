// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for HomePage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';
import React from 'react';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: { members: 100, hours_exchanged: 500, listings: 50, skills: 30, communities: 5 } }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({ user: null, isAuthenticated: false })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community', logo_url: null, tagline: 'A test community' },
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
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('framer-motion', () => {
  const motionProxy = new Proxy({}, {
    get: (_target, prop) => {
      return React.forwardRef(({ children, ...props }: Record<string, unknown>, ref: React.Ref<HTMLElement>) => {
        const clean = { ...props };
        delete clean.variants; delete clean.initial; delete clean.animate;
        delete clean.exit; delete clean.transition; delete clean.whileHover;
        delete clean.whileTap; delete clean.whileInView; delete clean.layout;
        delete clean.viewport;
        const Tag = typeof prop === 'string' ? prop : 'div';
        return React.createElement(Tag, { ...clean, ref }, children);
      });
    },
  });
  return {
    motion: motionProxy,
    AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
    useAnimation: () => ({ start: vi.fn() }),
    useInView: () => true,
  };
});

import { HomePage } from './HomePage';

describe('HomePage', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('renders without crashing', () => {
    render(<HomePage />);
    expect(document.body).toBeTruthy();
  });

  it('contains main content area', () => {
    const { container } = render(<HomePage />);
    expect(container.querySelector('div')).toBeTruthy();
  });
});
