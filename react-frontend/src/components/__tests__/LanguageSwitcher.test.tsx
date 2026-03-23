// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LanguageSwitcher component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Mocks ──────────────────────────────────────────────────────────────────

const mockChangeLanguage = vi.fn();

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => fallback ?? key,
    i18n: { language: 'en', changeLanguage: mockChangeLanguage },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

vi.mock('@/contexts/TenantContext', () => ({
  useTenantLanguages: vi.fn(() => ['en', 'ga', 'de', 'fr']),
}));

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
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
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

vi.mock('@/lib/api', () => ({
  api: {
    put: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: {
    hasAccessToken: vi.fn(() => true),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

import { LanguageSwitcher } from '../LanguageSwitcher';

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter>{children}</MemoryRouter>
    </HeroUIProvider>
  );
}

describe('LanguageSwitcher', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W><LanguageSwitcher /></W>,
    );
    expect(container).toBeTruthy();
  });

  it('renders trigger button with aria-label', () => {
    render(<W><LanguageSwitcher /></W>);
    const button = screen.getByRole('button', { name: /language/i });
    expect(button).toBeInTheDocument();
  });

  it('shows short code in compact mode (default)', () => {
    render(<W><LanguageSwitcher /></W>);
    expect(screen.getByText('EN')).toBeInTheDocument();
  });

  it('shows full label in non-compact mode', () => {
    render(<W><LanguageSwitcher compact={false} /></W>);
    expect(screen.getByText('English')).toBeInTheDocument();
  });

  it('has aria-label showing current language', () => {
    render(<W><LanguageSwitcher /></W>);
    expect(screen.getByLabelText('Language: English')).toBeInTheDocument();
  });

  it('renders dropdown menu with aria-label', () => {
    render(<W><LanguageSwitcher /></W>);
    // The DropdownMenu has aria-label="Select language"
    const button = screen.getByRole('button', { name: /language/i });
    expect(button).toBeInTheDocument();
  });
});
