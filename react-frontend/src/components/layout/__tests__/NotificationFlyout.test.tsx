// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for NotificationFlyout component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
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

const mockMarkAsRead = vi.fn();
const mockMarkAllAsRead = vi.fn();
const mockUnreadCount = 3;

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Test User' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantSlug: 'test',
    branding: { name: 'Test' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTheme: vi.fn(() => ({ resolvedTheme: 'light', theme: 'light', setTheme: vi.fn() })),
  useNotifications: () => ({
    unreadCount: mockUnreadCount,
    counts: {},
    notifications: [],
    markAsRead: mockMarkAsRead,
    markAllAsRead: mockMarkAllAsRead,
    hasMore: false,
    loadMore: vi.fn(),
    isLoading: false,
    refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: vi.fn(() => '5 minutes ago'),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { NotificationFlyout } from '../NotificationFlyout';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

describe('NotificationFlyout', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W><NotificationFlyout /></W>,
    );
    expect(container).toBeTruthy();
  });

  it('renders the bell button', () => {
    render(<W><NotificationFlyout /></W>);
    const button = screen.getByRole('button', { name: /notifications/i });
    expect(button).toBeInTheDocument();
  });

  it('shows unread count badge when there are unread notifications', () => {
    const { container } = render(<W><NotificationFlyout /></W>);
    expect(container.querySelector('span.bg-danger')).toBeInTheDocument();
  });

  it('bell button has correct aria-label with unread count', () => {
    render(<W><NotificationFlyout /></W>);
    const button = screen.getByRole('button', { name: /notifications.*3 unread/i });
    expect(button).toBeInTheDocument();
  });
});
