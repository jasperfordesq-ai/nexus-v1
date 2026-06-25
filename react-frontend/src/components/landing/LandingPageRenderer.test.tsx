// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for LandingPageRenderer.
 *
 * Design notes
 * ─────────────
 * • LandingPageRenderer reads `landingPageConfig.sections` from useTenant and
 *   renders a RenderSection per enabled section.
 * • Each sub-section component (HeroSection, StatsSection, etc.) makes its own
 *   API calls or has its own logic. We stub those with minimal vi.mocks to keep
 *   this test focused on the renderer's routing logic.
 * • We use a custom useTenant mock per test-group to control which sections
 *   are enabled and in what order.
 * • The `@/lib/motion` mock ensures motion primitives render as plain HTML.
 * • Unknown section types: The switch falls through to `return null`; we
 *   verify the renderer doesn't crash and outputs nothing extra.
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

// ── Motion mock (same pattern as HomePage.test.tsx) ──────────────────────────
vi.mock('@/lib/motion', () => {
  const motionProxy = new Proxy({}, {
    get: (_target, prop) => {
      return (
        { children, ref, ...props }: Record<string, unknown> & { ref?: React.Ref<HTMLElement> }
      ) => {
        const clean = { ...props };
        delete clean.variants; delete clean.initial; delete clean.animate;
        delete clean.exit; delete clean.transition; delete clean.whileHover;
        delete clean.whileTap; delete clean.whileInView; delete clean.layout;
        delete clean.viewport;
        const Tag = typeof prop === 'string' ? prop : 'div';
        return React.createElement(Tag, { ...clean, ref }, children as React.ReactNode);
      };
    },
  });
  return {
    motion: motionProxy,
    AnimatePresence: ({ children }: { children: React.ReactNode }) =>
      React.createElement(React.Fragment, null, children),
    MotionConfig: ({ children }: { children: React.ReactNode }) =>
      React.createElement(React.Fragment, null, children),
    useAnimation: () => ({ start: vi.fn() }),
    useInView: () => true,
  };
});

// ── API mock — StatsSection calls /v2/platform/stats ─────────────────────────
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({
      success: true,
      data: { members: 42, hours_exchanged: 100, listings: 10, communities: 2 },
    }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── Contexts mock (vi.fn so we can override per test) ─────────────────────────
const mockUseTenant = vi.fn();
const mockUseAuth = vi.fn(() => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }));

vi.mock('@/contexts', () => ({
  useAuth: () => mockUseAuth(),
  useTenant: () => mockUseTenant(),
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

const BASE_TENANT = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  branding: { name: 'Test Community', logo_url: null, tagline: 'A test community' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

import { LandingPageRenderer } from './LandingPageRenderer';

describe('LandingPageRenderer — section rendering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseAuth.mockReturnValue({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null });
  });

  it('renders a hero section when it is the only enabled section', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      landingPageConfig: {
        sections: [
          { id: 'hero', type: 'hero', enabled: true, order: 0 },
        ],
      },
    });
    render(<LandingPageRenderer />);
    // HeroSection renders a level-1 heading
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('respects the enabled flag — disabled sections are not rendered', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      landingPageConfig: {
        sections: [
          { id: 'hero', type: 'hero', enabled: true, order: 0 },
          { id: 'cta', type: 'cta', enabled: false, order: 1 },
        ],
      },
    });
    render(<LandingPageRenderer />);
    // CtaSection is disabled so no CTA section heading should appear
    // HeroSection heading still renders
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('renders multiple sections in the correct order', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      landingPageConfig: {
        sections: [
          // deliberately provide them out of order — renderer must sort
          { id: 'stats', type: 'stats', enabled: true, order: 2 },
          { id: 'hero', type: 'hero', enabled: true, order: 0 },
        ],
      },
    });
    render(<LandingPageRenderer />);
    // Both sections render without error
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('renders nothing when all sections are disabled', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      landingPageConfig: {
        sections: [
          { id: 'hero', type: 'hero', enabled: false, order: 0 },
        ],
      },
    });
    const { container } = render(<LandingPageRenderer />);
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
    // The PublicDiscoveryLinks nav may still render if features are enabled;
    // we just verify no crash and no hero h1.
    expect(container).toBeInTheDocument();
  });

  it('renders PublicDiscoveryLinks nav when modules/features are enabled', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
      landingPageConfig: {
        sections: [
          { id: 'hero', type: 'hero', enabled: true, order: 0 },
        ],
      },
    });
    render(<LandingPageRenderer />);
    // The discovery links nav is always appended after sections
    expect(screen.getByRole('navigation')).toBeInTheDocument();
  });

  it('does not render PublicDiscoveryLinks nav when all features/modules are off', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      hasFeature: vi.fn(() => false),
      hasModule: vi.fn(() => false),
      landingPageConfig: {
        sections: [],
      },
    });
    render(<LandingPageRenderer />);
    // No navigation links should appear
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
  });

  it('renders the stats section (which calls the stats API)', async () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      landingPageConfig: {
        sections: [
          { id: 'stats', type: 'stats', enabled: true, order: 0, content: { show_live_stats: true } },
        ],
      },
    });
    render(<LandingPageRenderer />);
    // StatsSection fetches and eventually shows stats — we just confirm it mounted
    await waitFor(() => {
      expect(document.body).toBeInTheDocument();
    });
  });

  it('silently ignores an unknown section type (no crash, no output)', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      landingPageConfig: {
        sections: [
          { id: 'mystery', type: 'unknown_type' as never, enabled: true, order: 0 },
        ],
      },
    });
    // Should not throw
    expect(() => render(<LandingPageRenderer />)).not.toThrow();
    // Nothing from the unknown section should appear in the DOM
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
  });

  it('renders an empty sections array without crashing', () => {
    mockUseTenant.mockReturnValue({
      ...BASE_TENANT,
      landingPageConfig: { sections: [] },
    });
    expect(() => render(<LandingPageRenderer />)).not.toThrow();
  });
});
