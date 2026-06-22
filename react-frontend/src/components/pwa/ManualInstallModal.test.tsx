// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ManualInstallModal.
 *
 * Design notes
 * ─────────────
 * • The component renders a HeroUI Modal (portal). When isOpen=true the modal
 *   is present in the DOM.  query via screen (no container arg needed).
 * • We mock @/lib/installPrompt — ManualInstallModal does NOT import it
 *   directly, but we include a stub to guard against transitive imports.
 * • The modal uses useTranslation('common') — the real i18n strings are loaded
 *   by the global test setup, so we assert on the actual English key values.
 * • Mocking `@/components/ui` is not needed because the Modal wrapper renders
 *   correctly in jsdom with the polyfills in setup.ts.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@/test/test-utils';
import type { BrowserKind } from '@/lib/installPrompt';
import { ManualInstallModal } from './ManualInstallModal';

vi.mock('@/contexts', () => ({
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, branding: { name: 'Test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
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

// Stub the installPrompt module — not imported by ManualInstallModal directly
// but may be required by transitive imports.
vi.mock('@/lib/installPrompt', () => ({
  useInstallPrompt: vi.fn(() => ({
    canPrompt: false,
    isIos: false,
    isInstalled: false,
    isIosSafari: false,
    browser: 'other',
    promptInstall: vi.fn(),
  })),
  shouldOfferInstall: vi.fn(() => true),
  requestInstall: vi.fn(),
}));

function renderModal(browser: BrowserKind = 'chrome-desktop', isOpen = true) {
  const onClose = vi.fn();
  const result = render(
    <ManualInstallModal isOpen={isOpen} onClose={onClose} browser={browser} />,
  );
  return { onClose, ...result };
}

describe('ManualInstallModal — chrome-desktop', () => {
  beforeEach(() => { vi.clearAllMocks(); });

  it('renders the modal dialog when isOpen=true', async () => {
    renderModal('chrome-desktop');
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });

  it('does not render when isOpen=false', () => {
    renderModal('chrome-desktop', false);
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders a numbered step list', async () => {
    renderModal('chrome-desktop');
    await waitFor(() => {
      // Step number badges: 1, 2, 3
      expect(screen.getByText('1')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();
      expect(screen.getByText('3')).toBeInTheDocument();
    });
  });

  it('calls onClose when the "Got it" button is pressed', async () => {
    const { onClose } = renderModal('chrome-desktop');
    await waitFor(() => screen.getByRole('dialog'));
    // The footer close button text comes from t('install.got_it')
    const btn = screen.getByRole('button', { name: /got it/i });
    fireEvent.click(btn);
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});

describe('ManualInstallModal — chrome-android', () => {
  it('renders with chrome-android instructions (3 steps)', async () => {
    renderModal('chrome-android');
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText('3')).toBeInTheDocument();
    });
  });
});

describe('ManualInstallModal — samsung', () => {
  it('renders with samsung instructions', async () => {
    renderModal('samsung');
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument();
    });
  });
});

describe('ManualInstallModal — firefox-android', () => {
  it('renders with firefox-android instructions', async () => {
    renderModal('firefox-android');
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
    });
  });
});

describe('ManualInstallModal — edge-desktop', () => {
  it('renders with edge-desktop instructions', async () => {
    renderModal('edge-desktop');
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();
    });
  });
});

describe('ManualInstallModal — firefox-desktop', () => {
  it('renders with firefox-desktop instructions (2 steps only)', async () => {
    renderModal('firefox-desktop');
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument();
      expect(screen.getByText('2')).toBeInTheDocument();
      // firefox-desktop only has 2 steps
      expect(screen.queryByText('3')).not.toBeInTheDocument();
    });
  });
});

describe('ManualInstallModal — other (fallback)', () => {
  it('renders the generic fallback instructions', async () => {
    renderModal('other');
    await waitFor(() => {
      expect(screen.getByRole('dialog')).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument();
    });
  });
});
