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

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, options?: string | Record<string, unknown>) => {
      const translations: Record<string, string> = {
        'flyout.bell_aria': 'Notifications',
        'flyout.bell_unread_aria': `Notifications, ${typeof options === 'object' ? options.count : 0} unread`,
      };

      return translations[key] ?? (typeof options === 'string' ? options : key);
    },
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

vi.mock('@/contexts/TenantContext', () => ({
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantSlug: 'test',
    branding: { name: 'Test' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: (p: string) => `/test${p}`,
  })),
}));

vi.mock('@/contexts/NotificationsContext', () => ({
  useNotificationsOptional: () => ({
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
}));

vi.mock('@/components/ui/Popover', () => ({
  Popover: ({ children, isOpen, onOpenChange }: {
    children: React.ReactNode; isOpen?: boolean; onOpenChange?: (open: boolean) => void;
  }) => (
    <div data-testid="popover" data-open={String(isOpen)} onClick={() => onOpenChange?.(!isOpen)}>
      {children}
    </div>
  ),
  PopoverTrigger: ({ children }: { children: React.ReactNode }) => <div data-testid="popover-trigger">{children}</div>,
  PopoverContent: ({ children }: { children: React.ReactNode }) => <div data-testid="popover-content">{children}</div>,
  PopoverHeading: ({ children }: { children: React.ReactNode }) => <h2>{children}</h2>,
}));

vi.mock('@/components/ui/Button', () => ({
  Button: ({ children, onPress, 'aria-label': ariaLabel, className }: {
    children?: React.ReactNode; onPress?: () => void; 'aria-label'?: string; className?: string;
  }) => (
    <button onClick={onPress} aria-label={ariaLabel} className={className}>{children}</button>
  ),
}));

vi.mock('@/components/ui/Avatar', () => ({
  Avatar: ({ name, src }: { name?: string; src?: string | null }) => <img alt={name || ''} src={src || ''} />,
  AvatarGroup: ({ children }: { children: React.ReactNode }) => <div data-testid="avatar-group">{children}</div>,
}));

vi.mock('@/components/ui/Drawer', () => ({
  Drawer: ({ isOpen, children }: { isOpen: boolean; children?: React.ReactNode }) =>
    isOpen ? <div role="dialog" aria-label="Dialog" data-testid="drawer">{children}</div> : null,
  DrawerContent: ({ children, 'aria-label': ariaLabel }: { children: React.ReactNode; 'aria-label'?: string }) => <div aria-label={ariaLabel}>{children}</div>,
  DrawerHeader: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
  DrawerBody: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

vi.mock('@/components/ui/Skeleton', () => ({
  Skeleton: ({ className }: { className?: string }) => <div data-testid="skeleton" className={className} />,
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
}));

vi.mock('@/lib/helpers', () => ({
  formatRelativeTime: vi.fn(() => '5 minutes ago'),
  resolveAvatarUrl: vi.fn((url: string | null | undefined) => url ?? null),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { NotificationFlyout } from '../NotificationFlyout';

function W({ children }: { children: React.ReactNode }) {
  return (
    <>
      <MemoryRouter initialEntries={['/test']}>
        {children}
      </MemoryRouter>
    </>
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
